<?php
declare(strict_types=1);

namespace App\Diagnostico;

use App\Auth\AuthService;
use App\Infra\Database;
use App\Support\Request;
use App\Support\View;
use RuntimeException;

final class ClientQuestionarioController
{
    private FormFieldRepository $fieldRepository;
    private VersionedResponseRepository $versionRepository;

    public function __construct(
        private readonly AuthService $auth,
        private readonly Database $database,
        private readonly Request $request
    ) {
        $pdo = $this->database->pdo();
        $this->fieldRepository = new FormFieldRepository($pdo);
        $this->versionRepository = new VersionedResponseRepository($pdo);
    }

    public function index(): void
    {
        $this->auth->requireAuth();
        $this->versionRepository->ensureSchema();

        if ($this->request->method() === 'POST') {
            $this->handleSave();
            return;
        }

        $this->renderForm();
    }

    private function handleSave(): void
    {
        if (!\check_csrf($this->request->input('_csrf'))) {
            http_response_code(422);
            echo 'CSRF inválido.';
            return;
        }

        $fields = $this->fieldRepository->all();
        $answers = $this->collectAnswers($fields);
        $saveMode = strtolower(trim((string) ($this->request->input('save_mode', 'draft') ?? 'draft')));
        $status = $saveMode === 'complete' ? 'complete' : 'draft';

        $emailUser = trim($this->auth->user()->email);
        $userName = trim($this->auth->user()->name);
        $emailResp = $emailUser;
        $sourceSessionId = (int) ($this->request->input('source_session_id', '0') ?? 0);
        $companyName = trim((string) ($this->request->input('company_name', '') ?: ($_SESSION['company_name'] ?? $_SESSION['empresa'] ?? '')));
        if ($companyName === '') {
            $companyName = 'Sem empresa';
        }

        try {
            $sessionId = $this->versionRepository->saveVersion(
                $userName,
                $emailUser,
                $emailResp,
                $companyName,
                $fields,
                $answers,
                $status,
                $sourceSessionId > 0 ? $sourceSessionId : null
            );

            if ($status === 'complete') {
                flash_set('diagnostico_notice', 'Questionário salvo como versão completa.');
            } else {
                flash_set('diagnostico_notice', 'Questionário salvo parcialmente. Você pode continuar depois da última versão.');
            }

            $target = 'diagnostico/respond.php?version=' . rawurlencode((string) $sessionId) . '&company=' . rawurlencode($companyName);
            if ($this->request->input('embed', '0') === '1') {
                $target .= '&embed=1';
            }
            redirect(url($target));
        } catch (RuntimeException $exception) {
            $viewData = $this->buildViewData(
                $fields,
                $answers,
                $this->versionRepository->versions($emailUser),
                $this->versionRepository->latestVersion($emailUser),
                $this->request->input('version') !== null ? (int) $this->request->input('version', '0') : 0,
                null,
                $exception->getMessage(),
                $this->requiredValidationErrors($fields, $answers)
            );
            View::render('diagnostico/respond', $viewData);
        }
    }

    private function renderForm(): void
    {
        $fields = $this->fieldRepository->all();
        $emailUser = trim($this->auth->user()->email);
        $selectedId = (int) ($this->request->query('version', '0') ?? 0);
        $forceNew = $this->request->query('new', '0') === '1';
        $continueLatest = $this->request->query('continue', '0') === '1';
        $companyFilter = trim((string) ($this->request->query('company', '') ?? ''));
        $versions = $emailUser !== '' ? $this->versionRepository->versions($emailUser) : [];
        $latestVersion = $versions[0] ?? null;
        $companyVersions = $companyFilter !== ''
            ? $this->filterVersionsByCompany($versions, $companyFilter)
            : [];
        $latestForCompany = $companyVersions[0] ?? null;

        $defaultVersion = $latestForCompany ?? $latestVersion;
        $latestIncomplete = is_array($defaultVersion) && $this->isIncompleteVersion($defaultVersion)
            ? $defaultVersion
            : null;

        if ($forceNew) {
            $selectedVersion = null;
        } elseif ($selectedId > 0 && $emailUser !== '') {
            $selectedVersion = $this->versionRepository->versionById($selectedId, $emailUser);
        } elseif ($continueLatest && $latestIncomplete !== null) {
            $selectedVersion = $latestIncomplete;
        } elseif ($latestIncomplete !== null) {
            $selectedVersion = $latestIncomplete;
        } else {
            $selectedVersion = $defaultVersion;
        }

        $answers = [];
        if (is_array($selectedVersion)) {
            $answers = $this->versionRepository->answersForVersion(
                (int) ($selectedVersion['id'] ?? 0),
                $emailUser,
                (string) ($selectedVersion['response_datetime'] ?? '')
            );
        }

        $notice = flash_get('diagnostico_notice');
        $error = flash_get('diagnostico_error');

        $viewData = $this->buildViewData(
            $fields,
            $answers,
            $versions,
            $latestVersion,
            (int) ($selectedVersion['id'] ?? 0),
            $notice,
            $error,
            $this->requiredValidationErrors($fields, $answers)
        );

        $viewData['companyContext'] = trim((string) ($selectedVersion['company_name'] ?? $companyFilter));
        $viewData['latestIncomplete'] = $latestIncomplete;
        $viewData['selectedVersion'] = $selectedVersion;
        $viewData['clientMode'] = true;

        View::render('diagnostico/respond', $viewData);
    }

    /**
     * @param array<int, array<string, mixed>> $versions
     * @return array<int, array<string, mixed>>
     */
    private function filterVersionsByCompany(array $versions, string $company): array
    {
        $normalized = mb_strtolower(trim($company));

        return array_values(array_filter(
            $versions,
            static fn (array $version): bool => mb_strtolower(trim((string) ($version['company_name'] ?? ''))) === $normalized
        ));
    }

    /**
     * @param array<string, mixed> $version
     */
    private function isIncompleteVersion(array $version): bool
    {
        return (string) ($version['status'] ?? 'draft') !== 'complete'
            || (int) ($version['required_missing_count'] ?? 0) > 0;
    }

    /**
     * @param array<int, array<string, mixed>> $fields
     * @return array<string, string>
     */
    private function collectAnswers(array $fields): array
    {
        $answers = [];

        foreach ($fields as $field) {
            $type = (string) ($field['type'] ?? '');
            if (in_array($type, ['title', 'subtitle'], true)) {
                continue;
            }

            $name = (string) ($field['name'] ?? '');
            if ($name === '') {
                continue;
            }

            if ($type === 'checkbox') {
                $value = $_POST[$name] ?? [];
                if (!is_array($value)) {
                    $value = [$value];
                }

                $clean = array_values(array_filter(array_map(
                    static fn ($item): string => trim((string) $item),
                    $value
                ), static fn (string $item): bool => $item !== ''));

                $answers[$name] = implode('; ', $clean);
                continue;
            }

            $answers[$name] = trim((string) ($_POST[$name] ?? ''));
        }

        return $answers;
    }

    /**
     * @param array<int, array<string, mixed>> $fields
     * @param array<string, string> $answers
     * @return array<string, string>
     */
    private function requiredValidationErrors(array $fields, array $answers): array
    {
        $errors = [];

        foreach ($fields as $field) {
            if (in_array((string) ($field['type'] ?? ''), ['title', 'subtitle'], true)) {
                continue;
            }

            $name = trim((string) ($field['name'] ?? ''));
            if ($name === '' || !$this->isRequiredField($field)) {
                continue;
            }

            if (trim((string) ($answers[$name] ?? '')) === '') {
                $errors[$name] = 'Campo obrigatório.';
            }
        }

        return $errors;
    }

    /**
     * @param array<int, array<string, mixed>> $fields
     * @param array<string, string> $answers
     * @param array<int, array<string, mixed>> $versions
     * @param array<string, mixed>|null $latestVersion
     * @param array<string, string> $validationErrors
     * @return array<string, mixed>
     */
    private function buildViewData(
        array $fields,
        array $answers,
        array $versions,
        ?array $latestVersion,
        int $selectedVersionId,
        ?string $notice,
        ?string $error,
        array $validationErrors
    ): array {
        $stats = $this->calculateStats($fields, $answers);
        $selectedVersion = null;

        foreach ($versions as $version) {
            if ((int) ($version['id'] ?? 0) === $selectedVersionId) {
                $selectedVersion = $version;
                break;
            }
        }

        if ($selectedVersion === null) {
            $selectedVersion = $latestVersion;
        }

        return [
            'auth' => $this->auth,
            'user' => $this->auth->user(),
            'pageTitle' => 'Responder Questionário',
            'contentTitle' => 'Questionário do cliente',
            'subtitle' => 'ProdCol',
            'fields' => $fields,
            'answers' => $answers,
            'versions' => $versions,
            'selectedVersion' => $selectedVersion,
            'latestVersion' => $latestVersion,
            'stats' => $stats,
            'notice' => $notice,
            'error' => $error,
            'validationErrors' => $validationErrors,
        ];
    }

    /**
     * @param array<string, mixed> $field
     */
    private function isRequiredField(array $field): bool
    {
        if (in_array((string) ($field['type'] ?? ''), ['title', 'subtitle'], true)) {
            return false;
        }

        return !empty($field['required']);
    }

    /**
     * @param array<int, array<string, mixed>> $fields
     * @param array<string, string> $answers
     * @return array<string, int|float>
     */
    private function calculateStats(array $fields, array $answers): array
    {
        $total = 0;
        $answered = 0;
        $requiredTotal = 0;
        $requiredAnswered = 0;

        foreach ($fields as $field) {
            if (in_array((string) ($field['type'] ?? ''), ['title', 'subtitle'], true)) {
                continue;
            }

            $name = trim((string) ($field['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $total++;
            $value = trim((string) ($answers[$name] ?? ''));

            if ($value !== '') {
                $answered++;
            }

            if ($this->isRequiredField($field)) {
                $requiredTotal++;
                if ($value !== '') {
                    $requiredAnswered++;
                }
            }
        }

        $completionBase = $requiredTotal > 0 ? $requiredTotal : $total;
        $completionAnswered = $requiredTotal > 0 ? $requiredAnswered : $answered;

        return [
            'total' => $total,
            'answered' => $answered,
            'pending' => max($total - $answered, 0),
            'required_total' => $requiredTotal,
            'required_pending' => max($requiredTotal - $requiredAnswered, 0),
            'completion_pct' => $completionBase > 0 ? round(($completionAnswered / $completionBase) * 100, 1) : 0.0,
        ];
    }
}
