<?php

declare(strict_types=1);

namespace Tests\Framework;

use Doctrine\ORM\EntityManagerInterface;
use Libok\Application\Contracts\ObjectStorageInterface;
use Libok\Framework\Controllers\HealthController;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class HealthControllerTest extends TestCase
{
    public function testReadyHidesSqlWhenDatabaseFailsWithoutDebug(): void
    {
        $_ENV['APP_DEBUG'] = 'false';
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willThrowException(
            new \RuntimeException('SQLSTATE[HY000] [1045] Access denied for user \'secret\'')
        );
        $controller = new HealthController($entityManager, $this->storage(true));

        $response = $controller->ready(Request::create('/api/v1/health/ready'));

        self::assertSame(503, $response->getStatusCode());
        $body = (string) $response->getContent();
        self::assertStringNotContainsString('SQLSTATE', $body);
        self::assertStringNotContainsString('secret', $body);
        $payload = json_decode($body, true);
        self::assertIsArray($payload);
        self::assertFalse($payload['success']);
        self::assertSame('failed', $payload['data']['checks']['database']);
    }

    public function testReadyFailsWhenStorageIsDown(): void
    {
        $connection = \Doctrine\DBAL\DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($connection);

        $response = (new HealthController($entityManager, $this->storage(false)))
            ->ready(Request::create('/api/v1/health/ready'));

        self::assertSame(503, $response->getStatusCode());
        $payload = json_decode((string) $response->getContent(), true);
        self::assertIsArray($payload);
        self::assertSame('failed', $payload['data']['checks']['storage']);
    }

    private function storage(bool $ready): ObjectStorageInterface
    {
        return new class ($ready) implements ObjectStorageInterface {
            public function __construct(private readonly bool $ready)
            {
            }

            public function writeStream(string $key, $stream, string $contentType = 'application/octet-stream'): void
            {
            }

            public function readStream(string $key)
            {
                return fopen('php://temp', 'rb');
            }

            public function exists(string $key): bool
            {
                return false;
            }

            public function delete(string $key): void
            {
            }

            public function temporaryDownloadUrl(string $key, \DateTimeImmutable $expiresAt): ?string
            {
                return null;
            }

            public function isReady(): bool
            {
                return $this->ready;
            }
        };
    }
}
