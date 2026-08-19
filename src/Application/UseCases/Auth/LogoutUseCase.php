<?php

declare(strict_types=1);

namespace Libok\Application\UseCases\Auth;

use Libok\Domain\Entities\RefreshToken;
use Libok\Domain\Repositories\RefreshTokenRepositoryInterface;

class LogoutUseCase
{
    public function __construct(
        private readonly RefreshTokenRepositoryInterface $refreshTokenRepository,
    ) {
    }

    public function execute(string $rawToken): void
    {
        $hash = RefreshToken::hashToken($rawToken);
        $refreshToken = $this->refreshTokenRepository->findByTokenHash($hash);

        if ($refreshToken !== null && !$refreshToken->isRevoked()) {
            $refreshToken->revoke();
            $this->refreshTokenRepository->save($refreshToken);
        }
    }
}
