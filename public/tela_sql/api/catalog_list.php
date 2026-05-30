<?php
declare(strict_types=1);
$app = require dirname(__DIR__, 3) . '/app/bootstrap/app.php';
$controller = new App\TelaSql\SqlController($app['auth'], $app['db']);
$search = (string) ($app['request']->query('search', '') ?? '');
$active = ((string) ($app['request']->query('active', '1') ?? '1')) !== '0';
$controller->catalogList($search, $active);
