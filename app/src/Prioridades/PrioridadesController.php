<?php
declare(strict_types=1);

namespace App\Prioridades;

use App\Auth\AuthService;
use App\Infra\Database;
use App\Support\Request;
use App\Support\View;
use RuntimeException;
use Throwable;

final class PrioridadesController
{
    private PrioridadesService $service;

    public function __construct(
        private readonly AuthService $auth,
        private readonly Database $database,
        private readonly Request $request
    ) {
        $this->service = new PrioridadesService($database);
    }

    public function show(): void
    {
        $this->auth->requireAuth();

        $emailUser = trim($this->auth->user()->email);
        $selectedVersionId = (int) (($this->request->query('version', '0') ?? '0'));
        $page = $this->service->buildPageData($emailUser, $selectedVersionId);

        View::render('prioridades/index', [
            'auth' => $this->auth,
            'user' => $this->auth->user(),
            'pageTitle' => 'IA Prioridades',
            'contentTitle' => 'IA Prioridades',
            'subtitle' => 'ProdCol',
            'versions' => $page['versions'],
            'selectedVersion' => $page['selectedVersion'],
            'groups' => $page['groups'],
            'storedResults' => $page['storedResults'],
            'apiUrl' => \url('prioridades/api.php'),
        ]);
    }

    public function api(): void
    {
        $this->auth->requireAuth();

        if ($this->request->method() !== 'POST') {
            $this->json(['ok' => false, 'error' => 'Método inválido.'], 405);
        }

        $payload = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($payload)) {
            $payload = [];
        }

        $csrf = trim((string) ($payload['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')));
        if (!\check_csrf($csrf)) {
            $this->json(['ok' => false, 'error' => 'CSRF inválido.'], 403);
        }

        $action = trim((string) ($payload['action'] ?? ''));

        try {
            if ($action === 'list_responses') {
                $versionId = (int) ($payload['version_id'] ?? 0);
                $groupId = (int) ($payload['priority_group_id'] ?? 0);
                $onlyWithAi = !empty($payload['only_with_ai_response']);
                $result = $this->service->listResponses($versionId, $groupId, $onlyWithAi);
                $this->json(['ok' => true] + $result);
            }

            if ($action === 'exec_priority_group') {
                $versionId = (int) ($payload['version_id'] ?? 0);
                $groupId = (int) ($payload['priority_group_id'] ?? 0);
                $answers = $payload['answers_override'] ?? [];
                if (!is_array($answers)) {
                    $answers = [];
                }
                $debug = !empty($payload['debug']);
                $result = $this->service->executePriorityGroup($versionId, $groupId, $answers, $debug);
                $this->json(['ok' => true] + $result);
            }

            if ($action === 'save_diag_priority') {
                $groupId = (int) ($payload['priority_group_id'] ?? 0);
                $questionnaireIdx = trim((string) ($payload['questionnaire_idx'] ?? ''));
                $resultJson = $payload['result_json'] ?? null;
                if (is_string($resultJson)) {
                    $decoded = json_decode($resultJson, true);
                    $resultJson = is_array($decoded) ? $decoded : [];
                }
                if (!is_array($resultJson)) {
                    throw new RuntimeException('JSON inválido para salvar.');
                }
                $id = $this->service->saveDiagPriority($groupId, $questionnaireIdx, $resultJson);
                $this->json(['ok' => true, 'id' => $id]);
            }

            if ($action === 'final_report') {
                $versionId = (int) ($payload['version_id'] ?? 0);
                $groupId = (int) ($payload['priority_group_id'] ?? 0);
                $currentPriorities = $payload['current_priorities'] ?? [];
                if (!is_array($currentPriorities)) {
                    $currentPriorities = [];
                }
                $result = $this->service->generateFinalReport($versionId, $groupId, $currentPriorities);
                $this->json(['ok' => true] + $result);
            }

            $this->json(['ok' => false, 'error' => 'Ação inválida.'], 400);
        } catch (Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function index(Request $request): string
    {
        return View::make('prioridades/index', ['title' => 'Prioridades']);
    }

    public function groupReport(Request $request): string
    {
        return View::make('prioridades/group_report', ['title' => 'Prioridades - Relatório por Grupo']);
    }

    private function json(array $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('X-Content-Type-Options: nosniff');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
