<?php
declare(strict_types=1);

$app = require dirname(__DIR__, 2) . '/app/bootstrap/app.php';
$service = new App\Agents\AgentConfigService();
$controller = new App\Agents\AgentConfigController($app['auth'], $service, $app['request']);
$controller->start();
