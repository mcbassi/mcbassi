<?php
declare(strict_types=1);

function ppt_root_path(): string {
    return dirname(__DIR__);
}

function ppt_load_config(): array {
    $cfgFile = ppt_root_path() . DIRECTORY_SEPARATOR . 'config_ppt.php';
    if (!is_file($cfgFile)) {
        throw new RuntimeException('Arquivo config_ppt.php não encontrado. Copie config_ppt.example.php para config_ppt.php e ajuste os parâmetros.');
    }
    /** @var array $cfg */
    $cfg = require $cfgFile;
    if (!is_array($cfg)) {
        throw new RuntimeException('config_ppt.php deve retornar um array.');
    }
    return $cfg;
}

function ppt_json_out(array $data, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

function ppt_get_pdo(array $cfg): PDO {
    $db = $cfg['main_db'] ?? [];
    $dsn = (string)($db['dsn'] ?? '');
    $user = (string)($db['user'] ?? '');
    $pass = (string)($db['pass'] ?? '');

    if ($dsn === '') {
        throw new RuntimeException('main_db.dsn não configurado em config_ppt.php');
    }

    return new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

function ppt_generator_api_url(array $cfg, string $endpoint): string {
    $base = rtrim((string)($cfg['generator']['base_url'] ?? ''), '/');
    if ($base === '') {
        throw new RuntimeException('generator.base_url não configurado em config_ppt.php');
    }
    return $base . '/' . ltrim($endpoint, '/');
}

function ppt_call_generator(array $cfg, string $endpoint, array $payload): array {
    $url = ppt_generator_api_url($cfg, $endpoint);
    $token = trim((string)($cfg['generator']['bearer_token'] ?? ''));
    $timeout = (int)($cfg['generator']['timeout_seconds'] ?? 120);

    $ch = curl_init($url);
    $headers = ['Content-Type: application/json'];
    if ($token !== '') {
        $headers[] = 'Authorization: Bearer ' . $token;
    }

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        CURLOPT_TIMEOUT => $timeout,
    ]);

    $resp = curl_exec($ch);
    $err = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($resp === false) {
        throw new RuntimeException('Falha HTTP ao chamar o gerador: ' . $err);
    }

    $decoded = json_decode($resp, true);
    if ($code >= 400) {
        if (is_array($decoded)) {
            $msg = (string)($decoded['error'] ?? ('HTTP ' . $code));
            throw new RuntimeException($msg);
        }
        throw new RuntimeException('Erro HTTP ' . $code . ': ' . $resp);
    }

    if (!is_array($decoded)) {
        throw new RuntimeException('Resposta inválida do gerador: ' . $resp);
    }

    return $decoded;
}

function ppt_base64url_encode(string $value): string {
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function ppt_base64url_decode(string $value): string {
    $remainder = strlen($value) % 4;
    if ($remainder > 0) {
        $value .= str_repeat('=', 4 - $remainder);
    }
    $decoded = base64_decode(strtr($value, '-_', '+/'), true);
    if ($decoded === false) {
        throw new RuntimeException('Token de caminho inválido.');
    }
    return $decoded;
}

function ppt_normalize_path(string $path): string {
    $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
    return $path;
}

function ppt_path_allowed(array $cfg, string $path): bool {
    $root = (string)($cfg['generator']['output_root'] ?? '');
    if ($root === '') {
        return false;
    }
    $rootReal = realpath(ppt_normalize_path($root));
    $fileReal = realpath(ppt_normalize_path($path));
    if ($rootReal === false || $fileReal === false) {
        return false;
    }
    $rootReal = rtrim(str_replace('\\', '/', $rootReal), '/');
    $fileReal = str_replace('\\', '/', $fileReal);
    return str_starts_with($fileReal, $rootReal . '/') || $fileReal === $rootReal;
}

function ppt_build_download_url(string $path): string {
    $token = urlencode(ppt_base64url_encode($path));
    $scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
    $scriptDir = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
    if ($scriptDir === '' || $scriptDir === '.') {
        $scriptDir = '';
    }
    return $scriptDir . '/ppt_download_proxy.php?path=' . $token;
}

function ppt_available_presentations(array $cfg): array {
    $options = $cfg['presentation_options'] ?? [];
    if (!is_array($options) || $options === []) {
        return [
            ['name' => 'TESTE_DIAGNOSTICO', 'label' => 'Teste Diagnóstico'],
        ];
    }
    return array_values(array_filter($options, static fn($row) => is_array($row) && !empty($row['name'])));
}
