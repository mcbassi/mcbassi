<?php
declare(strict_types=1);

namespace App\Agents;

use App\Auth\AuthService;
use App\Support\Request;
use App\Support\View;
use RuntimeException;

final class AgentConfigController
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly AgentConfigService $service,
        private readonly Request $request,
    ) {
    }

    public function index(): void
    {
        $this->auth->requireAuth();

        try {
            $config = $this->service->readConfig();
            View::render('agentes/index', [
                'config' => $config,
                'sections' => $this->service->sections(),
                'configPath' => $this->service->configPath(),
                'pageTitle' => 'Configurar Agentes',
                'contentTitle' => 'Configurar Agentes',
                'subtitle' => 'ProdCol',
            ]);
        } catch (RuntimeException $exception) {
            View::render('agentes/index', [
                'config' => [],
                'sections' => [],
                'configPath' => $this->service->configPath(),
                'error' => $exception->getMessage(),
                'pageTitle' => 'Configurar Agentes',
                'contentTitle' => 'Configurar Agentes',
                'subtitle' => 'ProdCol',
            ]);
        }
    }

    public function save(): void
    {
        $this->auth->requireAuth();

        $raw = file_get_contents('php://input');
        $data = json_decode((string) $raw, true);
        if (!is_array($data)) {
            $this->jsonOut(['success' => false, 'error' => 'JSON inválido.'], 400);
        }

        $token = (string) ($data['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
        if (!\check_csrf($token)) {
            $this->jsonOut(['success' => false, 'error' => 'CSRF inválido.'], 403);
        }

        $section = trim((string) ($data['section'] ?? ''));
        $fields = $data['fields'] ?? null;
        if ($section === '' || !is_array($fields)) {
            $this->jsonOut(['success' => false, 'error' => 'Dados inválidos.'], 400);
        }

        try {
            $this->service->saveSection($section, $fields);
            $this->jsonOut([
                'success' => true,
                'message' => "Configuração da seção '{$section}' salva com sucesso.",
            ]);
        } catch (RuntimeException $exception) {
            $this->jsonOut(['success' => false, 'error' => $exception->getMessage()], 400);
        }
    }

    public function start(): void
    {
        $this->auth->requireAuth();
        $token = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if (!\check_csrf($token)) {
            $this->jsonOut(['success' => false, 'error' => 'CSRF inválido.'], 403);
        }

        $this->jsonOut([
            'success' => false,
            'error' => 'START AGENT ainda não configurado nesta versão.',
        ], 501);
    }

    private function jsonOut(array $payload, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
