<?php

declare(strict_types=1);

use DI\ContainerBuilder;
use Doctrine\Common\EventManager;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMSetup;
use Libok\Application\Contracts\CacheStoreInterface;
use Libok\Application\Contracts\JobQueueInterface;
use Libok\Application\Contracts\MailerInterface;
use Libok\Application\Contracts\MalwareScannerInterface;
use Libok\Application\Contracts\ObjectStorageInterface;
use Libok\Application\Contracts\OutboxWriterInterface;
use Libok\Application\Contracts\RateLimiterInterface;
use Libok\Application\UseCases\Auth\LoginUseCase;
use Libok\Application\UseCases\Auth\LogoutUseCase;
use Libok\Application\UseCases\Auth\RefreshTokenUseCase;
use Libok\Application\UseCases\CreateItemUseCase;
use Libok\Application\UseCases\CreateUserUseCase;
use Libok\Application\UseCases\DeleteItemUseCase;
use Libok\Application\UseCases\DeleteUserUseCase;
use Libok\Application\UseCases\FindItemUseCase;
use Libok\Application\UseCases\FindUserUseCase;
use Libok\Application\UseCases\ListItemsUseCase;
use Libok\Application\UseCases\ListUsersUseCase;
use Libok\Application\UseCases\RegisterUserUseCase;
use Libok\Application\UseCases\UpdateItemUseCase;
use Libok\Application\UseCases\UpdateUserUseCase;
use Libok\Domain\Repositories\AuditLogRepositoryInterface;
use Libok\Domain\Repositories\ItemRepositoryInterface;
use Libok\Domain\Repositories\RefreshTokenRepositoryInterface;
use Libok\Domain\Repositories\UserRepositoryInterface;
use Libok\Framework\Controllers\Api\AuthController as ApiAuthController;
use Libok\Framework\Controllers\Api\ItemController;
use Libok\Framework\Controllers\Api\UploadController;
use Libok\Framework\Controllers\Api\UserController as ApiUserController;
use Libok\Framework\Controllers\AuthController;
use Libok\Framework\Controllers\HealthController;
use Libok\Framework\Controllers\UserController;
use Libok\Framework\Middleware\AuditMiddleware;
use Libok\Framework\Middleware\AuthRateLimitMiddleware;
use Libok\Framework\Middleware\IdempotencyMiddleware;
use Libok\Framework\Middleware\OperatorMiddleware;
use Libok\Framework\Middleware\RateLimitMiddleware;
use Libok\Framework\Middleware\TenantResetMiddleware;
use Libok\Framework\Middleware\TenantResolutionMiddleware;
use Libok\Infrastructure\Cache\FilesystemCacheStore;
use Libok\Infrastructure\Cache\FixedWindowRateLimiter;
use Libok\Infrastructure\Mail\MailerService;
use Libok\Infrastructure\Mail\NullMailer;
use Libok\Infrastructure\Observability\ContextSanitizer;
use Libok\Infrastructure\Observability\JsonLogger;
use Libok\Infrastructure\Observability\RequestContext;
use Libok\Infrastructure\Persistence\DoctrineOutboxWriter;
use Libok\Infrastructure\Persistence\Filters\TenantFilter;
use Libok\Infrastructure\Persistence\Listeners\TenantAssignmentSubscriber;
use Libok\Infrastructure\Persistence\Repositories\DoctrineAuditLogRepository;
use Libok\Infrastructure\Persistence\Repositories\DoctrineItemRepository;
use Libok\Infrastructure\Persistence\Repositories\DoctrineRefreshTokenRepository;
use Libok\Infrastructure\Persistence\Repositories\DoctrineUserRepository;
use Libok\Infrastructure\Queue\EmailJobHandler;
use Libok\Infrastructure\Queue\FilesystemJobQueue;
use Libok\Infrastructure\Queue\JobWorker;
use Libok\Infrastructure\Queue\KernelWorker;
use Libok\Infrastructure\Queue\OutboxPublisher;
use Libok\Infrastructure\Services\AuditLogService;
use Libok\Infrastructure\Services\FileStorageService;
use Libok\Infrastructure\Services\JwtService;
use Libok\Infrastructure\Services\PasswordService;
use Libok\Infrastructure\Storage\LocalPrivateStorage;
use Libok\Infrastructure\Storage\NullMalwareScanner;
use Libok\Infrastructure\Storage\S3CompatibleStorage;
use Libok\Infrastructure\Tenancy\TenantContext;
use Psr\Log\LoggerInterface;

if (!function_exists('isMemorySqlite')) {
    function isMemorySqlite(): bool
    {
        $driver = (string) ($_ENV['DB_DRIVER'] ?? 'pdo_pgsql');
        $sqlitePath = (string) ($_ENV['DB_PATH'] ?? ':memory:');

        return $driver === 'pdo_sqlite' && ($sqlitePath === ':memory:' || $sqlitePath === '');
    }
}

if (!function_exists('sharedTenantContext')) {
    function sharedTenantContext(): TenantContext
    {
        static $context = null;
        if (!isMemorySqlite()) {
            return new TenantContext();
        }
        if (!$context instanceof TenantContext) {
            $context = new TenantContext();
        }

        return $context;
    }
}

if (!function_exists('buildEntityManager')) {
    function buildEntityManager(?TenantContext $tenantContext = null): EntityManagerInterface
    {
        static $memoryEm = null;
        $tenantContext ??= sharedTenantContext();

        $paths = [LIBOK_SRC . '/Domain/Entities'];
        $isTest = strtolower((string) ($_ENV['APP_ENV'] ?? '')) === 'test';
        $isDevMode = $isTest || filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN);

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
        $config->addFilter('tenant', TenantFilter::class);

        $driver = (string) ($_ENV['DB_DRIVER'] ?? 'pdo_pgsql');
        $sqlitePath = (string) ($_ENV['DB_PATH'] ?? ':memory:');
        $useMemorySqlite = isMemorySqlite();

        if ($useMemorySqlite && $memoryEm instanceof EntityManager && $memoryEm->isOpen()) {
            return $memoryEm;
        }

        if ($driver === 'pdo_sqlite') {
            $connectionParams = $useMemorySqlite
                ? ['driver' => 'pdo_sqlite', 'memory' => true]
                : ['driver' => 'pdo_sqlite', 'path' => $sqlitePath];
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

        $eventManager = new EventManager();
        $eventManager->addEventSubscriber(new TenantAssignmentSubscriber($tenantContext));
        $connection = DriverManager::getConnection($connectionParams, $config, $eventManager);
        $entityManager = new EntityManager($connection, $config, $eventManager);

        if ($useMemorySqlite) {
            $memoryEm = $entityManager;
        }

        return $entityManager;
    }
}

$containerBuilder = new ContainerBuilder();
$containerBuilder->addDefinitions([
    TenantContext::class => DI\factory(static fn (): TenantContext => sharedTenantContext()),
    EntityManagerInterface::class => DI\factory(
        static fn (TenantContext $tenantContext): EntityManagerInterface => buildEntityManager($tenantContext)
    ),
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
    AuthRateLimitMiddleware::class => DI\autowire(),
    MalwareScannerInterface::class => DI\get(NullMalwareScanner::class),
    ObjectStorageInterface::class => DI\factory(static function (MalwareScannerInterface $scanner): ObjectStorageInterface {
        $driver = strtolower((string) ($_ENV['STORAGE_DRIVER'] ?? 'local'));
        if ($driver === 's3') {
            return S3CompatibleStorage::fromEnvironment($scanner);
        }
        $root = (string) ($_ENV['STORAGE_PATH'] ?? LIBOK_STORAGE);

        return new LocalPrivateStorage($root, $scanner);
    }),
    FileStorageService::class => DI\autowire(),
    JwtService::class => DI\autowire(),
    PasswordService::class => DI\autowire(),
    AuditLogService::class => DI\autowire(),
    AuditMiddleware::class => DI\autowire(),
    IdempotencyMiddleware::class => DI\autowire(),
    OperatorMiddleware::class => DI\autowire(),
    OutboxWriterInterface::class => DI\autowire(DoctrineOutboxWriter::class),
    JobQueueInterface::class => DI\factory(static function (): JobQueueInterface {
        $path = (string) ($_ENV['QUEUE_PATH'] ?? '');
        if ($path === '') {
            $path = LIBOK_STORAGE . '/queue';
        }

        return new FilesystemJobQueue($path);
    }),
    MailerInterface::class => DI\factory(static function (JobQueueInterface $queue, LoggerInterface $logger): MailerInterface {
        $transport = strtolower((string) ($_ENV['MAIL_TRANSPORT'] ?? 'smtp'));
        if ($transport === 'null') {
            return new NullMailer();
        }

        return new MailerService($queue, $logger);
    }),
    OutboxPublisher::class => DI\autowire(),
    JobWorker::class => DI\factory(static function (
        JobQueueInterface $queue,
        MailerInterface $mailer,
        LoggerInterface $logger,
    ): JobWorker {
        return new JobWorker($queue, [new EmailJobHandler($mailer)], $logger);
    }),
    KernelWorker::class => DI\autowire(),
    UserRepositoryInterface::class => DI\autowire(DoctrineUserRepository::class),
    RefreshTokenRepositoryInterface::class => DI\autowire(DoctrineRefreshTokenRepository::class),
    AuditLogRepositoryInterface::class => DI\autowire(DoctrineAuditLogRepository::class),
    ItemRepositoryInterface::class => DI\autowire(DoctrineItemRepository::class),
    LoginUseCase::class => DI\autowire(),
    LogoutUseCase::class => DI\autowire(),
    RefreshTokenUseCase::class => DI\autowire(),
    RegisterUserUseCase::class => DI\factory(static function (
        UserRepositoryInterface $users,
        PasswordService $passwords,
        OutboxWriterInterface $outbox,
        MailerInterface $mailer,
        EntityManagerInterface $entityManager,
        LoggerInterface $logger,
    ): RegisterUserUseCase {
        return new RegisterUserUseCase($users, $passwords, $outbox, $mailer, $entityManager, $logger);
    }),
    ListUsersUseCase::class => DI\autowire(),
    FindUserUseCase::class => DI\autowire(),
    CreateUserUseCase::class => DI\autowire(),
    UpdateUserUseCase::class => DI\autowire(),
    DeleteUserUseCase::class => DI\autowire(),
    CreateItemUseCase::class => DI\autowire(),
    ListItemsUseCase::class => DI\autowire(),
    FindItemUseCase::class => DI\autowire(),
    UpdateItemUseCase::class => DI\autowire(),
    DeleteItemUseCase::class => DI\autowire(),
    TenantResetMiddleware::class => DI\autowire(),
    TenantResolutionMiddleware::class => DI\autowire(),
    HealthController::class => DI\autowire(),
    AuthController::class => DI\autowire(),
    ApiAuthController::class => DI\autowire(),
    ApiUserController::class => DI\autowire(),
    UploadController::class => DI\autowire(),
    ItemController::class => DI\autowire(),
    UserController::class => DI\autowire(),
]);

return $containerBuilder->build();
