<?php

declare(strict_types=1);

namespace Tests\Framework;

use Libok\Framework\Core\Application;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

abstract class KernelTestCase extends TestCase
{
    protected function bootApplication(): Application
    {
        if (!defined('LIBOK_ROOT')) {
            require_once dirname(__DIR__, 2) . '/config/bootstrap.php';
        }

        /** @var Application $app */
        $app = require dirname(__DIR__, 2) . '/config/app.php';

        return $app;
    }

    protected function jsonRequest(string $method, string $uri, array $headers = []): \Symfony\Component\HttpFoundation\Response
    {
        $server = [];
        foreach ($headers as $name => $value) {
            $headerKey = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
            $server[$headerKey] = $value;
        }
        $request = Request::create($uri, $method, [], [], [], $server);

        return $this->bootApplication()->handle($request);
    }

    /** @return array<string, mixed> */
    protected function decode(string $content): array
    {
        $payload = json_decode($content, true);
        self::assertIsArray($payload);

        return $payload;
    }
}
