<?php

declare(strict_types=1);

namespace Libok\Domain\Entities;

use Doctrine\ORM\Mapping as ORM;
use Libok\Domain\Enums\MembershipRole;
use Libok\Domain\Enums\MembershipStatus;
use Ramsey\Uuid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'organization_memberships')]
#[ORM\UniqueConstraint(name: 'uniq_organization_membership', columns: ['organization_id', 'user_id'])]
#[ORM\Index(name: 'idx_membership_user_active', columns: ['user_id', 'status', 'is_default'])]
class OrganizationMembership
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id;

    #[ORM\ManyToOne(targetEntity: Organization::class)]
    #[ORM\JoinColumn(name: 'organization_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Organization $organization;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(type: 'string', length: 20, enumType: MembershipStatus::class)]
    private MembershipStatus $status = MembershipStatus::ACTIVE;

    #[ORM\Column(type: 'string', length: 20, enumType: MembershipRole::class)]
    private MembershipRole $role = MembershipRole::MEMBER;

    #[ORM\Column(name: 'is_default', type: 'boolean')]
    private bool $default = false;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(Organization $organization, User $user, MembershipRole $role = MembershipRole::MEMBER)
    {
        $this->id = Uuid::uuid4()->toString();
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
        $this->organization = $organization;
        $this->user = $user;
        $this->role = $role;
        $this->status = MembershipStatus::ACTIVE;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): void
    {
        $this->id = $id;
    }

    public function getOrganization(): Organization
    {
        return $this->organization;
    }

    public function setOrganization(Organization $organization): void
    {
        $this->organization = $organization;
        $this->touch();
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): void
    {
        $this->user = $user;
        $this->touch();
    }

    public function getStatus(): MembershipStatus
    {
        return $this->status;
    }

    public function setStatus(MembershipStatus $status): void
    {
        $this->status = $status;
        $this->touch();
    }

    public function getRole(): MembershipRole
    {
        return $this->role;
    }

    public function setRole(MembershipRole $role): void
    {
        $this->role = $role;
        $this->touch();
    }

    public function isDefault(): bool
    {
        return $this->default;
    }

    public function setDefault(bool $default): void
    {
        $this->default = $default;
        $this->touch();
    }

    public function isActive(): bool
    {
        return $this->status === MembershipStatus::ACTIVE && $this->organization->isActive();
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
