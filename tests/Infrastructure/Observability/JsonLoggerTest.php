<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Observability;

use Libok\Infrastructure\Observability\ContextSanitizer;
use Libok\Infrastructure\Observability\JsonLogger;
use Libok\Infrastructure\Observability\RequestContext;
use PHPUnit\Framework\TestCase;

final class JsonLoggerTest extends TestCase
{
    public function testItWritesStructuredJsonAndRedactsSensitiveContext(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'libok-log-');
        self::assertIsString($file);
        $context = new RequestContext();
        $context->set('request-123', 'correlation-456');
        $logger = new JsonLogger($context, new ContextSanitizer(), 'test', $file);

        $logger->info('Login for {email}', [
            'email' => 'user@example.test',
            'password' => 'never-log-me',
            'headers' => ['Authorization' => 'Bearer secret-token'],
            'url' => '/reset?token=plain-secret',
        ]);

        $record = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
        @unlink($file);
        self::assertSame('request-123', $record['request_id']);
        self::assertSame('correlation-456', $record['correlation_id']);
        self::assertSame('[REDACTED]', $record['context']['password']);
        self::assertSame('[REDACTED]', $record['context']['headers']['Authorization']);
        self::assertStringNotContainsString('plain-secret', $record['context']['url']);
        self::assertStringNotContainsString('never-log-me', json_encode($record, JSON_THROW_ON_ERROR));
    }
}
