<?php

declare(strict_types=1);

namespace Libok\Infrastructure\Services;

use Doctrine\ORM\EntityManagerInterface;
use Libok\Domain\Entities\AuditLog;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;

class AuditLogService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function log(
        ?string $userId,
        string $action,
        string $objectType,
        ?string $objectId,
        array $payload = [],
        string $ipAddress = 'unknown',
        string $userAgent = '',
        ?string $requestId = null,
    ): void {
        $log = new AuditLog();
        $log->setId(Uuid::uuid4()->toString());
        $log->setUserId($userId);
        $log->setAction($action);
        $log->setObjectType($objectType);
        $log->setObjectId($objectId);
        $log->setPayload($payload);
        $log->setIpAddress($ipAddress);
        $log->setUserAgent($userAgent !== '' ? $userAgent : null);
        $log->setRequestId($requestId);
        $log->setCreatedAt(new \DateTimeImmutable());

        $this->em->persist($log);

        try {
            $this->em->flush();
        } catch (\Throwable $e) {
            $this->logger->error('Failed to persist audit log', [
                'action' => $action,
                'user_id' => $userId,
                'exception' => $e,
            ]);
        }
    }
}
