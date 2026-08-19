<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Services;

use Libok\Infrastructure\Services\JwtService;
use PHPUnit\Framework\TestCase;

final class JwtServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $_ENV['JWT_SECRET'] = 'libok-test-jwt-secret-32-bytes!!';
        $_ENV['JWT_ACCESS_TTL'] = '900';
        $_ENV['JWT_REFRESH_TTL'] = '1209600';
        $_ENV['APP_URL'] = 'http://localhost:8000';
    }

    public function testIssuesAndDecodesAccessTokenClaims(): void
    {
        $jwt = new JwtService();
        $payload = $jwt->decode(
            $jwt->issueAccessToken('user-123', ['member']),
        );

        self::assertSame('user-123', $payload['sub']);
        self::assertSame(['member'], $payload['roles']);
        self::assertSame('access', $payload['type']);
        self::assertGreaterThan($payload['iat'], $payload['exp']);
    }

    public function testRejectsAccessTokenAsRefreshToken(): void
    {
        $jwt = new JwtService();

        $this->expectException(\InvalidArgumentException::class);
        $jwt->decodeRefreshToken($jwt->issueAccessToken('user-123', ['member']));
    }

    public function testRejectsAnUnsafeSecret(): void
    {
        $original = $_ENV['JWT_SECRET'];
        $_ENV['JWT_SECRET'] = 'too-short';

        try {
            $this->expectException(\RuntimeException::class);
            new JwtService();
        } finally {
            $_ENV['JWT_SECRET'] = $original;
        }
    }

    public function testDecodesWithPreviousSecretDuringRotation(): void
    {
        $jwt = new JwtService();
        $token = $jwt->issueAccessToken('user-123', ['member']);

        $_ENV['JWT_SECRET_PREVIOUS'] = $_ENV['JWT_SECRET'];
        $_ENV['JWT_SECRET'] = 'libok-rotated-jwt-secret-32byte!';
        $rotated = new JwtService();

        $payload = $rotated->decode($token);
        self::assertSame('user-123', $payload['sub']);
    }
}
