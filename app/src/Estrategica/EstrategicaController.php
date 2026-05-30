<?php
declare(strict_types=1);

namespace App\Estrategica;

use App\Auth\AuthService;
use App\Infra\Database;
use App\Support\Request;
use App\Support\View;
use RuntimeException;

final class EstrategicaController
{
    private EstrategicaService $service;

    public function __construct(
        private readonly AuthService $auth,
        private readonly Database $database,
        private readonly Request $request
    ) {
        $this->service = new EstrategicaService($database);
    }

    public function show(): void
    {
        $this->auth->requireAuth();

        $action = trim((string) ($this->request->query('action', '') ?? ''));
        if ($action === 'download_resumo_doc') {
            $token = trim((string) ($this->request->query('csrf_token', '') ?? ''));
            if (!\check_csrf($token)) {
                $this->renderDownloadError(403, 'CSRF inválido', 'Recarregue a página e tente novamente.');
            }

            try {
                $groupB64 = trim((string) ($this->request->query('question_group_b64', '') ?? ''));
                $priorityGroupName = trim((string) ($this->request->query('priority_group_name', '') ?? ''));
                $download = $this->service->downloadResumoDoc($groupB64, $priorityGroupName);
                if (!is_file($download['path'])) {
                    throw new RuntimeException('Arquivo não encontrado no servidor.');
                }
                header('Content-Type: application/msword');
                header('Content-Disposition: attachment; filename="' . str_replace('"', '', (string) $download['filename']) . '"');
                header('Content-Length: ' . (string) filesize((string) $download['path']));
                header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
                header('Pragma: no-cache');
                readfile((string) $download['path']);
                exit;
            } catch (RuntimeException $exception) {
                $this->renderDownloadError(404, 'Arquivo não encontrado', $exception->getMessage());
            }
        }

        View::render('estrategica/index', [
            'auth' => $this->auth,
            'user' => $this->auth->user(),
            'pageTitle' => \t('menu.ai_strategic'),
            'contentTitle' => \t('menu.ai_strategic'),
            'subtitle' => 'ProdCol',
            'groupOptions' => $this->service->fetchQuestionGroups(),
            'apiUrl' => \url('estrategica/api.php'),
            'statusUrl' => \url('api/status_questionario.php'),
            'listUrl' => \url('grupos/estrategicas_list.php'),
            'pageUrl' => \url('estrategica/index.php'),
            'assetsBaseUrl' => rtrim(\asset('assets/img'), '/'),
        ]);
    }

    public function api(): never
    {
        $this->auth->requireAuth();

        try {
            if ($_SERVER['REQUEST_METHOD'] === 'GET' && trim((string) ($_GET['action'] ?? '')) === 'download_status') {
                $type = trim((string) ($_GET['type'] ?? ''));
                $groupB64 = trim((string) ($_GET['question_group_b64'] ?? ''));
                if (!\check_csrf(trim((string) ($_GET['csrf_token'] ?? '')))) {
                    self::jsonOut(['ok' => false, 'error' => 'CSRF inválido.'], 403);
                }
                $download = $this->service->downloadStatus($type, $groupB64);
                header('X-Content-Type-Options: nosniff');
                header('Content-Type: ' . (string) $download['mime']);
                header('Content-Disposition: attachment; filename="' . str_replace('"', '', (string) $download['filename']) . '"');
                header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
                header('Pragma: no-cache');
                if (!empty($download['path']) && is_file((string) $download['path'])) {
                    readfile((string) $download['path']);
                    exit;
                }
                echo (string) ($download['blob'] ?? '');
                exit;
            }

            $raw = (string) file_get_contents('php://input');
            $body = json_decode($raw, true);
            if (!is_array($body)) {
                $body = [];
            }
            $action = trim((string) ($body['action'] ?? $_POST['action'] ?? ''));
            if ($action === '') {
                throw new RuntimeException('Ação inválida.');
            }

            if (!\check_csrf((string) ($body['csrf_token'] ?? ''))) {
                self::jsonOut(['ok' => false, 'error' => 'CSRF inválido.'], 403);
            }

            switch ($action) {
                case 'exec_priority_group':
                    $result = $this->service->executePriorityGroup(
                        trim((string) ($body['question_group_b64'] ?? '')),
                        trim((string) ($body['priority_group_id'] ?? '')),
                        trim((string) ($body['priority_group_name'] ?? ''))
                    );
                    self::jsonOut(['ok' => true] + $result);
                    break;

                case 'create_doc_final_consultoria':
                    $result = $this->service->createDocFinalConsultoria(
                        trim((string) ($body['question_group_b64'] ?? ''))
                    );
                    self::jsonOut(['ok' => true] + $result);
                    break;

                case 'create_ppt_final_diagnostico':
                    $result = $this->service->createPptFinalDiagnostico(
                        trim((string) ($body['question_group_b64'] ?? ''))
                    );
                    self::jsonOut(['ok' => true] + $result);
                    break;

                default:
                    throw new RuntimeException('Ação inválida.');
            }
        } catch (RuntimeException $exception) {
            self::jsonOut(['ok' => false, 'error' => $exception->getMessage()], 400);
        } catch (\Throwable $exception) {
            self::jsonOut(['ok' => false, 'error' => 'EXCEPTION: ' . $exception->getMessage()], 500);
        }
    }

    public function finalReport(): void
    {
        $this->auth->requireAuth();
        View::render('estrategica/final_report', [
            'auth' => $this->auth,
            'user' => $this->auth->user(),
            'pageTitle' => \t('strategic.final_report_title'),
            'contentTitle' => \t('strategic.final_report_title'),
            'subtitle' => 'ProdCol',
        ]);
    }

    private static function jsonOut(array $payload, int $status = 200): never
    {
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    private function renderDownloadError(int $code, string $title, string $message): never
    {
        http_response_code($code);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><html lang="pt-br"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
        echo '<title>' . h($title) . '</title>';
        echo '<style>body{font-family:system-ui,-apple-system,BlinkMacSystemFont,Segoe UI,sans-serif;background:#f8fafc;padding:24px} .box{max-width:820px;margin:0 auto;background:#fff;border:1px solid #e5e7eb;border-radius:16px;padding:22px;box-shadow:0 8px 24px rgba(15,23,42,.06)} .btn{display:inline-block;padding:10px 16px;border-radius:999px;background:#334155;color:#fff;text-decoration:none}</style>';
        echo '</head><body><div class="box"><h2>' . h($title) . '</h2><p>' . h($message) . '</p><a class="btn" href="javascript:history.back()">Voltar</a></div></body></html>';
        exit;
    }
}
