<?php

declare(strict_types=1);

namespace Libok\Infrastructure\Cache;

use Libok\Application\Contracts\CacheStoreInterface;
use Libok\Application\Contracts\RateLimiterInterface;
use Libok\Application\Contracts\RateLimitResult;

final class FixedWindowRateLimiter implements RateLimiterInterface
{
    public function __construct(private readonly CacheStoreInterface $cache)
    {
    }

    public function consume(string $key, int $limit, int $windowSeconds): RateLimitResult
    {
        $count = $this->cache->increment('rate-limit:' . hash('sha256', $key), $windowSeconds);

        return new RateLimitResult(
            allowed: $count <= $limit,
            remaining: max(0, $limit - $count),
            retryAfter: $count <= $limit ? 0 : $windowSeconds,
        );
    }
}
