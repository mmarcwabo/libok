<?php

declare(strict_types=1);

namespace Libok\Application\Contracts;

interface ObjectStorageInterface
{
    /** @param resource $stream */
    public function writeStream(string $key, $stream, string $contentType = 'application/octet-stream'): void;

    /** @return resource */
    public function readStream(string $key);

    public function exists(string $key): bool;

    public function delete(string $key): void;

    public function temporaryDownloadUrl(string $key, \DateTimeImmutable $expiresAt): ?string;

    public function isReady(): bool;
}
