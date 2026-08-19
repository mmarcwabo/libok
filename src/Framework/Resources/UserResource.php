<?php

declare(strict_types=1);

namespace Libok\Framework\Resources;

use Libok\Domain\Entities\User;

final class UserResource
{
    /**
     * @return array{id: string, name: string, email: string, status: string, roles: list<string>, created_at: string}
     */
    public static function toArray(User $user): array
    {
        return [
            'id' => $user->getId(),
            'name' => $user->getName(),
            'email' => $user->getEmail(),
            'status' => $user->getStatus(),
            'roles' => $user->getRoleNames(),
            'created_at' => $user->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}
