<?php

declare(strict_types=1);

namespace Libok\Infrastructure\Persistence\Repositories;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Libok\Domain\Entities\AuditLog;
use Libok\Domain\Repositories\AuditLogRepositoryInterface;

class DoctrineAuditLogRepository implements AuditLogRepositoryInterface
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function findAll(int $page, int $perPage, array $filters = []): array
    {
        $qb = $this->buildFilteredQuery($filters);
        $qb->select('al')
            ->orderBy('al.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage);

        /** @var list<AuditLog> $rows */
        $rows = $qb->getQuery()->getResult();

        return $rows;
    }

    public function countAll(array $filters = []): int
    {
        $qb = $this->buildFilteredQuery($filters);
        $qb->select('COUNT(al.id)');

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function save(AuditLog $log): void
    {
        $this->em->persist($log);
        $this->em->flush();
    }

    /** @param array<string, mixed> $filters */
    private function buildFilteredQuery(array $filters): QueryBuilder
    {
        $qb = $this->em->createQueryBuilder()
            ->from(AuditLog::class, 'al');

        if (!empty($filters['user_id'])) {
            $qb->andWhere('al.userId = :userId')
               ->setParameter('userId', $filters['user_id']);
        }

        if (!empty($filters['action'])) {
            $qb->andWhere('al.action = :action')
               ->setParameter('action', $filters['action']);
        }

        if (!empty($filters['object_type'])) {
            $qb->andWhere('al.objectType = :objectType')
               ->setParameter('objectType', $filters['object_type']);
        }

        if (!empty($filters['date_from'])) {
            $dateFrom = $filters['date_from'] instanceof \DateTimeInterface
                ? $filters['date_from']
                : new \DateTimeImmutable((string) $filters['date_from']);
            $qb->andWhere('al.createdAt >= :dateFrom')
               ->setParameter('dateFrom', $dateFrom);
        }

        if (!empty($filters['date_to'])) {
            $dateTo = $filters['date_to'] instanceof \DateTimeInterface
                ? $filters['date_to']
                : new \DateTimeImmutable((string) $filters['date_to']);
            $qb->andWhere('al.createdAt <= :dateTo')
               ->setParameter('dateTo', $dateTo);
        }

        return $qb;
    }
}
