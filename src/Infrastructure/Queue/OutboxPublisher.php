<?php

declare(strict_types=1);

namespace Libok\Infrastructure\Queue;

use Doctrine\ORM\EntityManagerInterface;
use Libok\Application\Contracts\MailerInterface;
use Libok\Domain\Entities\OutboxEvent;
use Psr\Log\LoggerInterface;

final class OutboxPublisher
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function publishOne(): bool
    {
        $event = $this->claim();
        if ($event === null) {
            return false;
        }

        try {
            $this->dispatch($event);
            $event->markPublished();
            $this->entityManager->flush();
            $this->logger->info('Outbox event published', [
                'outbox_id' => $event->getId(),
                'type' => $event->getType(),
            ]);
        } catch (\Throwable $error) {
            $event->markFailed($error);
            $this->entityManager->flush();
            $this->logger->error('Outbox event failed', [
                'outbox_id' => $event->getId(),
                'type' => $event->getType(),
                'attempt' => $event->getAttempts(),
                'exception' => $error,
            ]);
        }

        return true;
    }

    private function claim(): ?OutboxEvent
    {
        /** @var OutboxEvent|null $event */
        $event = $this->entityManager->createQueryBuilder()
            ->select('e')
            ->from(OutboxEvent::class, 'e')
            ->where('e.status = :status')
            ->andWhere('e.availableAt <= :now')
            ->orderBy('e.createdAt', 'ASC')
            ->setMaxResults(1)
            ->setParameter('status', OutboxEvent::STATUS_PENDING)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getOneOrNullResult();

        if ($event === null) {
            return null;
        }

        $event->markProcessing();
        $this->entityManager->flush();

        return $event;
    }

    private function dispatch(OutboxEvent $event): void
    {
        $payload = $event->getPayload();
        if ($event->getType() === 'user.registered') {
            $email = $payload['email'] ?? null;
            $name = $payload['name'] ?? null;
            if (!is_string($email) || !is_string($name) || $email === '' || $name === '') {
                throw new \InvalidArgumentException('Invalid user.registered outbox payload.');
            }
            $this->mailer->sendWelcomeNow($email, $name);

            return;
        }

        throw new \RuntimeException('No publisher for outbox type ' . $event->getType());
    }
}
