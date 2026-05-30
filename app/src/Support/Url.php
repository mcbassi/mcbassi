<?php
declare(strict_types=1);

namespace App\Support;

final class Url
{
    private static ?string $basePath = null;

    public static function setBasePath(string $basePath): void
    {
        self::$basePath = rtrim($basePath, '/');
    }

    public static function basePath(): string
    {
        return self::$basePath ?? '';
    }

    public static function to(string $path = ''): string
    {
        $base = self::basePath();

        if ($path === '' || $path === '/') {
            return ($base === '' ? '' : $base) . '/';
        }

        return ($base === '' ? '' : $base) . '/' . ltrim($path, '/');
    }

    public static function asset(string $path): string
    {
        return self::to($path);
    }
}
