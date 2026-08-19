<?php

declare(strict_types=1);

namespace Libok\Framework\Controllers\Api;

use Libok\Application\Pagination\PageQuery;
use Libok\Application\UseCases\CreateItemUseCase;
use Libok\Application\UseCases\DeleteItemUseCase;
use Libok\Application\UseCases\FindItemUseCase;
use Libok\Application\UseCases\ListItemsUseCase;
use Libok\Application\UseCases\UpdateItemUseCase;
use Libok\Domain\Entities\Item;
use Libok\Framework\Controllers\BaseController;
use Libok\Framework\Resources\ItemResource;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ItemController extends BaseController
{
    public function __construct(
        private readonly ListItemsUseCase $listItemsUseCase,
        private readonly CreateItemUseCase $createItemUseCase,
        private readonly FindItemUseCase $findItemUseCase,
        private readonly UpdateItemUseCase $updateItemUseCase,
        private readonly DeleteItemUseCase $deleteItemUseCase,
    ) {
    }

    public function index(Request $request): Response
    {
        $query = PageQuery::fromRequest($request, ['created_at', 'title']);
        $result = $this->listItemsUseCase->paginate($query);
        $items = array_map(
            static fn (Item $item): array => ItemResource::toArray($item),
            $result['items'],
        );

        return $this->paginated($items, $result['total'], $query->page, $query->perPage);
    }

    public function store(Request $request): Response
    {
        try {
            $item = $this->createItemUseCase->execute((string) $request->request->get('title', ''));

            return $this->json(ItemResource::toArray($item), 201, 'Item created.');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 400);
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), 403);
        }
    }

    public function show(Request $request): Response
    {
        $item = $this->findItemUseCase->execute((string) $request->attributes->get('id', ''));
        if ($item === null) {
            return $this->error('Resource not found.', 404);
        }

        return $this->json(ItemResource::toArray($item));
    }

    public function update(Request $request): Response
    {
        $id = (string) $request->attributes->get('id', '');

        try {
            $item = $this->updateItemUseCase->execute(
                $id,
                (string) $request->request->get('title', ''),
            );
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 400);
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), 403);
        }

        if ($item === null) {
            return $this->error('Resource not found.', 404);
        }

        return $this->json(ItemResource::toArray($item), 200, 'Item updated.');
    }

    public function destroy(Request $request): Response
    {
        $id = (string) $request->attributes->get('id', '');
        if (!$this->deleteItemUseCase->execute($id)) {
            return $this->error('Resource not found.', 404);
        }

        return new Response('', 204, ['Cache-Control' => 'no-store']);
    }
}
