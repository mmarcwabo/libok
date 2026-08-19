<?php

declare(strict_types=1);

namespace Libok\Application\Contracts;

interface JobQueueInterface
{
    /** @param array<string, mixed> $payload */
    public function dispatch(
        string $type,
        array $payload,
        ?string $idempotencyKey = null,
        int $maxAttempts = 5,
        ?\DateTimeImmutable $availableAt = null,
    ): string;

    /** @return array{id: string, type: string, payload: array<string, mixed>, attempts: int, max_attempts: int}|null */
    public function claim(): ?array;

    public function complete(string $id): void;

    public function fail(string $id, int $attempts, int $maxAttempts, \Throwable $error): void;

    public function releaseStale(int $timeoutSeconds = 900): int;
}
