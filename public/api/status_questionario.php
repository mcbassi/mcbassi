<?php
declare(strict_types=1);

$app = require dirname(__DIR__, 2) . '/app/bootstrap/app.php';
$app['auth']->requireAuth();
$service = new App\Estrategica\StatusService($app['db']);
$service->handle();
