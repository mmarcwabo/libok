<?php

declare(strict_types=1);

namespace Libok\Framework\Resources;

use Libok\Domain\Entities\Item;

final class ItemResource
{
    /**
     * @return array{id: string, title: string, organization_id: string|null, created_at: string}
     */
    public static function toArray(Item $item): array
    {
        return [
            'id' => $item->getId(),
            'title' => $item->getTitle(),
            'organization_id' => $item->getOrganizationId(),
            'created_at' => $item->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}
