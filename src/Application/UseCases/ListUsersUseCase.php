<?php

declare(strict_types=1);

namespace Libok\Application\UseCases;

use Libok\Domain\Repositories\UserRepositoryInterface;

class ListUsersUseCase
{
    public function __construct(private readonly UserRepositoryInterface $userRepository) {}

    /**
     * @return \Libok\Application\DTOs\UserData[]
     */
    public function execute(): array
    {
        $users = $this->userRepository->findAll();
        // Convert entities to DTOs to decouple the domain from the framework
        return array_map(fn($user) => $user->toDto(), $users);
    }
}