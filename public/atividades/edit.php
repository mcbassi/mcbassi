<?php
declare(strict_types=1);

$app = require dirname(__DIR__, 2) . '/app/bootstrap/app.php';

$controller = new App\Atividades\ActivityController($app['auth'], $app['db'], $app['request']);
$controller->edit();
