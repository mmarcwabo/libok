<?php

declare(strict_types=1);

namespace Libok\Framework\Middleware;

use Libok\Framework\Core\MiddlewareInterface;
use Libok\Infrastructure\Observability\ContextSanitizer;
use Libok\Infrastructure\Observability\RequestContext;
use Libok\Infrastructure\Services\AuditLogService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Records successful mutating API requests (POST/PUT/PATCH/DELETE) in the audit log.
 */
class AuditMiddleware implements MiddlewareInterface
{
    private const ACTION_PREFIX = 'api';

    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly ContextSanitizer $sanitizer,
        private readonly RequestContext $requestContext,
    ) {
    }

    public function process(Request $request, callable $next): Response
    {
        $response = $next($request);

        $method = strtoupper($request->getMethod());
        if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return $response;
        }

        $statusCode = $response->getStatusCode();
        if ($statusCode < 200 || $statusCode >= 300) {
            return $response;
        }

        $userId = $request->attributes->get('auth_user_id');
        $uri = $request->getPathInfo();
        $requestId = $this->requestContext->getRequestId();

        $this->auditLogService->log(
            userId: is_string($userId) ? $userId : null,
            action: $this->buildActionName($method, $uri),
            objectType: $this->extractObjectType($uri),
            objectId: $this->extractObjectId($uri),
            payload: $this->buildPayloadSummary($request, $requestId),
            ipAddress: $request->getClientIp() ?? 'unknown',
            userAgent: (string) $request->headers->get('User-Agent', ''),
            requestId: $requestId,
        );

        return $response;
    }

    private function buildActionName(string $method, string $uri): string
    {
        $resource = str_replace('-', '_', $this->resourceSegments($uri)[0] ?? 'resource');

        return match ($method) {
            'POST' => self::ACTION_PREFIX . ".{$resource}.create",
            'PUT', 'PATCH' => self::ACTION_PREFIX . ".{$resource}.update",
            'DELETE' => self::ACTION_PREFIX . ".{$resource}.delete",
            default => self::ACTION_PREFIX . ".{$resource}.action",
        };
    }

    private function extractObjectType(string $uri): string
    {
        return $this->resourceSegments($uri)[0] ?? 'unknown';
    }

    private function extractObjectId(string $uri): ?string
    {
        $segments = $this->resourceSegments($uri);
        $candidate = $segments[1] ?? null;
        if (is_string($candidate) && preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $candidate
        ) === 1) {
            return $candidate;
        }

        return null;
    }

    /** @return list<string> */
    private function resourceSegments(string $uri): array
    {
        $segments = array_values(array_filter(explode('/', $uri), static fn (string $s): bool => $s !== ''));
        if (($segments[0] ?? '') === 'api') {
            array_shift($segments);
        }
        if (($segments[0] ?? '') === 'v1') {
            array_shift($segments);
        }

        return $segments;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPayloadSummary(Request $request, ?string $requestId): array
    {
        $body = $request->request->all();
        $sanitized = $this->sanitizer->sanitize($body);
        if (!is_array($sanitized)) {
            $sanitized = [];
        }
        /** @var array<string, mixed> $sanitized */
        if ($requestId !== null) {
            $sanitized['request_id'] = $requestId;
        }

        return $sanitized;
    }
}
