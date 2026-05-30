<?php
declare(strict_types=1);

$app = require dirname(__DIR__, 2) . '/app/bootstrap/app.php';

$controller = new App\Diagnostico\ResultsController(
    $app['auth'],
    $app['db'],
    $app['request']
);
$controller->index();
