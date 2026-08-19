<?php

declare(strict_types=1);

namespace Libok\Infrastructure\Storage;

use Libok\Application\Contracts\MalwareScannerInterface;
use Libok\Application\Contracts\ObjectStorageInterface;

final class LocalPrivateStorage implements ObjectStorageInterface
{
    private string $root;

    public function __construct(
        string $root,
        private readonly MalwareScannerInterface $scanner,
    ) {
        $this->root = rtrim($root, '/\\');
        if (!is_dir($this->root) && !mkdir($this->root, 0750, true) && !is_dir($this->root)) {
            throw new \RuntimeException('Unable to create private storage root.');
        }
    }

    public function writeStream(string $key, $stream, string $contentType = 'application/octet-stream'): void
    {
        if (!is_resource($stream)) {
            throw new \InvalidArgumentException('Storage input must be a stream.');
        }
        $this->scanner->assertClean($stream, $key);
        rewind($stream);
        $path = $this->path($key);
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
            throw new \RuntimeException('Unable to create private storage directory.');
        }
        $temporary = $path . '.tmp-' . bin2hex(random_bytes(6));
        $output = @fopen($temporary, 'xb');
        if ($output === false) {
            throw new \RuntimeException('Unable to create private storage object.');
        }
        try {
            if (stream_copy_to_stream($stream, $output) === false) {
                throw new \RuntimeException('Unable to write private storage object.');
            }
        } finally {
            fclose($output);
        }
        @chmod($temporary, 0640);
        if (!@rename($temporary, $path)) {
            @unlink($temporary);
            throw new \RuntimeException('Unable to finalize private storage object.');
        }
    }

    public function readStream(string $key)
    {
        $stream = @fopen($this->path($key), 'rb');
        if ($stream === false) {
            throw new \RuntimeException('Private storage object not found.');
        }

        return $stream;
    }

    public function exists(string $key): bool
    {
        return is_file($this->path($key));
    }

    public function delete(string $key): void
    {
        $path = $this->path($key);
        if (is_file($path) && !@unlink($path)) {
            throw new \RuntimeException('Unable to delete private storage object.');
        }
    }

    public function temporaryDownloadUrl(string $key, \DateTimeImmutable $expiresAt): ?string
    {
        return null;
    }

    public function isReady(): bool
    {
        return is_dir($this->root) && is_readable($this->root) && is_writable($this->root);
    }

    private function path(string $key): string
    {
        if ($key === '' || str_contains($key, "\0") || str_starts_with($key, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $key)) {
            throw new \InvalidArgumentException('Invalid object key.');
        }
        $segments = preg_split('#[\\\\/]#', $key) ?: [];
        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new \InvalidArgumentException('Object key traversal is forbidden.');
            }
        }

        return $this->root . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $segments);
    }
}
