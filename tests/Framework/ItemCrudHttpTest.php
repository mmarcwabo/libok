<?php

declare(strict_types=1);

namespace Tests\Framework;

use Doctrine\ORM\EntityManagerInterface;
use Libok\Domain\Entities\Organization;
use Libok\Domain\Entities\OrganizationMembership;
use Libok\Domain\Entities\User;
use Libok\Domain\Enums\MembershipRole;

final class ItemCrudHttpTest extends KernelTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureSchema();
    }

    public function testMemberCanListCreateUpdateAndDeleteItems(): void
    {
        [$organization, $cookies] = $this->seedOrgAndLogin('item-crud', 'Item Org', 'item-crud@example.test');
        $headers = ['X-Organization' => $organization->getId()];

        $first = $this->jsonRequest('POST', '/api/v1/items', $headers, $cookies, ['title' => 'Alpha']);
        $second = $this->jsonRequest('POST', '/api/v1/items', $headers, $cookies, ['title' => 'Beta']);
        self::assertSame(201, $first->getStatusCode(), (string) $first->getContent());
        self::assertSame(201, $second->getStatusCode(), (string) $second->getContent());
        $id = $this->decode((string) $first->getContent())['data']['id'];
        self::assertIsString($id);

        $list = $this->jsonRequest('GET', '/api/v1/items?per_page=1&sort=title:asc', $headers, $cookies);
        self::assertSame(200, $list->getStatusCode(), (string) $list->getContent());
        $payload = $this->decode((string) $list->getContent());
        self::assertTrue($payload['success']);
        self::assertCount(1, $payload['data']);
        self::assertSame('Alpha', $payload['data'][0]['title']);
        self::assertSame(1, $payload['pagination']['page']);
        self::assertSame(1, $payload['pagination']['per_page']);
        self::assertSame(2, $payload['pagination']['total']);

        $updated = $this->jsonRequest('PATCH', '/api/v1/items/' . $id, $headers, $cookies, ['title' => 'Alpha renamed']);
        self::assertSame(200, $updated->getStatusCode(), (string) $updated->getContent());
        self::assertSame('Alpha renamed', $this->decode((string) $updated->getContent())['data']['title']);

        $deleted = $this->jsonRequest('DELETE', '/api/v1/items/' . $id, $headers, $cookies);
        self::assertSame(204, $deleted->getStatusCode());

        $missing = $this->jsonRequest('GET', '/api/v1/items/' . $id, $headers, $cookies);
        self::assertSame(404, $missing->getStatusCode());
        self::assertSame('Resource not found.', $this->decode((string) $missing->getContent())['message']);
    }

    /**
     * @return array{0: Organization, 1: array<string, string>}
     */
    private function seedOrgAndLogin(string $slug, string $orgName, string $email): array
    {
        $entityManager = $this->entityManager();
        $organization = new Organization($orgName, $slug);
        $entityManager->persist($organization);
        $entityManager->flush();

        $this->jsonRequest('POST', '/api/v1/auth/register', [], [], [
            'name' => $orgName,
            'email' => $email,
            'password' => 'password123',
        ]);
        $user = $entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        self::assertInstanceOf(User::class, $user);
        $membership = new OrganizationMembership($organization, $user, MembershipRole::OWNER);
        $membership->setDefault(true);
        $entityManager->persist($membership);
        $entityManager->flush();
        $entityManager->clear();

        $login = $this->jsonRequest('POST', '/api/v1/auth/login', [], [], [
            'email' => $email,
            'password' => 'password123',
        ]);
        self::assertSame(200, $login->getStatusCode(), (string) $login->getContent());
        $reloaded = $this->entityManager()->find(Organization::class, $organization->getId());
        self::assertInstanceOf(Organization::class, $reloaded);

        return [$reloaded, $this->cookiesFrom($login)];
    }

    private function entityManager(): EntityManagerInterface
    {
        $container = require dirname(__DIR__, 2) . '/config/services.php';

        return $container->get(EntityManagerInterface::class);
    }
}
