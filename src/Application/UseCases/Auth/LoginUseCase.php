<?php

declare(strict_types=1);

namespace Libok\Application\UseCases\Auth;

use Libok\Domain\Entities\RefreshToken;
use Libok\Domain\Entities\User;
use Libok\Domain\Repositories\RefreshTokenRepositoryInterface;
use Libok\Domain\Repositories\UserRepositoryInterface;
use Libok\Framework\Resources\UserResource;
use Libok\Infrastructure\Services\JwtService;
use Libok\Infrastructure\Services\PasswordService;
use Ramsey\Uuid\Uuid;

class LoginUseCase
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly PasswordService $passwordService,
        private readonly JwtService $jwtService,
        private readonly RefreshTokenRepositoryInterface $refreshTokenRepository,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @return array{access_token: string, refresh_token: string, token_type: string, expires_in: int, user: array<string, mixed>}
     */
    public function execute(array $data): array
    {
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $password = (string) ($data['password'] ?? '');

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
            $this->passwordService->verifyAgainstDummy($password);
            throw new \InvalidArgumentException('Invalid credentials.');
        }

        $user = $this->userRepository->findByEmail($email);
        if ($user === null) {
            $this->passwordService->verifyAgainstDummy($password);
            throw new \InvalidArgumentException('Invalid credentials.');
        }

        if (!$this->passwordService->verify($password, $user->getPassword())) {
            throw new \InvalidArgumentException('Invalid credentials.');
        }

        if (!$user->isActive()) {
            throw new \InvalidArgumentException('Invalid credentials.');
        }

        return $this->issueSession($user);
    }

    /**
     * @return array{access_token: string, refresh_token: string, token_type: string, expires_in: int, user: array<string, mixed>}
     */
    private function issueSession(User $user): array
    {
        $accessToken = $this->jwtService->issueAccessToken($user->getId(), $user->getRoleNames());
        $rawRefreshToken = $this->jwtService->issueRefreshToken($user->getId());

        $now = new \DateTimeImmutable();
        $refreshToken = new RefreshToken();
        $refreshToken->setId(Uuid::uuid4()->toString());
        $refreshToken->setUser($user);
        $refreshToken->setTokenHash(RefreshToken::hashToken($rawRefreshToken));
        $expiresAt = $now->modify('+' . $this->jwtService->getRefreshTtl() . ' seconds');
        if ($expiresAt === false) {
            throw new \RuntimeException('Unable to compute refresh token expiry.');
        }
        $refreshToken->setExpiresAt($expiresAt);
        $refreshToken->setCreatedAt($now);
        $this->refreshTokenRepository->save($refreshToken);

        return [
            'access_token' => $accessToken,
            'refresh_token' => $rawRefreshToken,
            'token_type' => 'Bearer',
            'expires_in' => $this->jwtService->getAccessTtl(),
            'user' => UserResource::toArray($user),
        ];
    }
}
