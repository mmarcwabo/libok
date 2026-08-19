<?php

declare(strict_types=1);

namespace Libok\Framework\Middleware;

use Libok\Framework\Core\MiddlewareInterface;
use Libok\Infrastructure\Observability\RequestContext;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequestContextMiddleware implements MiddlewareInterface
{
    public const REQUEST_ATTRIBUTE = 'request_id';
    public const CORRELATION_ATTRIBUTE = 'correlation_id';

    public function __construct(
        private readonly RequestContext $context,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function process(Request $request, callable $next): Response
    {
        $requestId = $this->validId($request->headers->get('X-Request-ID')) ?? $this->newId();
        $correlationId = $this->validId($request->headers->get('X-Correlation-ID')) ?? $requestId;
        $this->context->set($requestId, $correlationId);
        $request->attributes->set(self::REQUEST_ATTRIBUTE, $requestId);
        $request->attributes->set(self::CORRELATION_ATTRIBUTE, $correlationId);

        $started = hrtime(true);
        $this->logger->info('HTTP request started', [
            'method' => $request->getMethod(),
            'path' => $request->getPathInfo(),
            'client_ip' => $request->getClientIp(),
        ]);

        try {
            $response = $next($request);
        } catch (\Throwable $exception) {
            $this->logger->error('HTTP request failed', ['exception' => $exception]);
            throw $exception;
        }

        $response->headers->set('X-Request-ID', $requestId);
        $response->headers->set('X-Correlation-ID', $correlationId);
        $this->logger->info('HTTP request completed', [
            'status' => $response->getStatusCode(),
            'duration_ms' => round((hrtime(true) - $started) / 1_000_000, 2),
        ]);

        return $response;
    }

    private function validId(?string $id): ?string
    {
        if ($id === null || preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{0,127}$/', $id) !== 1) {
            return null;
        }

        return $id;
    }

    private function newId(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
