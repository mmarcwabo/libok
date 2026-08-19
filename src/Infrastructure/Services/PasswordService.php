<?php

declare(strict_types=1);

namespace Libok\Infrastructure\Services;

class PasswordService
{
    public const DUMMY_HASH = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';

    private const MIN_LENGTH = 8;

    public function hash(string $plainPassword): string
    {
        $this->validate($plainPassword);

        return password_hash($plainPassword, PASSWORD_DEFAULT);
    }

    public function verify(string $plainPassword, string $hash): bool
    {
        return password_verify($plainPassword, $hash);
    }

    public function verifyAgainstDummy(string $plainPassword): void
    {
        password_verify($plainPassword, self::DUMMY_HASH);
    }

    public function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_DEFAULT);
    }

    public function validate(string $plainPassword): void
    {
        if (strlen($plainPassword) < self::MIN_LENGTH) {
            throw new \InvalidArgumentException('Password must be at least ' . self::MIN_LENGTH . ' characters.');
        }
    }
}
