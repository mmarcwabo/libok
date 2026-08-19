<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Tenancy;

use Doctrine\ORM\EntityManagerInterface;
use Libok\Domain\Entities\Item;
use Libok\Domain\Entities\Organization;
use Libok\Domain\Entities\OrganizationMembership;
use Libok\Domain\Entities\User;
use Libok\Infrastructure\Tenancy\TenantContext;
use Tests\Framework\KernelTestCase;

final class TenantAssignmentSubscriberTest extends KernelTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureSchema();
    }

    public function testCannotPersistAnItemForAnotherOrganization(): void
    {
        $container = require dirname(__DIR__, 3) . '/config/services.php';
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);
        /** @var TenantContext $context */
        $context = $container->get(TenantContext::class);

        $orgA = new Organization('Assign A', 'assign-a');
        $orgB = new Organization('Assign B', 'assign-b');
        $user = new User('Assigner', 'assigner@example.test', password_hash('password123', PASSWORD_DEFAULT));
        $entityManager->persist($orgA);
        $entityManager->persist($orgB);
        $entityManager->persist($user);
        $entityManager->flush();

        $context->resolve($orgA, new OrganizationMembership($orgA, $user));
        $item = new Item('Foreign row');
        $item->setOrganizationId($orgB->getId());

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot persist an entity outside the current tenant.');
        $entityManager->persist($item);
    }

    public function testFilterHidesRowsFromOtherOrganizations(): void
    {
        $container = require dirname(__DIR__, 3) . '/config/services.php';
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);

        $orgA = new Organization('Filter A', 'filter-a');
        $orgB = new Organization('Filter B', 'filter-b');
        $entityManager->persist($orgA);
        $entityManager->persist($orgB);
        $entityManager->flush();

        $itemA = new Item('Visible');
        $itemA->setOrganizationId($orgA->getId());
        $itemB = new Item('Hidden');
        $itemB->setOrganizationId($orgB->getId());
        $entityManager->persist($itemA);
        $entityManager->persist($itemB);
        $entityManager->flush();
        $entityManager->clear();

        $filters = $entityManager->getFilters();
        $filter = $filters->isEnabled('tenant') ? $filters->getFilter('tenant') : $filters->enable('tenant');
        $filter->setParameter('organization_id', $orgA->getId());

        $visible = $entityManager->getRepository(Item::class)->findAll();
        $ids = array_map(static fn (Item $item): string => $item->getId(), $visible);
        self::assertContains($itemA->getId(), $ids);
        self::assertNotContains($itemB->getId(), $ids);

        $filters->disable('tenant');
    }
}
