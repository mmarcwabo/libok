<?php

declare(strict_types=1);

namespace Libok\Framework\Core;

use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class Router
{
    /** @var list<array{method: string, pattern: string, handler: callable|array{0: class-string, 1: string}, middleware: list<string>}> */
    private array $routes = [];

    /** @var string[] */
    private array $groupMiddleware = [];

    /** @var string[] */
    private array $globalMiddleware = [];

    private string $groupPrefix = '';

    public function __construct(
        private readonly ContainerInterface $container,
        private readonly MiddlewareRegistry $middlewareRegistry,
    ) {
    }

    /**
     * @param callable|array{0: class-string, 1: string} $handler
     * @param list<string> $middleware
     */
    public function get(string $path, array|callable $handler, array $middleware = []): void
    {
        $this->addRoute('GET', $path, $handler, $middleware);
    }

    /**
     * @param callable|array{0: class-string, 1: string} $handler
     * @param list<string> $middleware
     */
    public function post(string $path, array|callable $handler, array $middleware = []): void
    {
        $this->addRoute('POST', $path, $handler, $middleware);
    }

    /**
     * @param callable|array{0: class-string, 1: string} $handler
     * @param list<string> $middleware
     */
    public function put(string $path, array|callable $handler, array $middleware = []): void
    {
        $this->addRoute('PUT', $path, $handler, $middleware);
    }

    /**
     * @param callable|array{0: class-string, 1: string} $handler
     * @param list<string> $middleware
     */
    public function patch(string $path, array|callable $handler, array $middleware = []): void
    {
        $this->addRoute('PATCH', $path, $handler, $middleware);
    }

    /**
     * @param callable|array{0: class-string, 1: string} $handler
     * @param list<string> $middleware
     */
    public function delete(string $path, array|callable $handler, array $middleware = []): void
    {
        $this->addRoute('DELETE', $path, $handler, $middleware);
    }

    public function addGlobalMiddleware(string $alias): void
    {
        $this->globalMiddleware[] = $alias;
    }

    /**
     * @param list<string> $middleware
     */
    public function group(string $prefix, array $middleware, callable $callback): void
    {
        $previousPrefix = $this->groupPrefix;
        $previousMiddleware = $this->groupMiddleware;

        $this->groupPrefix = $previousPrefix . $prefix;
        $this->groupMiddleware = array_merge($previousMiddleware, $middleware);

        $callback($this);

        $this->groupPrefix = $previousPrefix;
        $this->groupMiddleware = $previousMiddleware;
    }

    /**
     * @param callable|array{0: class-string, 1: string} $handler
     * @param list<string> $middleware
     */
    private function addRoute(string $method, string $path, array|callable $handler, array $middleware): void
    {
        $fullPath = $this->groupPrefix . $path;
        $allMiddleware = array_merge($this->globalMiddleware, $this->groupMiddleware, $middleware);

        $this->routes[] = [
            'method' => strtoupper($method),
            'pattern' => $fullPath,
            'handler' => $handler,
            'middleware' => $allMiddleware,
        ];
    }

    public function dispatch(Request $request): Response
    {
        $method = strtoupper($request->getMethod());
        $uri = '/' . ltrim((string) parse_url($request->getRequestUri(), PHP_URL_PATH), '/');

        if ($method === 'OPTIONS') {
            foreach ($this->routes as $route) {
                if ($this->matchRoute($route['pattern'], $uri) !== null) {
                    return $this->runPipeline($request, $route['middleware'], $route['handler']);
                }
            }
        }

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            $params = $this->matchRoute($route['pattern'], $uri);
            if ($params === null) {
                continue;
            }

            foreach ($params as $key => $value) {
                $request->attributes->set($key, $value);
            }

            return $this->runPipeline($request, $route['middleware'], $route['handler']);
        }

        return $this->runPipeline(
            $request,
            $this->globalMiddleware,
            fn (): Response => $this->notFound(),
        );
    }

    /** @return array<string, string>|null */
    private function matchRoute(string $pattern, string $uri): ?array
    {
        $regex = preg_replace('/\{(\w+)\+\}/', '(?P<$1>.+)', $pattern) ?? $pattern;
        $regex = preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $regex) ?? $regex;
        $regex = preg_replace('/:(\w+)/', '(?P<$1>[^/]+)', $regex) ?? $regex;
        $regex = '#^' . $regex . '$#';

        if (!preg_match($regex, $uri, $matches)) {
            return null;
        }

        $params = [];
        foreach ($matches as $key => $value) {
            if (is_string($key)) {
                $params[$key] = $value;
            }
        }

        return $params;
    }

    /**
     * @param string[] $middlewareAliases
     * @param callable|array{0: class-string, 1: string} $handler
     */
    private function runPipeline(Request $request, array $middlewareAliases, array|callable $handler): Response
    {
        $middlewareInstances = $this->middlewareRegistry->resolveAll($middlewareAliases);

        $finalHandler = function (Request $req) use ($handler): Response {
            return $this->callHandler($req, $handler);
        };

        $pipeline = array_reduce(
            array_reverse($middlewareInstances),
            function (callable $next, MiddlewareInterface $middleware): callable {
                return function (Request $req) use ($middleware, $next): Response {
                    return $middleware->process($req, $next);
                };
            },
            $finalHandler
        );

        return $pipeline($request);
    }

    /** @param callable|array{0: class-string, 1: string} $handler */
    private function callHandler(Request $request, array|callable $handler): Response
    {
        if (is_callable($handler)) {
            $response = $handler($request);
            if (!$response instanceof Response) {
                throw new \RuntimeException('Route handler must return a Response.');
            }

            return $response;
        }

        [$controllerClass, $method] = $handler;
        $controller = $this->container->get($controllerClass);

        if (!is_object($controller) || !method_exists($controller, $method)) {
            throw new \RuntimeException("Method {$method} not found on controller {$controllerClass}");
        }

        $response = $controller->$method($request);
        if (!$response instanceof Response) {
            throw new \RuntimeException("Controller {$controllerClass}::{$method} must return a Response.");
        }

        return $response;
    }

    private function notFound(): Response
    {
        return new Response(
            json_encode([
                'success' => false,
                'message' => 'Route not found.',
                'code' => 'http.not_found',
            ], JSON_UNESCAPED_SLASHES),
            404,
            ['Content-Type' => 'application/json', 'Cache-Control' => 'no-store']
        );
    }
}
