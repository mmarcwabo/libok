<?php

declare(strict_types=1);

namespace Libok\Domain\Repositories;

use Libok\Domain\Entities\Item;

interface ItemRepositoryInterface
{
    public function findById(string $id): ?Item;

    public function save(Item $item): void;
}
