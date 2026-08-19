<?php

declare(strict_types=1);

namespace Libok\Infrastructure\Persistence\Repositories;

use Doctrine\ORM\EntityManagerInterface;
use Libok\Domain\Entities\RefreshToken;
use Libok\Domain\Repositories\RefreshTokenRepositoryInterface;

class DoctrineRefreshTokenRepository implements RefreshTokenRepositoryInterface
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function findByTokenHash(string $tokenHash): ?RefreshToken
    {
        return $this->em->createQueryBuilder()
            ->select('rt')
            ->from(RefreshToken::class, 'rt')
            ->where('rt.tokenHash = :tokenHash')
            ->setParameter('tokenHash', $tokenHash)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function save(RefreshToken $token): void
    {
        $this->em->persist($token);
        $this->em->flush();
    }

    public function revokeAllForUser(string $userId): void
    {
        $this->em->createQueryBuilder()
            ->update(RefreshToken::class, 'rt')
            ->set('rt.revokedAt', ':revokedAt')
            ->where('IDENTITY(rt.user) = :userId')
            ->andWhere('rt.revokedAt IS NULL')
            ->setParameter('revokedAt', new \DateTimeImmutable())
            ->setParameter('userId', $userId)
            ->getQuery()
            ->execute();
    }

    public function deleteExpired(): int
    {
        return (int) $this->em->createQueryBuilder()
            ->delete(RefreshToken::class, 'rt')
            ->where('rt.expiresAt < :now')
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->execute();
    }
}
