<?php

declare(strict_types=1);

namespace Libok\Framework\Middleware;

use Libok\Framework\Core\MiddlewareInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class CorsMiddleware implements MiddlewareInterface
{
    public function process(Request $request, callable $next): Response
    {
        $rawOrigins = $_ENV['CORS_ORIGIN'] ?? '';
        $allowedOrigins = array_filter(array_map('trim', explode(',', (string) $rawOrigins)));
        $requestOrigin = $request->headers->get('Origin', '');
        $origin = in_array($requestOrigin, $allowedOrigins, true) ? $requestOrigin : '';

        $headers = [
            'Access-Control-Allow-Methods' => 'GET, POST, PUT, PATCH, DELETE, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With, X-Request-ID, X-Correlation-ID, Idempotency-Key',
            'Access-Control-Expose-Headers' => 'X-Request-ID, X-Correlation-ID, X-RateLimit-Limit, X-RateLimit-Remaining',
            'Access-Control-Allow-Credentials' => 'true',
            'Access-Control-Max-Age' => '86400',
            'Vary' => 'Origin',
        ];

        if ($origin !== '') {
            $headers['Access-Control-Allow-Origin'] = $origin;
        }

        if ($request->getMethod() === 'OPTIONS') {
            return new Response('', 204, $headers);
        }

        $response = $next($request);

        foreach ($headers as $name => $value) {
            $response->headers->set($name, $value);
        }

        return $response;
    }
}
