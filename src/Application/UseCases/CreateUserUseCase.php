<?php

declare(strict_types=1);

namespace Libok\Application\UseCases;

use Libok\Domain\Entities\User;
use Libok\Domain\Repositories\UserRepositoryInterface;

class CreateUserUseCase
{
    public function __construct(private readonly UserRepositoryInterface $userRepository) {}

    public function execute(string $name, string $email, string $password): User
    {
        if ($this->userRepository->findByEmail($email)) {
            throw new \InvalidArgumentException("User with email {$email} already exists.");
        }
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $user = new User($name, $email, $hashedPassword);
        $this->userRepository->save($user);
        return $user;
    }
}