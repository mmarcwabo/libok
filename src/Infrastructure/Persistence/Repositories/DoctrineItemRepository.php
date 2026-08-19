<?php

declare(strict_types=1);

namespace Libok\Infrastructure\Persistence\Repositories;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Libok\Domain\Entities\Item;
use Libok\Domain\Repositories\ItemRepositoryInterface;

class DoctrineItemRepository implements ItemRepositoryInterface
{
    /** @var EntityRepository<Item> */
    private readonly EntityRepository $repository;

    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
        $this->repository = $this->entityManager->getRepository(Item::class);
    }

    public function findById(string $id): ?Item
    {
        return $this->repository->find($id);
    }

    public function save(Item $item): void
    {
        $this->entityManager->persist($item);
        $this->entityManager->flush();
    }
}
