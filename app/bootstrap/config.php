<?php
declare(strict_types=1);

$config = [
    'app' => require APP_ROOT . '/config/app.php',
    'database' => require APP_ROOT . '/config/database.php',
    'auth' => require APP_ROOT . '/config/auth.php',
    'ai' => require APP_ROOT . '/config/ai.php',
    'storage' => require APP_ROOT . '/config/storage.php',
    'integrations' => require APP_ROOT . '/config/integrations.php',
];

date_default_timezone_set($config['app']['timezone']);

return $config;
