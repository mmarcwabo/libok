<?php

declare(strict_types=1);

namespace Libok\Application\UseCases;

use Libok\Domain\Entities\Item;
use Libok\Domain\Repositories\ItemRepositoryInterface;

class CreateItemUseCase
{
    public function __construct(private readonly ItemRepositoryInterface $itemRepository)
    {
    }

    public function execute(string $title): Item
    {
        $item = new Item($title);
        $this->itemRepository->save($item);

        return $item;
    }
}
