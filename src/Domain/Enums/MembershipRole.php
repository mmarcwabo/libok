<?php

declare(strict_types=1);

namespace Libok\Domain\Enums;

enum MembershipRole: string
{
    case OWNER = 'owner';
    case ADMIN = 'admin';
    case MANAGER = 'manager';
    case MEMBER = 'member';
}
