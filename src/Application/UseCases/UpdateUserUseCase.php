<?php

declare(strict_types=1);

namespace Libok\Application\UseCases;

use Libok\Domain\Repositories\UserRepositoryInterface;

class UpdateUserUseCase
{
    public function __construct(private readonly UserRepositoryInterface $userRepository) {}

    public function execute(string $id, string $name, string $email): void
    {
        $user = $this->userRepository->findById($id);
        if ($user) {
            $user->setName($name);
            $user->setEmail($email);
            $this->userRepository->save($user);
        }
    }
}