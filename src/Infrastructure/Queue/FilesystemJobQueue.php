<?php

declare(strict_types=1);

namespace Libok\Infrastructure\Queue;

use Libok\Application\Contracts\JobQueueInterface;
use Ramsey\Uuid\Uuid;

final class FilesystemJobQueue implements JobQueueInterface
{
    public function __construct(private readonly string $directory)
    {
        foreach (['pending', 'processing', 'completed', 'dead', 'idempotency'] as $subdir) {
            $path = $this->path($subdir);
            if (!is_dir($path) && !mkdir($path, 0750, true) && !is_dir($path)) {
                throw new \RuntimeException('Unable to create queue directory.');
            }
        }
    }

    public function dispatch(
        string $type,
        array $payload,
        ?string $idempotencyKey = null,
        int $maxAttempts = 5,
        ?\DateTimeImmutable $availableAt = null,
    ): string {
        if (preg_match('/^[a-z][a-z0-9_.-]{1,100}$/', $type) !== 1) {
            throw new \InvalidArgumentException('Invalid job type.');
        }
        if ($maxAttempts < 1 || $maxAttempts > 100) {
            throw new \InvalidArgumentException('Job max attempts must be between 1 and 100.');
        }

        if ($idempotencyKey !== null) {
            $existing = $this->findByIdempotency($type, $idempotencyKey);
            if ($existing !== null) {
                return $existing;
            }
        }

        $id = Uuid::uuid4()->toString();
        $now = new \DateTimeImmutable();
        $available = $availableAt ?? $now;
        $record = [
            'id' => $id,
            'type' => $type,
            'payload' => $payload,
            'idempotency_key' => $idempotencyKey,
            'attempts' => 0,
            'max_attempts' => $maxAttempts,
            'available_at' => $available->format(\DateTimeInterface::ATOM),
            'reserved_at' => null,
            'last_error' => null,
            'created_at' => $now->format(\DateTimeInterface::ATOM),
        ];
        $this->write($this->pendingFile($id, $available), $record);
        if ($idempotencyKey !== null) {
            $this->rememberIdempotency($type, $idempotencyKey, $id);
        }

        return $id;
    }

    public function claim(): ?array
    {
        $files = glob($this->path('pending') . DIRECTORY_SEPARATOR . '*.json') ?: [];
        sort($files);
        $now = new \DateTimeImmutable();

        foreach ($files as $file) {
            $record = $this->read($file);
            if ($record === null) {
                continue;
            }
            try {
                $availableAt = new \DateTimeImmutable((string) $record['available_at']);
            } catch (\Exception) {
                continue;
            }
            if ($availableAt > $now) {
                continue;
            }

            $processing = $this->path('processing') . DIRECTORY_SEPARATOR . $record['id'] . '.json';
            if (!@rename($file, $processing)) {
                continue;
            }

            $record['attempts'] = (int) $record['attempts'] + 1;
            $record['reserved_at'] = $now->format(\DateTimeInterface::ATOM);
            $this->write($processing, $record);

            $payload = $record['payload'] ?? [];
            if (!is_array($payload)) {
                $payload = [];
            }
            /** @var array<string, mixed> $payload */

            return [
                'id' => (string) $record['id'],
                'type' => (string) $record['type'],
                'payload' => $payload,
                'attempts' => (int) $record['attempts'],
                'max_attempts' => (int) $record['max_attempts'],
            ];
        }

        return null;
    }

    public function complete(string $id): void
    {
        $processing = $this->path('processing') . DIRECTORY_SEPARATOR . $id . '.json';
        $record = $this->read($processing);
        if ($record === null) {
            return;
        }
        $record['last_error'] = null;
        $target = $this->path('completed') . DIRECTORY_SEPARATOR . $id . '.json';
        $this->write($target, $record);
        @unlink($processing);
    }

    public function fail(string $id, int $attempts, int $maxAttempts, \Throwable $error): void
    {
        $processing = $this->path('processing') . DIRECTORY_SEPARATOR . $id . '.json';
        $record = $this->read($processing);
        if ($record === null) {
            return;
        }
        $record['last_error'] = substr($error::class . ': ' . $error->getMessage(), 0, 2000);
        $dead = $attempts >= $maxAttempts;
        if ($dead) {
            $this->write($this->path('dead') . DIRECTORY_SEPARATOR . $id . '.json', $record);
            @unlink($processing);

            return;
        }
        $delay = min(3600, 5 * (2 ** max(0, $attempts - 1)));
        $available = new \DateTimeImmutable("+{$delay} seconds");
        $record['available_at'] = $available->format(\DateTimeInterface::ATOM);
        $record['reserved_at'] = null;
        $this->write($this->pendingFile($id, $available), $record);
        @unlink($processing);
    }

    public function releaseStale(int $timeoutSeconds = 900): int
    {
        $cutoff = (new \DateTimeImmutable("-{$timeoutSeconds} seconds"));
        $released = 0;
        $files = glob($this->path('processing') . DIRECTORY_SEPARATOR . '*.json') ?: [];
        foreach ($files as $file) {
            $record = $this->read($file);
            if ($record === null) {
                continue;
            }
            $reservedAt = isset($record['reserved_at']) && is_string($record['reserved_at'])
                ? $record['reserved_at']
                : '';
            if ($reservedAt === '') {
                continue;
            }
            try {
                $reserved = new \DateTimeImmutable($reservedAt);
            } catch (\Exception) {
                continue;
            }
            if ($reserved > $cutoff) {
                continue;
            }
            $available = new \DateTimeImmutable();
            $record['available_at'] = $available->format(\DateTimeInterface::ATOM);
            $record['reserved_at'] = null;
            $this->write($this->pendingFile((string) $record['id'], $available), $record);
            @unlink($file);
            ++$released;
        }

        return $released;
    }

    private function pendingFile(string $id, \DateTimeImmutable $availableAt): string
    {
        return $this->path('pending') . DIRECTORY_SEPARATOR . $availableAt->getTimestamp() . '_' . $id . '.json';
    }

    private function path(string $subdir): string
    {
        return rtrim($this->directory, '/\\') . DIRECTORY_SEPARATOR . $subdir;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function read(string $file): ?array
    {
        if (!is_file($file)) {
            return null;
        }
        $raw = file_get_contents($file);
        if ($raw === false || $raw === '') {
            return null;
        }
        try {
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return is_array($data) ? $data : null;
    }

    /**
     * @param array<string, mixed> $record
     */
    private function write(string $file, array $record): void
    {
        $payload = json_encode($record, JSON_THROW_ON_ERROR);
        if (file_put_contents($file, $payload, LOCK_EX) === false) {
            throw new \RuntimeException('Unable to write queue job.');
        }
    }

    private function findByIdempotency(string $type, string $key): ?string
    {
        $file = $this->idempotencyFile($type, $key);
        if (!is_file($file)) {
            return null;
        }
        $id = trim((string) file_get_contents($file));

        return $id !== '' ? $id : null;
    }

    private function rememberIdempotency(string $type, string $key, string $id): void
    {
        if (file_put_contents($this->idempotencyFile($type, $key), $id, LOCK_EX) === false) {
            throw new \RuntimeException('Unable to write queue idempotency key.');
        }
    }

    private function idempotencyFile(string $type, string $key): string
    {
        return $this->path('idempotency') . DIRECTORY_SEPARATOR . hash('sha256', $type . "\0" . $key) . '.json';
    }
}
