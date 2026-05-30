<?php
declare(strict_types=1);
$app = require dirname(__DIR__, 2) . '/app/bootstrap/app.php';
$repository = new App\Fields\FieldRepository($app['db']);
$controller = new App\Fields\FieldController($app['auth'], $repository, $app['request']);
$controller->form();
