<?php
declare(strict_types=1);

$app = require dirname(__DIR__, 2) . '/app/bootstrap/app.php';

$repository = new App\Grupos\GroupRepository($app['db']);
$controller = new App\Grupos\GroupController($app['auth'], $repository, $app['request']);
$controller->index();
