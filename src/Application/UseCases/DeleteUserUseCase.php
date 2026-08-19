<?php

declare(strict_types=1);

namespace Libok\Application\UseCases;

use Libok\Domain\Repositories\UserRepositoryInterface;

class DeleteUserUseCase
{
    public function __construct(private readonly UserRepositoryInterface $userRepository)
    {
    }

    public function execute(string $id): bool
    {
        $user = $this->userRepository->findById($id);
        if ($user === null) {
            return false;
        }

        $this->userRepository->delete($user);

        return true;
    }
}
