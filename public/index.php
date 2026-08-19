<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

/** @var Libok\Framework\Core\Application $app */
$app = require dirname(__DIR__) . '/config/app.php';
$app->run();
