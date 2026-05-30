<?php
declare(strict_types=1);

use App\Support\Env;

return [
    'name' => 'Projeto Refatorado',
    'env' => Env::get('APP_ENV', 'production'),
    'debug' => Env::bool('APP_DEBUG', false),
    'url' => Env::get('APP_URL', 'http://localhost'),
    'timezone' => Env::get('APP_TIMEZONE', 'America/Bogota'),
];
