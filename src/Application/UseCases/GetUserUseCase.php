<?php

declare(strict_types=1);

namespace Libok\Application\UseCases;

use Libok\Application\DTOs\UserData;
use Libok\Domain\Repositories\UserRepositoryInterface;

class GetUserUseCase
{
    public function __construct(private readonly UserRepositoryInterface $userRepository) {}

    public function execute(string $id): ?UserData
    {
        $user = $this->userRepository->findById($id);
        return $user?->toDto();
    }
}