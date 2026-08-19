<?php

declare(strict_types=1);

namespace Libok\Application\UseCases;

use Libok\Domain\Entities\User;
use Libok\Domain\Repositories\UserRepositoryInterface;

class UpdateUserUseCase
{
    public function __construct(private readonly UserRepositoryInterface $userRepository)
    {
    }

    public function execute(string $id, string $name, string $email): ?User
    {
        $user = $this->userRepository->findById($id);
        if ($user === null) {
            return null;
        }

        $name = trim($name);
        $email = strtolower(trim($email));
        if ($name === '' || strlen($name) > 255) {
            throw new \InvalidArgumentException('Name is required.');
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
            throw new \InvalidArgumentException('A valid email is required.');
        }

        $existing = $this->userRepository->findByEmail($email);
        if ($existing !== null && $existing->getId() !== $id) {
            throw new \InvalidArgumentException('User with this email already exists.');
        }

        $user->setName($name);
        $user->setEmail($email);
        $this->userRepository->save($user);

        return $user;
    }
}
