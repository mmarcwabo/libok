<?php

declare(strict_types=1);

use Libok\Framework\Core\Application;
use Libok\Framework\Core\MiddlewareRegistry;
use Libok\Framework\Core\Router;
use Libok\Framework\Middleware\CorsMiddleware;
use Libok\Framework\Middleware\JsonBodyMiddleware;
use Libok\Framework\Middleware\RateLimitMiddleware;
use Libok\Framework\Middleware\RequestContextMiddleware;
use Libok\Framework\Middleware\SecurityHeadersMiddleware;
use Libok\Framework\Middleware\SessionMiddleware;
use Libok\Infrastructure\Observability\RequestContext;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

if (!defined('LIBOK_ROOT')) {
    require_once __DIR__ . '/bootstrap.php';
}

$container = require __DIR__ . '/services.php';

$middlewareRegistry = new MiddlewareRegistry($container);
$middlewareRegistry->register('request_context', RequestContextMiddleware::class);
$middlewareRegistry->register('cors', CorsMiddleware::class);
$middlewareRegistry->register('security', SecurityHeadersMiddleware::class);
$middlewareRegistry->register('json', JsonBodyMiddleware::class);
$middlewareRegistry->register('ratelimit', RateLimitMiddleware::class);
$middlewareRegistry->register('session', SessionMiddleware::class);

$router = new Router($container, $middlewareRegistry);
$router->addGlobalMiddleware('request_context');

$registerApi = require __DIR__ . '/api_routes.php';
$registerApi($router);

$router->group('', ['session'], static function (Router $router): void {
    $registerHtml = require __DIR__ . '/routes.php';
    $registerHtml($router);
});

$router->get('/storage/{path+}', static function (Request $req): Response {
    $path = (string) $req->attributes->get('path', '');
    $relative = ltrim(str_replace('\\', '/', $path), '/');
    $firstSegment = explode('/', $relative)[0] ?? '';
    $allowedDirs = ['avatars', 'uploads'];

    if ($relative === '' || str_contains($relative, "\0") || !in_array($firstSegment, $allowedDirs, true)) {
        return new Response('Forbidden.', 403);
    }

    $roots = [];
    foreach ([
        dirname(__DIR__) . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'storage',
        (string) ($_ENV['PUBLIC_STORAGE_PATH'] ?? ''),
        (string) ($_ENV['STORAGE_PATH'] ?? LIBOK_STORAGE),
    ] as $root) {
        $root = rtrim($root, '/\\');
        if ($root === '') {
            continue;
        }
        $resolvedRoot = realpath($root);
        if ($resolvedRoot !== false) {
            $roots[] = $resolvedRoot;
        }
    }

    $realFile = false;
    foreach ($roots as $root) {
        $candidate = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        $resolved = realpath($candidate);
        $prefix = $root . DIRECTORY_SEPARATOR;
        if ($resolved !== false && is_file($resolved) && str_starts_with($resolved, $prefix)) {
            $realFile = $resolved;
            break;
        }
    }

    if ($realFile === false) {
        return new Response('Not found.', 404);
    }

    $ext = strtolower(pathinfo($realFile, PATHINFO_EXTENSION));
    $mimeByExt = [
        'pdf' => 'application/pdf',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
    ];
    $detected = mime_content_type($realFile) ?: null;
    $mime = $mimeByExt[$ext] ?? $detected ?? 'application/octet-stream';
    $response = new BinaryFileResponse($realFile, 200, [
        'Content-Type' => $mime,
        'Cache-Control' => 'public, max-age=86400',
        'X-Content-Type-Options' => 'nosniff',
    ]);
    $response->setContentDisposition('inline', basename($realFile));

    return $response;
}, ['cors']);

return new Application(
    $router,
    $container->get(LoggerInterface::class),
    $container->get(RequestContext::class),
);
