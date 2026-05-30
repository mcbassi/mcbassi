<?php
declare(strict_types=1);

use App\Infra\Env;

if (session_status() !== PHP_SESSION_ACTIVE) {
    $secure = filter_var(Env::get('SESSION_COOKIE_SECURE', 'false'), FILTER_VALIDATE_BOOL);
    $httpOnly = filter_var(Env::get('SESSION_COOKIE_HTTPONLY', 'true'), FILTER_VALIDATE_BOOL);
    $sameSite = Env::get('SESSION_COOKIE_SAMESITE', 'Lax');
    $cookiePath = Env::get('SESSION_COOKIE_PATH', '') ?: App\Support\Url::basePath() . '/';
    $sessionName = Env::get('SESSION_NAME', 'PRODCOLSESSID');

    session_name($sessionName);
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => rtrim($cookiePath, '/') . '/',
        'domain' => '',
        'secure' => $secure,
        'httponly' => $httpOnly,
        'samesite' => $sameSite,
    ]);

    session_start();
}
