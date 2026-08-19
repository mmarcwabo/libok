<?php

declare(strict_types=1);

namespace Libok\Domain\Entities;

use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'idempotency_keys')]
#[ORM\UniqueConstraint(name: 'uniq_idempotency_keys_key_org_actor', columns: ['idempotency_key', 'organization_id', 'actor_id'])]
#[ORM\Index(name: 'idx_idempotency_keys_expires_at', columns: ['expires_at'])]
class IdempotencyKey
{
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id;

    #[ORM\Column(name: 'idempotency_key', type: 'string', length: 255)]
    private string $key;

    #[ORM\Column(name: 'organization_id', type: 'string', length: 120)]
    private string $organizationId = '';

    #[ORM\Column(name: 'actor_id', type: 'string', length: 36)]
    private string $actorId = '';

    #[ORM\Column(name: 'request_hash', type: 'string', length: 64)]
    private string $requestHash;

    #[ORM\Column(type: 'string', length: 20)]
    private string $status = self::STATUS_PROCESSING;

    #[ORM\Column(name: 'response_code', type: 'integer', nullable: true)]
    private ?int $responseCode = null;

    #[ORM\Column(name: 'response_body', type: 'text', nullable: true)]
    private ?string $responseBody = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'expires_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $expiresAt;

    public function __construct(string $key, string $organizationId, string $actorId, string $requestHash, \DateTimeImmutable $expiresAt)
    {
        $now = new \DateTimeImmutable();
        $this->id = Uuid::uuid4()->toString();
        $this->key = $key;
        $this->organizationId = $organizationId;
        $this->actorId = $actorId;
        $this->requestHash = $requestHash;
        $this->status = self::STATUS_PROCESSING;
        $this->createdAt = $now;
        $this->expiresAt = $expiresAt;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getOrganizationId(): string
    {
        return $this->organizationId;
    }

    public function getActorId(): string
    {
        return $this->actorId;
    }

    public function getRequestHash(): string
    {
        return $this->requestHash;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getResponseCode(): ?int
    {
        return $this->responseCode;
    }

    public function getResponseBody(): ?string
    {
        return $this->responseBody;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isExpired(\DateTimeImmutable $now): bool
    {
        return $this->expiresAt <= $now;
    }

    public function restart(string $requestHash, \DateTimeImmutable $expiresAt): void
    {
        $this->requestHash = $requestHash;
        $this->status = self::STATUS_PROCESSING;
        $this->responseCode = null;
        $this->responseBody = null;
        $this->createdAt = new \DateTimeImmutable();
        $this->expiresAt = $expiresAt;
    }

    public function complete(int $responseCode, string $responseBody): void
    {
        $this->status = self::STATUS_COMPLETED;
        $this->responseCode = $responseCode;
        $this->responseBody = $responseBody;
    }
}
