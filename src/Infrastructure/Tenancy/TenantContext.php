<?php

declare(strict_types=1);

namespace Libok\Infrastructure\Tenancy;

use Libok\Domain\Entities\Organization;
use Libok\Domain\Entities\OrganizationMembership;

final class TenantContext
{
    private ?Organization $organization = null;
    private ?OrganizationMembership $membership = null;
    private bool $crossTenant = false;

    public function reset(): void
    {
        $this->organization = null;
        $this->membership = null;
        $this->crossTenant = false;
    }

    public function resolve(Organization $organization, OrganizationMembership $membership): void
    {
        if ($this->organization !== null && $this->organization->getId() !== $organization->getId()) {
            throw new \LogicException('Tenant context cannot be replaced during a request.');
        }
        if (!$membership->isActive() || $membership->getOrganization()->getId() !== $organization->getId()) {
            throw new \DomainException('An active membership is required for this organization.');
        }
        $this->organization = $organization;
        $this->membership = $membership;
    }

    public function requireOrganizationId(): string
    {
        if ($this->organization === null) {
            throw new \DomainException('Tenant context is required.');
        }

        return $this->organization->getId();
    }

    public function getOrganizationId(): ?string
    {
        return $this->organization?->getId();
    }

    public function getOrganization(): ?Organization
    {
        return $this->organization;
    }

    public function getMembership(): ?OrganizationMembership
    {
        return $this->membership;
    }

    public function isResolved(): bool
    {
        return $this->organization !== null;
    }

    public function isCrossTenant(): bool
    {
        return $this->crossTenant;
    }

    public function enableAuditedCrossTenantAccess(): void
    {
        if ($this->organization === null) {
            throw new \LogicException('Resolve an actor tenant before enabling cross-tenant access.');
        }
        $this->crossTenant = true;
    }
}
