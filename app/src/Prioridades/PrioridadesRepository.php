<?php
declare(strict_types=1);

namespace App\Prioridades;

use App\Diagnostico\VersionedResponseRepository;
use App\Grupos\GroupRepository;
use App\Infra\Database;
use App\Prompts\PromptRepository;
use PDO;
use Throwable;

final class PrioridadesRepository
{
    private ?PDO $pdo = null;
    private GroupRepository $groupRepository;
    private PromptRepository $promptRepository;

    public function __construct(private readonly Database $database)
    {
        $this->groupRepository = new GroupRepository($database);
        $this->promptRepository = new PromptRepository($database);
    }

    /** @return array<int, array<string, mixed>> */
    public function fetchGroups(): array
    {
        return $this->groupRepository->fetchGroups();
    }

    /** @return array<string, mixed>|null */
    public function fetchGroup(int $groupId): ?array
    {
        return $this->groupRepository->fetchGroup($groupId);
    }

    /** @return array<string, bool> */
    public function fetchGroupQuestions(int $groupId): array
    {
        return $this->groupRepository->fetchGroupQuestions($groupId);
    }

    /** @return array<string, mixed>|null */
    public function findPromptByAssistente(string $assistente): ?array
    {
        $row = $this->promptRepository->findByAssistente($assistente);
        return is_array($row) ? $row : null;
    }

    /** @return array<int, array<string, mixed>> */
    public function fetchResponsesForGroup(int $responseSessionId, array $questionNames, bool $onlyWithAiResponse = false): array
    {
        $questionNames = array_values(array_filter(array_map('strval', $questionNames), static fn(string $v): bool => trim($v) !== ''));
        if ($responseSessionId <= 0 || $questionNames === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($questionNames), '?'));
        $params = array_merge([$responseSessionId], $questionNames);

        $sql = 'SELECT `id`, `question_name`, `question_label`, `answer`, `prompt`, `prompt_code`, `prompt_response`, `response_datetime`
                FROM `responses_detailed`
                WHERE `response_session_id` = ?
                  AND `question_name` IN (' . $placeholders . ')
                  AND `answer` IS NOT NULL AND TRIM(`answer`) <> ""
                  AND `prompt` IS NOT NULL AND TRIM(`prompt`) <> ""';

        if ($onlyWithAiResponse) {
            $sql .= ' AND `prompt_response` IS NOT NULL AND TRIM(`prompt_response`) <> ""';
        }

        $sql .= ' ORDER BY `response_datetime` ASC, `id` ASC';

        $st = $this->pdo()->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array<string, mixed>|null */
    public function fetchSessionMeta(int $responseSessionId): ?array
    {
        if ($responseSessionId <= 0) {
            return null;
        }

        try {
            $st = $this->pdo()->prepare('SELECT `id`, `email_user`, `company_name`, `email_resp`, `response_datetime` FROM `response_sessions` WHERE `id` = ? LIMIT 1');
            $st->execute([$responseSessionId]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if (is_array($row)) {
                $row['sess_min'] = substr((string) ($row['response_datetime'] ?? ''), 0, 16);
                return $row;
            }
        } catch (Throwable) {
        }

        try {
            $st = $this->pdo()->prepare('SELECT `response_session_id` AS `id`, MAX(`email_user`) AS `email_user`, `company_name`, `email_resp`, MAX(`response_datetime`) AS `response_datetime` FROM `responses_detailed` WHERE `response_session_id` = ? GROUP BY `response_session_id`, `company_name`, `email_resp` LIMIT 1');
            $st->execute([$responseSessionId]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if (is_array($row)) {
                $row['sess_min'] = substr((string) ($row['response_datetime'] ?? ''), 0, 16);
                return $row;
            }
        } catch (Throwable) {
        }

        return null;
    }

    public function ensureSchema(): void
    {
        try {
            $this->pdo()->exec(
                'CREATE TABLE IF NOT EXISTS `prioridades_resultados` (
                    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `response_session_id` BIGINT UNSIGNED NOT NULL,
                    `group_id` BIGINT UNSIGNED NOT NULL,
                    `group_name` VARCHAR(255) NULL,
                    `prompt_code` VARCHAR(255) NULL,
                    `prompt_final` LONGTEXT NULL,
                    `resultado` LONGTEXT NULL,
                    `result_json` LONGTEXT NULL,
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    KEY `idx_prioridades_group` (`response_session_id`, `group_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        } catch (Throwable) {
        }

        try {
            $this->pdo()->exec(
                'CREATE TABLE IF NOT EXISTS `diag_priority` (
                    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `questionnaire_idx` VARCHAR(32) NOT NULL,
                    `group_id` BIGINT UNSIGNED NOT NULL,
                    `result_json` LONGTEXT NULL,
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `uk_diag_priority_q_g` (`questionnaire_idx`, `group_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        } catch (Throwable) {
        }
    }

    public function storeResult(int $responseSessionId, int $groupId, string $groupName, string $promptCode, string $promptFinal, string $resultado, ?string $resultJson = null): void
    {
        $this->ensureSchema();

        $st = $this->pdo()->prepare(
            'INSERT INTO `prioridades_resultados`
            (`response_session_id`, `group_id`, `group_name`, `prompt_code`, `prompt_final`, `resultado`, `result_json`)
            VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $st->execute([$responseSessionId, $groupId, $groupName, $promptCode, $promptFinal, $resultado, $resultJson]);
    }

    /** @return array<int, array<string, mixed>> */
    public function fetchStoredResults(int $responseSessionId): array
    {
        if ($responseSessionId <= 0 || !$this->tableExists('prioridades_resultados')) {
            return [];
        }
        $st = $this->pdo()->prepare(
            'SELECT * FROM `prioridades_resultados`
             WHERE `response_session_id` = ?
             ORDER BY `updated_at` DESC, `id` DESC'
        );
        $st->execute([$responseSessionId]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @param array<int, array<string, mixed>> $result */
    public function saveDiagPriority(string $questionnaireIdx, int $groupId, array $result): void
    {
        $this->ensureSchema();
        $json = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) {
            $json = '[]';
        }
        $st = $this->pdo()->prepare(
            'INSERT INTO `diag_priority` (`questionnaire_idx`, `group_id`, `result_json`)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE `result_json` = VALUES(`result_json`)'
        );
        $st->execute([$questionnaireIdx, $groupId, $json]);
    }

    /** @return array<int, array<string, mixed>> */
    public function fetchPromptResponsesForSession(int $responseSessionId): array
    {
        if ($responseSessionId <= 0) {
            return [];
        }
        $st = $this->pdo()->prepare(
            'SELECT `id`, `question_label`, `prompt_code`, `prompt`, `prompt_response`
             FROM `responses_detailed`
             WHERE `response_session_id` = ?
               AND `prompt_response` IS NOT NULL
               AND TRIM(`prompt_response`) <> ""
             ORDER BY `id` ASC'
        );
        $st->execute([$responseSessionId]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }


    /** @return array<int, array<string, mixed>> */
    public function fetchAnalyticalResponsesForGroup(int $responseSessionId, array $questionNames): array
    {
        $questionNames = array_values(array_filter(array_map('strval', $questionNames), static fn(string $v): bool => trim($v) !== ''));
        if ($responseSessionId <= 0 || $questionNames === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($questionNames), '?'));
        $params = array_merge([$responseSessionId], $questionNames);
        $sql = 'SELECT `id`, `question_name`, `question_label`, `prompt_code`, `prompt`, `prompt_response`, `response_datetime`
'
             . 'FROM `responses_detailed`
'
             . 'WHERE `response_session_id` = ?
'
             . '  AND `question_name` IN (' . $placeholders . ')
'
             . '  AND `prompt_response` IS NOT NULL
'
             . '  AND TRIM(`prompt_response`) <> ""
'
             . 'ORDER BY `response_datetime` ASC, `id` ASC';
        $st = $this->pdo()->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array<string, mixed>|null */
    public function fetchDiagPriority(string $questionnaireIdx, int $groupId): ?array
    {
        if ($questionnaireIdx === '' || $groupId <= 0 || !$this->tableExists('diag_priority')) {
            return null;
        }
        $st = $this->pdo()->prepare('SELECT `id`, `questionnaire_idx`, `group_id`, `result_json`, `updated_at` FROM `diag_priority` WHERE `questionnaire_idx` = ? AND `group_id` = ? LIMIT 1');
        $st->execute([$questionnaireIdx, $groupId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    private function tableExists(string $table): bool
    {
        try {
            $this->pdo()->query('SELECT 1 FROM `' . $table . '` LIMIT 1');
            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function pdo(): PDO
    {
        return $this->pdo ??= $this->database->pdo();
    }
}
