<?php

declare(strict_types=1);

namespace Libok\Application\UseCases;

use Libok\Domain\Repositories\UserRepositoryInterface;

class DeleteUserUseCase
{
    public function __construct(private readonly UserRepositoryInterface $userRepository) {}

    public function execute(string $id): void
    {
        $user = $this->userRepository->findById($id);
        if ($user) {
            $this->userRepository->delete($user);
        }
    }
}