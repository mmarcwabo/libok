<?php

declare(strict_types=1);

namespace Tests\Framework;

final class HealthControllerHttpTest extends KernelTestCase
{
    public function testLiveReturnsOkEnvelope(): void
    {
        $response = $this->jsonRequest('GET', '/api/v1/health/live');

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decode((string) $response->getContent());
        self::assertTrue($payload['success']);
        self::assertSame('ok', $payload['data']['status']);
        self::assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        self::assertSame('DENY', $response->headers->get('X-Frame-Options'));
    }

    public function testApiAliasPointsAtV1(): void
    {
        $response = $this->jsonRequest('GET', '/api/health/live');

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decode((string) $response->getContent());
        self::assertSame('ok', $payload['data']['status']);
    }

    public function testReadySucceedsWhenSqliteAndStorageAreUp(): void
    {
        $response = $this->jsonRequest('GET', '/api/v1/health/ready');

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decode((string) $response->getContent());
        self::assertTrue($payload['success']);
        self::assertSame('ready', $payload['data']['status']);
        self::assertSame('ok', $payload['data']['checks']['database']);
        self::assertSame('ok', $payload['data']['checks']['storage']);
        self::assertStringNotContainsString('SQLSTATE', (string) $response->getContent());
    }
}
