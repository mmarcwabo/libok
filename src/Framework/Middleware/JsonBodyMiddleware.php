<?php

declare(strict_types=1);

namespace Libok\Framework\Middleware;

use Libok\Framework\Core\MiddlewareInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class JsonBodyMiddleware implements MiddlewareInterface
{
    private const MAX_BODY_BYTES = 1_048_576;

    public function process(Request $request, callable $next): Response
    {
        $contentType = $request->headers->get('Content-Type', '');

        if (str_contains(strtolower((string) $contentType), 'application/json')) {
            $rawBody = $request->getContent();

            if (strlen($rawBody) > self::MAX_BODY_BYTES) {
                return new Response(
                    json_encode([
                        'success' => false,
                        'message' => 'JSON body is too large.',
                        'code' => 'http.payload_too_large',
                    ], JSON_UNESCAPED_SLASHES),
                    413,
                    ['Content-Type' => 'application/json', 'Cache-Control' => 'no-store']
                );
            }

            if ($rawBody !== '') {
                $decoded = json_decode($rawBody, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    return new Response(
                        json_encode([
                            'success' => false,
                            'message' => 'Invalid JSON request body.',
                            'code' => 'http.invalid_json',
                        ], JSON_UNESCAPED_SLASHES),
                        400,
                        ['Content-Type' => 'application/json', 'Cache-Control' => 'no-store']
                    );
                }

                if (is_array($decoded)) {
                    $request->request->replace($decoded);
                }
            }
        }

        return $next($request);
    }
}
