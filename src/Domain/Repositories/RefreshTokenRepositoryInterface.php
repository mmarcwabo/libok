<?php

declare(strict_types=1);

namespace Libok\Domain\Repositories;

use Libok\Domain\Entities\RefreshToken;

interface RefreshTokenRepositoryInterface
{
    public function findByTokenHash(string $tokenHash): ?RefreshToken;

    public function save(RefreshToken $token): void;

    public function revokeAllForUser(string $userId): void;

    public function deleteExpired(): int;
}
