<?php

declare(strict_types=1);

namespace Libok\Domain\Entities;

use Doctrine\ORM\Mapping as ORM;
use Libok\Domain\Enums\OrganizationStatus;
use Ramsey\Uuid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'organizations')]
#[ORM\UniqueConstraint(name: 'uniq_organizations_slug', columns: ['slug'])]
#[ORM\UniqueConstraint(name: 'uniq_organizations_host', columns: ['host'])]
#[ORM\Index(name: 'idx_organizations_status', columns: ['status'])]
class Organization
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id;

    #[ORM\Column(type: 'string', length: 120)]
    private string $slug;

    #[ORM\Column(type: 'string', length: 255)]
    private string $name;

    #[ORM\Column(type: 'string', length: 20, enumType: OrganizationStatus::class)]
    private OrganizationStatus $status = OrganizationStatus::ACTIVE;

    #[ORM\Column(type: 'string', length: 253, nullable: true)]
    private ?string $host = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(string $name, string $slug, ?string $host = null)
    {
        $this->id = Uuid::uuid4()->toString();
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
        $this->status = OrganizationStatus::ACTIVE;
        $this->setName($name);
        $this->setSlug($slug);
        $this->setHost($host);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): void
    {
        $this->id = $id;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): void
    {
        $normalized = strtolower(trim($slug));
        if ($normalized === '' || strlen($normalized) > 120) {
            throw new \InvalidArgumentException('A valid organization slug is required.');
        }
        $this->slug = $normalized;
        $this->touch();
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $name = trim($name);
        if ($name === '' || strlen($name) > 255) {
            throw new \InvalidArgumentException('Organization name is required.');
        }
        $this->name = $name;
        $this->touch();
    }

    public function getStatus(): OrganizationStatus
    {
        return $this->status;
    }

    public function setStatus(OrganizationStatus $status): void
    {
        $this->status = $status;
        $this->touch();
    }

    public function getHost(): ?string
    {
        return $this->host;
    }

    public function setHost(?string $host): void
    {
        if ($host === null || trim($host) === '') {
            $this->host = null;
            $this->touch();

            return;
        }
        $this->host = strtolower(rtrim(trim($host), '.'));
        $this->touch();
    }

    public function isActive(): bool
    {
        return $this->status === OrganizationStatus::ACTIVE;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
