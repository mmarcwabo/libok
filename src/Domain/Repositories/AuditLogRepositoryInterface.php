<?php

declare(strict_types=1);

namespace Libok\Domain\Repositories;

use Libok\Domain\Entities\AuditLog;

interface AuditLogRepositoryInterface
{
    /**
     * @param array<string, mixed> $filters
     * @return list<AuditLog>
     */
    public function findAll(int $page, int $perPage, array $filters = []): array;

    /** @param array<string, mixed> $filters */
    public function countAll(array $filters = []): int;

    public function save(AuditLog $log): void;
}
