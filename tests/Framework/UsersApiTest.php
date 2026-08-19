<?php

declare(strict_types=1);

namespace Tests\Framework;

use Doctrine\ORM\EntityManagerInterface;
use Libok\Domain\Entities\User;

final class UsersApiTest extends KernelTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureSchema();
    }

    public function testMemberCannotListUsers(): void
    {
        $this->jsonRequest('POST', '/api/v1/auth/register', [], [], [
            'name' => 'Member',
            'email' => 'member-users@example.test',
            'password' => 'password123',
        ]);
        $login = $this->jsonRequest('POST', '/api/v1/auth/login', [], [], [
            'email' => 'member-users@example.test',
            'password' => 'password123',
        ]);
        $response = $this->jsonRequest('GET', '/api/v1/users', [], $this->cookiesFrom($login));

        self::assertSame(403, $response->getStatusCode());
        self::assertSame('forbidden', $this->decode((string) $response->getContent())['code']);
    }

    public function testAdminListsUsersWithPagination(): void
    {
        $cookies = $this->loginAsAdmin('admin-list@example.test');
        $this->jsonRequest('POST', '/api/v1/auth/register', [], [], [
            'name' => 'Second User',
            'email' => 'second-users@example.test',
            'password' => 'password123',
        ]);

        $response = $this->jsonRequest('GET', '/api/v1/users?per_page=1&sort=created_at:desc', [], $cookies);
        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decode((string) $response->getContent());
        self::assertTrue($payload['success']);
        self::assertCount(1, $payload['data']);
        self::assertSame(1, $payload['pagination']['page']);
        self::assertSame(1, $payload['pagination']['per_page']);
        self::assertGreaterThanOrEqual(2, $payload['pagination']['total']);
        self::assertArrayNotHasKey('password', $payload['data'][0]);
    }

    public function testAdminCreatesUpdatesAndDeletesUser(): void
    {
        $cookies = $this->loginAsAdmin('admin-crud@example.test');

        $created = $this->jsonRequest('POST', '/api/v1/users', [], $cookies, [
            'name' => 'Casey',
            'email' => 'casey@example.test',
            'password' => 'password123',
            'roles' => [User::ROLE_MEMBER],
        ]);
        self::assertSame(201, $created->getStatusCode());
        $id = $this->decode((string) $created->getContent())['data']['id'];

        $shown = $this->jsonRequest('GET', '/api/v1/users/' . $id, [], $cookies);
        self::assertSame(200, $shown->getStatusCode());

        $updated = $this->jsonRequest('PATCH', '/api/v1/users/' . $id, [], $cookies, [
            'name' => 'Casey Updated',
            'email' => 'casey@example.test',
        ]);
        self::assertSame(200, $updated->getStatusCode());
        self::assertSame('Casey Updated', $this->decode((string) $updated->getContent())['data']['name']);

        $deleted = $this->jsonRequest('DELETE', '/api/v1/users/' . $id, [], $cookies);
        self::assertSame(204, $deleted->getStatusCode());

        $missing = $this->jsonRequest('GET', '/api/v1/users/' . $id, [], $cookies);
        self::assertSame(404, $missing->getStatusCode());
    }

    /** @return array<string, string> */
    private function loginAsAdmin(string $email): array
    {
        $this->jsonRequest('POST', '/api/v1/auth/register', [], [], [
            'name' => 'Admin',
            'email' => $email,
            'password' => 'password123',
        ]);

        $container = require dirname(__DIR__, 2) . '/config/services.php';
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);
        $user = $entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        self::assertInstanceOf(User::class, $user);
        $user->setRoles([User::ROLE_ADMIN]);
        $entityManager->flush();
        $entityManager->clear();
        $reloaded = $entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        self::assertInstanceOf(User::class, $reloaded);
        self::assertSame([User::ROLE_ADMIN], $reloaded->getRoleNames());

        $login = $this->jsonRequest('POST', '/api/v1/auth/login', [], [], [
            'email' => $email,
            'password' => 'password123',
        ]);
        self::assertSame(200, $login->getStatusCode(), (string) $login->getContent());
        $cookies = $this->cookiesFrom($login);
        $ping = $this->jsonRequest('GET', '/api/v1/secure/ping', [], $cookies);
        self::assertSame(200, $ping->getStatusCode(), (string) $ping->getContent());
        self::assertSame(
            [User::ROLE_ADMIN],
            $this->decode((string) $ping->getContent())['data']['roles'],
        );

        return $cookies;
    }
}
