<?php

declare(strict_types=1);

namespace Libok\Domain\Repositories;

use Libok\Domain\Entities\User;

interface UserRepositoryInterface
{
    public function findById(string $id): ?User;
    public function findByEmail(string $email): ?User;
    /**
     * @return list<User>
     */
    public function findAll(): array;

    /**
     * @param 'asc'|'desc' $direction
     * @return list<User>
     */
    public function paginate(int $page, int $perPage, string $sortField, string $direction): array;

    public function countAll(): int;

    public function save(User $user): void;
    public function delete(User $user): void;
}
