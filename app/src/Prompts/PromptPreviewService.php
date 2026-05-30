<?php
declare(strict_types=1);

namespace App\Prompts;

use App\Infra\Database;
use PDO;
use Throwable;

final class PromptPreviewService
{
    private ?PDO $pdo = null;

    public function __construct(private readonly Database $database)
    {
    }

    /**
     * @param array<string, mixed> $promptRow
     * @return array<string, mixed>
     */
    public function build(array $promptRow, string $emailUser): array
    {
        $prompt = (string) ($promptRow['prompt'] ?? '');

        if ($emailUser === '') {
            return [
                'available' => false,
                'source_label' => 'Sem usuário autenticado',
                'resolved_prompt' => $prompt,
                'unresolved_markers' => [],
                'marker_values' => [],
            ];
        }

        $source = $this->latestSource($emailUser);
        if ($source === null) {
            return [
                'available' => false,
                'source_label' => 'Sem respostas salvas para prévia',
                'resolved_prompt' => $prompt,
                'unresolved_markers' => $this->extractMarkers($prompt),
                'marker_values' => [],
            ];
        }

        $answers = $this->answersForSource($source, $emailUser);
        $papers = $this->paperTitles();
        [$resolved, $values, $missing] = $this->replaceMarkers($prompt, $answers, $papers);

        $sourceLabel = trim((string) ($source['company_name'] ?? ''));
        if ($sourceLabel === '') {
            $sourceLabel = 'Última versão do usuário';
        }
        $dateLabel = trim((string) ($source['response_datetime'] ?? ''));
        if ($dateLabel !== '') {
            $sourceLabel .= ' · ' . $dateLabel;
        }

        return [
            'available' => true,
            'source_label' => $sourceLabel,
            'resolved_prompt' => $resolved,
            'unresolved_markers' => $missing,
            'marker_values' => $values,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function latestSource(string $emailUser): ?array
    {
        if ($this->tableExists('response_sessions')) {
            $stmt = $this->pdo()->prepare('
                SELECT id, company_name, response_datetime
                FROM response_sessions
                WHERE email_user = :email_user
                ORDER BY response_datetime DESC, id DESC
                LIMIT 1
            ');
            $stmt->execute([':email_user' => $emailUser]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (is_array($row)) {
                return $row;
            }
        }

        if ($this->tableExists('responses_detailed')) {
            $stmt = $this->pdo()->prepare('
                SELECT company_name, MAX(response_datetime) AS response_datetime
                FROM responses_detailed
                WHERE email_user = :email_user
                GROUP BY company_name
                ORDER BY response_datetime DESC
                LIMIT 1
            ');
            $stmt->execute([':email_user' => $emailUser]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (is_array($row)) {
                return $row;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $source
     * @return array<string, string>
     */
    private function answersForSource(array $source, string $emailUser): array
    {
        $answers = [];

        if (isset($source['id']) && $this->columnExists('responses_detailed', 'response_session_id')) {
            $stmt = $this->pdo()->prepare('
                SELECT question_name, answer
                FROM responses_detailed
                WHERE response_session_id = :session_id
                ORDER BY id ASC
            ');
            $stmt->execute([':session_id' => (int) $source['id']]);

            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $name = trim((string) ($row['question_name'] ?? ''));
                if ($name === '') {
                    continue;
                }
                $answers[$name] = trim((string) ($row['answer'] ?? ''));
                $answers[mb_strtolower($name)] = trim((string) ($row['answer'] ?? ''));
            }

            return $answers;
        }

        $dateTime = trim((string) ($source['response_datetime'] ?? ''));
        if ($dateTime === '' || !$this->tableExists('responses_detailed')) {
            return $answers;
        }

        $stmt = $this->pdo()->prepare('
            SELECT question_name, answer
            FROM responses_detailed
            WHERE email_user = :email_user
              AND response_datetime = :response_datetime
            ORDER BY id ASC
        ');
        $stmt->execute([
            ':email_user' => $emailUser,
            ':response_datetime' => $dateTime,
        ]);

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $name = trim((string) ($row['question_name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $answers[$name] = trim((string) ($row['answer'] ?? ''));
            $answers[mb_strtolower($name)] = trim((string) ($row['answer'] ?? ''));
        }

        return $answers;
    }

    /**
     * @return array<string, string>
     */
    private function paperTitles(): array
    {
        if (!$this->tableExists('papers')) {
            return [];
        }

        $rows = $this->pdo()->query('SELECT COALESCE(title, "") AS title FROM papers WHERE title IS NOT NULL AND title <> ""')
            ->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $titles = [];
        foreach ($rows as $row) {
            $title = trim((string) ($row['title'] ?? ''));
            if ($title === '') {
                continue;
            }
            $titles[mb_strtolower($title)] = $title;
        }

        return $titles;
    }

    /**
     * @param array<string, string> $answers
     * @param array<string, string> $papers
     * @return array{0:string,1:array<string,string>,2:array<int,string>}
     */
    private function replaceMarkers(string $prompt, array $answers, array $papers): array
    {
        $values = [];
        $missing = [];

        $resolved = preg_replace_callback('/<<([^>]+)>>/u', function (array $matches) use ($answers, $papers, &$values, &$missing): string {
            $raw = trim((string) ($matches[1] ?? ''));
            $key = mb_strtolower($raw);

            if (array_key_exists($raw, $answers) || array_key_exists($key, $answers)) {
                $value = trim((string) ($answers[$raw] ?? $answers[$key] ?? ''));
                $values[$raw] = $value === '' ? '[sem resposta]' : $value;
                return $value === '' ? '[sem resposta]' : $value;
            }

            if (array_key_exists($key, $papers)) {
                $value = '[paper] ' . $papers[$key];
                $values[$raw] = $value;
                return $value;
            }

            $missing[] = $raw;
            $values[$raw] = '[não resolvido]';
            return '<<' . $raw . '>>';
        }, $prompt);

        return [$resolved ?? $prompt, $values, array_values(array_unique($missing))];
    }

    /**
     * @return array<int, string>
     */
    private function extractMarkers(string $prompt): array
    {
        preg_match_all('/<<([^>]+)>>/u', $prompt, $matches);
        return array_values(array_unique(array_map('trim', $matches[1] ?? [])));
    }

    private function tableExists(string $table): bool
    {
        try {
            $this->pdo()->query('SELECT 1 FROM ' . $table . ' LIMIT 1');
            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function columnExists(string $table, string $column): bool
    {
        try {
            $stmt = $this->pdo()->query('SHOW COLUMNS FROM ' . $table . ' LIKE ' . $this->pdo()->quote($column));
            return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable) {
            return false;
        }
    }

    private function pdo(): PDO
    {
        if ($this->pdo instanceof PDO) {
            return $this->pdo;
        }

        $this->pdo = $this->database->pdo();
        return $this->pdo;
    }
}
