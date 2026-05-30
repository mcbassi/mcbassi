<?php
declare(strict_types=1);
namespace App\Diagnostico;

use App\Auth\AuthService;
use App\Infra\Database;
use App\Support\Request;
use App\Support\View;
use RuntimeException;

final class QuestionarioController
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
        $saveMode = trim((string) ($this->request->input('save_mode', 'draft') ?? 'draft'));
        $status = $saveMode === 'complete' ? 'complete' : 'draft';
        $sourceSessionId = (int) ($this->request->input('source_session_id', '0') ?? 0);

        $user = $this->auth->user();
        $emailUser = trim($user->email);
        $userName = trim($user->name);

        if ($emailUser === '') {
            flash_set('diagnostico_error', 'Usuário sem e-mail na sessão.');
            redirect(\url('diagnostico/index.php'));
        }

        $companyName = trim((string) ($answers['razon_social'] ?? $answers['razao_social'] ?? ''));
        if ($companyName === '') {
            $latest = $this->versionRepository->latestVersion($emailUser);
            $companyName = trim((string) ($latest['company_name'] ?? ''));
        }
        if ($companyName === '') {
            $companyName = 'Sem nome';
        }

        $emailResp = trim((string) ($answers['email_resp'] ?? ''));
        if ($emailResp === '') {
            $emailResp = trim((string) ($answers['email_contacto'] ?? $emailUser));
        }

        $requiredValidationErrors = $this->requiredValidationErrors($fields, $answers);
        $validationErrors = $status === 'complete' ? $requiredValidationErrors : [];

        if ($validationErrors !== []) {
            $viewData = $this->buildViewData(
                $fields,
                $answers,
                $this->versionRepository->versions($emailUser),
                $this->versionRepository->latestVersion($emailUser),
                $this->request->input('version') !== null ? (int) $this->request->input('version', '0') : 0,
                'Existem campos obrigatórios sem resposta. Você pode salvar parcial ou completar os campos destacados.',
                null,
                $validationErrors
            );
            View::render('diagnostico/formulario', $viewData);
            return;
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

            $_SESSION['company_name'] = $companyName;
            $_SESSION['empresa'] = $companyName;
            $_SESSION['email_resp'] = $emailResp;

            $message = $status === 'complete'
                ? 'Versão completa salva. Esta passa a ser a versão usada no processamento.'
                : 'Versão parcial salva. Ela já fica disponível como versão mais recente.';

            flash_set('diagnostico_notice', $message);
            redirect(\url('diagnostico/index.php?version=' . rawurlencode((string) $sessionId)));
        } catch (RuntimeException $exception) {
            $viewData = $this->buildViewData(
                $fields,
                $answers,
                $this->versionRepository->versions($emailUser),
                $this->versionRepository->latestVersion($emailUser),
                $this->request->input('version') !== null ? (int) $this->request->input('version', '0') : 0,
                null,
                $exception->getMessage(),
                []
            );
            View::render('diagnostico/formulario', $viewData);
        }
    }

private function renderForm(): void
{
    $fields = $this->fieldRepository->all();
    $emailUser = trim($this->auth->user()->email);
    $selectedId = (int) ($this->request->query('version', '0') ?? 0);
    $isNewFromLatest = $this->request->query('new', '0') === '1';
    $companyFilter = trim((string) ($this->request->query('company', '') ?? ''));
    $versions = $emailUser !== '' ? $this->versionRepository->versions($emailUser) : [];
    $latestVersion = $versions[0] ?? null;
    $companyVersions = $companyFilter !== ''
        ? $this->filterVersionsByCompany($versions, $companyFilter)
        : [];
    $latestForCompany = $companyVersions[0] ?? null;

    if ($isNewFromLatest && is_array($latestForCompany ?? $latestVersion)) {
        $selectedVersion = $latestForCompany ?? $latestVersion;
    } else {
        $selectedVersion = $selectedId > 0 && $emailUser !== ''
            ? $this->versionRepository->versionById($selectedId, $emailUser)
            : ($latestForCompany ?? $latestVersion);
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

    $viewData['isDraftFromLatest'] = $isNewFromLatest;
    $viewData['sourceVersion'] = $selectedVersion;
    $viewData['companyContext'] = trim((string) ($selectedVersion['company_name'] ?? $companyFilter));

    View::render('diagnostico/formulario', $viewData);
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
        $grouped = $this->groupFields($fields);
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
            'contentTitle' => 'Formulário para diagnóstico empresarial',
            'subtitle' => 'ProdCol',
            'sections' => $grouped,
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
     * @param array<int, array<string, mixed>> $fields
     * @return array<int, array<string, mixed>>
     */
    private function groupFields(array $fields): array
    {
        $sections = [];
        $currentSection = null;

        foreach ($fields as $field) {
            $type = (string) ($field['type'] ?? '');
            $label = trim((string) ($field['label'] ?? ''));
            $name = trim((string) ($field['name'] ?? ''));

            if ($type === 'title') {
                continue;
            }

            if ($type === 'subtitle') {
                $isPrimary = preg_match('/^\d+\./', $label) === 1 && preg_match('/^\d+\.\d+/', $label) !== 1;
                if ($isPrimary || $currentSection === null) {
                    $currentSection = [
                        'title' => $label !== '' ? $label : 'Seção',
                        'bands' => [],
                    ];
                    $sections[] = &$currentSection;
                    continue;
                }

                $currentSection['bands'][] = [
                    'label' => $label,
                    'fields' => [],
                ];
                continue;
            }

            if ($name === '') {
                continue;
            }

            if ($currentSection === null) {
                $currentSection = [
                    'title' => 'Questionário',
                    'bands' => [],
                ];
                $sections[] = &$currentSection;
            }

            if ($currentSection['bands'] === []) {
                $currentSection['bands'][] = [
                    'label' => '',
                    'fields' => [],
                ];
            }

            $bandIndex = count($currentSection['bands']) - 1;
            $currentSection['bands'][$bandIndex]['fields'][] = $field;
        }

        return array_map(static function (array $section): array {
            if ($section['bands'] === []) {
                $section['bands'][] = ['label' => '', 'fields' => []];
            }
            return $section;
        }, $sections);
    }

    /**
     * @param array<int, array<string, mixed>> $fields
     * @param array<string, string> $answers
     * @return array<string, int|float>
     */

    private function isRequiredField(array $field): bool
    {
        if (in_array((string) ($field['type'] ?? ''), ['title', 'subtitle'], true)) {
            return false;
        }

        return !empty($field['required']);
    }

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

    /**
     * Filtra versÃµes pelo nome da empresa.
     *
     * Este mÃ©todo Ã© usado pelos fluxos:
     * - Editar Ãšltima
     * - Nova SessÃ£o
     * - navegaÃ§Ã£o com ?company=
     *
     * @param array<int, array<string, mixed>> $versions
     * @return array<int, array<string, mixed>>
     */
    private function filterVersionsByCompany(array $versions, string $companyName): array
    {
        $target = trim($companyName);
        if ($target === '') {
            return [];
        }

        return array_values(array_filter($versions, static function (array $row) use ($target): bool {
            return trim((string) ($row['company_name'] ?? '')) === $target;
        }));
    }
}

