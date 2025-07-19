<?php

declare(strict_types=1);

namespace Libok\Application\DTOs;

readonly class UserData
{
    public function __construct(
        public string $id,
        public string $name,
        public string $email,
        public string $createdAt
    ) {}
}