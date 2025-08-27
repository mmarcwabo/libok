<?php

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;

require_once __DIR__ . '/../vendor/autoload.php';

// Create a simple "default" Doctrine ORM configuration
$config = ORMSetup::createAttributeMetadataConfiguration(
    paths: [__DIR__ . '/../src/Domain/Entities'],
    isDevMode: $_ENV['DEV_MODE'] ?? true,
);

// Database connection configuration
$connectionParams = [
    'driver'   => $_ENV['DB_DRIVER'],
    'host'     => $_ENV['DB_HOST'],
    'port'     => $_ENV['DB_PORT'],
    'user'     => $_ENV['DB_USER'],
    'password' => $_ENV['DB_PASSWORD'],
    'dbname'   => $_ENV['DB_NAME'],
];

$connection = DriverManager::getConnection($connectionParams, $config);
if (! $connection) {
    throw new Exception("Database configuration: correct parameters are missing. Please check them.");
}
$entityManager = new EntityManager($connection, $config);

// Access the EntityManager
function getEntityManager(): EntityManager
{
    global $entityManager;
    return $entityManager;
}