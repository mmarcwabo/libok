<?php

declare(strict_types=1);

namespace Libok\Framework\Middleware;

use Libok\Application\Contracts\RateLimiterInterface;
use Libok\Framework\Core\MiddlewareInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Default: 10 requests per 15 minutes per IP.
 */
class RateLimitMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly RateLimiterInterface $limiter,
        private readonly int $maxAttempts = 10,
        private readonly int $windowSeconds = 900,
    ) {
    }

    public function process(Request $request, callable $next): Response
    {
        $ip = $this->resolveIp($request);
        $result = $this->limiter->consume($ip . ':' . $request->getPathInfo(), $this->maxAttempts, $this->windowSeconds);
        if (!$result->allowed) {
            return $this->tooManyRequests($result->retryAfter);
        }
        $response = $next($request);
        $response->headers->set('X-RateLimit-Limit', (string) $this->maxAttempts);
        $response->headers->set('X-RateLimit-Remaining', (string) $result->remaining);

        return $response;
    }

    private function resolveIp(Request $request): string
    {
        return (string) $request->server->get('REMOTE_ADDR', 'unknown');
    }

    private function tooManyRequests(int $retryAfter): Response
    {
        return new Response(
            json_encode([
                'success' => false,
                'message' => 'Too many requests. Please retry later.',
                'code' => 'rate_limited',
            ], JSON_UNESCAPED_SLASHES),
            429,
            [
                'Content-Type' => 'application/json',
                'Retry-After' => (string) $retryAfter,
                'Cache-Control' => 'no-store',
            ]
        );
    }
}
