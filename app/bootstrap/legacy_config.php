<?php
declare(strict_types=1);

$projectRoot = dirname(__DIR__, 2);
$candidates = [
    $projectRoot . '/papers/config.php',
    $projectRoot . '/config.php',
];

$assignments = [
    'DB_DRIVER' => 'mysql',
    'DB_HOST' => '127.0.0.1',
    'DB_PORT' => '3306',
    'DB_NAME' => 'form_app',
    'DB_USER' => 'root',
    'DB_PASS' => '',
    'DB_CHARSET' => 'utf8mb4',
    'STAT_DB_DRIVER' => 'mysql',
    'STAT_DB_HOST' => '127.0.0.1',
    'STAT_DB_PORT' => '3306',
    'STAT_DB_NAME' => 'statistics',
    'STAT_DB_USER' => 'root',
    'STAT_DB_PASS' => '',
    'STAT_DB_CHARSET' => 'utf8mb4',
];

foreach ($candidates as $candidate) {
    if (!is_file($candidate) || !is_readable($candidate)) {
        continue;
    }

    $source = (string) file_get_contents($candidate);

    foreach ([
        'DB_HOST' => '$DB_HOST',
        'DB_NAME' => '$DB_NAME',
        'DB_USER' => '$DB_USER',
        'DB_PASS' => '$DB_PASS',
    ] as $envKey => $phpVar) {
        if ((($_SERVER[$envKey] ?? getenv($envKey)) !== false) && (string) ($_SERVER[$envKey] ?? getenv($envKey)) !== '') {
            continue;
        }

        $pattern = '/' . preg_quote($phpVar, '/') . "\s*=\s*([\"\'])(.*?)\\1\s*;/";
        if (preg_match($pattern, $source, $matches) === 1) {
            $_SERVER[$envKey] = (string) $matches[2];
        }
    }
}

foreach ($assignments as $key => $value) {
    $current = $_SERVER[$key] ?? getenv($key);
    if ($current === false || $current === null || $current === '') {
        $_SERVER[$key] = $value;
    }
}

if (empty($_SERVER['STAT_DB_HOST'])) {
    $_SERVER['STAT_DB_HOST'] = (string) ($_SERVER['DB_HOST'] ?? '127.0.0.1');
}
if (empty($_SERVER['STAT_DB_PORT'])) {
    $_SERVER['STAT_DB_PORT'] = (string) ($_SERVER['DB_PORT'] ?? '3306');
}
if (empty($_SERVER['STAT_DB_USER'])) {
    $_SERVER['STAT_DB_USER'] = (string) ($_SERVER['DB_USER'] ?? 'root');
}
if (!isset($_SERVER['STAT_DB_PASS']) || $_SERVER['STAT_DB_PASS'] === '') {
    $_SERVER['STAT_DB_PASS'] = (string) ($_SERVER['DB_PASS'] ?? '');
}
if (empty($_SERVER['STAT_DB_CHARSET'])) {
    $_SERVER['STAT_DB_CHARSET'] = (string) ($_SERVER['DB_CHARSET'] ?? 'utf8mb4');
}
if (empty($_SERVER['STAT_DB_NAME'])) {
    $_SERVER['STAT_DB_NAME'] = 'statistics';
}


if (empty($_SERVER['OPENAI_API_KEY'])) {
    foreach ([$projectRoot . '/config.cfg', $projectRoot . '/Config.cfg'] as $cfgPath) {
        if (!is_file($cfgPath) || !is_readable($cfgPath)) {
            continue;
        }
        $lines = @file($cfgPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        foreach ($lines as $line) {
            if (preg_match('/^\s*OPENAI_API_KEY\s*=\s*(.+)\s*$/', (string) $line, $matches) === 1) {
                $_SERVER['OPENAI_API_KEY'] = trim((string) ($matches[1] ?? ''), " \t\n\r\0\x0B\"'");
                break 2;
            }
        }
    }
}
