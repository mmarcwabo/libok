<?php

// config/routes.php
return [
    // Format: 'METHOD /path' => [Controller::class, 'methodName']
    'GET /' => [Libok\Framework\Controllers\UserController::class, 'index'],
    
    // Auth Routes
    'GET /login' => [Libok\Framework\Controllers\AuthController::class, 'showLoginForm'],
    'POST /login' => [Libok\Framework\Controllers\AuthController::class, 'login'],
    'GET /register' => [Libok\Framework\Controllers\AuthController::class, 'showRegistrationForm'],
    'POST /register' => [Libok\Framework\Controllers\AuthController::class, 'register'],
    'GET /logout' => [Libok\Framework\Controllers\AuthController::class, 'logout'],

    // User CRUD Routes
    'GET /users' => [Libok\Framework\Controllers\UserController::class, 'index'],
    'GET /users/create' => [Libok\Framework\Controllers\UserController::class, 'create'],
    'POST /users' => [Libok\Framework\Controllers\UserController::class, 'store'],
    'GET /users/edit' => [Libok\Framework\Controllers\UserController::class, 'edit'],
    'POST /users/update' => [Libok\Framework\Controllers\UserController::class, 'update'],
    'POST /users/delete' => [Libok\Framework\Controllers\UserController::class, 'delete'],
];
