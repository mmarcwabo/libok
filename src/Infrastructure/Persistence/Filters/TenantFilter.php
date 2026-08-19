<?php

declare(strict_types=1);

namespace Libok\Infrastructure\Persistence\Filters;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;
use Libok\Domain\AllowsGlobalRows;

final class TenantFilter extends SQLFilter
{
    public function addFilterConstraint(ClassMetadata $targetEntity, mixed $targetTableAlias): string
    {
        if (!is_string($targetTableAlias) || !$targetEntity->hasField('organizationId')) {
            return '';
        }

        $parameter = $this->getParameter('organization_id');
        if (is_a($targetEntity->getName(), AllowsGlobalRows::class, true)) {
            return sprintf(
                '(%s.organization_id = %s OR %s.organization_id IS NULL)',
                $targetTableAlias,
                $parameter,
                $targetTableAlias,
            );
        }

        return sprintf('%s.organization_id = %s', $targetTableAlias, $parameter);
    }
}
