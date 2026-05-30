<?php
declare(strict_types=1);

namespace App\Papers;

use App\Infra\Database;
use PDO;
use RuntimeException;
use Throwable;

final class PaperRepository
{
    private ?PDO $pdo = null;
    private array $paperColumns = [];

    public function __construct(
        private readonly Database $database,
        private readonly RagRepository $ragRepository
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(?string $query = null, ?string $ragFilter = null): array
    {
        $rows = $this->searchLegacy($query, null, null, null);
        if ($ragFilter !== null && trim($ragFilter) !== '') {
            $rows = array_values(array_filter($rows, fn (array $row): bool => $this->matchesRagFilter($row, trim((string) $ragFilter))));
        }

        return $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function searchLegacy(?string $query, ?string $chapter, ?string $prompt, ?string $sort): array
    {
        $this->assertPapersTable();

        $query = trim((string) $query);
        $chapter = trim((string) $chapter);
        $prompt = trim((string) $prompt);
        $sort = trim((string) $sort);

        $params = [];
        $conditions = ['1=1'];

        if ($query !== '') {
            $searchParts = [];
            foreach (['title', 'journal', 'keywords'] as $column) {
                if (isset($this->paperColumns()[$column])) {
                    $searchParts[] = 'p.' . $column . ' LIKE :q';
                }
            }

            if ($searchParts !== []) {
                $conditions[] = '(' . implode(' OR ', $searchParts) . ')';
                $params[':q'] = '%' . $query . '%';
            }
        }

        if ($chapter !== '' && isset($this->paperColumns()['chapter_code'])) {
            $conditions[] = 'p.chapter_code = :chapter';
            $params[':chapter'] = $chapter;
        }

        if ($prompt !== '' && isset($this->paperColumns()['prompt_code'])) {
            $conditions[] = 'p.prompt_code = :prompt';
            $params[':prompt'] = $prompt;
        }

        $sql = 'SELECT ' . $this->selectFields() . ' FROM papers p WHERE ' . implode(' AND ', $conditions) . ' ' . $this->legacyOrderClause($sort);
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($params);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return array_map(fn (array $row): array => $this->ragRepository->enrich($this->normalizeRow($row)), $rows);
    }

    public function find(int|string|null $id): ?array
    {
        $paperId = (int) $id;
        if ($paperId <= 0) {
            return null;
        }

        $this->assertPapersTable();

        $sql = 'SELECT ' . $this->selectFields() . ' FROM papers p WHERE p.id = :id LIMIT 1';
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute([':id' => $paperId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!is_array($row)) {
            return null;
        }

        return $this->ragRepository->enrich($this->normalizeRow($row));
    }

    public function save(array $data): int
    {
        $this->assertPapersTable();

        $payload = $this->sanitizePayload($data);
        $id = (int) ($data['id'] ?? 0);

        if ($payload['title'] === null || $payload['journal'] === null) {
            throw new RuntimeException('Título e Journal são obrigatórios.');
        }

        if ($id > 0) {
            $assignments = [];
            $params = [':id' => $id];

            foreach ($payload as $column => $value) {
                $assignments[] = sprintf('%s = :%s', $column, $column);
                $params[':' . $column] = $value;
            }

            if ($assignments === []) {
                return $id;
            }

            $sql = 'UPDATE papers SET ' . implode(', ', $assignments) . ' WHERE id = :id';
            $stmt = $this->pdo()->prepare($sql);
            $stmt->execute($params);

            return $id;
        }

        $columns = array_keys($payload);
        $placeholders = array_map(static fn (string $column): string => ':' . $column, $columns);

        $sql = sprintf(
            'INSERT INTO papers (%s) VALUES (%s)',
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        $stmt = $this->pdo()->prepare($sql);
        foreach ($payload as $column => $value) {
            $stmt->bindValue(':' . $column, $value);
        }
        $stmt->execute();

        return (int) $this->pdo()->lastInsertId();
    }

    public function upsertImported(array $paper): string
    {
        $this->assertPapersTable();

        $existingId = $this->findExistingImportedId($paper);
        $paper['file_enabled'] = 1;

        if ($existingId !== null) {
            $paper['id'] = $existingId;
            $this->save($paper);
            return 'updated';
        }

        $this->save($paper);
        return 'created';
    }

    public function delete(int|string|null $id): void
    {
        $paperId = (int) $id;
        if ($paperId <= 0) {
            return;
        }

        $this->assertPapersTable();

        $stmt = $this->pdo()->prepare('DELETE FROM papers WHERE id = :id');
        $stmt->execute([':id' => $paperId]);
    }


/**
 * @return array<string, mixed>
 */
public function chapterTreeData(?string $search = null): array
{
    $this->assertPapersTable();

    if (!isset($this->paperColumns()['chapter_code'])) {
        throw new RuntimeException('A tabela `papers` não possui a coluna `chapter_code` necessária para vincular capítulos.');
    }

    $papers = $this->searchLegacy($search, null, null, null);
    $chapters = $this->chapterOptionsFromRows($papers);

    $grouped = [];
    foreach ($chapters as $chapterCode) {
        $grouped[$chapterCode] = [];
    }
    $grouped['__NONE__'] = [];

    foreach ($papers as $paper) {
        $chapterCode = trim((string) ($paper['chapter_code'] ?? ''));
        if ($chapterCode === '' || !isset($grouped[$chapterCode])) {
            $grouped['__NONE__'][] = $paper;
            continue;
        }

        $grouped[$chapterCode][] = $paper;
    }

    return [
        'chapters' => array_map(function (string $chapterCode) use ($grouped): array {
            return [
                'code' => $chapterCode,
                'label' => $chapterCode,
                'papers' => array_values($grouped[$chapterCode] ?? []),
                'count' => count($grouped[$chapterCode] ?? []),
            ];
        }, $chapters),
        'unassigned' => [
            'code' => '__NONE__',
            'label' => 'Sem capítulo',
            'papers' => array_values($grouped['__NONE__'] ?? []),
            'count' => count($grouped['__NONE__'] ?? []),
        ],
        'papers' => array_map(static function (array $paper): array {
            return [
                'id' => (int) ($paper['id'] ?? 0),
                'title' => (string) ($paper['title'] ?? ''),
                'journal' => (string) ($paper['journal'] ?? ''),
                'chapter_code' => trim((string) ($paper['chapter_code'] ?? '')),
            ];
        }, $papers),
    ];
}

public function assignChapter(int $paperId, ?string $chapterCode): void
{
    $this->assertPapersTable();

    if (!isset($this->paperColumns()['chapter_code'])) {
        throw new RuntimeException('A tabela `papers` não possui a coluna `chapter_code` necessária para vincular capítulos.');
    }

    if ($paperId <= 0) {
        throw new RuntimeException('Paper inválido.');
    }

    $chapter = $chapterCode === null ? null : trim($chapterCode);
    if ($chapter !== null && $chapter !== '' && !in_array($chapter, $this->chapterOptionsFromRows($this->all()), true)) {
        $chapter = strtoupper($chapter);
    }

    $stmt = $this->pdo()->prepare('UPDATE papers SET chapter_code = :chapter_code WHERE id = :id');
    $stmt->bindValue(':chapter_code', $chapter, $chapter === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $stmt->bindValue(':id', $paperId, PDO::PARAM_INT);
    $stmt->execute();
}

/**
 * @param array<int, array<string, mixed>> $rows
 * @return array<int, string>
 */
private function chapterOptionsFromRows(array $rows): array
{
    $defaults = ['CAP_01','CAP_02','CAP_03','CAP_04','CAP_05','CAP_06','CAP_07','CAP_08','CAP_09'];

    foreach ($rows as $row) {
        $chapter = trim((string) ($row['chapter_code'] ?? ''));
        if ($chapter !== '' && !in_array($chapter, $defaults, true)) {
            $defaults[] = $chapter;
        }
    }

    sort($defaults);

    return array_values(array_unique($defaults));
}

    /**
     * @return array<string, int>
     */
    public function stats(): array
    {
        $rows = $this->all();

        $stats = [
            'total' => count($rows),
            'com_cache' => 0,
            'openai' => 0,
            'vetorizados' => 0,
            'usados_em_prompt' => 0,
        ];

        foreach ($rows as $row) {
            if (!empty($row['has_cache'])) {
                $stats['com_cache']++;
            }
            if (!empty($row['has_openai_file'])) {
                $stats['openai']++;
            }
            if (!empty($row['has_vector_store'])) {
                $stats['vetorizados']++;
            }
            if ((int) ($row['usage_count'] ?? 0) > 0) {
                $stats['usados_em_prompt']++;
            }
        }

        return $stats;
    }

    public function availability(): array
    {
        return $this->ragRepository->availability();
    }

    private function findExistingImportedId(array $paper): ?int
    {
        $sourceType = trim((string) ($paper['file_source_type'] ?? ''));
        $sourceValue = trim((string) ($paper['file_source_value'] ?? ''));

        if ($sourceType !== '' && $sourceValue !== '' && isset($this->paperColumns()['file_source_type'], $this->paperColumns()['file_source_value'])) {
            $stmt = $this->pdo()->prepare('SELECT id FROM papers WHERE file_source_type = :type AND file_source_value = :value LIMIT 1');
            $stmt->execute([
                ':type' => $sourceType,
                ':value' => $sourceValue,
            ]);
            $existingId = $stmt->fetchColumn();
            if ($existingId !== false) {
                return (int) $existingId;
            }
        }

        $preferredName = trim((string) ($paper['file_preferred_name'] ?? ''));
        $title = trim((string) ($paper['title'] ?? ''));
        if ($preferredName !== '' && $title !== '' && isset($this->paperColumns()['file_preferred_name'], $this->paperColumns()['title'])) {
            $stmt = $this->pdo()->prepare('SELECT id FROM papers WHERE file_preferred_name = :name AND title = :title LIMIT 1');
            $stmt->execute([
                ':name' => $preferredName,
                ':title' => $title,
            ]);
            $existingId = $stmt->fetchColumn();
            if ($existingId !== false) {
                return (int) $existingId;
            }
        }

        return null;
    }

    private function sanitizePayload(array $data): array
    {
        $columns = $this->paperColumns();

        $linkUrl = $this->nullableString($data['link_url'] ?? null);
        $sourceType = $this->normalizeSourceType($data['file_source_type'] ?? null);
        $sourceValue = $this->nullableString($data['file_source_value'] ?? null);

        if ($sourceValue === null && $linkUrl !== null) {
            $sourceValue = $linkUrl;
        }

        if ($sourceType === null && $sourceValue !== null) {
            $sourceType = $this->inferSourceTypeFromValue($sourceValue);
        }

        $preferredName = $this->nullableString($data['file_preferred_name'] ?? null);
        if ($preferredName === null && $sourceValue !== null) {
            $preferredName = basename(str_replace('\\', '/', $sourceValue));
        }

        $preferredMime = $this->nullableString($data['file_preferred_mime'] ?? null);
        if ($preferredMime === null && $preferredName !== null) {
            $preferredMime = $this->inferMimeFromName($preferredName);
        }

        $resolvedAt = $data['file_last_resolved_at'] ?? null;
        if ($resolvedAt === null && $sourceValue !== null) {
            $resolvedAt = date('Y-m-d H:i:s');
        }

        $payload = [
            'title' => $this->nullableString($data['title'] ?? null),
            'journal' => $this->nullableString($data['journal'] ?? null),
            'key_insight' => $this->nullableString($data['key_insight'] ?? null),
            'citation_count' => (int) ($data['citation_count'] ?? 0),
            'keywords' => $this->nullableString($data['keywords'] ?? null),
            'link_url' => $linkUrl,
            'file_source_type' => $sourceType,
            'file_source_value' => $sourceValue,
            'file_enabled' => isset($data['file_enabled']) ? 1 : 0,
            'file_preferred_name' => $preferredName,
            'file_preferred_mime' => $preferredMime,
            'file_last_resolved_at' => $this->nullableString($resolvedAt),
            'prompt_code' => $this->nullableString($data['prompt_code'] ?? null),
            'chapter_code' => $this->nullableString($data['chapter_code'] ?? null),
        ];

        return array_intersect_key($payload, $columns);
    }

    private function nullableString(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));

        return $text === '' ? null : $text;
    }

    private function normalizeSourceType(mixed $value): ?string
    {
        $type = trim((string) ($value ?? ''));
        if ($type === '') {
            return null;
        }

        $allowed = ['url', 'relative_path', 'local_path', 'cloud_path', 'openai_file_id'];
        return in_array($type, $allowed, true) ? $type : null;
    }

    private function inferSourceTypeFromValue(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (preg_match('~^https?://~i', $value) === 1) {
            return 'url';
        }

        if (preg_match('~^(file-[A-Za-z0-9_-]+)$~', $value) === 1) {
            return 'openai_file_id';
        }

        if (preg_match('~^[A-Za-z]:[\\\\/]~', $value) === 1 || str_starts_with($value, '/') || str_starts_with($value, '\\\\')) {
            return 'local_path';
        }

        return 'relative_path';
    }

    private function inferMimeFromName(string $name): ?string
    {
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        return match ($ext) {
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'txt' => 'text/plain',
            'md' => 'text/markdown',
            'csv' => 'text/csv',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'xlsm' => 'application/vnd.ms-excel.sheet.macroEnabled.12',
            'ppt' => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'json' => 'application/json',
            'xml' => 'application/xml',
            'html' => 'text/html',
            default => null,
        };
    }

    private function matchesRagFilter(array $row, string $filter): bool
    {
        return match ($filter) {
            'with_cache' => !empty($row['has_cache']),
            'without_cache' => empty($row['has_cache']),
            'openai' => !empty($row['has_openai_file']),
            'vector' => !empty($row['has_vector_store']),
            'used' => (int) ($row['usage_count'] ?? 0) > 0,
            'error' => strtolower((string) ($row['cache_status'] ?? '')) === 'error',
            default => true,
        };
    }

    private function assertPapersTable(): void
    {
        $availability = $this->ragRepository->availability();

        if (!$availability['papers']) {
            throw new RuntimeException('Tabela `papers` não encontrada no banco configurado. Ajuste o banco legado.');
        }
    }

    private function selectFields(): string
    {
        $baseFields = [
            'id',
            'title',
            'journal',
            'key_insight',
            'citation_count',
            'keywords',
            'link_url',
            'file_source_type',
            'file_source_value',
            'file_enabled',
            'file_preferred_name',
            'file_preferred_mime',
            'file_last_resolved_at',
            'prompt_code',
            'chapter_code',
            'created_at',
            'updated_at',
        ];

        $fields = [];
        foreach ($baseFields as $column) {
            if (isset($this->paperColumns()[$column])) {
                $fields[] = 'p.' . $column;
            }
        }

        if (!in_array('p.id', $fields, true)) {
            $fields[] = 'p.id';
        }

        return implode(', ', array_unique($fields));
    }

    private function legacyOrderClause(?string $sort): string
    {
        return match ($sort) {
            'cit_desc' => isset($this->paperColumns()['citation_count']) ? 'ORDER BY p.citation_count DESC, p.id DESC' : 'ORDER BY p.id DESC',
            'cit_asc' => isset($this->paperColumns()['citation_count']) ? 'ORDER BY p.citation_count ASC, p.id DESC' : 'ORDER BY p.id DESC',
            default => 'ORDER BY p.id DESC',
        };
    }

    private function normalizeRow(array $row): array
    {
        $row['id'] = isset($row['id']) ? (int) $row['id'] : null;
        $row['citation_count'] = (int) ($row['citation_count'] ?? 0);
        $row['file_enabled'] = (int) ($row['file_enabled'] ?? 1);

        return $row;
    }

    private function paperColumns(): array
    {
        if ($this->paperColumns !== []) {
            return $this->paperColumns;
        }

        $columns = [];
        $pdo = $this->pdo();

        try {
            $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

            if ($driver === 'sqlite') {
                $stmt = $pdo->query('PRAGMA table_info(papers)');
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $name = strtolower((string) ($row['name'] ?? ''));
                    if ($name !== '') {
                        $columns[$name] = true;
                    }
                }
            } else {
                $stmt = $pdo->query('SHOW COLUMNS FROM `papers`');
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $name = strtolower((string) ($row['Field'] ?? ''));
                    if ($name !== '') {
                        $columns[$name] = true;
                    }
                }
            }
        } catch (Throwable $exception) {
            throw new RuntimeException('Não foi possível inspecionar a tabela `papers`: ' . $exception->getMessage(), 0, $exception);
        }

        return $this->paperColumns = $columns;
    }

    private function pdo(): PDO
    {
        return $this->pdo ??= $this->database->pdo();
    }
}
