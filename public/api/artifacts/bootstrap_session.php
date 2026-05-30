<?php
declare(strict_types=1);

$app = require dirname(__DIR__, 3) . '/app/bootstrap/app.php';
$controller = new App\Artifacts\ArtifactController($app['auth'], $app['db'], $app['request']);
$controller->bootstrap();
