<?php
declare(strict_types=1);

$app = require dirname(__DIR__) . '/app/bootstrap/app.php';

$controller = new App\Home\HomeController($app['auth']);
$controller->index();
