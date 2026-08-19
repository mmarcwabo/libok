<?php

declare(strict_types=1);

namespace Libok\Application\Contracts;

final class RateLimitResult
{
    public function __construct(
        public readonly bool $allowed,
        public readonly int $remaining,
        public readonly int $retryAfter,
    ) {
    }
}
