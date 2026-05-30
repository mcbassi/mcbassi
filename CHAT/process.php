<?php
header('Content-Type: application/json; charset=utf-8');

$api_key = 'app-LLMcNnKvVP4x0LNvtI5qhnWS';
$api_url = 'http://192.168.1.15:8080/v1/chat-messages';

function out(array $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$raw = file_get_contents('php://input');
$input = json_decode($raw, true);

if (!is_array($input)) {
    out(['answer' => 'Requisição inválida.'], 400);
}

$query = trim((string)($input['query'] ?? ''));
if ($query === '') {
    out(['answer' => 'Mensagem vazia.'], 400);
}

$data = [
    'inputs' => new stdClass(),
    'query' => $query,
    'response_mode' => 'blocking',
    'user' => 'prof_marco',
    'conversation_id' => ''
];

$ch = curl_init($api_url);
curl_setopt_array($ch, [
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $api_key,
        'Content-Type: application/json'
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT => 120,
]);

$response = curl_exec($ch);
$curlError = curl_error($ch);
$httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response === false || $curlError) {
    out(['answer' => 'Falha de conexão com o assistente: ' . $curlError], 502);
}

$decoded = json_decode($response, true);

if (!is_array($decoded)) {
    out([
        'answer' => 'O assistente retornou uma resposta inválida.',
        'raw' => mb_substr($response, 0, 500)
    ], 502);
}

if ($httpCode < 200 || $httpCode >= 300) {
    $msg = (string)($decoded['message'] ?? $decoded['error'] ?? 'Erro na API do assistente.');
    out(['answer' => $msg], $httpCode);
}

$answer =
    (string)($decoded['answer'] ?? '') ?:
    (string)($decoded['message'] ?? '') ?:
    'O assistente não retornou texto.';

out(['answer' => $answer]);