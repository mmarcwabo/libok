<?php

declare(strict_types=1);

namespace Libok\Application\Contracts;

interface CacheStoreInterface
{
    public function get(string $key, mixed $default = null): mixed;

    public function set(string $key, mixed $value, int $ttlSeconds): void;

    public function delete(string $key): void;

    /** Atomically increments and applies TTL when the key is created. */
    public function increment(string $key, int $ttlSeconds): int;
}
