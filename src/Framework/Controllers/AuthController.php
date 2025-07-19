<?php

declare(strict_types=1);

namespace Libok\Framework\Controllers;

use Libok\Application\UseCases\LoginUserUseCase;
use Libok\Application\UseCases\RegisterUserUseCase;
use Libok\Infrastructure\Persistence\Repositories\DoctrineUserRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;

class AuthController extends BaseController
{
    public function showLoginForm(): void
    {
        $this->render('auth/login');
    }

    public function login(Request $request): void
    {
        $email = $request->request->get('email');
        $password = $request->request->get('password');

        $userRepository = new DoctrineUserRepository($this->entityManager);
        $loginUseCase = new LoginUserUseCase($userRepository);
        $user = $loginUseCase->execute($email, $password);

        if ($user) {
            $_SESSION['user_id'] = $user->getId();
            $_SESSION['user_name'] = $user->getName();
            (new RedirectResponse('/users'))->send();
        } else {
            $this->render('auth/login', ['error' => 'Invalid credentials']);
        }
    }
    
    public function showRegistrationForm(): void
    {
        $this->render('auth/register');
    }

    public function register(Request $request): void
    {
        $name = $request->request->get('name');
        $email = $request->request->get('email');
        $password = $request->request->get('password');

        $userRepository = new DoctrineUserRepository($this->entityManager);
        $registerUseCase = new RegisterUserUseCase($userRepository);

        try {
            $registerUseCase->execute($name, $email, $password);
            (new RedirectResponse('/login'))->send();
        } catch (\InvalidArgumentException $e) {
            $this->render('auth/register', ['error' => $e->getMessage()]);
        }
    }

    public function logout(): void
    {
        session_destroy();
        (new RedirectResponse('/login'))->send();
    }
}