<?php
declare(strict_types=1);

$app = require dirname(__DIR__, 2) . '/app/bootstrap/app.php';

$ragRepository = new App\Papers\RagRepository($app['db']);
$repository = new App\Papers\PaperRepository($app['db'], $ragRepository);
$promptFlowRepository = new App\Papers\PromptFlowRepository($app['db']);
$controller = new App\Papers\PromptFlowController($app['auth'], $repository, $promptFlowRepository);
$controller->index();
