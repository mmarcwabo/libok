<?php

declare(strict_types=1);

namespace Tests\Framework;

final class ApplicationKernelTest extends KernelTestCase
{
    public function testUnknownApiRouteReturnsJson404Envelope(): void
    {
        $response = $this->jsonRequest('GET', '/api/v1/nope');

        self::assertSame(404, $response->getStatusCode());
        self::assertStringContainsString('application/json', (string) $response->headers->get('Content-Type'));
        $payload = $this->decode((string) $response->getContent());
        self::assertFalse($payload['success']);
        self::assertSame('Route not found.', $payload['message']);
        self::assertSame('http.not_found', $payload['code']);
        self::assertArrayNotHasKey('debug', $payload);
        self::assertNotEmpty($response->headers->get('X-Request-ID'));
    }

    public function testEchoesClientRequestId(): void
    {
        $response = $this->jsonRequest('GET', '/api/v1/nope', ['X-Request-ID' => 'req-test-123']);

        self::assertSame('req-test-123', $response->headers->get('X-Request-ID'));
    }
}
