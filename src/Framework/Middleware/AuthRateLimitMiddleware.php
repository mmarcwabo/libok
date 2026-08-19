<?php

declare(strict_types=1);

namespace Libok\Framework\Middleware;

use Libok\Application\Contracts\RateLimiterInterface;

final class AuthRateLimitMiddleware extends RateLimitMiddleware
{
    public function __construct(RateLimiterInterface $limiter)
    {
        parent::__construct($limiter, 5, 900);
    }
}
