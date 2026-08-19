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

$publicStorage = $testStorage . DIRECTORY_SEPARATOR . 'public';
if (!is_dir($publicStorage) && !mkdir($publicStorage, 0750, true) && !is_dir($publicStorage)) {
    throw new RuntimeException('Unable to create test public storage directory.');
}
putenv('PUBLIC_STORAGE_PATH=' . $publicStorage);
$_ENV['PUBLIC_STORAGE_PATH'] = $publicStorage;
$_SERVER['PUBLIC_STORAGE_PATH'] = $publicStorage;
$_ENV['MAX_UPLOAD_MB'] = '20';
$_SERVER['MAX_UPLOAD_MB'] = '20';

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

$queuePath = $testStorage . DIRECTORY_SEPARATOR . 'queue';
if (!is_dir($queuePath) && !mkdir($queuePath, 0750, true) && !is_dir($queuePath)) {
    throw new RuntimeException('Unable to create test queue directory.');
}
putenv('QUEUE_PATH=' . $queuePath);
$_ENV['QUEUE_PATH'] = $queuePath;
$_SERVER['QUEUE_PATH'] = $queuePath;
$_ENV['MAIL_TRANSPORT'] = 'null';
$_SERVER['MAIL_TRANSPORT'] = 'null';
$_ENV['MAIL_SYNC'] = 'false';
$_SERVER['MAIL_SYNC'] = 'false';
$_ENV['QUEUE_DRIVER'] = 'filesystem';
$_SERVER['QUEUE_DRIVER'] = 'filesystem';
