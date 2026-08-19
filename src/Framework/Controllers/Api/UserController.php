<?php

declare(strict_types=1);

namespace Libok\Framework\Controllers\Api;

use Libok\Application\Pagination\PageQuery;
use Libok\Application\UseCases\CreateUserUseCase;
use Libok\Application\UseCases\DeleteUserUseCase;
use Libok\Application\UseCases\FindUserUseCase;
use Libok\Application\UseCases\ListUsersUseCase;
use Libok\Application\UseCases\UpdateUserUseCase;
use Libok\Domain\Entities\User;
use Libok\Framework\Controllers\BaseController;
use Libok\Framework\Resources\UserResource;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class UserController extends BaseController
{
    public function __construct(
        private readonly ListUsersUseCase $listUsersUseCase,
        private readonly FindUserUseCase $findUserUseCase,
        private readonly CreateUserUseCase $createUserUseCase,
        private readonly UpdateUserUseCase $updateUserUseCase,
        private readonly DeleteUserUseCase $deleteUserUseCase,
    ) {
    }

    public function index(Request $request): Response
    {
        $query = PageQuery::fromRequest($request, ['created_at', 'email', 'name']);
        $result = $this->listUsersUseCase->paginate($query);
        $items = array_map(
            static fn (User $user): array => UserResource::toArray($user),
            $result['items'],
        );

        return $this->paginated($items, $result['total'], $query->page, $query->perPage);
    }

    public function show(Request $request): Response
    {
        $user = $this->findUserUseCase->execute((string) $request->attributes->get('id', ''));
        if ($user === null) {
            return $this->error('Resource not found.', 404);
        }

        return $this->json(UserResource::toArray($user));
    }

    public function store(Request $request): Response
    {
        try {
            $roles = $request->request->all()['roles'] ?? [User::ROLE_MEMBER];
            if (!is_array($roles)) {
                return $this->error('Roles must be an array.', 400);
            }
            /** @var list<string> $roleNames */
            $roleNames = array_values(array_filter($roles, 'is_string'));

            $user = $this->createUserUseCase->execute(
                (string) $request->request->get('name', ''),
                (string) $request->request->get('email', ''),
                (string) $request->request->get('password', ''),
                $roleNames,
            );

            return $this->json(UserResource::toArray($user), 201, 'User created.');
        } catch (\InvalidArgumentException $e) {
            $status = str_contains($e->getMessage(), 'already exists') ? 409 : 400;

            return $this->error($e->getMessage(), $status);
        }
    }

    public function update(Request $request): Response
    {
        $id = (string) $request->attributes->get('id', '');

        try {
            $user = $this->updateUserUseCase->execute(
                $id,
                (string) $request->request->get('name', ''),
                (string) $request->request->get('email', ''),
            );
        } catch (\InvalidArgumentException $e) {
            $status = str_contains($e->getMessage(), 'already exists') ? 409 : 400;

            return $this->error($e->getMessage(), $status);
        }

        if ($user === null) {
            return $this->error('Resource not found.', 404);
        }

        return $this->json(UserResource::toArray($user), 200, 'User updated.');
    }

    public function destroy(Request $request): Response
    {
        $id = (string) $request->attributes->get('id', '');
        $actorId = $request->attributes->get('auth_user_id');
        if (is_string($actorId) && $actorId === $id) {
            return $this->error('You cannot delete your own account.', 409, 'conflict');
        }

        if (!$this->deleteUserUseCase->execute($id)) {
            return $this->error('Resource not found.', 404);
        }

        return new Response('', 204, ['Cache-Control' => 'no-store']);
    }
}
