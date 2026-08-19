<?php

declare(strict_types=1);

use Libok\Framework\Controllers\AuthController;
use Libok\Framework\Controllers\UserController;
use Libok\Framework\Core\Router;

return static function (Router $router): void {
    $router->get('/', [UserController::class, 'index']);

    $router->get('/login', [AuthController::class, 'showLoginForm']);
    $router->post('/login', [AuthController::class, 'login']);
    $router->get('/register', [AuthController::class, 'showRegistrationForm']);
    $router->post('/register', [AuthController::class, 'register']);
    $router->get('/logout', [AuthController::class, 'logout']);

    $router->get('/users', [UserController::class, 'index']);
    $router->get('/users/create', [UserController::class, 'create']);
    $router->post('/users', [UserController::class, 'store']);
    $router->get('/users/edit', [UserController::class, 'edit']);
    $router->post('/users/update', [UserController::class, 'update']);
    $router->post('/users/delete', [UserController::class, 'delete']);
};
