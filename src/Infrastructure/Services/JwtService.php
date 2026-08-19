<?php

declare(strict_types=1);

namespace Libok\Infrastructure\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JwtService
{
    private string $secret;
    private string $previousSecret;
    private string $algorithm;
    private int $accessTtl;
    private int $refreshTtl;

    public function __construct()
    {
        $this->secret = (string) ($_ENV['JWT_SECRET'] ?? getenv('JWT_SECRET') ?: '');
        $this->previousSecret = (string) ($_ENV['JWT_SECRET_PREVIOUS'] ?? getenv('JWT_SECRET_PREVIOUS') ?: '');
        $this->algorithm = (string) ($_ENV['JWT_ALGORITHM'] ?? 'HS256');
        $this->accessTtl = (int) ($_ENV['JWT_ACCESS_TTL'] ?? 900);
        $this->refreshTtl = (int) ($_ENV['JWT_REFRESH_TTL'] ?? 1209600);

        if (strlen($this->secret) < 32) {
            throw new \RuntimeException('JWT_SECRET must be at least 32 characters.');
        }
    }

    /**
     * @param list<string> $roles
     * @param array<string, mixed> $extra
     */
    public function issueAccessToken(string $userId, array $roles, array $extra = []): string
    {
        $now = time();

        $payload = array_merge($extra, [
            'iss' => $_ENV['APP_URL'] ?? 'libok',
            'sub' => $userId,
            'iat' => $now,
            'exp' => $now + $this->accessTtl,
            'type' => 'access',
            'roles' => $roles,
        ]);

        return JWT::encode($payload, $this->secret, $this->algorithm);
    }

    public function issueRefreshToken(string $userId): string
    {
        $now = time();

        $payload = [
            'iss' => $_ENV['APP_URL'] ?? 'libok',
            'sub' => $userId,
            'iat' => $now,
            'exp' => $now + $this->refreshTtl,
            'type' => 'refresh',
            'jti' => bin2hex(random_bytes(16)),
        ];

        return JWT::encode($payload, $this->secret, $this->algorithm);
    }

    /** @return array<string, mixed> */
    public function decode(string $token): array
    {
        try {
            return (array) JWT::decode($token, new Key($this->secret, $this->algorithm));
        } catch (\Throwable $e) {
            if (strlen($this->previousSecret) < 32) {
                throw $e;
            }

            return (array) JWT::decode($token, new Key($this->previousSecret, $this->algorithm));
        }
    }

    /** @return array<string, mixed> */
    public function decodeRefreshToken(string $token): array
    {
        $payload = $this->decode($token);

        if (($payload['type'] ?? '') !== 'refresh') {
            throw new \InvalidArgumentException('Invalid refresh token.');
        }

        return $payload;
    }

    public function getAccessTtl(): int
    {
        return $this->accessTtl;
    }

    public function getRefreshTtl(): int
    {
        return $this->refreshTtl;
    }
}
