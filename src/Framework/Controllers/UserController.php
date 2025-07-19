<?php

declare(strict_types=1);

namespace Libok\Framework\Controllers;

use Libok\Application\UseCases\CreateUserUseCase;
use Libok\Application\UseCases\DeleteUserUseCase;
use Libok\Application\UseCases\GetUserUseCase;
use Libok\Application\UseCases\ListUsersUseCase;
use Libok\Application\UseCases\UpdateUserUseCase;
use Libok\Infrastructure\Persistence\Repositories\DoctrineUserRepository;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

class UserController extends BaseController
{
    /**
     * Redirects to login if the user is not authenticated.
     */
    private function guard(): void
    {
        if (!isset($_SESSION['user_id'])) {
            (new RedirectResponse('/login'))->send();
            exit();
        }
    }

    /**
     * Displays the list of all users. Also serves as the homepage.
     */
    public function index(): void
    {
        $this->guard();
        $userRepository = new DoctrineUserRepository($this->entityManager);
        $listUsersUseCase = new ListUsersUseCase($userRepository);
        $users = $listUsersUseCase->execute();

        $this->render('users/index', ['users' => $users]);
    }

    /**
     * Shows the form for creating a new user.
     */
    public function create(): void
    {
        $this->guard();
        $this->render('users/create');
    }

    /**
     * Stores a new user in the database.
     */
    public function store(Request $request): void
    {
        $this->guard();
        $userRepository = new DoctrineUserRepository($this->entityManager);
        $createUserUseCase = new CreateUserUseCase($userRepository);

        try {
            $createUserUseCase->execute(
                $request->request->get('name'),
                $request->request->get('email'),
                $request->request->get('password')
            );
            (new RedirectResponse('/users'))->send();
        } catch (\InvalidArgumentException $e) {
            $this->render('users/create', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Shows the form for editing an existing user.
     */
    public function edit(Request $request): void
    {
        $this->guard();
        $userId = $request->query->get('id');

        $userRepository = new DoctrineUserRepository($this->entityManager);
        $getUserUseCase = new GetUserUseCase($userRepository);
        $user = $getUserUseCase->execute($userId);

        if (!$user) {
            // In a real app, you'd use flash messages for errors
            (new RedirectResponse('/users'))->send();
            return;
        }

        $this->render('users/edit', ['user' => $user]);
    }

    /**
     * Updates an existing user in the database.
     */
    public function update(Request $request): void
    {
        $this->guard();
        $userRepository = new DoctrineUserRepository($this->entityManager);
        $updateUserUseCase = new UpdateUserUseCase($userRepository);

        $updateUserUseCase->execute(
            $request->request->get('id'),
            $request->request->get('name'),
            $request->request->get('email')
        );

        (new RedirectResponse('/users'))->send();
    }

    /**
     * Deletes a user from the database.
     */
    public function delete(Request $request): void
    {
        $this->guard();
        $userId = $request->request->get('id');

        // Prevent users from deleting themselves for simplicity
        if ($userId === $_SESSION['user_id']) {
            (new RedirectResponse('/users'))->send();
            return;
        }
        
        $userRepository = new DoctrineUserRepository($this->entityManager);
        $deleteUserUseCase = new DeleteUserUseCase($userRepository);
        $deleteUserUseCase->execute($userId);

        (new RedirectResponse('/users'))->send();
    }
}
