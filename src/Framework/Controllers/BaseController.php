<?php

declare(strict_types=1);

namespace Libok\Framework\Controllers;

use Symfony\Component\HttpFoundation\Response;

abstract class BaseController
{
    protected function json(mixed $data, int $status = 200, string $message = ''): Response
    {
        return new Response(
            json_encode(['success' => true, 'data' => $data, 'message' => $message], JSON_UNESCAPED_SLASHES),
            $status,
            [
                'Content-Type' => 'application/json',
                'Cache-Control' => 'private, no-store',
            ]
        );
    }

    protected function jsonCached(mixed $data, int $ttlSeconds = 60, string $message = ''): Response
    {
        return new Response(
            json_encode(['success' => true, 'data' => $data, 'message' => $message], JSON_UNESCAPED_SLASHES),
            200,
            [
                'Content-Type' => 'application/json',
                'Cache-Control' => "private, max-age={$ttlSeconds}, stale-while-revalidate=30",
            ]
        );
    }

    protected function error(string $message, int $status = 400, string $code = ''): Response
    {
        if ($code === '') {
            $code = match ($status) {
                400 => 'validation',
                401 => 'auth.expired',
                403 => 'forbidden',
                404 => 'http.not_found',
                409 => 'conflict',
                422 => 'unprocessable',
                429 => 'rate_limited',
                503 => 'unavailable',
                default => 'internal_error',
            };
        }

        return new Response(
            json_encode(['success' => false, 'message' => $message, 'code' => $code], JSON_UNESCAPED_SLASHES),
            $status,
            [
                'Content-Type' => 'application/json',
                'Cache-Control' => 'no-store',
            ]
        );
    }

    /**
     * @param array<int, mixed> $items
     */
    protected function paginated(array $items, int $total, int $page, int $perPage, int $cacheTtl = 0): Response
    {
        $cacheHeader = $cacheTtl > 0
            ? "private, max-age={$cacheTtl}, stale-while-revalidate=30"
            : 'private, no-store';

        return new Response(
            json_encode([
                'success' => true,
                'data' => $items,
                'pagination' => [
                    'total' => $total,
                    'page' => $page,
                    'per_page' => $perPage,
                    'total_pages' => $perPage > 0 ? (int) ceil($total / $perPage) : 0,
                ],
            ], JSON_UNESCAPED_SLASHES),
            200,
            [
                'Content-Type' => 'application/json',
                'Cache-Control' => $cacheHeader,
            ]
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function render(string $view, array $data = []): Response
    {
        extract($data, EXTR_SKIP);

        ob_start();
        require __DIR__ . '/../Views/layout/header.php';
        require __DIR__ . "/../Views/{$view}.php";
        require __DIR__ . '/../Views/layout/footer.php';
        $html = (string) ob_get_clean();

        return new Response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }
}
