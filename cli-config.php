<?php

declare(strict_types=1);

require_once __DIR__ . '/config/bootstrap.php';

use Doctrine\Migrations\Configuration\EntityManager\ExistingEntityManager;
use Doctrine\Migrations\Configuration\Migration\PhpFile;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\ORM\EntityManagerInterface;

$container = require __DIR__ . '/config/services.php';

/** @var EntityManagerInterface $em */
$em = $container->get(EntityManagerInterface::class);

$config = new PhpFile(__DIR__ . '/config/migrations.php');

return DependencyFactory::fromEntityManager($config, new ExistingEntityManager($em));
