<?php

declare(strict_types=1);

use Doctrine\Migrations\Configuration\Configuration;

$config = new Configuration();
$config->addMigrationsDirectory('Libok\\Migrations', __DIR__ . '/../migrations');
$config->setAllOrNothing(true);
$config->setCheckDatabasePlatform(true);

return $config;
