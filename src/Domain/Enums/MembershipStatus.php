<?php

declare(strict_types=1);

namespace Libok\Domain\Enums;

enum MembershipStatus: string
{
    case ACTIVE = 'active';
    case INVITED = 'invited';
    case SUSPENDED = 'suspended';
    case REMOVED = 'removed';
}
