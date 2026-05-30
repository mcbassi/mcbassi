<?php
declare(strict_types=1);

$app = require dirname(__DIR__, 2) . '/app/bootstrap/app.php';

$ragRepository = new App\Papers\RagRepository($app['db']);
$repository = new App\Papers\PaperRepository($app['db'], $ragRepository);
$promptFlowRepository = new App\Papers\PromptFlowRepository($app['db']);
$fileService = new App\Papers\PaperFileService();
$controller = new App\Papers\PaperController($app['auth'], $repository, $promptFlowRepository, $fileService, $app['request']);
$controller->view();
