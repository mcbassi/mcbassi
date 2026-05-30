<?php
declare(strict_types=1);

namespace App\Papers;

use App\Infra\Database;
use PDO;
use Throwable;

final class PromptFlowRepository
{
    private ?PDO $pdo = null;
    private array $tableExists = [];
    private array $columns = [];

    public function __construct(private readonly Database $database)
    {
    }

    public function availability(): array
    {
        $prompts = $this->tableExists('prompts');
        $usage = $this->tableExists('prompt_file_usage');
        $responses = $this->tableExists('responses_detailed');

        return [
            'prompts' => $prompts,
            'prompt_file_usage' => $usage,
            'responses_detailed' => $responses,
            'usage_has_prompt_row_id' => $usage && $this->hasColumn('prompt_file_usage', 'prompt_row_id'),
            'usage_has_cache_id' => $usage && $this->hasColumn('prompt_file_usage', 'cache_id'),
            'prompts_has_assistente' => $prompts && $this->hasColumn('prompts', 'assistente'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function paperContext(array $paper): array
    {
        $cacheId = (int) ($paper['cache_id'] ?? 0);
        $promptCode = trim((string) ($paper['prompt_code'] ?? ''));

        $promptCatalog = $promptCode !== '' ? $this->promptCatalogForCode($promptCode) : [];
        $recentUsage = $cacheId > 0 ? $this->recentUsageByCacheId($cacheId, 12) : [];
        $usageStats = $cacheId > 0 ? $this->usageStatsByCacheId($cacheId) : ['count' => 0, 'latest' => null];

        return [
            'availability' => $this->availability(),
            'prompt_catalog' => $promptCatalog,
            'recent_usage' => $recentUsage,
            'usage_stats' => $usageStats,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $papers
     * @return array<string, mixed>
     */
    public function overview(array $papers): array
    {
        $prompts = $this->allPrompts();
        $promptMap = [];
        foreach ($prompts as $prompt) {
            $assistente = trim((string) ($prompt['assistente'] ?? ''));
            if ($assistente !== '') {
                $promptMap[$assistente][] = $prompt;
            }
        }

        $rows = [];
        foreach ($prompts as $prompt) {
            $assistente = trim((string) ($prompt['assistente'] ?? ''));
            $linkedPapers = array_values(array_filter(
                $papers,
                static fn (array $paper): bool => trim((string) ($paper['prompt_code'] ?? '')) === $assistente
            ));

            $usageCount = 0;
            $cacheReady = 0;
            foreach ($linkedPapers as $paper) {
                $usageCount += (int) ($paper['usage_count'] ?? 0);
                if (!empty($paper['has_cache'])) {
                    $cacheReady++;
                }
            }

            $rows[] = [
                'id' => $prompt['id'] ?? null,
                'assistente' => $assistente,
                'funcao' => $prompt['funcao'] ?? null,
                'descricao' => $prompt['descricao'] ?? null,
                'linked_papers_count' => count($linkedPapers),
                'usage_count' => $usageCount,
                'cache_ready_count' => $cacheReady,
                'linked_papers' => array_slice(array_map(
                    static fn (array $paper): array => [
                        'id' => (int) ($paper['id'] ?? 0),
                        'title' => (string) ($paper['title'] ?? ''),
                        'rag_status_label' => (string) ($paper['rag_status_label'] ?? 'Sem cache'),
                    ],
                    $linkedPapers
                ), 0, 6),
            ];
        }

        usort($rows, static function (array $left, array $right): int {
            return [$right['linked_papers_count'], $right['usage_count'], (string) $left['assistente']]
                <=> [$left['linked_papers_count'], $left['usage_count'], (string) $right['assistente']];
        });

        $linkedPromptCodes = array_values(array_unique(array_filter(array_map(
            static fn (array $paper): string => trim((string) ($paper['prompt_code'] ?? '')),
            $papers
        ))));

        $promptCodesWithCatalog = array_values(array_filter(
            $linkedPromptCodes,
            static fn (string $code): bool => isset($promptMap[$code])
        ));

        $recentUsage = $this->recentUsage(18);

        return [
            'stats' => [
                'total_prompts' => count($prompts),
                'papers_with_prompt_code' => count($linkedPromptCodes),
                'prompt_codes_linked_to_catalog' => count($promptCodesWithCatalog),
                'prompt_usage_rows' => $this->promptUsageRowCount(),
            ],
            'rows' => $rows,
            'recent_usage' => $recentUsage,
            'availability' => $this->availability(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function promptCatalogForCode(string $promptCode): array
    {
        $promptCode = trim($promptCode);
        if ($promptCode === '' || !$this->tableExists('prompts') || !$this->hasColumn('prompts', 'assistente')) {
            return [];
        }

        $fields = ['id', 'assistente'];
        foreach (['funcao', 'descricao', 'prompt'] as $column) {
            if ($this->hasColumn('prompts', $column)) {
                $fields[] = $column;
            }
        }

        $sql = 'SELECT ' . implode(', ', $fields) . ' FROM prompts WHERE assistente = :assistente ORDER BY id DESC';

        try {
            $stmt = $this->pdo()->prepare($sql);
            $stmt->execute([':assistente' => $promptCode]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function recentUsageByCacheId(int $cacheId, int $limit = 10): array
    {
        if ($cacheId <= 0 || !$this->tableExists('prompt_file_usage') || !$this->hasColumn('prompt_file_usage', 'cache_id')) {
            return [];
        }

        $usageFields = ['u.cache_id'];
        foreach ([
            'prompt_row_id',
            'paper_title',
            'source_type',
            'source_value',
            'openai_file_id',
            'execution_mode',
            'company_name',
            'email_resp',
            'sess_min',
            'response_detailed_id',
        ] as $column) {
            if ($this->hasColumn('prompt_file_usage', $column)) {
                $usageFields[] = 'u.' . $column;
            }
        }

        if ($this->hasColumn('prompt_file_usage', 'created_at')) {
            $usageFields[] = 'u.created_at AS used_at';
            $orderBy = 'u.created_at DESC';
        } elseif ($this->hasColumn('prompt_file_usage', 'used_at')) {
            $usageFields[] = 'u.used_at';
            $orderBy = 'u.used_at DESC';
        } else {
            $usageFields[] = 'NULL AS used_at';
            $orderBy = 'u.cache_id DESC';
        }

        $join = '';
        if ($this->tableExists('prompts') && $this->hasColumn('prompt_file_usage', 'prompt_row_id')) {
            $join = ' LEFT JOIN prompts p ON p.id = u.prompt_row_id';
            foreach (['id', 'assistente', 'funcao', 'descricao'] as $column) {
                if ($this->hasColumn('prompts', $column)) {
                    $usageFields[] = 'p.' . $column . ' AS prompt_' . $column;
                }
            }
        }

        $sql = 'SELECT ' . implode(', ', array_unique($usageFields))
            . ' FROM prompt_file_usage u'
            . $join
            . ' WHERE u.cache_id = :cache_id ORDER BY ' . $orderBy . ' LIMIT ' . (int) $limit;

        try {
            $stmt = $this->pdo()->prepare($sql);
            $stmt->execute([':cache_id' => $cacheId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function usageStatsByCacheId(int $cacheId): array
    {
        if ($cacheId <= 0 || !$this->tableExists('prompt_file_usage') || !$this->hasColumn('prompt_file_usage', 'cache_id')) {
            return ['count' => 0, 'latest' => null];
        }

        $fields = ['COUNT(*) AS qty'];
        if ($this->hasColumn('prompt_file_usage', 'created_at')) {
            $fields[] = 'MAX(created_at) AS latest';
        } elseif ($this->hasColumn('prompt_file_usage', 'used_at')) {
            $fields[] = 'MAX(used_at) AS latest';
        } else {
            $fields[] = 'NULL AS latest';
        }

        try {
            $stmt = $this->pdo()->prepare('SELECT ' . implode(', ', $fields) . ' FROM prompt_file_usage WHERE cache_id = :cache_id');
            $stmt->execute([':cache_id' => $cacheId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            return [
                'count' => (int) ($row['qty'] ?? 0),
                'latest' => $row['latest'] ?? null,
            ];
        } catch (Throwable) {
            return ['count' => 0, 'latest' => null];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function recentUsage(int $limit = 20): array
    {
        if (!$this->tableExists('prompt_file_usage')) {
            return [];
        }

        $fields = ['u.cache_id'];
        foreach ([
            'prompt_row_id',
            'paper_title',
            'source_type',
            'source_value',
            'openai_file_id',
            'execution_mode',
            'company_name',
            'email_resp',
            'sess_min',
        ] as $column) {
            if ($this->hasColumn('prompt_file_usage', $column)) {
                $fields[] = 'u.' . $column;
            }
        }

        if ($this->hasColumn('prompt_file_usage', 'created_at')) {
            $fields[] = 'u.created_at AS used_at';
            $orderBy = 'u.created_at DESC';
        } elseif ($this->hasColumn('prompt_file_usage', 'used_at')) {
            $fields[] = 'u.used_at';
            $orderBy = 'u.used_at DESC';
        } else {
            $fields[] = 'NULL AS used_at';
            $orderBy = 'u.cache_id DESC';
        }

        $join = '';
        if ($this->tableExists('prompts') && $this->hasColumn('prompt_file_usage', 'prompt_row_id')) {
            $join = ' LEFT JOIN prompts p ON p.id = u.prompt_row_id';
            foreach (['id', 'assistente', 'funcao'] as $column) {
                if ($this->hasColumn('prompts', $column)) {
                    $fields[] = 'p.' . $column . ' AS prompt_' . $column;
                }
            }
        }

        $sql = 'SELECT ' . implode(', ', array_unique($fields))
            . ' FROM prompt_file_usage u'
            . $join
            . ' ORDER BY ' . $orderBy
            . ' LIMIT ' . (int) $limit;

        try {
            return $this->pdo()->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function allPrompts(): array
    {
        if (!$this->tableExists('prompts')) {
            return [];
        }

        $fields = ['id'];
        foreach (['assistente', 'funcao', 'descricao', 'prompt'] as $column) {
            if ($this->hasColumn('prompts', $column)) {
                $fields[] = $column;
            }
        }

        try {
            return $this->pdo()->query('SELECT ' . implode(', ', array_unique($fields)) . ' FROM prompts ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
            return [];
        }
    }

    public function promptUsageRowCount(): int
    {
        if (!$this->tableExists('prompt_file_usage')) {
            return 0;
        }

        try {
            return (int) $this->pdo()->query('SELECT COUNT(*) FROM prompt_file_usage')->fetchColumn();
        } catch (Throwable) {
            return 0;
        }
    }

    private function pdo(): PDO
    {
        return $this->pdo ??= $this->database->pdo();
    }

    private function tableExists(string $table): bool
    {
        if (array_key_exists($table, $this->tableExists)) {
            return $this->tableExists[$table];
        }

        try {
            $driver = (string) $this->pdo()->getAttribute(PDO::ATTR_DRIVER_NAME);

            if ($driver === 'sqlite') {
                $stmt = $this->pdo()->prepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name = :name");
                $stmt->execute([':name' => $table]);
                return $this->tableExists[$table] = (bool) $stmt->fetchColumn();
            }

            $stmt = $this->pdo()->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table LIMIT 1');
            $stmt->execute([':table' => $table]);

            return $this->tableExists[$table] = (bool) $stmt->fetchColumn();
        } catch (Throwable) {
            return $this->tableExists[$table] = false;
        }
    }

    private function hasColumn(string $table, string $column): bool
    {
        return array_key_exists($column, $this->columns($table));
    }

    /**
     * @return array<string, bool>
     */
    private function columns(string $table): array
    {
        if (array_key_exists($table, $this->columns)) {
            return $this->columns[$table];
        }

        $columns = [];
        if (!$this->tableExists($table)) {
            return $this->columns[$table] = $columns;
        }

        try {
            $driver = (string) $this->pdo()->getAttribute(PDO::ATTR_DRIVER_NAME);

            if ($driver === 'sqlite') {
                $stmt = $this->pdo()->query('PRAGMA table_info(' . $table . ')');
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $name = strtolower((string) ($row['name'] ?? ''));
                    if ($name !== '') {
                        $columns[$name] = true;
                    }
                }
            } else {
                $stmt = $this->pdo()->query('SHOW COLUMNS FROM `' . $table . '`');
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $name = strtolower((string) ($row['Field'] ?? ''));
                    if ($name !== '') {
                        $columns[$name] = true;
                    }
                }
            }
        } catch (Throwable) {
            $columns = [];
        }

        return $this->columns[$table] = $columns;
    }
}
