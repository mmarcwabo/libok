<?php

declare(strict_types=1);

namespace Libok\Domain\Repositories;

use Libok\Domain\Entities\Item;

interface ItemRepositoryInterface
{
    public function findById(string $id): ?Item;

    /**
     * @return list<Item>
     */
    public function paginate(int $page, int $perPage, string $sortField, string $direction): array;

    public function countAll(): int;

    public function save(Item $item): void;

    public function delete(Item $item): void;
}
