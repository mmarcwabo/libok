<?php

declare(strict_types=1);

namespace Libok\Application\UseCases;

use Libok\Domain\Repositories\ItemRepositoryInterface;

class DeleteItemUseCase
{
    public function __construct(private readonly ItemRepositoryInterface $itemRepository)
    {
    }

    public function execute(string $id): bool
    {
        $item = $this->itemRepository->findById($id);
        if ($item === null) {
            return false;
        }

        $this->itemRepository->delete($item);

        return true;
    }
}
