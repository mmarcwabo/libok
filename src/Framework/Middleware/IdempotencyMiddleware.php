<?php

declare(strict_types=1);

namespace Libok\Framework\Middleware;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Libok\Domain\Entities\IdempotencyKey;
use Libok\Framework\Core\MiddlewareInterface;
use Libok\Infrastructure\Services\JwtService;
use Libok\Infrastructure\Tenancy\TenantContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Replays stored responses for create-once POSTs that send an Idempotency-Key header.
 */
class IdempotencyMiddleware implements MiddlewareInterface
{
    private const TTL_SECONDS = 86400;

    /** @var list<string> */
    private const PROTECTED_PATHS = [
        '/auth/register',
        '/items',
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TenantContext $tenantContext,
        private readonly JwtService $jwtService,
    ) {
    }

    public function process(Request $request, callable $next): Response
    {
        if (strtoupper($request->getMethod()) !== 'POST') {
            return $next($request);
        }

        $key = trim((string) $request->headers->get('Idempotency-Key', ''));
        if ($key === '') {
            return $next($request);
        }

        if (!$this->isProtectedPath($request)) {
            return $next($request);
        }

        if (preg_match('/^[A-Za-z0-9._~-]{1,255}$/', $key) !== 1) {
            return $this->error('Idempotency-Key is invalid.', 400, 'idempotency.invalid');
        }

        $hash = hash(
            'sha256',
            strtoupper($request->getMethod()) . "\n" . $this->canonicalPath($request) . "\n" . $request->getContent()
        );
        $organizationId = $this->tenantContext->getOrganizationId()
            ?? trim((string) $request->headers->get('X-Organization', ''));
        $actorId = $this->actorId($request);
        $now = new \DateTimeImmutable();
        $expiresAt = $now->modify('+' . self::TTL_SECONDS . ' seconds');

        $record = $this->find($key, $organizationId, $actorId);
        if ($record !== null && !$record->isExpired($now)) {
            if ($record->getRequestHash() !== $hash) {
                return $this->error(
                    'This Idempotency-Key was already used with a different request.',
                    409,
                    'idempotency.mismatch',
                );
            }
            if ($record->getStatus() === IdempotencyKey::STATUS_COMPLETED && $record->getResponseBody() !== null) {
                return $this->replay($record);
            }
            if ($record->getStatus() === IdempotencyKey::STATUS_PROCESSING) {
                return $this->error(
                    'A request with this Idempotency-Key is already in progress.',
                    409,
                    'idempotency.conflict',
                );
            }
        }

        try {
            if ($record === null) {
                $record = new IdempotencyKey($key, $organizationId, $actorId, $hash, $expiresAt);
                $this->entityManager->persist($record);
            } else {
                $record->restart($hash, $expiresAt);
            }
            $this->entityManager->flush();
        } catch (\Throwable $exception) {
            if (!$this->isUniqueViolation($exception)) {
                throw $exception;
            }
            $existing = $this->find($key, $organizationId, $actorId);
            if (
                $existing !== null
                && $existing->getRequestHash() === $hash
                && $existing->getStatus() === IdempotencyKey::STATUS_COMPLETED
            ) {
                return $this->replay($existing);
            }

            return $this->error(
                'A request with this Idempotency-Key is already in progress.',
                409,
                'idempotency.conflict',
            );
        }

        $response = $next($request);
        $status = $response->getStatusCode();
        if ($status >= 200 && $status < 500) {
            $record->complete($status, (string) $response->getContent());
            $this->entityManager->flush();
        }

        return $response;
    }

    private function isProtectedPath(Request $request): bool
    {
        return in_array($this->canonicalPath($request), self::PROTECTED_PATHS, true);
    }

    private function canonicalPath(Request $request): string
    {
        $path = $request->getPathInfo();
        if (str_starts_with($path, '/api/v1/')) {
            return substr($path, 7);
        }
        if (str_starts_with($path, '/api/')) {
            return substr($path, 4);
        }

        return $path;
    }

    private function actorId(Request $request): string
    {
        $fromRequest = $request->attributes->get('auth_user_id');
        if (is_string($fromRequest) && $fromRequest !== '') {
            return $fromRequest;
        }

        $token = (string) $request->cookies->get('access_token', '');
        if ($token === '') {
            return '';
        }

        try {
            $payload = $this->jwtService->decode($token);
        } catch (\Throwable) {
            return '';
        }

        $sub = $payload['sub'] ?? null;

        return is_string($sub) ? $sub : '';
    }

    private function find(string $key, string $organizationId, string $actorId): ?IdempotencyKey
    {
        return $this->entityManager->getRepository(IdempotencyKey::class)->findOneBy([
            'key' => $key,
            'organizationId' => $organizationId,
            'actorId' => $actorId,
        ]);
    }

    private function isUniqueViolation(\Throwable $exception): bool
    {
        if ($exception instanceof UniqueConstraintViolationException) {
            return true;
        }

        return $exception->getPrevious() instanceof UniqueConstraintViolationException;
    }

    private function replay(IdempotencyKey $record): Response
    {
        return new Response(
            (string) $record->getResponseBody(),
            $record->getResponseCode() ?? 200,
            [
                'Content-Type' => 'application/json',
                'Cache-Control' => 'private, no-store',
                'X-Idempotent-Replay' => '1',
            ]
        );
    }

    private function error(string $message, int $status, string $code): Response
    {
        return new Response(
            json_encode(['success' => false, 'message' => $message, 'code' => $code], JSON_UNESCAPED_SLASHES),
            $status,
            ['Content-Type' => 'application/json', 'Cache-Control' => 'no-store']
        );
    }
}
