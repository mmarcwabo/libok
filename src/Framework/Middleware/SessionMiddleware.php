<?php

declare(strict_types=1);

namespace Libok\Framework\Middleware;

use Libok\Framework\Core\MiddlewareInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class SessionMiddleware implements MiddlewareInterface
{
    public function process(Request $request, callable $next): Response
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        return $next($request);
    }
}
