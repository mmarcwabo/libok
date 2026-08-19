<?php

declare(strict_types=1);

use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Dotenv\Dotenv;
use Libok\Infrastructure\Persistence\Types\LenientDateTimeImmutableType;
use Libok\Infrastructure\Persistence\Types\LenientDateTimeTzImmutableType;

define('LIBOK_ROOT', dirname(__DIR__));
define('LIBOK_SRC', LIBOK_ROOT . '/src');
define('LIBOK_STORAGE', LIBOK_ROOT . '/storage');
define('LIBOK_CONFIG', LIBOK_ROOT . '/config');

require_once LIBOK_ROOT . '/vendor/autoload.php';

Type::overrideType(Types::DATETIMETZ_IMMUTABLE, LenientDateTimeTzImmutableType::class);
Type::overrideType(Types::DATETIME_IMMUTABLE, LenientDateTimeImmutableType::class);

$dotenv = Dotenv::createImmutable(LIBOK_ROOT);
$dotenv->safeLoad();

$requiredEnv = [
    'CORS_ORIGIN',
    'STORAGE_PATH',
];

$mirrorKeys = array_merge($requiredEnv, [
    'APP_ENV',
    'APP_DEBUG',
    'APP_URL',
    'APP_TIMEZONE',
    'DB_DRIVER',
    'DB_HOST',
    'DB_PORT',
    'DB_NAME',
    'DB_USER',
    'DB_PASSWORD',
    'DB_PATH',
    'FRONTEND_URL',
    'LOG_DESTINATION',
    'PUBLIC_STORAGE_PATH',
    'MAX_UPLOAD_MB',
]);

foreach ($mirrorKeys as $key) {
    $process = $_SERVER[$key] ?? getenv($key);
    if (is_string($process) && $process !== '') {
        $_ENV[$key] = $process;
        $_SERVER[$key] = $process;
        continue;
    }
    if (array_key_exists($key, $_ENV) && $_ENV[$key] !== '') {
        $_SERVER[$key] = (string) $_ENV[$key];
    }
}

if (($_ENV['STORAGE_PATH'] ?? '') === '') {
    $_ENV['STORAGE_PATH'] = LIBOK_STORAGE;
    $_SERVER['STORAGE_PATH'] = LIBOK_STORAGE;
}

$driver = (string) ($_ENV['DB_DRIVER'] ?? 'pdo_pgsql');
if ($driver !== 'pdo_sqlite') {
    $requiredEnv = array_merge($requiredEnv, [
        'DB_HOST',
        'DB_PORT',
        'DB_NAME',
        'DB_USER',
        'DB_PASSWORD',
    ]);
}

$dotenv->required($requiredEnv);

$timezone = $_ENV['APP_TIMEZONE'] ?? 'UTC';
date_default_timezone_set($timezone);

$debug = filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN);
if ($debug) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
}
