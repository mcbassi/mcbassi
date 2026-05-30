<?php
declare(strict_types=1);

$app = require dirname(__DIR__, 2) . '/app/bootstrap/app.php';

$ragRepository = new App\Papers\RagRepository($app['db']);
$repository = new App\Papers\PaperRepository($app['db'], $ragRepository);
$service = new App\Papers\PaperImportService($repository);
$controller = new App\Papers\PaperImportController($app['auth'], $service, $app['request']);
$controller->index();
