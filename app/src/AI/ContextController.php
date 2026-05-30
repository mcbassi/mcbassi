<?php
declare(strict_types=1);

namespace App\AI;

use App\Auth\AuthService;
use App\Diagnostico\VersionedResponseRepository;
use App\Infra\Database;
use App\Prompts\PromptRepository;
use App\Prompts\PromptRuntimeService;
use App\Support\Request;
use App\Support\View;
use RuntimeException;

final class ContextController
{
    private PromptRepository $promptRepository;
    private PromptRuntimeService $runtimeService;
    private AnaliticaExecutionService $executionService;

    public function __construct(
        private readonly AuthService $auth,
        private readonly Database $database,
        private readonly Request $request
    ) {
        $this->promptRepository = new PromptRepository($this->database);
        $this->runtimeService = new PromptRuntimeService($this->database, $this->promptRepository);
        $this->executionService = new AnaliticaExecutionService($this->database, $this->runtimeService);
    }

    public function show(string $context): void
    {
        $this->auth->requireAuth();

        $emailUser = trim($this->auth->user()->email);
        $versionsRepo = new VersionedResponseRepository($this->database->pdo());
        $versionsRepo->ensureSchema();
        $versions = $emailUser !== '' ? $versionsRepo->versions($emailUser) : [];

        $company = trim((string) (($this->request->method() === 'POST' ? $this->request->input('company') : $this->request->query('company', '')) ?? ''));
        $versionId = (int) (($this->request->method() === 'POST' ? $this->request->input('version') : $this->request->query('version', '0')) ?? 0);
        $onlyWithPrompt = (($this->request->method() === 'POST'
            ? $this->request->input('only_with_prompt', '0')
            : $this->request->query('only_with_prompt', '0')) ?? '0') === '1';

        if ($versionId <= 0 && $versions !== []) {
            $versionId = (int) ($versions[0]['id'] ?? 0);
            if ($company === '') {
                $company = trim((string) ($versions[0]['company_name'] ?? ''));
            }
        }

        $selectedVersion = $versionId > 0 ? $versionsRepo->versionById($versionId, $emailUser) : null;

        if ($context === 'analitica' && is_array($selectedVersion) && $this->hasSessionPromptSyncPending($selectedVersion, $emailUser)) {
            $this->syncSessionPrompts($selectedVersion, $emailUser);
        }

        $package = $this->runtimeService->buildContextPackage(
            $context,
            $emailUser,
            $company !== '' ? $company : null,
            $versionId > 0 ? $versionId : null,
            $onlyWithPrompt
        );

        $storedResponses = is_array($selectedVersion) ? $this->storedResponsesBySession($selectedVersion, $emailUser) : [];

        $execution = null;
        $executionError = null;

        if ($this->request->method() === 'POST' && $context === 'analitica') {
            if (!\check_csrf($this->request->input('_csrf'))) {
                $executionError = 'CSRF inválido.';
            } else {
                $action = trim((string) ($this->request->input('action') ?? ''));
                try {
                    if ($action === 'execute_all') {
                        $execution = $this->executionService->execute($package, $emailUser, null);
                    } elseif ($action === 'execute_one') {
                        $questionName = trim((string) ($this->request->input('question_name') ?? ''));
                        $execution = $this->executionService->execute($package, $emailUser, $questionName);
                    } elseif ($action === 'execute_selected') {
                        $selected = $_POST['question_names'] ?? [];
                        if (!is_array($selected) || $selected === []) {
                            throw new RuntimeException('Selecione ao menos um prompt para executar.');
                        }

                        $results = [];
                        $executed = 0;
                        $failed = 0;

                        foreach (array_values(array_unique(array_map('strval', $selected))) as $questionName) {
                            $partial = $this->executionService->execute($package, $emailUser, $questionName);
                            foreach ((array) ($partial['results'] ?? []) as $result) {
                                if (!is_array($result)) {
                                    continue;
                                }
                                $results[] = $result;
                                if (!empty($result['ok'])) {
                                    $executed++;
                                } else {
                                    $failed++;
                                }
                            }
                        }

                        $execution = [
                            'results' => $results,
                            'summary' => [
                                'executed' => $executed,
                                'failed' => $failed,
                                'total' => count($results),
                            ],
                        ];
                    }
                } catch (RuntimeException $exception) {
                    $executionError = $exception->getMessage();
                }
            }
        }

        $label = $context === 'estrategica' ? 'IA Estratégica' : 'IA Analítica';
        $version = is_array($package['selectedVersion'] ?? null) ? $package['selectedVersion'] : null;
        $promptIndexHref = \url('prompts/index.php?context=' . rawurlencode($context));
        $newPromptHref = \url('prompts/form.php?context=' . rawurlencode($context));
        if ($version !== null) {
            $companyPart = trim((string) ($version['company_name'] ?? ''));
            $versionPart = (int) ($version['id'] ?? 0);
            if ($companyPart !== '') {
                $promptIndexHref .= '&company=' . rawurlencode($companyPart);
                $newPromptHref .= '&company=' . rawurlencode($companyPart);
            }
            if ($versionPart > 0) {
                $promptIndexHref .= '&version=' . $versionPart;
                $newPromptHref .= '&version=' . $versionPart;
            }
        }

        View::render('ai/context', [
            'auth' => $this->auth,
            'user' => $this->auth->user(),
            'context' => $context,
            'label' => $label,
            'package' => $package,
            'versions' => $versions,
            'selectedVersionId' => $versionId,
            'onlyWithPrompt' => $onlyWithPrompt,
            'pageTitle' => $label,
            'contentTitle' => $label,
            'subtitle' => 'ProdCol',
            'promptIndexHref' => $promptIndexHref,
            'newPromptHref' => $newPromptHref,
            'execution' => $execution,
            'executionError' => $executionError,
            'storedResponses' => $storedResponses,
        ]);
    }

    /**
     * @param array<string, mixed> $selectedVersion
     * @return array<string, array<string, mixed>>
     */
    private function storedResponsesBySession(array $selectedVersion, string $emailUser): array
    {
        $pdo = $this->database->pdo();
        $sessionId = (int) ($selectedVersion['id'] ?? 0);
        $questionarioEmail = trim((string) ($selectedVersion['email_resp'] ?? ''));
        $companyName = trim((string) ($selectedVersion['company_name'] ?? ''));
        $responseDateTime = trim((string) ($selectedVersion['response_datetime'] ?? ''));
        $sessionMinute = $responseDateTime !== '' ? substr($responseDateTime, 0, 16) : '';

        $params = [];
        $where = ['`prompt_response` IS NOT NULL', 'TRIM(`prompt_response`) <> ""'];
        $scopeParts = [];

        if ($sessionId > 0 && $this->columnExists('responses_detailed', 'response_session_id')) {
            $scopeParts[] = '`response_session_id` = :response_session_id';
            $params[':response_session_id'] = $sessionId;
        }

        if ($sessionMinute !== '') {
            $minuteConditions = ["DATE_FORMAT(`response_datetime`, '%Y-%m-%d %H:%i') = :sess_min"];
            $params[':sess_min'] = $sessionMinute;

            if ($this->columnExists('responses_detailed', 'email_user') && $emailUser !== '') {
                $minuteConditions[] = '`email_user` = :email_user';
                $params[':email_user'] = $emailUser;
            }
            if ($this->columnExists('responses_detailed', 'email_resp') && $questionarioEmail !== '') {
                $minuteConditions[] = '`email_resp` = :email_resp';
                $params[':email_resp'] = $questionarioEmail;
            }
            if ($this->columnExists('responses_detailed', 'company_name') && $companyName !== '') {
                $minuteConditions[] = '`company_name` = :company_name';
                $params[':company_name'] = $companyName;
            }

            $scopeParts[] = '(' . implode(' AND ', $minuteConditions) . ')';
        }

        if ($scopeParts === []) {
            return [];
        }

        $where[] = '(' . implode(' OR ', $scopeParts) . ')';

        $sql = 'SELECT `question_name`, `prompt_response`, `response_datetime`, `prompt_executed_at`, `prompt_code`, `prompt`
'
             . 'FROM `responses_detailed`
'
             . 'WHERE ' . implode(' AND ', $where) . '
'
             . 'ORDER BY COALESCE(`prompt_executed_at`, `response_datetime`) DESC, `id` DESC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        $mapped = [];
        foreach ($rows as $row) {
            $questionName = trim((string) ($row['question_name'] ?? ''));
            if ($questionName === '' || isset($mapped[$questionName])) {
                continue;
            }
            $mapped[$questionName] = [
                'question_name' => $questionName,
                'response_text' => trim((string) ($row['prompt_response'] ?? '')),
                'response_datetime' => trim((string) ($row['response_datetime'] ?? '')),
                'prompt_executed_at' => trim((string) ($row['prompt_executed_at'] ?? '')),
                'prompt_code' => trim((string) ($row['prompt_code'] ?? '')),
                'prompt' => (string) ($row['prompt'] ?? ''),
                'source' => 'stored',
                'ok' => true,
            ];
        }

        return $mapped;
    }


    /**
     * @param array<string, mixed> $selectedVersion
     */
    private function syncSessionPrompts(array $selectedVersion, string $emailUser): void
    {
        $pdo = $this->database->pdo();
        $isLegacy = !empty($selectedVersion['is_legacy']) || strtolower(trim((string) ($selectedVersion['status'] ?? ''))) === 'legacy';
        $sessionId = (int) ($selectedVersion['id'] ?? 0);
        $responseDateTime = trim((string) ($selectedVersion['response_datetime'] ?? ''));

        if ($this->columnExists('responses_detailed', 'prompt_code')) {
            if (!$isLegacy && $sessionId > 0) {
                $sql = "UPDATE responses_detailed rd
                        JOIN form_fields ff ON TRIM(ff.name) = TRIM(rd.question_name)
                        SET rd.prompt_code = ff.prompt_code
                        WHERE rd.response_session_id = :response_session_id
                          AND TRIM(COALESCE(ff.prompt_code, '')) <> ''
                          AND TRIM(COALESCE(rd.prompt_code, '')) = ''";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':response_session_id' => $sessionId]);
            } elseif ($responseDateTime !== '') {
                $sql = "UPDATE responses_detailed rd
                        JOIN form_fields ff ON TRIM(ff.name) = TRIM(rd.question_name)
                        SET rd.prompt_code = ff.prompt_code
                        WHERE rd.email_user = :email_user
                          AND DATE_FORMAT(rd.response_datetime, '%Y-%m-%d %H:%i') = :response_datetime
                          AND TRIM(COALESCE(ff.prompt_code, '')) <> ''
                          AND TRIM(COALESCE(rd.prompt_code, '')) = ''";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':email_user' => $emailUser,
                    ':response_datetime' => $responseDateTime,
                ]);
            }
        }

        if ($this->columnExists('responses_detailed', 'prompt') && $this->columnExists('responses_detailed', 'prompt_synced_at')) {
            if (!$isLegacy && $sessionId > 0) {
                $sql = "UPDATE responses_detailed rd
                        JOIN prompts p ON TRIM(p.assistente) = TRIM(rd.prompt_code)
                        SET rd.prompt = p.prompt,
                            rd.prompt_synced_at = NOW()
                        WHERE rd.response_session_id = :response_session_id
                          AND TRIM(COALESCE(rd.prompt_code, '')) <> ''
                          AND (
                                rd.prompt IS NULL
                                OR TRIM(rd.prompt) = ''
                                OR rd.prompt <> p.prompt
                                OR rd.prompt_synced_at IS NULL
                          )";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':response_session_id' => $sessionId]);
            } elseif ($responseDateTime !== '') {
                $sql = "UPDATE responses_detailed rd
                        JOIN prompts p ON TRIM(p.assistente) = TRIM(rd.prompt_code)
                        SET rd.prompt = p.prompt,
                            rd.prompt_synced_at = NOW()
                        WHERE rd.email_user = :email_user
                          AND DATE_FORMAT(rd.response_datetime, '%Y-%m-%d %H:%i') = :response_datetime
                          AND TRIM(COALESCE(rd.prompt_code, '')) <> ''
                          AND (
                                rd.prompt IS NULL
                                OR TRIM(rd.prompt) = ''
                                OR rd.prompt <> p.prompt
                                OR rd.prompt_synced_at IS NULL
                          )";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':email_user' => $emailUser,
                    ':response_datetime' => $responseDateTime,
                ]);
            }
        }
    }

    /**
     * @param array<string, mixed> $selectedVersion
     */
    private function hasSessionPromptSyncPending(array $selectedVersion, string $emailUser): bool
    {
        if (!$this->columnExists('responses_detailed', 'prompt_code')) {
            return false;
        }

        $scope = $this->sessionScopeWhere($selectedVersion, $emailUser, 'rd');
        if ($scope === null) {
            return false;
        }

        [$whereSql, $params] = $scope;
        $pdo = $this->database->pdo();

        $sql = "SELECT 1
                FROM responses_detailed rd
                JOIN form_fields ff ON TRIM(ff.name) = TRIM(rd.question_name)
                WHERE {$whereSql}
                  AND TRIM(COALESCE(ff.prompt_code, '')) <> ''
                  AND TRIM(COALESCE(rd.prompt_code, '')) = ''
                LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        if ($stmt->fetchColumn() !== false) {
            return true;
        }

        if (!$this->columnExists('responses_detailed', 'prompt') || !$this->columnExists('responses_detailed', 'prompt_synced_at')) {
            return false;
        }

        $sql = "SELECT 1
                FROM responses_detailed rd
                JOIN prompts p ON TRIM(p.assistente) = TRIM(rd.prompt_code)
                WHERE {$whereSql}
                  AND TRIM(COALESCE(rd.prompt_code, '')) <> ''
                  AND (
                        rd.prompt IS NULL
                        OR TRIM(rd.prompt) = ''
                        OR rd.prompt_synced_at IS NULL
                  )
                LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchColumn() !== false;
    }

    /**
     * @param array<string, mixed> $selectedVersion
     * @return array{0:string,1:array<string, mixed>}|null
     */
    private function sessionScopeWhere(array $selectedVersion, string $emailUser, string $alias): ?array
    {
        $isLegacy = !empty($selectedVersion['is_legacy']) || strtolower(trim((string) ($selectedVersion['status'] ?? ''))) === 'legacy';
        $sessionId = (int) ($selectedVersion['id'] ?? 0);
        $responseDateTime = trim((string) ($selectedVersion['response_datetime'] ?? ''));
        $params = [];

        if (!$isLegacy && $sessionId > 0 && $this->columnExists('responses_detailed', 'response_session_id')) {
            return [$alias . '.response_session_id = :response_session_id', [':response_session_id' => $sessionId]];
        }

        if ($responseDateTime === '' || $emailUser === '') {
            return null;
        }

        $params[':email_user'] = $emailUser;
        $params[':response_datetime'] = $responseDateTime;

        return [
            $alias . ".email_user = :email_user AND DATE_FORMAT({$alias}.response_datetime, '%Y-%m-%d %H:%i') = :response_datetime",
            $params,
        ];
    }

    private function columnExists(string $table, string $column): bool
    {
        $stmt = $this->database->pdo()->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name'
        );
        $stmt->execute([
            ':table_name' => $table,
            ':column_name' => $column,
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }

}
