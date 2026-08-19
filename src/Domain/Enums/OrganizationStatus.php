<?php

declare(strict_types=1);

namespace Libok\Domain\Enums;

enum OrganizationStatus: string
{
    case ACTIVE = 'active';
    case SUSPENDED = 'suspended';
    case ARCHIVED = 'archived';
}
