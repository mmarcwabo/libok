<?php

declare(strict_types=1);

namespace Tests\Framework;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Libok\Framework\Core\Application;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

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

    protected function ensureSchema(): EntityManagerInterface
    {
        $container = require dirname(__DIR__, 2) . '/config/services.php';
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);
        $tool = new SchemaTool($entityManager);
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);

        return $entityManager;
    }

    /**
     * @param array<string, string> $headers
     * @param array<string, string> $cookies
     * @param array<string, mixed>|null $json
     */
    protected function jsonRequest(
        string $method,
        string $uri,
        array $headers = [],
        array $cookies = [],
        ?array $json = null,
    ): Response {
        $server = [];
        foreach ($headers as $name => $value) {
            $headerKey = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
            $server[$headerKey] = $value;
        }
        $content = null;
        if ($json !== null) {
            $server['CONTENT_TYPE'] = 'application/json';
            $content = json_encode($json, JSON_THROW_ON_ERROR);
        }
        $request = Request::create($uri, $method, [], $cookies, [], $server, $content);

        return $this->bootApplication()->handle($request);
    }

    /**
     * @return array<string, string>
     */
    protected function cookiesFrom(Response $response): array
    {
        $cookies = [];
        foreach ($response->headers->all('set-cookie') as $header) {
            if (preg_match('/^([^=]+)=([^;]*)/', $header, $matches) === 1) {
                $cookies[rawurldecode($matches[1])] = rawurldecode($matches[2]);
            }
        }

        return array_filter($cookies, static fn (string $value): bool => $value !== '');
    }

    /** @return array<string, mixed> */
    protected function decode(string $content): array
    {
        $payload = json_decode($content, true);
        self::assertIsArray($payload);

        return $payload;
    }
}
