<?php
declare(strict_types=1);

namespace App\Diagnostico;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use RuntimeException;

final class VersionedResponseRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function ensureSchema(): void
    {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS response_sessions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user VARCHAR(255) NOT NULL DEFAULT '',
                email_user VARCHAR(255) NOT NULL,
                email_resp VARCHAR(255) NOT NULL DEFAULT '',
                company_name VARCHAR(255) NOT NULL DEFAULT '',
                version_no INT NOT NULL DEFAULT 1,
                status VARCHAR(20) NOT NULL DEFAULT 'draft',
                source_session_id INT NULL,
                answered_count INT NOT NULL DEFAULT 0,
                total_questions INT NOT NULL DEFAULT 0,
                completion_pct DECIMAL(6,2) NOT NULL DEFAULT 0.00,
                required_missing_count INT NOT NULL DEFAULT 0,
                response_datetime DATETIME NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_resp_sessions_user_company (email_user, company_name, response_datetime),
                INDEX idx_resp_sessions_dt (response_datetime)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");

        if (!$this->columnExists('responses_detailed', 'response_session_id')) {
            $this->pdo->exec('ALTER TABLE responses_detailed ADD COLUMN response_session_id INT NULL AFTER id');
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function versions(string $emailUser): array
    {
        $this->ensureSchema();

        $stmt = $this->pdo->prepare("
            SELECT
                rs.id,
                rs.user,
                rs.email_user,
                rs.email_resp,
                rs.company_name,
                rs.version_no,
                rs.status,
                rs.answered_count,
                rs.total_questions,
                rs.completion_pct,
                rs.required_missing_count,
                rs.response_datetime,
                rs.created_at
            FROM response_sessions rs
            WHERE (rs.email_user = ? OR rs.email_resp = ?)
            ORDER BY rs.response_datetime DESC, rs.id DESC
            LIMIT 20
        ");
        $stmt->execute([$emailUser, $emailUser]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($rows !== []) {
            return array_map(fn (array $row): array => $this->normalizeVersionRow($row), $rows);
        }

        return $this->legacyVersions($emailUser);
    }

    /**
     * @return array<string, string>
     */
    public function latestAnswers(string $emailUser): array
    {
        $latest = $this->latestVersion($emailUser);
        if ($latest === null) {
            return [];
        }

        return $this->answersForVersion((int) $latest['id'], $emailUser, (string) ($latest['response_datetime'] ?? ''));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function latestVersion(string $emailUser): ?array
    {
        $versions = $this->versions($emailUser);
        return $versions[0] ?? null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function versionById(int $id, string $emailUser): ?array
    {
        $this->ensureSchema();

        if ($id > 0) {
            $stmt = $this->pdo->prepare("
                SELECT
                    rs.id,
                    rs.user,
                    rs.email_user,
                    rs.email_resp,
                    rs.company_name,
                    rs.version_no,
                    rs.status,
                    rs.answered_count,
                    rs.total_questions,
                    rs.completion_pct,
                    rs.required_missing_count,
                    rs.response_datetime,
                    rs.created_at
                FROM response_sessions rs
                WHERE rs.id = ? AND (rs.email_user = ? OR rs.email_resp = ?)
                LIMIT 1
            ");
            $stmt->execute([$id, $emailUser, $emailUser]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (is_array($row)) {
                return $this->normalizeVersionRow($row);
            }
        }

        foreach ($this->legacyVersions($emailUser) as $legacy) {
            if ((int) ($legacy['id'] ?? 0) === $id) {
                return $legacy;
            }
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    public function answersForVersion(int $versionId, string $emailUser, string $responseDateTime = ''): array
    {
        $this->ensureSchema();

        $sessionRow = $versionId > 0 ? $this->sessionRowById($versionId, $emailUser) : null;

        if (is_array($sessionRow)) {
            $stmt = $this->pdo->prepare("
                SELECT question_name, answer
                FROM responses_detailed
                WHERE response_session_id = ?
                ORDER BY id ASC
            ");
            $stmt->execute([$versionId]);

            $answers = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $answers[(string) $row['question_name']] = (string) ($row['answer'] ?? '');
            }

            if ($answers !== []) {
                return $answers;
            }

            // Compatibilidade com sessões antigas: em algumas bases,
            // response_sessions existe, mas responses_detailed ainda não foi
            // preenchida com response_session_id. Nesses casos, recupera pela
            // data/minuto da sessão, que era a regra original do sistema.
            if ($responseDateTime === '') {
                $responseDateTime = (string) ($sessionRow['response_datetime'] ?? '');
            }
        }

        $sessionMinute = $this->sessionMinute($responseDateTime);
        if ($sessionMinute === '') {
            return [];
        }

        $stmt = $this->pdo->prepare("
            SELECT question_name, answer
            FROM responses_detailed
            WHERE (email_user = ? OR email_resp = ?)
              AND DATE_FORMAT(response_datetime, '%Y-%m-%d %H:%i') = ?
            ORDER BY id ASC
        ");
        $stmt->execute([$emailUser, $emailUser, $sessionMinute]);

        $answers = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $answers[(string) $row['question_name']] = (string) ($row['answer'] ?? '');
        }

        return $answers;
    }

    /**
     * @param array<int, array<string, mixed>> $fields
     * @param array<string, string> $answers
     */
    public function saveVersion(
        string $user,
        string $emailUser,
        string $emailResp,
        string $companyName,
        array $fields,
        array $answers,
        string $status,
        ?int $sourceSessionId = null
    ): int {
        $this->ensureSchema();

        $totalQuestions = 0;
        $answeredCount = 0;
        $requiredTotal = 0;
        $requiredAnswered = 0;
        $requiredMissingCount = 0;

        foreach ($fields as $field) {
            if (in_array((string) ($field['type'] ?? ''), ['title', 'subtitle'], true)) {
                continue;
            }

            $name = trim((string) ($field['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $totalQuestions++;
            $answer = trim((string) ($answers[$name] ?? ''));

            if ($answer !== '') {
                $answeredCount++;
            }

            $isRequired = !empty($field['required']);
            if ($isRequired) {
                $requiredTotal++;

                if ($answer !== '') {
                    $requiredAnswered++;
                } else {
                    $requiredMissingCount++;
                }
            }
        }

        $completionBase = $requiredTotal > 0 ? $requiredTotal : $totalQuestions;
        $completionAnswered = $requiredTotal > 0 ? $requiredAnswered : $answeredCount;
        $completionPct = $completionBase > 0 ? round(($completionAnswered / $completionBase) * 100, 2) : 0.0;
        $versionNo = $this->nextVersionNumber($emailUser, $companyName);
        $responseDateTime = $this->nextResponseDateTime($emailUser, $companyName);

        $this->pdo->beginTransaction();

        try {
            $insertSession = $this->pdo->prepare("
                INSERT INTO response_sessions (
                    user, email_user, email_resp, company_name, version_no, status, source_session_id,
                    answered_count, total_questions, completion_pct, required_missing_count, response_datetime
                ) VALUES (
                    :user, :email_user, :email_resp, :company_name, :version_no, :status, :source_session_id,
                    :answered_count, :total_questions, :completion_pct, :required_missing_count, :response_datetime
                )
            ");

            $insertSession->execute([
                ':user' => $user,
                ':email_user' => $emailUser,
                ':email_resp' => $emailResp,
                ':company_name' => $companyName,
                ':version_no' => $versionNo,
                ':status' => $status,
                ':source_session_id' => $sourceSessionId,
                ':answered_count' => $answeredCount,
                ':total_questions' => $totalQuestions,
                ':completion_pct' => $completionPct,
                ':required_missing_count' => $requiredMissingCount,
                ':response_datetime' => $responseDateTime->format('Y-m-d H:i:s'),
            ]);

            $sessionId = (int) $this->pdo->lastInsertId();

            $insertAnswer = $this->pdo->prepare("
                INSERT INTO responses_detailed (
                    response_session_id,
                    user,
                    email_user,
                    email_resp,
                    company_name,
                    question_name,
                    question_label,
                    answer,
                    prompt_code,
                    response_datetime
                ) VALUES (
                    :response_session_id,
                    :user,
                    :email_user,
                    :email_resp,
                    :company_name,
                    :question_name,
                    :question_label,
                    :answer,
                    :prompt_code,
                    :response_datetime
                )
            ");

            foreach ($fields as $field) {
                $type = (string) ($field['type'] ?? '');
                if (in_array($type, ['title', 'subtitle'], true)) {
                    continue;
                }

                $name = (string) ($field['name'] ?? '');
                if ($name === '') {
                    continue;
                }

                $insertAnswer->execute([
                    ':response_session_id' => $sessionId,
                    ':user' => $user,
                    ':email_user' => $emailUser,
                    ':email_resp' => $emailResp,
                    ':company_name' => $companyName,
                    ':question_name' => $name,
                    ':question_label' => (string) ($field['label'] ?? $name),
                    ':answer' => (string) ($answers[$name] ?? ''),
                    ':prompt_code' => (string) ($field['prompt_code'] ?? ''),
                    ':response_datetime' => $responseDateTime->format('Y-m-d H:i:s'),
                ]);
            }

            $this->pdo->commit();

            return $sessionId;
        } catch (\Throwable $throwable) {
            $this->pdo->rollBack();
            throw new RuntimeException('Falha ao salvar a nova versão do questionário: ' . $throwable->getMessage(), 0, $throwable);
        }
    }

    private function nextVersionNumber(string $emailUser, string $companyName): int
    {
        $stmt = $this->pdo->prepare("
            SELECT COALESCE(MAX(version_no), 0)
            FROM response_sessions
            WHERE email_user = ?
              AND company_name = ?
        ");
        $stmt->execute([$emailUser, $companyName]);

        return ((int) $stmt->fetchColumn()) + 1;
    }

    private function nextResponseDateTime(string $emailUser, string $companyName): DateTimeImmutable
    {
        $timezone = new DateTimeZone(date_default_timezone_get());
        $now = new DateTimeImmutable('now', $timezone);

        $stmt = $this->pdo->prepare("
            SELECT MAX(response_datetime)
            FROM responses_detailed
            WHERE email_user = ?
              AND company_name = ?
        ");
        $stmt->execute([$emailUser, $companyName]);
        $last = $stmt->fetchColumn();

        if (!is_string($last) || trim($last) === '') {
            return $now;
        }

        $lastDate = new DateTimeImmutable($last, $timezone);
        $nowMinute = $now->format('Y-m-d H:i');
        $lastMinute = $lastDate->format('Y-m-d H:i');

        if ($lastMinute >= $nowMinute) {
            return $lastDate->modify('+1 minute');
        }

        return $now;
    }

    private function columnExists(string $table, string $column): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
        ");
        $stmt->execute([$table, $column]);

        return (int) $stmt->fetchColumn() > 0;
    }

    private function hasSessionRow(int $sessionId, string $emailUser): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM response_sessions
            WHERE id = ?
              AND (email_user = ? OR email_resp = ?)
        ");
        $stmt->execute([$sessionId, $emailUser, $emailUser]);

        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function sessionRowById(int $sessionId, string $emailUser): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM response_sessions
            WHERE id = ?
              AND (email_user = ? OR email_resp = ?)
            LIMIT 1
        ");
        $stmt->execute([$sessionId, $emailUser, $emailUser]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    private function sessionMinute(string $responseDateTime): string
    {
        $responseDateTime = trim($responseDateTime);
        if ($responseDateTime === '') {
            return '';
        }

        try {
            return (new DateTimeImmutable($responseDateTime))->format('Y-m-d H:i');
        } catch (\Throwable) {
            return substr($responseDateTime, 0, 16);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function legacyVersions(string $emailUser): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                MIN(id) AS legacy_id,
                MAX(user) AS user,
                email_user,
                MAX(email_resp) AS email_resp,
                MAX(company_name) AS company_name,
                COUNT(*) AS answered_count,
                DATE_FORMAT(response_datetime, '%Y-%m-%d %H:%i') AS response_datetime
            FROM responses_detailed
            WHERE (email_user = ? OR email_resp = ?)
            GROUP BY email_user, DATE_FORMAT(response_datetime, '%Y-%m-%d %H:%i')
            ORDER BY response_datetime DESC, legacy_id DESC
            LIMIT 20
        ");
        $stmt->execute([$emailUser, $emailUser]);

        $rows = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $index => $row) {
            $rows[] = [
                'id' => (int) ($row['legacy_id'] ?? 0),
                'user' => (string) ($row['user'] ?? ''),
                'email_user' => (string) ($row['email_user'] ?? ''),
                'email_resp' => (string) ($row['email_resp'] ?? ''),
                'company_name' => (string) ($row['company_name'] ?? ''),
                'version_no' => $index + 1,
                'status' => 'legacy',
                'answered_count' => (int) ($row['answered_count'] ?? 0),
                'total_questions' => 0,
                'completion_pct' => 0.0,
                'required_missing_count' => 0,
                'response_datetime' => (string) ($row['response_datetime'] ?? ''),
                'created_at' => (string) ($row['response_datetime'] ?? ''),
                'is_legacy' => true,
            ];
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalizeVersionRow(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'user' => (string) ($row['user'] ?? ''),
            'email_user' => (string) ($row['email_user'] ?? ''),
            'email_resp' => (string) ($row['email_resp'] ?? ''),
            'company_name' => (string) ($row['company_name'] ?? ''),
            'version_no' => (int) ($row['version_no'] ?? 1),
            'status' => (string) ($row['status'] ?? 'draft'),
            'answered_count' => (int) ($row['answered_count'] ?? 0),
            'total_questions' => (int) ($row['total_questions'] ?? 0),
            'completion_pct' => (float) ($row['completion_pct'] ?? 0),
            'required_missing_count' => (int) ($row['required_missing_count'] ?? 0),
            'response_datetime' => (string) ($row['response_datetime'] ?? ''),
            'created_at' => (string) ($row['created_at'] ?? ''),
            'is_legacy' => false,
        ];
    }
}
