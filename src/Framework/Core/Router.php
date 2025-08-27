<?php

declare(strict_types=1);

namespace Libok\Framework\Core;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class Router
{
    public function __construct(private readonly array $routes) {}

    public function dispatch(Request $request): void
    {
        $uri = $request->getPathInfo();
        $method = $request->getMethod();
        $routeKey = "{$method} {$uri}";
        
        // Simple parameter handling for edit route
        if (preg_match('/^\/users\/edit$/', $uri) && $method === 'GET' && $request->query->has('id')) {
            $routeKey = 'GET /users/edit';
        }
        
        if (array_key_exists($routeKey, $this->routes)) {
            [$controllerClass, $methodName] = $this->routes[$routeKey];

            $entityManager = getEntityManager();
            $controller = new $controllerClass($entityManager);

            // Call the controller method
            $controller->$methodName($request);
        } else {
            $this->notFound();
        }
    }

    private function notFound(): void
    {
        $response = new Response('404 - Not Found', Response::HTTP_NOT_FOUND);
        $response->send();
    }
}