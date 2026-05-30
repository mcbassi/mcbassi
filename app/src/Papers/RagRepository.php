<?php
declare(strict_types=1);

namespace App\Papers;

use App\Infra\Database;
use PDO;
use Throwable;

final class RagRepository
{
    private ?PDO $pdo = null;
    private array $tableExists = [];
    private array $columns = [];

    public function __construct(private readonly Database $database)
    {
    }

    public function availability(): array
    {
        $papers = $this->tableExists('papers');
        $cache = $this->tableExists('papers_file_cache');
        $usage = $this->tableExists('prompt_file_usage');

        return [
            'papers' => $papers,
            'papers_file_cache' => $cache,
            'prompt_file_usage' => $usage,
            'cache_has_paper_id' => $cache && $this->hasColumn('papers_file_cache', 'paper_id'),
            'usage_has_cache_id' => $usage && $this->hasColumn('prompt_file_usage', 'cache_id'),
            'cache_has_vector_store' => $cache && $this->hasColumn('papers_file_cache', 'vector_store_id'),
        ];
    }

    public function enrich(array $paper): array
    {
        if (!$this->tableExists('papers_file_cache') || !$this->hasColumn('papers_file_cache', 'paper_id')) {
            return $this->decorateWithDerivedStatus($paper);
        }

        $sql = 'SELECT * FROM papers_file_cache WHERE paper_id = :paper_id LIMIT 1';
        try {
            $stmt = $this->pdo()->prepare($sql);
            $stmt->execute([':paper_id' => (int) ($paper['id'] ?? 0)]);
            $cache = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (Throwable) {
            $cache = null;
        }

        if (is_array($cache)) {
            $paper = array_merge($paper, $cache);
        }

        if (isset($paper['cache_id']) && $paper['cache_id'] !== null && $this->tableExists('prompt_file_usage') && $this->hasColumn('prompt_file_usage', 'cache_id')) {
            $paper = array_merge($paper, $this->usageStatsByCacheId((int) $paper['cache_id']));
        }

        return $this->decorateWithDerivedStatus($paper);
    }

    public function usageStatsByCacheId(int $cacheId): array
    {
        if ($cacheId <= 0 || !$this->tableExists('prompt_file_usage') || !$this->hasColumn('prompt_file_usage', 'cache_id')) {
            return ['usage_count' => 0, 'usage_last_at' => null];
        }

        $fields = ['COUNT(*) AS usage_count'];
        if ($this->hasColumn('prompt_file_usage', 'created_at')) {
            $fields[] = 'MAX(created_at) AS usage_last_at';
        } elseif ($this->hasColumn('prompt_file_usage', 'used_at')) {
            $fields[] = 'MAX(used_at) AS usage_last_at';
        } else {
            $fields[] = 'NULL AS usage_last_at';
        }

        $sql = 'SELECT ' . implode(', ', $fields) . ' FROM prompt_file_usage WHERE cache_id = :cache_id';

        try {
            $stmt = $this->pdo()->prepare($sql);
            $stmt->execute([':cache_id' => $cacheId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
            $row = [];
        }

        return [
            'usage_count' => (int) ($row['usage_count'] ?? 0),
            'usage_last_at' => $row['usage_last_at'] ?? null,
        ];
    }

    public function decorateWithDerivedStatus(array $paper): array
    {
        $cacheStatus = strtolower(trim((string) ($paper['cache_status'] ?? '')));
        $hasCache = !empty($paper['cache_id']);
        $hasOpenAi = trim((string) ($paper['openai_file_id'] ?? '')) !== '';
        $hasVector = trim((string) ($paper['vector_store_id'] ?? '')) !== '';
        $existsFlag = (string) ($paper['exists_flag'] ?? '') !== '' ? (int) $paper['exists_flag'] : null;

        $label = 'Sem cache';
        $tone = 'muted';

        if ($hasVector) {
            $label = 'Vetorizado';
            $tone = 'success';
        } elseif ($hasOpenAi) {
            $label = 'Arquivo OpenAI';
            $tone = 'info';
        } elseif ($cacheStatus !== '') {
            $label = ucfirst($cacheStatus);
            $tone = $cacheStatus === 'ready' ? 'success' : ($cacheStatus === 'error' ? 'danger' : 'warning');
        } elseif ($hasCache) {
            $label = 'Cache local';
            $tone = 'info';
        }

        if ($existsFlag === 0) {
            $label = 'Cache ausente';
            $tone = 'danger';
        }

        $paper['rag_status_label'] = $label;
        $paper['rag_status_tone'] = $tone;
        $paper['has_cache'] = $hasCache;
        $paper['has_openai_file'] = $hasOpenAi;
        $paper['has_vector_store'] = $hasVector;
        $paper['usage_count'] = (int) ($paper['usage_count'] ?? 0);

        return $paper;
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
