<?php

declare(strict_types=1);

use DI\ContainerBuilder;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMSetup;
use Libok\Application\Contracts\CacheStoreInterface;
use Libok\Application\Contracts\MalwareScannerInterface;
use Libok\Application\Contracts\ObjectStorageInterface;
use Libok\Application\Contracts\RateLimiterInterface;
use Libok\Domain\Repositories\UserRepositoryInterface;
use Libok\Framework\Controllers\AuthController;
use Libok\Framework\Controllers\HealthController;
use Libok\Framework\Controllers\UserController;
use Libok\Framework\Middleware\RateLimitMiddleware;
use Libok\Infrastructure\Cache\FilesystemCacheStore;
use Libok\Infrastructure\Cache\FixedWindowRateLimiter;
use Libok\Infrastructure\Observability\ContextSanitizer;
use Libok\Infrastructure\Observability\JsonLogger;
use Libok\Infrastructure\Observability\RequestContext;
use Libok\Infrastructure\Persistence\Repositories\DoctrineUserRepository;
use Libok\Infrastructure\Storage\LocalPrivateStorage;
use Libok\Infrastructure\Storage\NullMalwareScanner;
use Psr\Log\LoggerInterface;

if (!function_exists('buildEntityManager')) {
    function buildEntityManager(): EntityManagerInterface
    {
        $paths = [LIBOK_SRC . '/Domain/Entities'];
        $isDevMode = filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $proxyDir = LIBOK_STORAGE . '/cache/proxies';
        if (!is_dir($proxyDir) && !mkdir($proxyDir, 0750, true) && !is_dir($proxyDir)) {
            throw new RuntimeException('Unable to create Doctrine proxy directory.');
        }

        if ($isDevMode) {
            $cache = new Symfony\Component\Cache\Adapter\ArrayAdapter();
        } else {
            $metaCacheDir = LIBOK_STORAGE . '/cache/doctrine';
            if (!is_dir($metaCacheDir) && !mkdir($metaCacheDir, 0750, true) && !is_dir($metaCacheDir)) {
                throw new RuntimeException('Unable to create Doctrine metadata cache directory.');
            }
            $cache = new Symfony\Component\Cache\Adapter\PhpFilesAdapter(
                namespace: 'doctrine',
                directory: $metaCacheDir,
            );
        }

        $config = ORMSetup::createAttributeMetadataConfiguration(
            paths: $paths,
            isDevMode: $isDevMode,
            proxyDir: $proxyDir,
            cache: $cache,
        );
        $config->setAutoGenerateProxyClasses(Doctrine\ORM\Proxy\ProxyFactory::AUTOGENERATE_FILE_NOT_EXISTS);

        $driver = (string) ($_ENV['DB_DRIVER'] ?? 'pdo_pgsql');
        if ($driver === 'pdo_sqlite') {
            $path = (string) ($_ENV['DB_PATH'] ?? ':memory:');
            $connectionParams = ($path === ':memory:' || $path === '')
                ? ['driver' => 'pdo_sqlite', 'memory' => true]
                : ['driver' => 'pdo_sqlite', 'path' => $path];
        } else {
            $env = static function (string $key): string {
                $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
                if (!is_string($value) || $value === '') {
                    throw new RuntimeException("Missing required environment variable: {$key}");
                }

                return $value;
            };
            $connectionParams = [
                'driver' => $driver,
                'host' => $env('DB_HOST'),
                'port' => (int) $env('DB_PORT'),
                'dbname' => $env('DB_NAME'),
                'user' => $env('DB_USER'),
                'password' => $env('DB_PASSWORD'),
                'charset' => $driver === 'pdo_mysql' ? 'utf8mb4' : 'utf8',
            ];
        }

        $connection = DriverManager::getConnection($connectionParams, $config);

        return new EntityManager($connection, $config);
    }
}

$containerBuilder = new ContainerBuilder();
$containerBuilder->addDefinitions([
    EntityManagerInterface::class => DI\factory(static fn (): EntityManagerInterface => buildEntityManager()),
    EntityManager::class => DI\get(EntityManagerInterface::class),
    Connection::class => DI\factory(
        static fn (EntityManagerInterface $entityManager): Connection => $entityManager->getConnection()
    ),
    RequestContext::class => DI\autowire(),
    ContextSanitizer::class => DI\autowire(),
    LoggerInterface::class => DI\factory(
        static fn (RequestContext $context, ContextSanitizer $sanitizer): LoggerInterface => new JsonLogger($context, $sanitizer)
    ),
    CacheStoreInterface::class => DI\factory(static function (): CacheStoreInterface {
        return new FilesystemCacheStore(LIBOK_STORAGE . '/cache/app');
    }),
    RateLimiterInterface::class => DI\get(FixedWindowRateLimiter::class),
    RateLimitMiddleware::class => DI\autowire()
        ->constructorParameter('maxAttempts', 10)
        ->constructorParameter('windowSeconds', 900),
    MalwareScannerInterface::class => DI\get(NullMalwareScanner::class),
    ObjectStorageInterface::class => DI\factory(static function (MalwareScannerInterface $scanner): ObjectStorageInterface {
        $root = (string) ($_ENV['STORAGE_PATH'] ?? LIBOK_STORAGE);

        return new LocalPrivateStorage($root, $scanner);
    }),
    UserRepositoryInterface::class => DI\autowire(DoctrineUserRepository::class),
    HealthController::class => DI\autowire(),
    AuthController::class => DI\autowire(),
    UserController::class => DI\autowire(),
]);

return $containerBuilder->build();
