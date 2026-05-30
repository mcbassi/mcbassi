<?php
declare(strict_types=1);

namespace App\Infra;

final class Env
{
    private static array $values = [];
    private static bool $booted = false;

    public static function boot(string $envFile): void
    {
        if (self::$booted) {
            return;
        }

        if (is_file($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

            foreach ($lines as $line) {
                $trimmed = trim($line);

                if ($trimmed === '' || str_starts_with($trimmed, '#') || !str_contains($trimmed, '=')) {
                    continue;
                }

                [$key, $value] = explode('=', $trimmed, 2);
                $key = trim($key);
                $value = trim($value);

                if ($value !== '' && (
                    ($value[0] === '"' && substr($value, -1) === '"') ||
                    ($value[0] === "'" && substr($value, -1) === "'")
                )) {
                    $value = substr($value, 1, -1);
                }

                self::$values[$key] = $value;
            }
        }

        self::$booted = true;
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        if (array_key_exists($key, self::$values)) {
            return self::$values[$key];
        }

        $server = $_SERVER[$key] ?? getenv($key);

        if ($server !== false && $server !== null && $server !== '') {
            return (string) $server;
        }

        return $default;
    }
}
