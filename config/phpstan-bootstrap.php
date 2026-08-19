<?php

declare(strict_types=1);

define('LIBOK_ROOT', dirname(__DIR__));
define('LIBOK_SRC', LIBOK_ROOT . '/src');
define('LIBOK_STORAGE', LIBOK_ROOT . '/storage');
define('LIBOK_CONFIG', LIBOK_ROOT . '/config');

$_ENV['APP_ENV'] = 'test';
$_ENV['APP_DEBUG'] = 'true';
$_ENV['CORS_ORIGIN'] = 'http://localhost:3000';
$_ENV['STORAGE_PATH'] = LIBOK_STORAGE;
$_ENV['DB_DRIVER'] = 'pdo_sqlite';
$_ENV['DB_PATH'] = ':memory:';
$_ENV['LOG_DESTINATION'] = 'php://stderr';
$_ENV['JWT_SECRET'] = 'libok-test-jwt-secret-32-bytes!!';
$_ENV['JWT_ACCESS_TTL'] = '900';
$_ENV['JWT_REFRESH_TTL'] = '1209600';
$_ENV['MAIL_TRANSPORT'] = 'null';
$_ENV['MAIL_SYNC'] = 'false';
$_ENV['QUEUE_PATH'] = LIBOK_STORAGE . '/queue';
