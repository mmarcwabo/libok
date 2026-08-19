<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

$testStorage = dirname(__DIR__) . '/storage/test';
if (!is_dir($testStorage) && !mkdir($testStorage, 0750, true) && !is_dir($testStorage)) {
    throw new RuntimeException('Unable to create test storage directory.');
}

putenv('STORAGE_PATH=' . $testStorage);
$_ENV['STORAGE_PATH'] = $testStorage;
$_SERVER['STORAGE_PATH'] = $testStorage;

$logFile = $testStorage . DIRECTORY_SEPARATOR . 'app.log';
putenv('LOG_DESTINATION=' . $logFile);
$_ENV['LOG_DESTINATION'] = $logFile;
$_SERVER['LOG_DESTINATION'] = $logFile;

$jwtSecret = 'libok-test-jwt-secret-32-bytes!!';
putenv('JWT_SECRET=' . $jwtSecret);
$_ENV['JWT_SECRET'] = $jwtSecret;
$_SERVER['JWT_SECRET'] = $jwtSecret;
$_ENV['JWT_ACCESS_TTL'] = '900';
$_ENV['JWT_REFRESH_TTL'] = '1209600';
$_SERVER['JWT_ACCESS_TTL'] = '900';
$_SERVER['JWT_REFRESH_TTL'] = '1209600';
