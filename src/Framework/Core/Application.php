<?php

declare(strict_types=1);

namespace Libok\Framework\Core;

use Libok\Infrastructure\Observability\RequestContext;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class Application
{
    public function __construct(
        private readonly Router $router,
        private readonly LoggerInterface $logger,
        private readonly RequestContext $requestContext,
    ) {
    }

    public function run(): void
    {
        try {
            $request = Request::createFromGlobals();
            $response = $this->handle($request);
        } catch (\Throwable $e) {
            $response = $this->handleException($e);
        }

        $response->send();
    }

    public function handle(Request $request): Response
    {
        try {
            $response = $this->router->dispatch($request);
        } catch (\Throwable $e) {
            $response = $this->handleException($e, $request);
        }
        $ids = $this->requestContext->asLogContext();
        if (isset($ids['request_id'])) {
            $response->headers->set('X-Request-ID', $ids['request_id']);
        }
        if (isset($ids['correlation_id'])) {
            $response->headers->set('X-Correlation-ID', $ids['correlation_id']);
        }

        return $response;
    }

    private function handleException(\Throwable $e, ?Request $request = null): Response
    {
        $this->logger->error('Unhandled application exception', ['exception' => $e]);
        $debug = strtolower((string) ($_ENV['APP_ENV'] ?? 'production')) !== 'production'
            && filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $statusCode = match (true) {
            $e instanceof \InvalidArgumentException => 400,
            $e instanceof \Symfony\Component\HttpFoundation\File\Exception\FileException => 422,
            $e instanceof \RuntimeException && str_contains(strtolower($e->getMessage()), 'not found') => 404,
            default => 500,
        };

        $code = match ($statusCode) {
            400 => 'validation',
            404 => 'http.not_found',
            422 => 'unprocessable',
            default => 'internal_error',
        };

        $safeMessage = $this->safeClientMessage($e, $statusCode, $debug);

        $payload = [
            'success' => false,
            'message' => $safeMessage,
            'code' => $code,
        ];

        if ($debug) {
            $payload['debug'] = [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => explode("\n", $e->getTraceAsString()),
            ];
        }

        $headers = [
            'Content-Type' => 'application/json',
            'Cache-Control' => 'no-store',
        ];

        $origin = $this->allowedOrigin($request);
        if ($origin !== '') {
            $headers['Access-Control-Allow-Origin'] = $origin;
            $headers['Access-Control-Allow-Credentials'] = 'true';
            $headers['Vary'] = 'Origin';
        }

        return new Response(
            json_encode($payload, JSON_UNESCAPED_SLASHES),
            $statusCode,
            $headers,
        );
    }

    private function safeClientMessage(\Throwable $e, int $statusCode, bool $debug): string
    {
        if ($statusCode === 500 && !$debug) {
            return 'An internal error occurred.';
        }

        $raw = $e->getMessage();
        if (str_contains($raw, 'SQLSTATE') || str_contains($raw, 'PDOException')) {
            return $debug ? $raw : 'An internal error occurred.';
        }

        if ($e instanceof \Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException) {
            return 'The uploaded file is temporarily unavailable. Retry or check the file size.';
        }

        if ($statusCode === 500) {
            return 'An internal error occurred.';
        }

        return $raw !== '' ? $raw : 'An internal error occurred.';
    }

    private function allowedOrigin(?Request $request): string
    {
        if ($request === null) {
            return '';
        }
        $rawOrigins = $_ENV['CORS_ORIGIN'] ?? '';
        $allowedOrigins = array_filter(array_map('trim', explode(',', (string) $rawOrigins)));
        $requestOrigin = $request->headers->get('Origin', '');

        return in_array($requestOrigin, $allowedOrigins, true) ? $requestOrigin : '';
    }
}
