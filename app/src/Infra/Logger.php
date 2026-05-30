<?php
declare(strict_types=1);

namespace App\Infra;

final class Logger
{
    public static function error(string $message): void
    {
        $dir = base_path('storage/logs');

        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $line = sprintf("[%s] ERROR %s\n", date('c'), $message);
        file_put_contents($dir . '/app.log', $line, FILE_APPEND);
    }
}
