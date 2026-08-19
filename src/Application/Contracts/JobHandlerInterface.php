<?php

declare(strict_types=1);

namespace Libok\Application\Contracts;

interface JobHandlerInterface
{
    public function type(): string;

    /** @param array<string, mixed> $payload */
    public function handle(array $payload): void;
}
