<?php

declare(strict_types=1);

namespace Libok\Application\UseCases\Auth;

use Libok\Domain\Entities\RefreshToken;
use Libok\Domain\Repositories\RefreshTokenRepositoryInterface;
use Libok\Domain\Repositories\UserRepositoryInterface;
use Libok\Infrastructure\Services\JwtService;
use Ramsey\Uuid\Uuid;

class RefreshTokenUseCase
{
    public function __construct(
        private readonly JwtService $jwtService,
        private readonly RefreshTokenRepositoryInterface $refreshTokenRepository,
        private readonly UserRepositoryInterface $userRepository,
    ) {
    }

    /**
     * @return array{access_token: string, refresh_token: string, token_type: string, expires_in: int}
     */
    public function execute(string $rawToken): array
    {
        try {
            $payload = $this->jwtService->decodeRefreshToken($rawToken);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Invalid or expired refresh token.', 0, $e);
        }

        $hash = RefreshToken::hashToken($rawToken);
        $refreshToken = $this->refreshTokenRepository->findByTokenHash($hash);

        if ($refreshToken === null || !$refreshToken->isValid()) {
            throw new \RuntimeException('Invalid or expired refresh token.');
        }

        $userId = is_string($payload['sub'] ?? null) ? $payload['sub'] : '';
        $user = $this->userRepository->findById($userId);

        if ($user === null || !$user->isActive()) {
            throw new \RuntimeException('Invalid or expired refresh token.');
        }
        if ($refreshToken->getUser()->getId() !== $user->getId()) {
            throw new \RuntimeException('Invalid or expired refresh token.');
        }

        $accessToken = $this->jwtService->issueAccessToken($user->getId(), $user->getRoleNames());
        $newRawRefreshToken = $this->jwtService->issueRefreshToken($user->getId());
        $now = new \DateTimeImmutable();
        $newRefreshToken = new RefreshToken();
        $newRefreshToken->setId(Uuid::uuid4()->toString());
        $newRefreshToken->setUser($user);
        $newRefreshToken->setTokenHash(RefreshToken::hashToken($newRawRefreshToken));
        $expiresAt = $now->modify('+' . $this->jwtService->getRefreshTtl() . ' seconds');
        if ($expiresAt === false) {
            throw new \RuntimeException('Unable to compute refresh token expiry.');
        }
        $newRefreshToken->setExpiresAt($expiresAt);
        $newRefreshToken->setCreatedAt($now);

        $refreshToken->revoke();
        $this->refreshTokenRepository->save($refreshToken);
        $this->refreshTokenRepository->save($newRefreshToken);

        return [
            'access_token' => $accessToken,
            'refresh_token' => $newRawRefreshToken,
            'token_type' => 'Bearer',
            'expires_in' => $this->jwtService->getAccessTtl(),
        ];
    }
}
