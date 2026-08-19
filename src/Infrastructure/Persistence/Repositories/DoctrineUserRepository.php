<?php

declare(strict_types=1);

namespace Libok\Infrastructure\Persistence\Repositories;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Libok\Domain\Entities\User;
use Libok\Domain\Repositories\UserRepositoryInterface;

class DoctrineUserRepository implements UserRepositoryInterface
{
    /** @var EntityRepository<User> */
    private readonly EntityRepository $repository;

    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
        $this->repository = $this->entityManager->getRepository(User::class);
    }

    public function findById(string $id): ?User
    {
        return $this->repository->find($id);
    }

    public function findByEmail(string $email): ?User
    {
        return $this->repository->findOneBy(['email' => $email]);
    }

    /**
     * @return list<User>
     */
    public function findAll(): array
    {
        return $this->repository->findAll();
    }

    public function paginate(int $page, int $perPage, string $sortField, string $direction): array
    {
        $fieldMap = [
            'created_at' => 'u.createdAt',
            'email' => 'u.email',
            'name' => 'u.name',
        ];
        $order = $fieldMap[$sortField] ?? 'u.createdAt';
        $dir = strtoupper($direction) === 'ASC' ? 'ASC' : 'DESC';

        /** @var list<User> $rows */
        $rows = $this->entityManager->createQueryBuilder()
            ->select('u')
            ->from(User::class, 'u')
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
            ->select('COUNT(u.id)')
            ->from(User::class, 'u')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function save(User $user): void
    {
        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }

    public function delete(User $user): void
    {
        $this->entityManager->remove($user);
        $this->entityManager->flush();
    }
}
