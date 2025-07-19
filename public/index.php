<?php

declare(strict_types=1);

// public/index.php
session_start();

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use Libok\Framework\Core\Router;
use Symfony\Component\HttpFoundation\Request;

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// Load Doctrine EntityManager
require_once __DIR__ . '/../config/bootstrap.php';

// Load routes
$routes = require_once __DIR__ . '/../config/routes.php';

// Create a request object
$request = Request::createFromGlobals();

// Initialize the router and dispatch the request
$router = new Router($routes);
$router->dispatch($request);