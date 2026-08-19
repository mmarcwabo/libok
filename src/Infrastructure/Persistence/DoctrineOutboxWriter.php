<?php

declare(strict_types=1);

namespace Libok\Infrastructure\Persistence;

use Doctrine\ORM\EntityManagerInterface;
use Libok\Application\Contracts\OutboxWriterInterface;
use Libok\Domain\Entities\OutboxEvent;

final class DoctrineOutboxWriter implements OutboxWriterInterface
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function append(
        string $type,
        array $payload,
        ?string $aggregateId = null,
        ?string $organizationId = null,
    ): string {
        if (preg_match('/^[a-z][a-z0-9_.-]{1,100}$/', $type) !== 1) {
            throw new \InvalidArgumentException('Invalid outbox event type.');
        }

        $event = new OutboxEvent($type, $payload, $aggregateId, $organizationId);
        $this->entityManager->persist($event);
        $this->entityManager->flush();

        return $event->getId();
    }
}
