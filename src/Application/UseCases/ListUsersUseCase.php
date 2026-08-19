<?php

declare(strict_types=1);

namespace Libok\Application\UseCases;

use Libok\Application\Pagination\PageQuery;
use Libok\Domain\Entities\User;
use Libok\Domain\Repositories\UserRepositoryInterface;

class ListUsersUseCase
{
    public function __construct(private readonly UserRepositoryInterface $userRepository)
    {
    }

    /**
     * @return \Libok\Application\DTOs\UserData[]
     */
    public function execute(): array
    {
        $users = $this->userRepository->findAll();
        // Convert entities to DTOs to decouple the domain from the framework
        return array_map(fn ($user) => $user->toDto(), $users);
    }

    /**
     * @return array{items: list<User>, total: int}
     */
    public function paginate(PageQuery $query): array
    {
        return [
            'items' => $this->userRepository->paginate(
                $query->page,
                $query->perPage,
                $query->sortField,
                $query->sortDir,
            ),
            'total' => $this->userRepository->countAll(),
        ];
    }
}
