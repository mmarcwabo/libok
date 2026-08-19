<?php

declare(strict_types=1);

namespace Libok\Framework\Controllers;

use Doctrine\ORM\EntityManagerInterface;
use Libok\Application\UseCases\LoginUserUseCase;
use Libok\Application\UseCases\RegisterUserUseCase;
use Libok\Infrastructure\Persistence\Repositories\DoctrineUserRepository;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends BaseController
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function showLoginForm(Request $request): Response
    {
        return $this->render('auth/login');
    }

    public function login(Request $request): Response
    {
        $email = (string) $request->request->get('email', '');
        $password = (string) $request->request->get('password', '');

        $userRepository = new DoctrineUserRepository($this->entityManager);
        $loginUseCase = new LoginUserUseCase($userRepository);
        $user = $loginUseCase->execute($email, $password);

        if ($user) {
            $_SESSION['user_id'] = $user->getId();
            $_SESSION['user_name'] = $user->getName();

            return new RedirectResponse('/users');
        }

        return $this->render('auth/login', ['error' => 'Invalid credentials']);
    }

    public function showRegistrationForm(Request $request): Response
    {
        return $this->render('auth/register');
    }

    public function register(Request $request): Response
    {
        $name = (string) $request->request->get('name', '');
        $email = (string) $request->request->get('email', '');
        $password = (string) $request->request->get('password', '');

        $userRepository = new DoctrineUserRepository($this->entityManager);
        $registerUseCase = new RegisterUserUseCase($userRepository);

        try {
            $registerUseCase->execute($name, $email, $password);

            return new RedirectResponse('/login');
        } catch (\InvalidArgumentException $e) {
            return $this->render('auth/register', ['error' => $e->getMessage()]);
        }
    }

    public function logout(Request $request): Response
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }

        return new RedirectResponse('/login');
    }
}
