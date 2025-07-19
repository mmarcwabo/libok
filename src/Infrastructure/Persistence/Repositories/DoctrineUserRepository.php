<?php

declare(strict_types=1);

namespace Libok\Infrastructure\Persistence\Repositories;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Libok\Domain\Entities\User;
use Libok\Domain\Repositories\UserRepositoryInterface;

class DoctrineUserRepository implements UserRepositoryInterface
{
    private readonly EntityRepository $repository;

    public function __construct(private readonly EntityManager $entityManager)
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

    public function findAll(): array
    {
        return $this->repository->findAll();
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
