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

    public function execute(string $name, string $email, string $password): User
    {
        if ($this->userRepository->findByEmail($email)) {
            throw new \InvalidArgumentException("User with email {$email} already exists.");
        }
        $user = new User($name, $email, $this->passwordService->hash($password));
        $this->userRepository->save($user);

        return $user;
    }
}
