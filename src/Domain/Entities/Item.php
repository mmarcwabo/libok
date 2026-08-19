<?php

declare(strict_types=1);

namespace Libok\Domain\Entities;

use Doctrine\ORM\Mapping as ORM;
use Libok\Domain\Entities\Concerns\OrganizationOwned;
use Ramsey\Uuid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'items')]
#[ORM\Index(name: 'idx_items_organization_created_at', columns: ['organization_id', 'created_at'])]
class Item
{
    use OrganizationOwned;

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id;

    #[ORM\Column(type: 'string', length: 255)]
    private string $title;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(string $title)
    {
        $this->id = Uuid::uuid4()->toString();
        $this->setTitle($title);
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $title = trim($title);
        if ($title === '' || strlen($title) > 255) {
            throw new \InvalidArgumentException('Title is required.');
        }
        $this->title = $title;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
