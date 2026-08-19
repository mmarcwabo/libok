<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Tenancy;

use Libok\Domain\Entities\Organization;
use Libok\Domain\Entities\OrganizationMembership;
use Libok\Domain\Entities\User;
use Libok\Infrastructure\Tenancy\TenantContext;
use PHPUnit\Framework\TestCase;

final class TenantContextTest extends TestCase
{
    public function testTenantContextCannotBeReplacedWithinRequest(): void
    {
        $actor = new User('Actor', 'actor@example.test', password_hash('password123', PASSWORD_DEFAULT));
        $orgA = new Organization('Org A', 'org-a');
        $orgB = new Organization('Org B', 'org-b');
        $context = new TenantContext();
        $context->resolve($orgA, new OrganizationMembership($orgA, $actor));

        $this->expectException(\LogicException::class);
        $context->resolve($orgB, new OrganizationMembership($orgB, $actor));
    }

    public function testResetAllowsANewOrganization(): void
    {
        $actor = new User('Actor', 'actor@example.test', password_hash('password123', PASSWORD_DEFAULT));
        $orgA = new Organization('Org A', 'org-a');
        $orgB = new Organization('Org B', 'org-b');
        $context = new TenantContext();
        $context->resolve($orgA, new OrganizationMembership($orgA, $actor));
        $context->reset();
        $context->resolve($orgB, new OrganizationMembership($orgB, $actor));

        self::assertSame($orgB->getId(), $context->requireOrganizationId());
    }
}
