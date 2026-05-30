<?php
declare(strict_types=1);

namespace App\Admin;

use App\Infra\Database;
use PDO;
use Throwable;

final class ResponseRepository
{
    public function __construct(private readonly Database $database)
    {
    }

    /** @return string[] */
    public function companiesByEmailUser(string $emailUser): array
    {
        if ($emailUser === '') {
            return [];
        }

        $stmt = $this->pdo()->prepare(
            'SELECT DISTINCT company_name FROM responses_detailed WHERE email_user = ? ORDER BY company_name'
        );
        $stmt->execute([$emailUser]);

        return array_values(array_filter(array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []), static fn(string $v): bool => trim($v) !== ''));
    }

    public function latestCompanyByEmailUser(string $emailUser): string
    {
        if ($emailUser === '') {
            return '';
        }

        try {
            $stmt = $this->pdo()->prepare(
                'SELECT company_name FROM responses_detailed WHERE email_user = ? ORDER BY response_datetime DESC, id DESC LIMIT 1'
            );
            $stmt->execute([$emailUser]);
            return trim((string) ($stmt->fetchColumn() ?: ''));
        } catch (Throwable) {
            return '';
        }
    }

    public function totalQuestions(): int
    {
        try {
            $stmt = $this->pdo()->query("SELECT COUNT(*) FROM form_fields WHERE type NOT IN ('title','subtitle')");
            return (int) ($stmt->fetchColumn() ?: 0);
        } catch (Throwable) {
            return 0;
        }
    }

    /** @return array<int, array<string,mixed>> */
    public function sessionRows(string $emailUser, string $companyFilter = ''): array
    {
        $params = [$emailUser];
        $where = '';
        if ($companyFilter !== '') {
            $where = ' AND company_name = ?';
            $params[] = $companyFilter;
        }

        $stmt = $this->pdo()->prepare(
            "SELECT DATE_FORMAT(response_datetime, '%Y-%m-%d %H:%i') AS sess,
                    company_name,
                    COUNT(*) AS n
             FROM responses_detailed
             WHERE email_user = ? {$where}
             GROUP BY sess, company_name
             ORDER BY sess DESC"
        );
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array<int, array<string,mixed>> */
    public function answerRows(string $emailUser, string $companyFilter = ''): array
    {
        $params = [$emailUser];
        $where = '';
        if ($companyFilter !== '') {
            $where = ' AND rd.company_name = ?';
            $params[] = $companyFilter;
        }

        $stmt = $this->pdo()->prepare(
            "SELECT DATE_FORMAT(rd.response_datetime, '%Y-%m-%d %H:%i') AS sess,
                    rd.company_name,
                    rd.question_name,
                    rd.answer
             FROM responses_detailed rd
             WHERE rd.email_user = ? {$where}
             ORDER BY rd.response_datetime ASC, rd.id ASC"
        );
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function pdo(): PDO
    {
        return $this->database->pdo();
    }
}
