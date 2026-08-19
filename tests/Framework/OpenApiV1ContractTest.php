<?php

declare(strict_types=1);

namespace Tests\Framework;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

final class OpenApiV1ContractTest extends TestCase
{
    public function testSpecificationIsValidAndMatchesRegisteredV1Paths(): void
    {
        $root = dirname(__DIR__, 2);
        $document = Yaml::parseFile($root . '/docs/openapi-v1.yaml');
        self::assertIsArray($document);
        self::assertSame('3.0.3', $document['openapi']);
        self::assertNotEmpty($document['paths']);
        self::assertIsArray($document['paths']);

        $routes = (string) file_get_contents($root . '/config/api_routes.php');
        foreach (array_keys($document['paths']) as $path) {
            self::assertIsString($path);
            self::assertStringContainsString("'" . $path . "'", $routes, "OpenAPI path {$path} is not registered.");
        }
    }
}
