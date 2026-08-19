<?php

declare(strict_types=1);

namespace Libok\Domain\Entities\Concerns;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Persistence\Proxy;

trait OrganizationOwned
{
    #[ORM\Column(name: 'organization_id', type: 'string', length: 36, nullable: true)]
    private ?string $organizationId = null;

    public function getOrganizationId(): ?string
    {
        if ($this instanceof Proxy && !$this->__isInitialized()) {
            $this->__load();
        }

        return $this->organizationId;
    }

    public function setOrganizationId(?string $organizationId): void
    {
        if ($this instanceof Proxy && !$this->__isInitialized()) {
            $this->__load();
        }

        $this->organizationId = $organizationId;
    }
}
