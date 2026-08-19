<?php

declare(strict_types=1);

namespace Libok\Application\UseCases;

use Libok\Domain\Entities\Item;
use Libok\Domain\Repositories\ItemRepositoryInterface;

class UpdateItemUseCase
{
    public function __construct(private readonly ItemRepositoryInterface $itemRepository)
    {
    }

    public function execute(string $id, string $title): ?Item
    {
        $item = $this->itemRepository->findById($id);
        if ($item === null) {
            return null;
        }

        $item->setTitle($title);
        $this->itemRepository->save($item);

        return $item;
    }
}
