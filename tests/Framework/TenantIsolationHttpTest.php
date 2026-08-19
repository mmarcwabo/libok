<?php

declare(strict_types=1);

namespace Tests\Framework;

use Doctrine\ORM\EntityManagerInterface;
use Libok\Domain\Entities\Organization;
use Libok\Domain\Entities\OrganizationMembership;
use Libok\Domain\Entities\User;
use Libok\Domain\Enums\MembershipRole;
use Ramsey\Uuid\Uuid;

final class TenantIsolationHttpTest extends KernelTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureSchema();
    }

    public function testItemCreatedInOrgAIsNotFoundInOrgBWithTheSameMessageAsUnknownId(): void
    {
        [$orgA, $cookiesA] = $this->seedOrgAndLogin('org-a', 'Ada Org', 'ada-org@example.test');
        [$orgB, $cookiesB] = $this->seedOrgAndLogin('org-b', 'Ben Org', 'ben-org@example.test');

        $created = $this->jsonRequest('POST', '/api/v1/items', [
            'X-Organization' => $orgA->getId(),
        ], $cookiesA, ['title' => 'Secret from A']);
        self::assertSame(201, $created->getStatusCode(), (string) $created->getContent());
        $itemId = $this->decode((string) $created->getContent())['data']['id'];
        self::assertIsString($itemId);

        $asOwner = $this->jsonRequest('GET', '/api/v1/items/' . $itemId, [
            'X-Organization' => $orgA->getId(),
        ], $cookiesA);
        self::assertSame(200, $asOwner->getStatusCode());
        self::assertSame('Secret from A', $this->decode((string) $asOwner->getContent())['data']['title']);
        self::assertSame($orgA->getId(), $this->decode((string) $asOwner->getContent())['data']['organization_id']);

        $asOtherTenant = $this->jsonRequest('GET', '/api/v1/items/' . $itemId, [
            'X-Organization' => $orgB->getId(),
        ], $cookiesB);
        $unknown = $this->jsonRequest('GET', '/api/v1/items/' . Uuid::uuid4()->toString(), [
            'X-Organization' => $orgB->getId(),
        ], $cookiesB);

        self::assertSame(404, $asOtherTenant->getStatusCode());
        self::assertSame(404, $unknown->getStatusCode());
        $foreignPayload = $this->decode((string) $asOtherTenant->getContent());
        $unknownPayload = $this->decode((string) $unknown->getContent());
        self::assertSame($unknownPayload['message'], $foreignPayload['message']);
        self::assertSame('Resource not found.', $foreignPayload['message']);
        self::assertSame('http.not_found', $foreignPayload['code']);
        self::assertSame($unknownPayload['code'], $foreignPayload['code']);
    }

    public function testListUpdateAndDeleteDoNotLeakItemsAcrossOrganizations(): void
    {
        [$orgA, $cookiesA] = $this->seedOrgAndLogin('list-a', 'List A', 'list-a@example.test');
        [$orgB, $cookiesB] = $this->seedOrgAndLogin('list-b', 'List B', 'list-b@example.test');

        $created = $this->jsonRequest('POST', '/api/v1/items', [
            'X-Organization' => $orgA->getId(),
        ], $cookiesA, ['title' => 'Only in A']);
        self::assertSame(201, $created->getStatusCode(), (string) $created->getContent());
        $itemId = $this->decode((string) $created->getContent())['data']['id'];

        $listB = $this->jsonRequest('GET', '/api/v1/items', [
            'X-Organization' => $orgB->getId(),
        ], $cookiesB);
        self::assertSame(200, $listB->getStatusCode());
        $ids = array_column($this->decode((string) $listB->getContent())['data'], 'id');
        self::assertNotContains($itemId, $ids);

        $updateB = $this->jsonRequest('PATCH', '/api/v1/items/' . $itemId, [
            'X-Organization' => $orgB->getId(),
        ], $cookiesB, ['title' => 'Hijacked']);
        $deleteB = $this->jsonRequest('DELETE', '/api/v1/items/' . $itemId, [
            'X-Organization' => $orgB->getId(),
        ], $cookiesB);
        self::assertSame(404, $updateB->getStatusCode());
        self::assertSame(404, $deleteB->getStatusCode());
        self::assertSame('Resource not found.', $this->decode((string) $updateB->getContent())['message']);
        self::assertSame('http.not_found', $this->decode((string) $updateB->getContent())['code']);

        $stillThere = $this->jsonRequest('GET', '/api/v1/items/' . $itemId, [
            'X-Organization' => $orgA->getId(),
        ], $cookiesA);
        self::assertSame(200, $stillThere->getStatusCode());
        self::assertSame('Only in A', $this->decode((string) $stillThere->getContent())['data']['title']);
    }

    public function testDefaultMembershipIsUsedWhenOrganizationHeaderIsMissing(): void
    {
        [$orgA, $cookiesA] = $this->seedOrgAndLogin('default-org', 'Default Org', 'default-org@example.test');

        $created = $this->jsonRequest('POST', '/api/v1/items', [], $cookiesA, ['title' => 'Default tenant item']);
        self::assertSame(201, $created->getStatusCode(), (string) $created->getContent());
        self::assertSame($orgA->getId(), $this->decode((string) $created->getContent())['data']['organization_id']);
    }

    public function testUserWithoutMembershipCannotAccessTenantRoutes(): void
    {
        $this->jsonRequest('POST', '/api/v1/auth/register', [], [], [
            'name' => 'No Org',
            'email' => 'no-org@example.test',
            'password' => 'password123',
        ]);
        $login = $this->jsonRequest('POST', '/api/v1/auth/login', [], [], [
            'email' => 'no-org@example.test',
            'password' => 'password123',
        ]);
        $response = $this->jsonRequest('POST', '/api/v1/items', [], $this->cookiesFrom($login), [
            'title' => 'Should fail',
        ]);

        self::assertSame(403, $response->getStatusCode());
        $payload = $this->decode((string) $response->getContent());
        self::assertSame('forbidden', $payload['code']);
        self::assertSame('No active organization membership is available.', $payload['message']);
    }

    public function testOrganizationHeaderSelectsMembershipNotTheDefault(): void
    {
        $entityManager = $this->entityManager();
        $orgA = new Organization('Header A', 'header-a');
        $orgB = new Organization('Header B', 'header-b');
        $entityManager->persist($orgA);
        $entityManager->persist($orgB);
        $entityManager->flush();

        $this->jsonRequest('POST', '/api/v1/auth/register', [], [], [
            'name' => 'Multi Tenant',
            'email' => 'multi-org@example.test',
            'password' => 'password123',
        ]);
        $user = $entityManager->getRepository(User::class)->findOneBy(['email' => 'multi-org@example.test']);
        self::assertInstanceOf(User::class, $user);
        $membershipA = new OrganizationMembership($orgA, $user, MembershipRole::MEMBER);
        $membershipA->setDefault(true);
        $membershipB = new OrganizationMembership($orgB, $user, MembershipRole::ADMIN);
        $membershipB->setDefault(false);
        $entityManager->persist($membershipA);
        $entityManager->persist($membershipB);
        $entityManager->flush();
        $entityManager->clear();

        $login = $this->jsonRequest('POST', '/api/v1/auth/login', [], [], [
            'email' => 'multi-org@example.test',
            'password' => 'password123',
        ]);
        $cookies = $this->cookiesFrom($login);

        $inB = $this->jsonRequest('POST', '/api/v1/items', [
            'X-Organization' => $orgB->getSlug(),
        ], $cookies, ['title' => 'In B']);
        self::assertSame(201, $inB->getStatusCode(), (string) $inB->getContent());
        self::assertSame($orgB->getId(), $this->decode((string) $inB->getContent())['data']['organization_id']);

        $inA = $this->jsonRequest('POST', '/api/v1/items', [
            'X-Organization' => $orgA->getId(),
        ], $cookies, ['title' => 'In A']);
        self::assertSame(201, $inA->getStatusCode(), (string) $inA->getContent());
        self::assertSame($orgA->getId(), $this->decode((string) $inA->getContent())['data']['organization_id']);
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
