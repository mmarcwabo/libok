<?php

declare(strict_types=1);

namespace Libok\Infrastructure\Persistence\Listeners;

use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Events;
use Libok\Domain\AllowsGlobalRows;
use Libok\Infrastructure\Tenancy\TenantContext;

final class TenantAssignmentSubscriber implements EventSubscriber
{
    public function __construct(private readonly TenantContext $tenantContext)
    {
    }

    public function getSubscribedEvents(): array
    {
        return [Events::prePersist];
    }

    public function prePersist(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!method_exists($entity, 'getOrganizationId') || !method_exists($entity, 'setOrganizationId')) {
            return;
        }

        if ($entity->getOrganizationId() === null && $this->tenantContext->isResolved()) {
            $entity->setOrganizationId($this->tenantContext->requireOrganizationId());
        }

        if ($entity->getOrganizationId() === null && !$entity instanceof AllowsGlobalRows) {
            throw new \DomainException('Tenant context is required.');
        }

        if ($this->tenantContext->isResolved()
            && !$this->tenantContext->isCrossTenant()
            && $entity->getOrganizationId() !== $this->tenantContext->requireOrganizationId()) {
            throw new \DomainException('Cannot persist an entity outside the current tenant.');
        }
    }
}
