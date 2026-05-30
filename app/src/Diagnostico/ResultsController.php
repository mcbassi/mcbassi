<?php
declare(strict_types=1);

namespace App\Diagnostico;

use App\Auth\AuthService;
use App\Infra\Database;
use App\Support\Request;
use App\Support\View;

final class ResultsController
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

        $emailUser = trim($this->auth->user()->email);
        $fields = $this->fieldRepository->all();
        $selectedId = (int) ($this->request->query('version', '0') ?? 0);
        $companyFilter = trim((string) ($this->request->query('company', '') ?? ''));
        $versions = $emailUser !== '' ? $this->versionRepository->versions($emailUser) : [];
        $latestVersion = $versions[0] ?? null;
        $companyVersions = $companyFilter !== ''
            ? $this->filterVersionsByCompany($versions, $companyFilter)
            : [];

        if ($selectedId > 0 && $emailUser !== '') {
            $selectedVersion = $this->versionRepository->versionById($selectedId, $emailUser);
        } elseif ($companyVersions !== []) {
            $selectedVersion = $companyVersions[0];
        } else {
            $selectedVersion = $latestVersion;
        }

        $answers = [];
        if (is_array($selectedVersion)) {
            $answers = $this->versionRepository->answersForVersion(
                (int) ($selectedVersion['id'] ?? 0),
                $emailUser,
                (string) ($selectedVersion['response_datetime'] ?? '')
            );
        }

        $selectedCompany = trim((string) ($selectedVersion['company_name'] ?? $companyFilter));
        if ($selectedCompany !== '') {
            $companyVersions = $this->filterVersionsByCompany($versions, $selectedCompany);
        }

        View::render('diagnostico/results', [
            'auth' => $this->auth,
            'user' => $this->auth->user(),
            'selectedVersion' => $selectedVersion,
            'latestVersion' => $latestVersion,
            'versions' => $versions,
            'companyVersions' => $companyVersions,
            'companyContext' => $selectedCompany,
            'overview' => $this->overview($fields, $answers),
            'sections' => $this->sectionBreakdown($fields, $answers),
            'pendingRequired' => $this->pendingRequired($fields, $answers),
            'pageTitle' => 'Resultados',
            'contentTitle' => 'Resultados do questionário',
            'subtitle' => 'ProdCol',
        ]);
    }

    /**
     * @param array<int, array<string, mixed>> $versions
     * @return array<int, array<string, mixed>>
     */
    private function filterVersionsByCompany(array $versions, string $companyName): array
    {
        return array_values(array_filter($versions, static function (array $row) use ($companyName): bool {
            return trim((string) ($row['company_name'] ?? '')) === trim($companyName);
        }));
    }

    /**
     * @param array<int, array<string, mixed>> $fields
     * @param array<string, string> $answers
     * @return array<string, int|float>
     */
    private function overview(array $fields, array $answers): array
    {
        $total = 0;
        $answered = 0;
        $requiredTotal = 0;
        $requiredAnswered = 0;

        foreach ($fields as $field) {
            $type = strtolower(trim((string) ($field['type'] ?? '')));
            if (in_array($type, ['title', 'subtitle'], true)) {
                continue;
            }

            $name = trim((string) ($field['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $value = trim((string) ($answers[$name] ?? ''));
            $isAnswered = $value !== '';
            $isRequired = (bool) ($field['required'] ?? false);

            $total++;
            if ($isAnswered) {
                $answered++;
            }

            if ($isRequired) {
                $requiredTotal++;
                if ($isAnswered) {
                    $requiredAnswered++;
                }
            }
        }

        $requiredPending = max($requiredTotal - $requiredAnswered, 0);
        $completion = $requiredTotal > 0 ? ($requiredAnswered / $requiredTotal) * 100 : 100;

        return [
            'total' => $total,
            'answered' => $answered,
            'required_total' => $requiredTotal,
            'required_answered' => $requiredAnswered,
            'required_pending' => $requiredPending,
            'completion_pct' => round($completion, 1),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $fields
     * @param array<string, string> $answers
     * @return array<int, array<string, mixed>>
     */
    private function sectionBreakdown(array $fields, array $answers): array
    {
        $sections = [];
        $currentKey = '__geral__';
        $sections[$currentKey] = [
            'label' => 'Geral',
            'total' => 0,
            'answered' => 0,
            'required_total' => 0,
            'required_answered' => 0,
            'completion_pct' => 0.0,
        ];

        foreach ($fields as $field) {
            $type = strtolower(trim((string) ($field['type'] ?? '')));
            $label = trim((string) ($field['label'] ?? ''));

            if ($type === 'title') {
                $currentKey = $label !== '' ? $label : 'Capítulo';
                if (!isset($sections[$currentKey])) {
                    $sections[$currentKey] = [
                        'label' => $currentKey,
                        'total' => 0,
                        'answered' => 0,
                        'required_total' => 0,
                        'required_answered' => 0,
                        'completion_pct' => 0.0,
                    ];
                }
                continue;
            }

            if ($type === 'subtitle') {
                continue;
            }

            $name = trim((string) ($field['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $value = trim((string) ($answers[$name] ?? ''));
            $isAnswered = $value !== '';
            $isRequired = (bool) ($field['required'] ?? false);

            $sections[$currentKey]['total']++;
            if ($isAnswered) {
                $sections[$currentKey]['answered']++;
            }

            if ($isRequired) {
                $sections[$currentKey]['required_total']++;
                if ($isAnswered) {
                    $sections[$currentKey]['required_answered']++;
                }
            }
        }

        foreach ($sections as &$section) {
            $requiredTotal = (int) ($section['required_total'] ?? 0);
            $requiredAnswered = (int) ($section['required_answered'] ?? 0);
            $section['completion_pct'] = $requiredTotal > 0
                ? round(($requiredAnswered / $requiredTotal) * 100, 1)
                : 100.0;
        }
        unset($section);

        return array_values($sections);
    }

    /**
     * @param array<int, array<string, mixed>> $fields
     * @param array<string, string> $answers
     * @return array<int, array<string, string>>
     */
    private function pendingRequired(array $fields, array $answers): array
    {
        $pending = [];
        $currentSection = 'Geral';

        foreach ($fields as $field) {
            $type = strtolower(trim((string) ($field['type'] ?? '')));
            if ($type === 'title') {
                $currentSection = trim((string) ($field['label'] ?? '')) ?: 'Capítulo';
                continue;
            }

            if ($type === 'subtitle') {
                continue;
            }

            if (!(bool) ($field['required'] ?? false)) {
                continue;
            }

            $name = trim((string) ($field['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $value = trim((string) ($answers[$name] ?? ''));
            if ($value !== '') {
                continue;
            }

            $pending[] = [
                'section' => $currentSection,
                'label' => trim((string) ($field['label'] ?? $name)),
            ];
        }

        return $pending;
    }
}
