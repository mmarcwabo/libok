<?php

declare(strict_types=1);

namespace Libok\Framework\Controllers\Api;

use Libok\Application\UseCases\Auth\LoginUseCase;
use Libok\Application\UseCases\Auth\LogoutUseCase;
use Libok\Application\UseCases\Auth\RefreshTokenUseCase;
use Libok\Application\UseCases\RegisterUserUseCase;
use Libok\Domain\Entities\User;
use Libok\Domain\Repositories\UserRepositoryInterface;
use Libok\Framework\Controllers\BaseController;
use Libok\Framework\Resources\UserResource;
use Libok\Infrastructure\Services\JwtService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends BaseController
{
    private bool $isProduction;

    public function __construct(
        private readonly LoginUseCase $loginUseCase,
        private readonly RegisterUserUseCase $registerUserUseCase,
        private readonly RefreshTokenUseCase $refreshTokenUseCase,
        private readonly LogoutUseCase $logoutUseCase,
        private readonly UserRepositoryInterface $userRepository,
        private readonly JwtService $jwtService,
    ) {
        $this->isProduction = (($_ENV['APP_ENV'] ?? 'production') === 'production');
    }

    public function register(Request $request): Response
    {
        try {
            $user = $this->registerUserUseCase->execute(
                (string) $request->request->get('name', ''),
                (string) $request->request->get('email', ''),
                (string) $request->request->get('password', ''),
            );

            return $this->json(UserResource::toArray($user), 201, 'Registration successful.');
        } catch (\InvalidArgumentException $e) {
            $status = str_contains($e->getMessage(), 'already exists') ? 409 : 400;

            return $this->error($e->getMessage(), $status);
        }
    }

    public function login(Request $request): Response
    {
        try {
            $result = $this->loginUseCase->execute($request->request->all());
            $response = $this->json(['user' => $result['user']], 200, 'Login successful.');
            $this->setAccessTokenCookie($response, $result['access_token']);
            $this->setRefreshTokenCookie($response, $result['refresh_token']);

            return $response;
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 401, 'auth.invalid_credentials');
        }
    }

    public function refresh(Request $request): Response
    {
        try {
            $rawToken = (string) $request->cookies->get('refresh_token', '');
            if ($rawToken === '') {
                return $this->error('Authentication required.', 401);
            }

            $result = $this->refreshTokenUseCase->execute($rawToken);
            $payload = $this->jwtService->decode($result['access_token']);
            $userId = $payload['sub'] ?? null;
            $user = is_string($userId) ? $this->userRepository->findById($userId) : null;

            $response = $this->json(
                ['user' => $user instanceof User ? UserResource::toArray($user) : null],
                200,
                'Session refreshed.',
            );
            $this->setAccessTokenCookie($response, $result['access_token']);
            $this->setRefreshTokenCookie($response, $result['refresh_token']);

            return $response;
        } catch (\Throwable) {
            $response = $this->error('Authentication required.', 401);
            $this->clearAccessTokenCookie($response);
            $this->clearRefreshTokenCookie($response);

            return $response;
        }
    }

    public function logout(Request $request): Response
    {
        try {
            $rawToken = (string) $request->cookies->get('refresh_token', '');
            if ($rawToken !== '') {
                $this->logoutUseCase->execute($rawToken);
            }
        } catch (\Throwable) {
        }

        $response = $this->json(null, 200, 'Logout successful.');
        $this->clearAccessTokenCookie($response);
        $this->clearRefreshTokenCookie($response);

        return $response;
    }

    public function me(Request $request): Response
    {
        $user = $request->attributes->get('auth_user');
        if (!$user instanceof User) {
            return $this->error('Authentication required.', 401);
        }

        return $this->json(UserResource::toArray($user));
    }

    public function ping(Request $request): Response
    {
        $roles = $request->attributes->get('auth_roles', []);
        if (!is_array($roles)) {
            $roles = [];
        }

        /** @var list<string> $roleNames */
        $roleNames = array_values(array_filter($roles, 'is_string'));

        return $this->json(['ok' => true, 'roles' => $roleNames]);
    }

    private function setAccessTokenCookie(Response $response, string $token): void
    {
        $ttl = (int) ($_ENV['JWT_ACCESS_TTL'] ?? 900);
        $response->headers->set('Set-Cookie', $this->buildCookieHeader('access_token', $token, '/', $ttl), false);
    }

    private function setRefreshTokenCookie(Response $response, string $token): void
    {
        $ttl = (int) ($_ENV['JWT_REFRESH_TTL'] ?? 1209600);
        $response->headers->set('Set-Cookie', $this->buildCookieHeader('refresh_token', $token, '/api', $ttl), false);
    }

    private function clearAccessTokenCookie(Response $response): void
    {
        $response->headers->set(
            'Set-Cookie',
            $this->buildCookieHeader('access_token', '', '/', 0) . '; Expires=Thu, 01 Jan 1970 00:00:00 GMT',
            false
        );
    }

    private function clearRefreshTokenCookie(Response $response): void
    {
        $response->headers->set(
            'Set-Cookie',
            $this->buildCookieHeader('refresh_token', '', '/api', 0) . '; Expires=Thu, 01 Jan 1970 00:00:00 GMT',
            false
        );
    }

    private function buildCookieHeader(string $name, string $value, string $path, int $maxAge): string
    {
        $secure = $this->isProduction ? '; Secure' : '';
        $domain = trim((string) ($_ENV['COOKIE_DOMAIN'] ?? ''));
        $domainPart = $domain !== '' ? '; Domain=' . $domain : '';
        $sameSite = trim((string) ($_ENV['COOKIE_SAMESITE'] ?? 'Lax'));
        if (!in_array($sameSite, ['Lax', 'Strict', 'None'], true)) {
            $sameSite = 'Lax';
        }
        if ($sameSite === 'None' && !$this->isProduction) {
            $sameSite = 'Lax';
        }

        return sprintf(
            '%s=%s; Path=%s; HttpOnly; SameSite=%s%s%s; Max-Age=%d',
            $name,
            rawurlencode($value),
            $path,
            $sameSite,
            $secure,
            $domainPart,
            $maxAge
        );
    }
}
