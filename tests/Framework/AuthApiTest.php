<?php

declare(strict_types=1);

namespace Tests\Framework;

use Doctrine\ORM\EntityManagerInterface;
use Libok\Domain\Entities\User;

final class AuthApiTest extends KernelTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureSchema();
        $this->clearRateLimitCache();
    }

    public function testRegisterLoginMeRefreshLogout(): void
    {
        $register = $this->jsonRequest('POST', '/api/v1/auth/register', [], [], [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.test',
            'password' => 'password123',
        ]);
        self::assertSame(201, $register->getStatusCode());
        $registered = $this->decode((string) $register->getContent());
        self::assertTrue($registered['success']);
        self::assertSame('ada@example.test', $registered['data']['email']);
        self::assertSame(['member'], $registered['data']['roles']);

        $login = $this->jsonRequest('POST', '/api/v1/auth/login', [], [], [
            'email' => 'ada@example.test',
            'password' => 'password123',
        ]);
        self::assertSame(200, $login->getStatusCode());
        self::assertMatchesRegularExpression('/httponly/i', implode(',', $login->headers->all('set-cookie')));
        $cookies = $this->cookiesFrom($login);
        self::assertArrayHasKey('access_token', $cookies);
        self::assertArrayHasKey('refresh_token', $cookies);

        $me = $this->jsonRequest('GET', '/api/v1/me', [], $cookies);
        self::assertSame(200, $me->getStatusCode());
        $mePayload = $this->decode((string) $me->getContent());
        self::assertSame('Ada Lovelace', $mePayload['data']['name']);

        $refresh = $this->jsonRequest('POST', '/api/v1/auth/refresh', [], $cookies);
        self::assertSame(200, $refresh->getStatusCode());
        $refreshedCookies = array_merge($cookies, $this->cookiesFrom($refresh));

        $logout = $this->jsonRequest('POST', '/api/v1/auth/logout', [], $refreshedCookies);
        self::assertSame(200, $logout->getStatusCode());

        $refreshAfterLogout = $this->jsonRequest('POST', '/api/v1/auth/refresh', [], $refreshedCookies);
        self::assertSame(401, $refreshAfterLogout->getStatusCode());

        $meWithoutCookies = $this->jsonRequest('GET', '/api/v1/me', [], $this->cookiesFrom($logout));
        self::assertSame(401, $meWithoutCookies->getStatusCode());
    }

    public function testMissingCookieReturns401(): void
    {
        $response = $this->jsonRequest('GET', '/api/v1/me');
        self::assertSame(401, $response->getStatusCode());
        $payload = $this->decode((string) $response->getContent());
        self::assertSame('Authentication required.', $payload['message']);
        self::assertSame('auth.expired', $payload['code']);
    }

    public function testUnknownEmailAndBadPasswordShareTheSameMessage(): void
    {
        $unknown = $this->jsonRequest('POST', '/api/v1/auth/login', [], [], [
            'email' => 'missing@example.test',
            'password' => 'password123',
        ]);
        $this->jsonRequest('POST', '/api/v1/auth/register', [], [], [
            'name' => 'Known User',
            'email' => 'known@example.test',
            'password' => 'password123',
        ]);
        $badPassword = $this->jsonRequest('POST', '/api/v1/auth/login', [], [], [
            'email' => 'known@example.test',
            'password' => 'wrong-password',
        ]);

        self::assertSame(401, $unknown->getStatusCode());
        self::assertSame(401, $badPassword->getStatusCode());
        $unknownPayload = $this->decode((string) $unknown->getContent());
        $badPayload = $this->decode((string) $badPassword->getContent());
        self::assertSame($unknownPayload['message'], $badPayload['message']);
        self::assertSame('Invalid credentials.', $unknownPayload['message']);
        self::assertSame('auth.invalid_credentials', $unknownPayload['code']);
    }

    public function testRolesComeFromTheDatabaseNotJwtClaims(): void
    {
        $this->jsonRequest('POST', '/api/v1/auth/register', [], [], [
            'name' => 'Admin User',
            'email' => 'admin@example.test',
            'password' => 'password123',
        ]);

        $entityManager = $this->entityManager();
        $user = $entityManager->getRepository(User::class)->findOneBy(['email' => 'admin@example.test']);
        self::assertInstanceOf(User::class, $user);
        $user->setRoles([User::ROLE_ADMIN]);
        $entityManager->flush();

        $login = $this->jsonRequest('POST', '/api/v1/auth/login', [], [], [
            'email' => 'admin@example.test',
            'password' => 'password123',
        ]);
        $cookies = $this->cookiesFrom($login);
        $ping = $this->jsonRequest('GET', '/api/v1/secure/ping', [], $cookies);
        self::assertSame(200, $ping->getStatusCode());
        self::assertSame([User::ROLE_ADMIN], $this->decode((string) $ping->getContent())['data']['roles']);

        $user->setRoles([User::ROLE_MEMBER]);
        $entityManager->flush();

        $pingAfterRevoke = $this->jsonRequest('GET', '/api/v1/secure/ping', [], $cookies);
        self::assertSame(200, $pingAfterRevoke->getStatusCode());
        self::assertSame([User::ROLE_MEMBER], $this->decode((string) $pingAfterRevoke->getContent())['data']['roles']);
    }

    public function testLoginIsRateLimited(): void
    {
        for ($i = 0; $i < 5; ++$i) {
            $response = $this->jsonRequest('POST', '/api/v1/auth/login', [], [], [
                'email' => 'rate@example.test',
                'password' => 'password123',
            ]);
            self::assertSame(401, $response->getStatusCode());
        }

        $limited = $this->jsonRequest('POST', '/api/v1/auth/login', [], [], [
            'email' => 'rate@example.test',
            'password' => 'password123',
        ]);
        self::assertSame(429, $limited->getStatusCode());
        self::assertNotEmpty($limited->headers->get('Retry-After'));
        $payload = $this->decode((string) $limited->getContent());
        self::assertSame('rate_limited', $payload['code']);
    }

    private function entityManager(): EntityManagerInterface
    {
        $container = require dirname(__DIR__, 2) . '/config/services.php';

        return $container->get(EntityManagerInterface::class);
    }

    private function clearRateLimitCache(): void
    {
        $dir = dirname(__DIR__, 2) . '/storage/cache/app';
        if (!is_dir($dir)) {
            return;
        }
        $files = glob($dir . '/*.cache') ?: [];
        foreach ($files as $file) {
            @unlink($file);
        }
    }
}
