<?php
declare(strict_types=1);

namespace App\Support;

final class RequestContext
{
    public static function currentPath(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        return $path !== '/' ? rtrim($path, '/') : '/';
    }
}
