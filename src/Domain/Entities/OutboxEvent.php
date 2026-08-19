<?php

declare(strict_types=1);

namespace Libok\Domain\Entities;

use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'outbox_events')]
#[ORM\Index(name: 'idx_outbox_claim', columns: ['status', 'available_at', 'created_at'])]
class OutboxEvent
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_FAILED = 'failed';

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id;

    #[ORM\Column(type: 'string', length: 100)]
    private string $type;

    /** @var array<string, mixed> */
    #[ORM\Column(type: 'json')]
    private array $payload = [];

    #[ORM\Column(name: 'aggregate_id', type: 'string', length: 36, nullable: true)]
    private ?string $aggregateId = null;

    #[ORM\Column(name: 'organization_id', type: 'string', length: 36, nullable: true)]
    private ?string $organizationId = null;

    #[ORM\Column(type: 'string', length: 20)]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(type: 'integer')]
    private int $attempts = 0;

    #[ORM\Column(name: 'max_attempts', type: 'integer')]
    private int $maxAttempts = 5;

    #[ORM\Column(name: 'available_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $availableAt;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'published_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $publishedAt = null;

    #[ORM\Column(name: 'last_error', type: 'text', nullable: true)]
    private ?string $lastError = null;

    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        string $type,
        array $payload,
        ?string $aggregateId = null,
        ?string $organizationId = null,
    ) {
        $now = new \DateTimeImmutable();
        $this->id = Uuid::uuid4()->toString();
        $this->type = $type;
        $this->payload = $payload;
        $this->aggregateId = $aggregateId;
        $this->organizationId = $organizationId;
        $this->status = self::STATUS_PENDING;
        $this->attempts = 0;
        $this->maxAttempts = 5;
        $this->availableAt = $now;
        $this->createdAt = $now;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getType(): string
    {
        return $this->type;
    }

    /** @return array<string, mixed> */
    public function getPayload(): array
    {
        return $this->payload;
    }

    public function getAggregateId(): ?string
    {
        return $this->aggregateId;
    }

    public function getOrganizationId(): ?string
    {
        return $this->organizationId;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getAttempts(): int
    {
        return $this->attempts;
    }

    public function getMaxAttempts(): int
    {
        return $this->maxAttempts;
    }

    public function getAvailableAt(): \DateTimeImmutable
    {
        return $this->availableAt;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getPublishedAt(): ?\DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    public function markProcessing(): void
    {
        $this->status = self::STATUS_PROCESSING;
        $this->attempts++;
    }

    public function markPublished(): void
    {
        $this->status = self::STATUS_PUBLISHED;
        $this->publishedAt = new \DateTimeImmutable();
        $this->lastError = null;
    }

    public function markFailed(\Throwable $error): void
    {
        $this->lastError = substr($error::class . ': ' . $error->getMessage(), 0, 2000);
        if ($this->attempts >= $this->maxAttempts) {
            $this->status = self::STATUS_FAILED;

            return;
        }
        $delay = min(3600, 5 * (2 ** max(0, $this->attempts - 1)));
        $this->status = self::STATUS_PENDING;
        $this->availableAt = new \DateTimeImmutable("+{$delay} seconds");
    }
}
