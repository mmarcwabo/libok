<?php

declare(strict_types=1);

namespace Libok\Domain\Repositories;

use Libok\Domain\Entities\User;

interface UserRepositoryInterface
{
    public function findById(string $id): ?User;
    public function findByEmail(string $email): ?User;
    public function findAll(): array;
    public function save(User $user): void;
    public function delete(User $user): void;
}