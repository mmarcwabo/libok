<?php

declare(strict_types=1);

namespace Libok\Application\UseCases;

use Libok\Domain\Entities\User;
use Libok\Domain\Repositories\UserRepositoryInterface;
use Libok\Infrastructure\Services\PasswordService;

class LoginUserUseCase
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly PasswordService $passwordService = new PasswordService(),
    ) {
    }

    public function execute(string $email, string $password): ?User
    {
        $user = $this->userRepository->findByEmail(strtolower(trim($email)));
        $hash = $user !== null ? $user->getPassword() : PasswordService::DUMMY_HASH;
        $ok = $this->passwordService->verify($password, $hash);

        if ($user !== null && $user->isActive() && $ok) {
            return $user;
        }

        return null;
    }
}
