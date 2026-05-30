<?php
declare(strict_types=1);
$app = require dirname(__DIR__, 3) . '/app/bootstrap/app.php';
$controller = new App\TelaSql\SqlController($app['auth'], $app['db']);
$controller->schema();
