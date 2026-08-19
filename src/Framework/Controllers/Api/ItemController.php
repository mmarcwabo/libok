<?php

declare(strict_types=1);

namespace Libok\Framework\Controllers\Api;

use Libok\Application\UseCases\CreateItemUseCase;
use Libok\Application\UseCases\FindItemUseCase;
use Libok\Framework\Controllers\BaseController;
use Libok\Framework\Resources\ItemResource;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ItemController extends BaseController
{
    public function __construct(
        private readonly CreateItemUseCase $createItemUseCase,
        private readonly FindItemUseCase $findItemUseCase,
    ) {
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
}
