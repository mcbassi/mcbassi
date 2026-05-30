<?php
declare(strict_types=1);

$app = require dirname(__DIR__, 2) . '/app/bootstrap/app.php';
$app['auth']->requireAuth();
$service = new App\Estrategica\EstrategicaService($app['db']);
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok' => true, 'items' => $service->listPriorityGroups()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
