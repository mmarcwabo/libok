<?php

declare(strict_types=1);

namespace Libok\Domain;

/**
 * Tenant-owned entities that also expose global rows (organization_id NULL).
 */
interface AllowsGlobalRows
{
}
