<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/lib/common.php';

try {
    $cfg = ppt_load_config();
    $token = trim((string)($_GET['path'] ?? ''));
    if ($token === '') {
        throw new RuntimeException('Parâmetro path ausente.');
    }

    $path = ppt_base64url_decode($token);
    if (!ppt_path_allowed($cfg, $path)) {
        throw new RuntimeException('Arquivo fora da área permitida.');
    }
    if (!is_file($path)) {
        throw new RuntimeException('Arquivo não encontrado.');
    }

    $ext = strtolower((string)pathinfo($path, PATHINFO_EXTENSION));
    $mimeMap = [
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'json' => 'application/json; charset=utf-8',
        'txt'  => 'text/plain; charset=utf-8',
        'pdf'  => 'application/pdf',
    ];
    $mime = $mimeMap[$ext] ?? 'application/octet-stream';

    header('Content-Type: ' . $mime);
    header('Content-Length: ' . (string)filesize($path));
    header('Content-Disposition: attachment; filename="' . basename($path) . '"');
    readfile($path);
    exit;
} catch (Throwable $e) {
    ppt_json_out([
        'ok' => false,
        'error' => $e->getMessage(),
    ], 400);
}
