<?php

declare(strict_types=1);

namespace Libok\Framework\Core;

use Psr\Container\ContainerInterface;

class MiddlewareRegistry
{
    /** @var array<string, class-string<MiddlewareInterface>> */
    private array $named = [];

    public function __construct(private readonly ContainerInterface $container)
    {
    }

    public function register(string $alias, string $middlewareClass): void
    {
        $this->named[$alias] = $middlewareClass;
    }

    public function resolve(string $alias): MiddlewareInterface
    {
        if (!isset($this->named[$alias])) {
            throw new \RuntimeException("Middleware not registered: {$alias}");
        }
        $instance = $this->container->get($this->named[$alias]);
        if (!$instance instanceof MiddlewareInterface) {
            throw new \RuntimeException(
                "Class {$this->named[$alias]} does not implement MiddlewareInterface"
            );
        }

        return $instance;
    }

    /**
     * @param string[] $aliases
     * @return MiddlewareInterface[]
     */
    public function resolveAll(array $aliases): array
    {
        return array_map(fn (string $alias) => $this->resolve($alias), $aliases);
    }
}
