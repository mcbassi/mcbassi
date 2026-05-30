<?php
declare(strict_types=1);

namespace App\Prompts;

use App\Infra\Database;
use PDO;
use RuntimeException;
use Throwable;

final class PromptRepository
{
    private ?PDO $pdo = null;

    public function __construct(private readonly Database $database)
    {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function search(array $filters = []): array
    {
        $this->assertPromptsTable();

        $query = trim((string) ($filters['q'] ?? ''));
        $assistente = trim((string) ($filters['assistente'] ?? ''));
        $funcao = trim((string) ($filters['funcao'] ?? ''));
        $section = trim((string) ($filters['section'] ?? ''));
        $hasSql = trim((string) ($filters['has_sql'] ?? ''));

        $conditions = ['1=1'];
        $params = [];

        if ($query !== '') {
            $conditions[] = '(p.assistente LIKE :q OR p.funcao LIKE :q OR p.descricao LIKE :q OR p.prompt LIKE :q)';
            $params[':q'] = '%' . $query . '%';
        }

        if ($assistente !== '') {
            $conditions[] = 'TRIM(p.assistente) = TRIM(:assistente)';
            $params[':assistente'] = $assistente;
        }

        if ($funcao !== '') {
            $conditions[] = 'TRIM(p.funcao) = TRIM(:funcao)';
            $params[':funcao'] = $funcao;
        }

        if ($section !== '') {
            $conditions[] = 'TRIM(COALESCE(f.section_code, "")) = TRIM(:section)';
            $params[':section'] = $section;
        }

        if ($hasSql === 'yes') {
            $conditions[] = 'p.prompt LIKE :sql_marker_yes';
            $params[':sql_marker_yes'] = '%EXECUTAR SQL=%';
        } elseif ($hasSql === 'no') {
            $conditions[] = 'p.prompt NOT LIKE :sql_marker_no';
            $params[':sql_marker_no'] = '%EXECUTAR SQL=%';
        }

        $sql = '
            SELECT
                p.id,
                p.assistente,
                p.funcao,
                p.descricao,
                p.prompt,
                f.section_code,
                f.sort_order,
                CASE
                    WHEN LOCATE("EXECUTAR SQL=", p.prompt) > 0 THEN 1
                    ELSE 0
                END AS has_sql
            FROM prompts p
            LEFT JOIN form_fields f
                ON TRIM(f.prompt_code) = TRIM(p.assistente)
            WHERE ' . implode(' AND ', $conditions) . '
            ORDER BY
                (f.sort_order IS NULL) ASC,
                f.sort_order ASC,
                p.assistente ASC,
                p.funcao ASC
        ';

        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($rows as &$row) {
            $row = $this->hydrateRow($row);
        }

        return $rows;
    }

    public function findByAssistente(string $assistente): ?array
    {
        $this->assertPromptsTable();

        $assistente = trim($assistente);
        if ($assistente === '') {
            return null;
        }

        $stmt = $this->pdo()->prepare('
            SELECT
                p.*,
                f.section_code AS ff_section_code,
                f.sort_order AS ff_sort_order,
                CASE
                    WHEN LOCATE("EXECUTAR SQL=", COALESCE(p.prompt, "")) > 0 THEN 1
                    ELSE 0
                END AS has_sql
            FROM prompts p
            LEFT JOIN form_fields f
                ON TRIM(f.prompt_code) = TRIM(p.assistente)
            WHERE TRIM(p.assistente) = TRIM(:assistente)
            ORDER BY
                (f.sort_order IS NULL) ASC,
                f.sort_order ASC,
                p.id ASC
            LIMIT 1
        ');
        $stmt->execute([':assistente' => $assistente]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->hydrateRow($row) : null;
    }

    public function find(int $id): ?array
    {
        $this->assertPromptsTable();

        $stmt = $this->pdo()->prepare('SELECT * FROM prompts WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!is_array($row)) {
            return null;
        }

        return $this->hydrateRow($row);
    }

    public function save(array $payload): int
    {
        $this->assertPromptsTable();

        $id = (int) ($payload['id'] ?? 0);
        $assistente = trim((string) ($payload['assistente'] ?? ''));
        $funcao = trim((string) ($payload['funcao'] ?? ''));
        $descricao = trim((string) ($payload['descricao'] ?? ''));
        $prompt = trim((string) ($payload['prompt'] ?? ''));

        if ($assistente === '' || $funcao === '' || $prompt === '') {
            throw new RuntimeException('Prompt Code, Função e Prompt são obrigatórios.');
        }

        if ($id > 0) {
            $stmt = $this->pdo()->prepare('
                UPDATE prompts
                SET assistente = :assistente, funcao = :funcao, descricao = :descricao, prompt = :prompt
                WHERE id = :id
            ');
            $stmt->execute([
                ':assistente' => $assistente,
                ':funcao' => $funcao,
                ':descricao' => $descricao,
                ':prompt' => $prompt,
                ':id' => $id,
            ]);

            return $id;
        }

        $stmt = $this->pdo()->prepare('
            INSERT INTO prompts (assistente, funcao, descricao, prompt)
            VALUES (:assistente, :funcao, :descricao, :prompt)
        ');
        $stmt->execute([
            ':assistente' => $assistente,
            ':funcao' => $funcao,
            ':descricao' => $descricao,
            ':prompt' => $prompt,
        ]);

        return (int) $this->pdo()->lastInsertId();
    }

    public function delete(int $id): void
    {
        $this->assertPromptsTable();

        if ($id <= 0) {
            throw new RuntimeException('ID inválido para exclusão.');
        }

        $stmt = $this->pdo()->prepare('DELETE FROM prompts WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function formFields(): array
    {
        if (!$this->tableExists('form_fields')) {
            return [];
        }

        $stmt = $this->pdo()->query('
            SELECT
                COALESCE(section_code, "") AS section_code,
                COALESCE(name, "") AS name
            FROM form_fields
            WHERE name IS NOT NULL
              AND name <> ""
            ORDER BY
                (sort_order IS NULL) ASC,
                sort_order ASC,
                name ASC
        ');

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return array<int, array<string, string|int>>
     */
    public function papers(): array
    {
        if (!$this->tableExists('papers')) {
            return [];
        }

        $stmt = $this->pdo()->query('
            SELECT id, COALESCE(title, "") AS title
            FROM papers
            WHERE title IS NOT NULL
              AND title <> ""
            ORDER BY title ASC
        ');

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return array<int, array<string, string|int>>
     */
    public function sqlOptions(): array
    {
        if (!$this->tableExists('datasmart_query_versions')) {
            return [];
        }

        $sql = '
            SELECT q.id, q.select_desc, q.sql_text
            FROM datasmart_query_versions q
            INNER JOIN (
                SELECT select_desc, MAX(id) AS max_id
                FROM datasmart_query_versions
                WHERE select_desc IS NOT NULL
                  AND select_desc <> ""
                GROUP BY select_desc
            ) latest
                ON latest.select_desc = q.select_desc
               AND latest.max_id = q.id
            ORDER BY q.select_desc ASC
        ';

        $stmt = $this->pdo()->query($sql);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }


    /**
     * @return array<string, mixed>
     */
    public function metadataForPromptCode(string $assistente): array
    {
        if ($assistente === '' || !$this->tableExists('form_fields')) {
            return [
                'section_code' => '',
                'sort_order' => '',
                'question_name' => '',
                'required' => 0,
                'type' => '',
            ];
        }

        $stmt = $this->pdo()->prepare('
            SELECT
                COALESCE(section_code, "") AS section_code,
                COALESCE(sort_order, "") AS sort_order,
                COALESCE(name, "") AS question_name,
                COALESCE(required, 0) AS required,
                COALESCE(type, "") AS type
            FROM form_fields
            WHERE TRIM(prompt_code) = TRIM(:assistente)
            ORDER BY
                (sort_order IS NULL) ASC,
                sort_order ASC,
                id ASC
            LIMIT 1
        ');
        $stmt->execute([':assistente' => $assistente]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : [
            'section_code' => '',
            'sort_order' => '',
            'question_name' => '',
            'required' => 0,
            'type' => '',
        ];
    }

    public function stats(): array
    {
        $rows = $this->search();

        $total = count($rows);
        $withSection = 0;
        $withSql = 0;
        $withMarkers = 0;

        foreach ($rows as $row) {
            if (trim((string) ($row['section_code'] ?? '')) !== '') {
                $withSection++;
            }
            if ((int) ($row['has_sql'] ?? 0) === 1) {
                $withSql++;
            }
            if ((int) ($row['marker_count'] ?? 0) > 0) {
                $withMarkers++;
            }
        }

        return [
            'total' => $total,
            'with_section' => $withSection,
            'with_sql' => $withSql,
            'with_markers' => $withMarkers,
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function filterOptions(): array
    {
        $rows = $this->search();

        $assistentes = [];
        $funcoes = [];
        $sections = [];

        foreach ($rows as $row) {
            $assistente = trim((string) ($row['assistente'] ?? ''));
            $funcao = trim((string) ($row['funcao'] ?? ''));
            $section = trim((string) ($row['section_code'] ?? ''));

            if ($assistente !== '') {
                $assistentes[$assistente] = $assistente;
            }
            if ($funcao !== '') {
                $funcoes[$funcao] = $funcao;
            }
            if ($section !== '') {
                $sections[$section] = $section;
            }
        }

        natcasesort($assistentes);
        natcasesort($funcoes);
        natcasesort($sections);

        return [
            'assistentes' => array_values($assistentes),
            'funcoes' => array_values($funcoes),
            'sections' => array_values($sections),
        ];
    }


    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function hydrateRow(array $row): array
    {
        $promptFullText = trim((string) ($row['prompt'] ?? ''));
        $sqlBlockText = '';
        $promptBaseText = $promptFullText;

        [$sqlFromPrompt, $baseFromPrompt] = $this->extractSqlBlock($promptFullText);
        if ($sqlFromPrompt !== '') {
            $sqlBlockText = $sqlFromPrompt;
            $promptBaseText = $baseFromPrompt;
        }

        $separateSqlText = $this->firstNonEmpty($row, [
            'sql_text',
            'sql',
            'query_sql',
            'select_sql',
            'sql_statement',
            'sql_query',
            'sp_sql',
            'procedure_sql',
        ]);
        $separateSqlDesc = $this->firstNonEmpty($row, [
            'sql_desc',
            'select_desc',
            'query_desc',
            'sql_name',
            'sql_label',
        ]);

        if ($sqlBlockText === '' && $separateSqlText !== '') {
            $sqlBlockText = $separateSqlDesc !== ''
                ? '-- DESC: ' . $separateSqlDesc . "\n" . $separateSqlText
                : $separateSqlText;

            $promptBaseText = $promptFullText;
            $promptFullText = trim($promptBaseText . "\n\nEXECUTAR SQL=\n" . $sqlBlockText);
        }

        $markers = $this->extractMarkers($promptFullText);

        $row['section_code'] = $row['section_code'] ?? $row['ff_section_code'] ?? '';
        $row['sort_order'] = $row['sort_order'] ?? $row['ff_sort_order'] ?? '';
        $row['prompt_full_text'] = $promptFullText;
        $row['prompt'] = $promptBaseText;
        $row['prompt_base_text'] = $promptBaseText;
        $row['sql_block_text'] = $sqlBlockText;
        $row['has_sql'] = $sqlBlockText !== '' ? 1 : (int) ($row['has_sql'] ?? 0);
        $row['marker_names'] = $markers;
        $row['marker_count'] = count($markers);
        $row['sql_desc'] = $separateSqlDesc !== '' ? $separateSqlDesc : $this->extractSqlDescHint($promptFullText);
        $row['prompt_preview'] = mb_substr(trim(preg_replace('/\s+/u', ' ', $promptBaseText) ?? ''), 0, 220);

        return $row;
    }

    private function firstNonEmpty(array $row, array $candidates): string
    {
        foreach ($candidates as $candidate) {
            if (!array_key_exists($candidate, $row)) {
                continue;
            }

            $value = trim((string) $row[$candidate]);
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    public function extractSqlDescHint(string $prompt): string
    {
        [$sql, ] = $this->extractSqlBlock($prompt);
        if ($sql === '') {
            return '';
        }

        $firstLine = trim((string) strtok($sql, "\r\n"));
        if (preg_match('/^\s*--\s*DESC\s*:\s*(.+)\s*$/i', $firstLine, $matches) === 1) {
            return trim((string) $matches[1]);
        }

        return '';
    }

    /**
     * @return array{0:string,1:string}
     */
    private function extractSqlBlock(string $prompt): array
    {
        $marker = 'EXECUTAR SQL=';
        $position = strrpos($prompt, $marker);

        if ($position === false) {
            return ['', $prompt];
        }

        $base = rtrim(substr($prompt, 0, $position));
        $sql = trim(substr($prompt, $position + strlen($marker)));

        return [$sql, $base];
    }

    private function countMarkers(string $prompt): int
    {
        return count($this->extractMarkers($prompt));
    }

    /**
     * @return array<int, string>
     */
    private function extractMarkers(string $prompt): array
    {
        preg_match_all('/<<([^>]+)>>/u', $prompt, $matches);
        $markers = array_map(static fn (string $item): string => trim($item), $matches[1] ?? []);
        $markers = array_values(array_filter($markers, static fn (string $item): bool => $item !== ''));

        return array_values(array_unique($markers));
    }

    private function assertPromptsTable(): void
    {
        if ($this->tableExists('prompts')) {
            return;
        }

        throw new RuntimeException('Tabela prompts não encontrada na base atual.');
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

    private function pdo(): PDO
    {
        if ($this->pdo instanceof PDO) {
            return $this->pdo;
        }

        $this->pdo = $this->database->pdo();

        return $this->pdo;
    }
}
