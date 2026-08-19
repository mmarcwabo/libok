<?php

declare(strict_types=1);

namespace Libok\Application\Contracts;

interface OutboxWriterInterface
{
    /**
     * @param array<string, mixed> $payload
     */
    public function append(
        string $type,
        array $payload,
        ?string $aggregateId = null,
        ?string $organizationId = null,
    ): string;
}
