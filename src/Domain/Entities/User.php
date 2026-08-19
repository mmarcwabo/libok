<?php

declare(strict_types=1);

namespace Libok\Domain\Entities;

use Doctrine\ORM\Mapping as ORM;
use Libok\Application\DTOs\UserData;
use Ramsey\Uuid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'users')]
#[ORM\Index(name: 'idx_users_status', columns: ['status'])]
class User
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_ARCHIVED = 'archived';

    public const STATUSES = [self::STATUS_ACTIVE, self::STATUS_SUSPENDED, self::STATUS_ARCHIVED];

    public const ROLE_SUPER_ADMIN = 'super_admin';
    public const ROLE_ADMIN = 'admin';
    public const ROLE_MANAGER = 'manager';
    public const ROLE_MEMBER = 'member';

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id;

    #[ORM\Column(type: 'string', length: 255)]
    private string $name;

    #[ORM\Column(type: 'string', length: 255, unique: true)]
    private string $email;

    #[ORM\Column(type: 'string', length: 255)]
    private string $password;

    #[ORM\Column(type: 'string', length: 20)]
    private string $status = self::STATUS_ACTIVE;

    /** @var list<string> */
    #[ORM\Column(type: 'json')]
    private array $roles = [self::ROLE_MEMBER];

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    /**
     * @param list<string> $roles
     */
    public function __construct(string $name, string $email, string $password, array $roles = [self::ROLE_MEMBER])
    {
        $this->id = Uuid::uuid4()->toString();
        $this->name = $name;
        $this->email = $email;
        $this->password = $password;
        $this->roles = $roles === [] ? [self::ROLE_MEMBER] : array_values($roles);
        $this->status = self::STATUS_ACTIVE;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): void
    {
        $this->password = $password;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        if (!in_array($status, self::STATUSES, true)) {
            throw new \InvalidArgumentException('Invalid user status.');
        }
        $this->status = $status;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * @return list<string>
     */
    public function getRoleNames(): array
    {
        return array_values($this->roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): void
    {
        $this->roles = $roles === [] ? [self::ROLE_MEMBER] : array_values($roles);
    }

    public function hasRole(string $roleName): bool
    {
        return in_array($roleName, $this->getRoleNames(), true);
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function toDto(): UserData
    {
        return new UserData(
            $this->id,
            $this->name,
            $this->email,
            $this->createdAt->format('Y-m-d H:i:s')
        );
    }
}
