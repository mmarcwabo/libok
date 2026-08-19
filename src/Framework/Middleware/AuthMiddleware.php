<?php

declare(strict_types=1);

namespace Libok\Framework\Middleware;

use Libok\Domain\Entities\User;
use Libok\Domain\Repositories\UserRepositoryInterface;
use Libok\Framework\Core\MiddlewareInterface;
use Libok\Infrastructure\Services\JwtService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly JwtService $jwtService,
        private readonly UserRepositoryInterface $userRepository,
    ) {
    }

    public function process(Request $request, callable $next): Response
    {
        $token = (string) $request->cookies->get('access_token', '');

        if ($token === '') {
            return $this->unauthorized('Authentication required.');
        }

        try {
            $payload = $this->jwtService->decode($token);
        } catch (\Exception) {
            return $this->unauthorized('Authentication required.');
        }

        $userId = $payload['sub'] ?? null;
        if (!is_string($userId) || $userId === '') {
            return $this->unauthorized('Authentication required.');
        }

        $user = $this->userRepository->findById($userId);
        if ($user === null) {
            return $this->unauthorized('Authentication required.');
        }

        if ($user->getStatus() !== User::STATUS_ACTIVE) {
            return $this->unauthorized('Account suspended or archived.');
        }

        $request->attributes->set('auth_user', $user);
        $request->attributes->set('auth_user_id', $userId);
        $request->attributes->set('auth_roles', $user->getRoleNames());

        return $next($request);
    }

    private function unauthorized(string $message): Response
    {
        return new Response(
            json_encode([
                'success' => false,
                'message' => $message,
                'code' => 'auth.expired',
            ], JSON_UNESCAPED_SLASHES),
            401,
            ['Content-Type' => 'application/json', 'Cache-Control' => 'no-store']
        );
    }
}
