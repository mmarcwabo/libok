<?php

declare(strict_types=1);

namespace Libok\Framework\Middleware;

use Libok\Domain\Entities\User;
use Libok\Framework\Core\MiddlewareInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Allows tenant/platform operators (admin, manager, super_admin) after AuthMiddleware.
 */
class OperatorMiddleware implements MiddlewareInterface
{
    private const OPERATOR_ROLES = [
        User::ROLE_SUPER_ADMIN,
        User::ROLE_ADMIN,
        User::ROLE_MANAGER,
    ];

    public function process(Request $request, callable $next): Response
    {
        $roles = $request->attributes->get('auth_roles', []);
        if (!is_array($roles)) {
            $roles = [];
        }

        $roleNames = array_values(array_filter($roles, 'is_string'));
        if (array_intersect(self::OPERATOR_ROLES, $roleNames) === []) {
            return new Response(
                json_encode([
                    'success' => false,
                    'message' => 'Forbidden.',
                    'code' => 'forbidden',
                ], JSON_UNESCAPED_SLASHES),
                403,
                ['Content-Type' => 'application/json', 'Cache-Control' => 'no-store']
            );
        }

        return $next($request);
    }
}
