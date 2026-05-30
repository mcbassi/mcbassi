<?php
declare(strict_types=1);

namespace App\Estrategica;

use App\Infra\Database;

final class StatusService
{
    private EstrategicaService $service;

    public function __construct(Database $database)
    {
        $this->service = new EstrategicaService($database);
    }

    public function handle(): never
    {
        $raw = (string) file_get_contents('php://input');
        $body = json_decode($raw, true);
        if (!is_array($body)) {
            $body = [];
        }
        $request = array_merge($_GET, $_POST, $body);
        $action = trim((string) ($request['action'] ?? ''));

        if ($action === 'get_status') {
            $groupB64 = trim((string) ($request['question_group_b64'] ?? ''));
            if ($groupB64 === '') {
                $user = trim((string) ($request['user'] ?? ''));
                $email = trim((string) ($request['email_user'] ?? ''));
                $dt = trim((string) ($request['response_datetime'] ?? ''));
                if ($user !== '' && $email !== '' && $dt !== '') {
                    $groupB64 = base64_encode(json_encode(['c' => $user, 'e' => $email, 'k' => $dt], JSON_UNESCAPED_UNICODE));
                }
            }
            try {
                $status = $this->service->getStatusFromGroupB64($groupB64);
                $this->json(['ok' => true, 'status' => $status]);
            } catch (\RuntimeException $exception) {
                $this->json(['ok' => false, 'error' => $exception->getMessage()], 400);
            }
        }

        if ($action === 'download_resumo') {
            $groupB64 = trim((string) ($request['question_group_b64'] ?? ''));
            try {
                $download = $this->service->downloadStatus('resumo', $groupB64);
                header('Content-Type: ' . (string) $download['mime']);
                header('Content-Disposition: attachment; filename="' . str_replace('"', '', (string) $download['filename']) . '"');
                if (!empty($download['path']) && is_file((string) $download['path'])) {
                    readfile((string) $download['path']);
                    exit;
                }
                echo (string) ($download['blob'] ?? '');
                exit;
            } catch (\RuntimeException $exception) {
                http_response_code(404);
                echo 'Resumo não disponível';
                exit;
            }
        }

        $this->json(['ok' => false, 'error' => 'Ação inválida.'], 400);
    }

    private function json(array $payload, int $status = 200): never
    {
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
