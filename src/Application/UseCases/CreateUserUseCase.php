<?php

declare(strict_types=1);

namespace Libok\Application\UseCases;

use Libok\Domain\Entities\User;
use Libok\Domain\Repositories\UserRepositoryInterface;
use Libok\Infrastructure\Services\PasswordService;

class CreateUserUseCase
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly PasswordService $passwordService = new PasswordService(),
    ) {
    }

    /**
     * @param list<string> $roles
     */
    public function execute(string $name, string $email, string $password, array $roles = [User::ROLE_MEMBER]): User
    {
        $name = trim($name);
        $email = strtolower(trim($email));

        if ($name === '' || strlen($name) > 255) {
            throw new \InvalidArgumentException('Name is required.');
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
            throw new \InvalidArgumentException('A valid email is required.');
        }
        if ($this->userRepository->findByEmail($email)) {
            throw new \InvalidArgumentException('User with this email already exists.');
        }

        $allowed = [
            User::ROLE_MEMBER,
            User::ROLE_MANAGER,
            User::ROLE_ADMIN,
            User::ROLE_SUPER_ADMIN,
        ];
        $roles = array_values(array_intersect($roles, $allowed));
        if ($roles === []) {
            $roles = [User::ROLE_MEMBER];
        }

        $user = new User($name, $email, $this->passwordService->hash($password), $roles);
        $this->userRepository->save($user);

        return $user;
    }
}
