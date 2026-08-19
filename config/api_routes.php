<?php

declare(strict_types=1);

use Libok\Framework\Controllers\HealthController;
use Libok\Framework\Core\Router;

return static function (Router $router): void {
    $register = static function (Router $r): void {
        $r->get('/health/live', [HealthController::class, 'live']);
        $r->get('/health/ready', [HealthController::class, 'ready']);
    };

    $apiMiddleware = ['cors', 'security', 'json'];
    $router->group('/api/v1', $apiMiddleware, $register);
    $router->group('/api', $apiMiddleware, $register);
};
