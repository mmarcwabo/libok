<?php

declare(strict_types=1);

use Libok\Framework\Controllers\Api\AuthController;
use Libok\Framework\Controllers\Api\ItemController;
use Libok\Framework\Controllers\Api\UploadController;
use Libok\Framework\Controllers\Api\UserController;
use Libok\Framework\Controllers\HealthController;
use Libok\Framework\Core\Router;

return static function (Router $router): void {
    $register = static function (Router $r): void {
        $r->get('/health/live', [HealthController::class, 'live']);
        $r->get('/health/ready', [HealthController::class, 'ready']);

        $r->post('/auth/register', [AuthController::class, 'register']);
        $r->post('/auth/login', [AuthController::class, 'login'], ['auth_ratelimit']);
        $r->post('/auth/refresh', [AuthController::class, 'refresh'], ['auth_ratelimit']);
        $r->post('/auth/logout', [AuthController::class, 'logout']);
        $r->get('/me', [AuthController::class, 'me'], ['auth']);
        $r->get('/secure/ping', [AuthController::class, 'ping'], ['auth']);

        $r->post('/uploads', [UploadController::class, 'store'], ['auth']);

        $operator = ['auth', 'operator'];
        $r->get('/users', [UserController::class, 'index'], $operator);
        $r->post('/users', [UserController::class, 'store'], $operator);
        $r->get('/users/{id}', [UserController::class, 'show'], $operator);
        $r->patch('/users/{id}', [UserController::class, 'update'], $operator);
        $r->delete('/users/{id}', [UserController::class, 'destroy'], $operator);

        $tenant = ['auth', 'tenant'];
        $r->post('/items', [ItemController::class, 'store'], $tenant);
        $r->get('/items/{id}', [ItemController::class, 'show'], $tenant);
    };

    $apiMiddleware = ['cors', 'security', 'json', 'idempotency', 'audit'];
    $router->group('/api/v1', $apiMiddleware, $register);
    $router->group('/api', $apiMiddleware, $register);
};
