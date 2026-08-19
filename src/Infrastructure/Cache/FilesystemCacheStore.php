<?php

declare(strict_types=1);

namespace Libok\Infrastructure\Cache;

use Libok\Application\Contracts\CacheStoreInterface;

final class FilesystemCacheStore implements CacheStoreInterface
{
    public function __construct(private readonly string $directory)
    {
        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
            throw new \RuntimeException('Unable to create cache directory.');
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $file = $this->file($key);
        $handle = @fopen($file, 'rb');
        if ($handle === false) {
            return $default;
        }
        try {
            flock($handle, LOCK_SH);
            $data = json_decode((string) stream_get_contents($handle), true);
            flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }
        if (!is_array($data) || ($data['expires_at'] ?? 0) <= time()) {
            @unlink($file);

            return $default;
        }

        return $data['value'] ?? $default;
    }

    public function set(string $key, mixed $value, int $ttlSeconds): void
    {
        $payload = json_encode(['expires_at' => time() + max(1, $ttlSeconds), 'value' => $value], JSON_THROW_ON_ERROR);
        if (file_put_contents($this->file($key), $payload, LOCK_EX) === false) {
            throw new \RuntimeException('Unable to write cache entry.');
        }
    }

    public function delete(string $key): void
    {
        @unlink($this->file($key));
    }

    public function increment(string $key, int $ttlSeconds): int
    {
        $file = $this->file($key);
        $handle = @fopen($file, 'c+b');
        if ($handle === false) {
            throw new \RuntimeException('Unable to open cache entry.');
        }
        try {
            if (!flock($handle, LOCK_EX)) {
                throw new \RuntimeException('Unable to lock cache entry.');
            }
            $data = json_decode((string) stream_get_contents($handle), true);
            $now = time();
            if (!is_array($data) || ($data['expires_at'] ?? 0) <= $now) {
                $data = ['expires_at' => $now + max(1, $ttlSeconds), 'value' => 0];
            }
            $data['value'] = (int) ($data['value'] ?? 0) + 1;
            rewind($handle);
            ftruncate($handle, 0);
            fwrite($handle, json_encode($data, JSON_THROW_ON_ERROR));
            fflush($handle);
            flock($handle, LOCK_UN);

            return $data['value'];
        } finally {
            fclose($handle);
        }
    }

    private function file(string $key): string
    {
        return rtrim($this->directory, '/\\') . DIRECTORY_SEPARATOR . hash('sha256', $key) . '.cache';
    }
}
