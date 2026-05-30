<?php
declare(strict_types=1);

namespace App\Diagnostico;

use App\Auth\AuthService;
use App\Infra\Database;
use App\Support\Request;
use App\Support\View;

final class HistoryController
{
    private VersionedResponseRepository $versionRepository;

    public function __construct(
        private readonly AuthService $auth,
        private readonly Database $database,
        private readonly Request $request
    ) {
        $this->versionRepository = new VersionedResponseRepository($this->database->pdo());
    }

    public function index(): void
    {
        $this->auth->requireAuth();
        $this->versionRepository->ensureSchema();

        $emailUser = trim($this->auth->user()->email);
        $versions = $emailUser !== '' ? $this->versionRepository->versions($emailUser) : [];
        $companyFilter = trim((string) ($this->request->query('company', '') ?? ''));
        $statusFilter = trim((string) ($this->request->query('status', '') ?? ''));

        $filtered = array_values(array_filter($versions, static function (array $row) use ($companyFilter, $statusFilter): bool {
            $companyOk = $companyFilter === '' || mb_stripos((string) ($row['company_name'] ?? ''), $companyFilter) !== false;
            $statusOk = $statusFilter === '' || (string) ($row['status'] ?? '') === $statusFilter;
            return $companyOk && $statusOk;
        }));

        $processingVersion = $emailUser !== '' ? $this->versionRepository->latestVersion($emailUser) : null;
        $processingVersionId = (int) ($processingVersion['id'] ?? 0);

        $groupedVersions = $this->groupByCompany($filtered, $processingVersionId);

        $summary = [
            'total_versions' => count($versions),
            'filtered_versions' => count($filtered),
            'complete_versions' => count(array_filter($versions, static fn(array $row): bool => (string) ($row['status'] ?? '') === 'complete')),
            'draft_versions' => count(array_filter($versions, static fn(array $row): bool => (string) ($row['status'] ?? '') !== 'complete')),
            'companies' => count($groupedVersions),
        ];

        View::render('diagnostico/history', [
            'auth' => $this->auth,
            'user' => $this->auth->user(),
            'versions' => $filtered,
            'groupedVersions' => $groupedVersions,
            'processingVersion' => $processingVersion,
            'summary' => $summary,
            'companyFilter' => $companyFilter,
            'statusFilter' => $statusFilter,
            'pageTitle' => 'Histórico',
            'contentTitle' => 'Histórico de versões',
            'subtitle' => 'ProdCol',
        ]);
    }

    /**
     * @param array<int, array<string, mixed>> $versions
     * @return array<int, array<string, mixed>>
     */
    private function groupByCompany(array $versions, int $processingVersionId): array
    {
        $groups = [];

        foreach ($versions as $row) {
            $companyName = trim((string) ($row['company_name'] ?? ''));
            $key = $companyName !== '' ? mb_strtolower($companyName) : '__sem_empresa__';

            $row['is_processing_base'] = $processingVersionId > 0 && (int) ($row['id'] ?? 0) === $processingVersionId;

            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'company_name' => $companyName !== '' ? $companyName : 'Sem empresa',
                    'versions' => [],
                    'latest' => null,
                    'count' => 0,
                    'complete_count' => 0,
                    'draft_count' => 0,
                    'processing_in_group' => false,
                ];
            }

            $groups[$key]['versions'][] = $row;
            $groups[$key]['count']++;
            if ((string) ($row['status'] ?? '') === 'complete') {
                $groups[$key]['complete_count']++;
            } else {
                $groups[$key]['draft_count']++;
            }
            if (($row['is_processing_base'] ?? false) === true) {
                $groups[$key]['processing_in_group'] = true;
            }
        }

        foreach ($groups as &$group) {
            usort($group['versions'], static function (array $a, array $b): int {
                $dateA = (string) ($a['response_datetime'] ?? '');
                $dateB = (string) ($b['response_datetime'] ?? '');
                if ($dateA === $dateB) {
                    return ((int) ($b['id'] ?? 0)) <=> ((int) ($a['id'] ?? 0));
                }

                return strcmp($dateB, $dateA);
            });

            $group['latest'] = $group['versions'][0] ?? null;
        }
        unset($group);

        usort($groups, static function (array $a, array $b): int {
            $dateA = (string) (($a['latest']['response_datetime'] ?? '') ?: '');
            $dateB = (string) (($b['latest']['response_datetime'] ?? '') ?: '');
            if ($dateA === $dateB) {
                return strcmp((string) ($a['company_name'] ?? ''), (string) ($b['company_name'] ?? ''));
            }

            return strcmp($dateB, $dateA);
        });

        return array_values($groups);
    }
}
