<?php

declare(strict_types=1);

namespace Tests\Framework;

final class CorsMiddlewareTest extends KernelTestCase
{
    public function testAllowsConfiguredOrigin(): void
    {
        $response = $this->jsonRequest('GET', '/api/v1/health/live', [
            'Origin' => 'http://localhost:3000',
        ]);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('http://localhost:3000', $response->headers->get('Access-Control-Allow-Origin'));
    }

    public function testRejectsUnknownOrigin(): void
    {
        $response = $this->jsonRequest('GET', '/api/v1/health/live', [
            'Origin' => 'https://evil.example',
        ]);

        self::assertSame(200, $response->getStatusCode());
        self::assertNotSame('https://evil.example', $response->headers->get('Access-Control-Allow-Origin'));
        self::assertNull($response->headers->get('Access-Control-Allow-Origin'));
    }

    public function testOptionsPreflightOnHealth(): void
    {
        $response = $this->jsonRequest('OPTIONS', '/api/v1/health/live', [
            'Origin' => 'http://localhost:3000',
        ]);

        self::assertSame(204, $response->getStatusCode());
        self::assertSame('http://localhost:3000', $response->headers->get('Access-Control-Allow-Origin'));
    }
}
