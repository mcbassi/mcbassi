<?php
declare(strict_types=1);

$app = require dirname(__DIR__, 2) . '/app/bootstrap/app.php';

$repository = new App\Prompts\PromptRepository($app['db']);
$preview = new App\Prompts\PromptRuntimeService($app['db'], $repository);
$controller = new App\Prompts\PromptController($app['auth'], $repository, $preview, $app['request']);
$controller->index();
