<?php
declare(strict_types=1);

namespace App\Admin;

use App\Auth\AuthService;
use App\Infra\Database;
use App\Support\Request;
use App\Support\View;
use DateTime;

final class AdminController
{
    private ResponseRepository $repository;

    public function __construct(
        private readonly AuthService $auth,
        private readonly Database $database,
        private readonly Request $request
    ) {
        $this->repository = new ResponseRepository($this->database);
    }

    public function responses(): void
    {
        $this->auth->requireAuth();

        $sessionAuth = $_SESSION['auth'] ?? null;
        $emailUser = $this->pickFirstNonEmpty([
            is_array($sessionAuth) ? ($sessionAuth['email'] ?? '') : '',
            $this->auth->user()->email,
            $_SESSION['email_user'] ?? '',
            $_SESSION['user_email'] ?? '',
            $_SESSION['usuario_email'] ?? '',
            $_SESSION['email'] ?? '',
            $_SESSION['email_resp'] ?? '',
        ]);

        if ($emailUser === '') {
            http_response_code(401);
            echo 'Sessão expirada / usuário não identificado.';
            return;
        }

        $companyName = $this->pickFirstNonEmpty([
            is_array($sessionAuth) ? ($sessionAuth['company_name'] ?? '') : '',
            $_SESSION['company_name'] ?? '',
            $_SESSION['empresa'] ?? '',
            $_SESSION['company'] ?? '',
        ]);

        if ($companyName === '') {
            $companyName = $this->repository->latestCompanyByEmailUser($emailUser);
        }

        $companyFilter = trim((string) ($this->request->query('company', '') ?? ''));
        if ($companyFilter === '' && $companyName !== '') {
            $companyFilter = $companyName;
        }

        $companies = $this->repository->companiesByEmailUser($emailUser);
        $sessRows = $this->repository->sessionRows($emailUser, $companyFilter);
        $ansRows = $this->repository->answerRows($emailUser, $companyFilter);
        $totalQuestions = $this->repository->totalQuestions();

        $sessions = [];
        foreach ($ansRows as $row) {
            $sess = (string) ($row['sess'] ?? '');
            $company = (string) ($row['company_name'] ?? '');
            $questionName = (string) ($row['question_name'] ?? '');
            $answer = (string) ($row['answer'] ?? '');
            if ($sess === '' || $questionName === '') {
                continue;
            }

            $key = $sess . '||' . $company;
            if (!isset($sessions[$key])) {
                $sessions[$key] = [
                    'sess' => $sess,
                    'company' => $company,
                    'answers' => [],
                    'answered_count' => 0,
                ];
            }

            $prev = $sessions[$key]['answers'][$questionName] ?? null;
            $sessions[$key]['answers'][$questionName] = $answer;

            if ($prev === null) {
                if (trim($answer) !== '') {
                    $sessions[$key]['answered_count']++;
                }
            } elseif (trim((string) $prev) === '' && trim($answer) !== '') {
                $sessions[$key]['answered_count']++;
            }
        }

        $sessionKeys = array_keys($sessions);
        usort($sessionKeys, static function (string $a, string $b) use ($sessions): int {
            $sa = (string) ($sessions[$a]['sess'] ?? '');
            $sb = (string) ($sessions[$b]['sess'] ?? '');
            if ($sa === $sb) {
                return strcmp((string) ($sessions[$a]['company'] ?? ''), (string) ($sessions[$b]['company'] ?? ''));
            }
            return strcmp($sa, $sb);
        });

        $seriesLabels = [];
        $seriesCompleteness = [];
        $seriesEmployees = [];
        $riskBuckets = ['Bajo' => [], 'Medio' => [], 'Alto' => []];
        $techCounts = [];

        foreach ($sessionKeys as $key) {
            $sess = (string) $sessions[$key]['sess'];
            $company = (string) $sessions[$key]['company'];
            $label = $company !== '' ? ($company . ' — ' . $this->formatMinute($sess)) : $this->formatMinute($sess);
            $seriesLabels[] = $label;

            $answered = (int) $sessions[$key]['answered_count'];
            $seriesCompleteness[] = $totalQuestions > 0 ? round(($answered / $totalQuestions) * 100, 1) : 0.0;

            $employees = $sessions[$key]['answers']['num_total_prev'] ?? '';
            $seriesEmployees[] = is_numeric(trim((string) $employees)) ? (float) $employees : null;

            $b = 0;
            $m = 0;
            $a = 0;
            foreach ($sessions[$key]['answers'] as $questionName => $answer) {
                $questionName = (string) $questionName;
                $value = trim((string) $answer);
                if (!str_starts_with($questionName, 'riesgo_')) {
                    continue;
                }
                if (stripos($value, 'alto') !== false) {
                    $a++;
                } elseif (stripos($value, 'medio') !== false) {
                    $m++;
                } elseif (stripos($value, 'bajo') !== false) {
                    $b++;
                }
            }
            $riskBuckets['Bajo'][] = $b;
            $riskBuckets['Medio'][] = $m;
            $riskBuckets['Alto'][] = $a;

            $techCount = 0;
            foreach ($sessions[$key]['answers'] as $questionName => $answer) {
                $questionName = (string) $questionName;
                if (!str_ends_with($questionName, '_tiene')) {
                    continue;
                }
                $value = trim((string) $answer);
                if ($value === '') {
                    continue;
                }
                $parts = array_values(array_filter(array_map('trim', preg_split('/[;,]+/', $value) ?: []), static fn(string $item): bool => $item !== ''));
                $techCount += max(1, count($parts));
            }
            $techCounts[] = $techCount;
        }

        $kpiTotalSessions = count($sessionKeys);
        $kpiCompanies = count($companies);
        $kpiLastLabel = $kpiTotalSessions > 0 ? (string) end($seriesLabels) : '-';
        $kpiLastCompleteness = $kpiTotalSessions > 0 ? (string) end($seriesCompleteness) : '0';

        View::render('admin/responses', [
            'pageTitle' => 'Dashboard',
            'contentTitle' => 'Dashboard — Respostas',
            'subtitle' => 'ProdCol',
            'companies' => $companies,
            'companyFilter' => $companyFilter,
            'sessRows' => $sessRows,
            'seriesLabels' => $seriesLabels,
            'seriesCompleteness' => $seriesCompleteness,
            'seriesEmployees' => $seriesEmployees,
            'riskBuckets' => $riskBuckets,
            'techCounts' => $techCounts,
            'kpi_total_sessions' => $kpiTotalSessions,
            'kpi_companies' => $kpiCompanies,
            'kpi_last_label' => $kpiLastLabel,
            'kpi_last_completeness' => $kpiLastCompleteness,
        ]);
    }

    private function formatMinute(string $dateTime): string
    {
        try {
            return (new DateTime($dateTime))->format('d/m/Y H:i');
        } catch (\Throwable) {
            return $dateTime;
        }
    }

    /** @param array<int|string,mixed> $values */
    private function pickFirstNonEmpty(array $values): string
    {
        foreach ($values as $value) {
            $value = trim((string) $value);
            if ($value !== '') {
                return $value;
            }
        }
        return '';
    }
}
