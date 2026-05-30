<?php
declare(strict_types=1);

$app = require dirname(__DIR__, 2) . '/app/bootstrap/app.php';

$controller = new App\Clientes\ClienteController(
    $app['auth'],
    $app['db'],
    $app['request']
);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $controller->cadastroSalvar();
    return;
}

$controller->cadastro();
