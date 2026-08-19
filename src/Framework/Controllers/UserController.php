<?php

declare(strict_types=1);

namespace Libok\Framework\Controllers;

use Doctrine\ORM\EntityManagerInterface;
use Libok\Application\UseCases\CreateUserUseCase;
use Libok\Application\UseCases\DeleteUserUseCase;
use Libok\Application\UseCases\GetUserUseCase;
use Libok\Application\UseCases\ListUsersUseCase;
use Libok\Application\UseCases\UpdateUserUseCase;
use Libok\Infrastructure\Persistence\Repositories\DoctrineUserRepository;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class UserController extends BaseController
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    private function guard(): ?Response
    {
        if (!isset($_SESSION['user_id'])) {
            return new RedirectResponse('/login');
        }

        return null;
    }

    public function index(Request $request): Response
    {
        if ($redirect = $this->guard()) {
            return $redirect;
        }
        $userRepository = new DoctrineUserRepository($this->entityManager);
        $listUsersUseCase = new ListUsersUseCase($userRepository);
        $users = $listUsersUseCase->execute();

        return $this->render('users/index', ['users' => $users]);
    }

    public function create(Request $request): Response
    {
        if ($redirect = $this->guard()) {
            return $redirect;
        }

        return $this->render('users/create');
    }

    public function store(Request $request): Response
    {
        if ($redirect = $this->guard()) {
            return $redirect;
        }
        $userRepository = new DoctrineUserRepository($this->entityManager);
        $createUserUseCase = new CreateUserUseCase($userRepository);

        try {
            $createUserUseCase->execute(
                (string) $request->request->get('name', ''),
                (string) $request->request->get('email', ''),
                (string) $request->request->get('password', '')
            );

            return new RedirectResponse('/users');
        } catch (\InvalidArgumentException $e) {
            return $this->render('users/create', ['error' => $e->getMessage()]);
        }
    }

    public function edit(Request $request): Response
    {
        if ($redirect = $this->guard()) {
            return $redirect;
        }
        $userId = (string) $request->query->get('id', '');

        $userRepository = new DoctrineUserRepository($this->entityManager);
        $getUserUseCase = new GetUserUseCase($userRepository);
        $user = $getUserUseCase->execute($userId);

        if (!$user) {
            return new RedirectResponse('/users');
        }

        return $this->render('users/edit', ['user' => $user]);
    }

    public function update(Request $request): Response
    {
        if ($redirect = $this->guard()) {
            return $redirect;
        }
        $userRepository = new DoctrineUserRepository($this->entityManager);
        $updateUserUseCase = new UpdateUserUseCase($userRepository);

        $updateUserUseCase->execute(
            (string) $request->request->get('id', ''),
            (string) $request->request->get('name', ''),
            (string) $request->request->get('email', '')
        );

        return new RedirectResponse('/users');
    }

    public function delete(Request $request): Response
    {
        if ($redirect = $this->guard()) {
            return $redirect;
        }
        $userId = (string) $request->request->get('id', '');

        if ($userId === ($_SESSION['user_id'] ?? '')) {
            return new RedirectResponse('/users');
        }

        $userRepository = new DoctrineUserRepository($this->entityManager);
        $deleteUserUseCase = new DeleteUserUseCase($userRepository);
        $deleteUserUseCase->execute($userId);

        return new RedirectResponse('/users');
    }
}
