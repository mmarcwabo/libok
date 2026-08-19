<?php

declare(strict_types=1);

namespace Libok\Framework\Middleware;

use Libok\Framework\Core\MiddlewareInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Injects security headers on every API response.
 */
class SecurityHeadersMiddleware implements MiddlewareInterface
{
    public function process(Request $request, callable $next): Response
    {
        $response = $next($request);

        $isProduction = ($_ENV['APP_ENV'] ?? 'production') === 'production';

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'geolocation=(), camera=(), microphone=()');
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        if ($isProduction && $request->isSecure()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains'
            );
        }

        $response->headers->set(
            'Content-Security-Policy',
            "default-src 'none'; frame-ancestors 'none'"
        );

        return $response;
    }
}
