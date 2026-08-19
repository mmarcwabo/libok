<?php

declare(strict_types=1);

namespace Libok\Application\Contracts;

interface RateLimiterInterface
{
    public function consume(string $key, int $limit, int $windowSeconds): RateLimitResult;
}
