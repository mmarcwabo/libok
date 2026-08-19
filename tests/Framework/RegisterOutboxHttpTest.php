<?php

declare(strict_types=1);

namespace Tests\Framework;

use Doctrine\ORM\EntityManagerInterface;
use Libok\Domain\Entities\OutboxEvent;
use Libok\Infrastructure\Queue\KernelWorker;
use Libok\Infrastructure\Queue\OutboxPublisher;
use Psr\Log\NullLogger;
use Tests\Support\RecordingMailer;

final class RegisterOutboxHttpTest extends KernelTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureSchema();
    }

    public function testRegisterCreatesPendingOutboxWithoutSendingMail(): void
    {
        $response = $this->jsonRequest('POST', '/api/v1/auth/register', [], [], [
            'name' => 'Ada Outbox',
            'email' => 'ada-outbox@example.test',
            'password' => 'password123',
        ]);
        self::assertSame(201, $response->getStatusCode(), (string) $response->getContent());

        $events = $this->entityManager()->getRepository(OutboxEvent::class)->findAll();
        self::assertCount(1, $events);
        $event = $events[0];
        self::assertSame('user.registered', $event->getType());
        self::assertSame(OutboxEvent::STATUS_PENDING, $event->getStatus());
        self::assertSame('ada-outbox@example.test', $event->getPayload()['email'] ?? null);
        self::assertArrayNotHasKey('password', $event->getPayload());
        self::assertNull($event->getPublishedAt());
    }

    public function testWorkerPublishesWelcomeMailFromOutbox(): void
    {
        $this->jsonRequest('POST', '/api/v1/auth/register', [], [], [
            'name' => 'Ada Worker',
            'email' => 'ada-worker@example.test',
            'password' => 'password123',
        ]);

        $mailer = new RecordingMailer();
        $publisher = new OutboxPublisher($this->entityManager(), $mailer, new NullLogger());
        self::assertTrue($publisher->publishOne());
        self::assertCount(1, $mailer->welcome);
        self::assertSame('ada-worker@example.test', $mailer->welcome[0]['to']);
        self::assertSame('Ada Worker', $mailer->welcome[0]['name']);

        $this->entityManager()->clear();
        $event = $this->entityManager()->getRepository(OutboxEvent::class)->findOneBy(['type' => 'user.registered']);
        self::assertInstanceOf(OutboxEvent::class, $event);
        self::assertSame(OutboxEvent::STATUS_PUBLISHED, $event->getStatus());
        self::assertNotNull($event->getPublishedAt());
    }

    public function testKernelWorkerRunOncePublishesOutbox(): void
    {
        $this->jsonRequest('POST', '/api/v1/auth/register', [], [], [
            'name' => 'Ada Kernel',
            'email' => 'ada-kernel@example.test',
            'password' => 'password123',
        ]);

        $container = require dirname(__DIR__, 2) . '/config/services.php';
        /** @var KernelWorker $worker */
        $worker = $container->get(KernelWorker::class);
        self::assertGreaterThanOrEqual(1, $worker->runOnce());

        $event = $this->entityManager()->getRepository(OutboxEvent::class)->findOneBy(['type' => 'user.registered']);
        self::assertInstanceOf(OutboxEvent::class, $event);
        self::assertSame(OutboxEvent::STATUS_PUBLISHED, $event->getStatus());
    }

    private function entityManager(): EntityManagerInterface
    {
        $container = require dirname(__DIR__, 2) . '/config/services.php';

        return $container->get(EntityManagerInterface::class);
    }
}
