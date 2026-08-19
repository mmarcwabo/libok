<?php

declare(strict_types=1);

namespace Libok\Application\UseCases;

use Libok\Application\Pagination\PageQuery;
use Libok\Domain\Entities\Item;
use Libok\Domain\Repositories\ItemRepositoryInterface;

class ListItemsUseCase
{
    public function __construct(private readonly ItemRepositoryInterface $itemRepository)
    {
    }

    /**
     * @return array{items: list<Item>, total: int}
     */
    public function paginate(PageQuery $query): array
    {
        return [
            'items' => $this->itemRepository->paginate(
                $query->page,
                $query->perPage,
                $query->sortField,
                $query->sortDir,
            ),
            'total' => $this->itemRepository->countAll(),
        ];
    }
}
