<?php

declare(strict_types=1);

namespace Libok\Application\UseCases;

use Libok\Domain\Entities\Item;
use Libok\Domain\Repositories\ItemRepositoryInterface;

class FindItemUseCase
{
    public function __construct(private readonly ItemRepositoryInterface $itemRepository)
    {
    }

    public function execute(string $id): ?Item
    {
        return $this->itemRepository->findById($id);
    }
}
