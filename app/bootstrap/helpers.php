<?php
declare(strict_types=1);

use App\Security\Csrf;
use App\Support\Url;

function app_path(string $path = ''): string
{
    $root = dirname(__DIR__, 2);

    return $path === '' ? $root : $root . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR);
}

function storage_path(string $path = ''): string
{
    $root = app_path('storage');

    return $path === '' ? $root : $root . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR);
}

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function base_path(): string
{
    return Url::basePath();
}

function url(string $path = ''): string
{
    return Url::to($path);
}

function asset(string $path): string
{
    return Url::asset($path);
}

function csrf_token(): string
{
    return Csrf::token();
}

function csrf_input(): string
{
    return '<input type="hidden" name="_csrf" value="' . h(csrf_token()) . '">';
}

function check_csrf(?string $token = null): bool
{
    return Csrf::check($token);
}

function redirect(string $to): never
{
    header('Location: ' . $to, true, 302);
    exit;
}

function current_path(): string
{
    $uri = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
    $uri = str_replace('\\', '/', $uri);
    $basePath = base_path();

    if ($basePath !== '' && str_starts_with($uri, $basePath)) {
        $uri = substr($uri, strlen($basePath));
    }

    if ($uri === '' || $uri === false) {
        $uri = '/';
    }

    if (!str_starts_with($uri, '/')) {
        $uri = '/' . $uri;
    }

    return normalize_path($uri);
}

function normalize_path(string $path): string
{
    $path = trim(str_replace('\\', '/', $path));

    if ($path === '' || $path === '/') {
        return '/';
    }

    if (!str_starts_with($path, '/')) {
        $path = '/' . $path;
    }

    $path = preg_replace('#/+#', '/', $path) ?: '/';
    $path = rtrim($path, '/');

    if ($path === '/index.php') {
        return '/';
    }

    return $path === '' ? '/' : $path;
}

function path_is(string $path): bool
{
    return current_path() === normalize_path($path);
}

function path_starts_with(string $prefix): bool
{
    $current = current_path();
    $prefix = normalize_path($prefix);

    if ($prefix === '/') {
        return $current === '/';
    }

    return $current === $prefix || str_starts_with($current, $prefix . '/');
}

function nav_active(string $prefix): string
{
    return path_starts_with($prefix) ? 'is-active' : '';
}

function session_user_name(): string
{
    return (string) ($_SESSION['user'] ?? $_SESSION['usuario'] ?? 'Convidado');
}

function session_user_email(): string
{
    return (string) ($_SESSION['email'] ?? $_SESSION['user_email'] ?? '');
}

function session_user_level(): string
{
    return (string) ($_SESSION['nivel'] ?? 'Sem nível');
}

function is_authenticated(): bool
{
    return (bool) ($_SESSION['auth'] ?? false);
}

function flash_set(string $key, string $message): void
{
    $_SESSION['_flash'][$key] = $message;
}

function flash_get(string $key): ?string
{
    if (!isset($_SESSION['_flash'][$key])) {
        return null;
    }

    $message = (string) $_SESSION['_flash'][$key];
    unset($_SESSION['_flash'][$key]);

    return $message;
}


function t(string $key, array $replace = [], ?string $default = null): string
{
    return \App\Support\I18n::get($key, $replace, $default);
}

function current_lang(): string
{
    return \App\Support\I18n::lang();
}

function html_lang(): string
{
    return \App\Support\I18n::htmlLang();
}

function available_langs(): array
{
    return \App\Support\I18n::available();
}
