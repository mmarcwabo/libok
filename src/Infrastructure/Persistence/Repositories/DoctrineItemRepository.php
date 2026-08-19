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

    /**
     * @return list<Item>
     */
    public function paginate(int $page, int $perPage, string $sortField, string $direction): array
    {
        $fieldMap = [
            'created_at' => 'i.createdAt',
            'title' => 'i.title',
        ];
        $order = $fieldMap[$sortField] ?? 'i.createdAt';
        $dir = strtoupper($direction) === 'ASC' ? 'ASC' : 'DESC';

        /** @var list<Item> $rows */
        $rows = $this->entityManager->createQueryBuilder()
            ->select('i')
            ->from(Item::class, 'i')
            ->orderBy($order, $dir)
            ->setFirstResult(max(0, ($page - 1) * $perPage))
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        return $rows;
    }

    public function countAll(): int
    {
        return (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(i.id)')
            ->from(Item::class, 'i')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function save(Item $item): void
    {
        $this->entityManager->persist($item);
        $this->entityManager->flush();
    }

    public function delete(Item $item): void
    {
        $this->entityManager->remove($item);
        $this->entityManager->flush();
    }
}
