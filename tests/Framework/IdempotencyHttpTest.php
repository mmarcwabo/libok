<?php

declare(strict_types=1);

namespace Tests\Framework;

use Doctrine\ORM\EntityManagerInterface;
use Libok\Domain\Entities\Organization;
use Libok\Domain\Entities\OrganizationMembership;
use Libok\Domain\Entities\User;
use Libok\Domain\Enums\MembershipRole;

final class IdempotencyHttpTest extends KernelTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureSchema();
    }

    public function testRegisterReplaysTheSameResponseForTheSameKeyAndBody(): void
    {
        $headers = ['Idempotency-Key' => 'reg-ada-1'];
        $body = [
            'name' => 'Ada Idempotent',
            'email' => 'ada-idem@example.test',
            'password' => 'password123',
        ];
        $first = $this->jsonRequest('POST', '/api/v1/auth/register', $headers, [], $body);
        $second = $this->jsonRequest('POST', '/api/v1/auth/register', $headers, [], $body);

        self::assertSame(201, $first->getStatusCode(), (string) $first->getContent());
        self::assertSame(201, $second->getStatusCode(), (string) $second->getContent());
        self::assertSame($first->getContent(), $second->getContent());
        self::assertSame('1', $second->headers->get('X-Idempotent-Replay'));

        $users = $this->entityManager()->getRepository(User::class)->findBy(['email' => 'ada-idem@example.test']);
        self::assertCount(1, $users);
    }

    public function testRegisterRejectsTheSameKeyWithADifferentBody(): void
    {
        $headers = ['Idempotency-Key' => 'reg-ada-2'];
        $first = $this->jsonRequest('POST', '/api/v1/auth/register', $headers, [], [
            'name' => 'Ada One',
            'email' => 'ada-mismatch@example.test',
            'password' => 'password123',
        ]);
        $second = $this->jsonRequest('POST', '/api/v1/auth/register', $headers, [], [
            'name' => 'Ada Two',
            'email' => 'ada-other@example.test',
            'password' => 'password123',
        ]);

        self::assertSame(201, $first->getStatusCode());
        self::assertSame(409, $second->getStatusCode());
        $payload = $this->decode((string) $second->getContent());
        self::assertSame('idempotency.mismatch', $payload['code']);
        self::assertSame('This Idempotency-Key was already used with a different request.', $payload['message']);
    }

    public function testItemCreateReplaysWithTheSameIdempotencyKey(): void
    {
        [$organization, $cookies] = $this->seedOrgAndLogin('idem-org', 'Idem Org', 'idem-org@example.test');
        $headers = [
            'Idempotency-Key' => 'item-1',
            'X-Organization' => $organization->getId(),
        ];
        $first = $this->jsonRequest('POST', '/api/v1/items', $headers, $cookies, ['title' => 'Once']);
        $second = $this->jsonRequest('POST', '/api/v1/items', $headers, $cookies, ['title' => 'Once']);

        self::assertSame(201, $first->getStatusCode(), (string) $first->getContent());
        self::assertSame(201, $second->getStatusCode(), (string) $second->getContent());
        self::assertSame($first->getContent(), $second->getContent());
        self::assertSame(
            $this->decode((string) $first->getContent())['data']['id'],
            $this->decode((string) $second->getContent())['data']['id'],
        );
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
