<?php
declare(strict_types=1);

namespace App\TelaSql;

use App\Infra\Database;
use PDO;
use RuntimeException;
use Throwable;

final class SqlCatalogRepository
{
    private ?PDO $pdo = null;

    public function __construct(private readonly Database $database)
    {
    }

    /** @return array<int,array<string,mixed>> */
    public function all(string $search = '', bool $onlyActive = true): array
    {
        $this->ensureSchema();
        if ($search !== '') {
            $like = '%' . $search . '%';
            $st = $this->pdo()->prepare("\n                SELECT id, slug, title, description, db_name, is_active, current_version, updated_at\n                FROM datasmart_queries\n                WHERE (slug LIKE ? OR title LIKE ?)\n                  AND (? = 0 OR is_active = 1)\n                ORDER BY updated_at DESC\n                LIMIT 200\n            ");
            $st->execute([$like, $like, $onlyActive ? 1 : 0]);
            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        $st = $this->pdo()->prepare("\n            SELECT id, slug, title, description, db_name, is_active, current_version, updated_at\n            FROM datasmart_queries\n            WHERE (? = 0 OR is_active = 1)\n            ORDER BY updated_at DESC\n            LIMIT 200\n        ");
        $st->execute([$onlyActive ? 1 : 0]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array<string,mixed>|null */
    public function getBySlug(string $slug): ?array
    {
        $this->ensureSchema();
        $slug = strtoupper(trim($slug));
        if ($slug === '') {
            return null;
        }

        $q = $this->pdo()->prepare('SELECT * FROM datasmart_queries WHERE slug = ? LIMIT 1');
        $q->execute([$slug]);
        $main = $q->fetch(PDO::FETCH_ASSOC);
        if (!is_array($main)) {
            return null;
        }

        $v = $this->pdo()->prepare("\n            SELECT *\n            FROM datasmart_query_versions\n            WHERE query_id = ? AND version = ?\n            LIMIT 1\n        ");
        $v->execute([(int) $main['id'], (int) $main['current_version']]);
        $ver = $v->fetch(PDO::FETCH_ASSOC);
        if (is_array($ver)) {
            foreach (['tables_json', 'tags_json', 'params_json'] as $key) {
                if (isset($ver[$key]) && $ver[$key] !== null && $ver[$key] !== '') {
                    $decoded = json_decode((string) $ver[$key], true);
                    $ver[$key] = is_array($decoded) ? $decoded : null;
                }
            }
        }

        return [
            'query' => $main,
            'version' => is_array($ver) ? $ver : null,
        ];
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    public function save(array $payload): array
    {
        $this->ensureSchema();

        $slug = strtoupper(trim((string) ($payload['slug'] ?? '')));
        $title = trim((string) ($payload['title'] ?? ''));
        $description = (string) ($payload['description'] ?? '');
        $sql = (string) ($payload['sql'] ?? '');
        $prompt = (string) ($payload['prompt_text'] ?? '');
        $selectDesc = trim((string) ($payload['select_desc'] ?? ''));
        $tags = $payload['tags_json'] ?? null;
        $tables = $payload['tables_json'] ?? null;
        $paramsJson = $payload['params_json'] ?? null;
        $createdBy = (string) ($payload['created_by'] ?? '');

        if ($selectDesc !== '' && mb_strlen($selectDesc) > 80) {
            throw new RuntimeException('select_desc excede 80 caracteres.');
        }
        if ($slug === '' || preg_match('/^[A-Z0-9_]{3,80}$/', $slug) !== 1) {
            throw new RuntimeException('slug inválido (use A-Z 0-9 e _; 3-80 chars).');
        }
        if ($title === '') {
            throw new RuntimeException('title é obrigatório.');
        }

        if ($paramsJson === null) {
            $paramsJson = array_map(
                static fn(string $name): array => ['name' => $name, 'type' => 'string', 'required' => true, 'label' => $name],
                (new SqlGuard())->extractNamedParams($sql)
            );
        }

        $checksum = hash('sha256', $sql);
        $dbName = (string) ($_SERVER['TELA_SQL_TARGET_DB_NAME'] ?? $_SERVER['DB_NAME'] ?? 'form_app');

        $pdo = $this->pdo();
        try {
            $pdo->beginTransaction();

            $sel = $pdo->prepare('SELECT id, current_version FROM datasmart_queries WHERE slug = ? LIMIT 1');
            $sel->execute([$slug]);
            $row = $sel->fetch(PDO::FETCH_ASSOC);

            if (!is_array($row)) {
                $ins = $pdo->prepare("\n                    INSERT INTO datasmart_queries (slug, title, description, db_name, is_active, current_version, created_by)\n                    VALUES (?, ?, ?, ?, 1, 1, ?)\n                ");
                $ins->execute([$slug, $title, $description, $dbName, $createdBy !== '' ? $createdBy : null]);
                $queryId = (int) $pdo->lastInsertId();
                $newVersion = 1;
            } else {
                $queryId = (int) $row['id'];
                $newVersion = ((int) $row['current_version']) + 1;
                $upd = $pdo->prepare("\n                    UPDATE datasmart_queries\n                    SET title = ?, description = ?, db_name = ?, current_version = ?, updated_at = NOW()\n                    WHERE id = ?\n                ");
                $upd->execute([$title, $description, $dbName, $newVersion, $queryId]);
            }

            $insv = $pdo->prepare("\n                INSERT INTO datasmart_query_versions\n                  (query_id, version, select_desc, prompt_text, sql_text, tables_json, tags_json, params_json, checksum_sha256, created_by)\n                VALUES (?,?,?,?,?,?,?,?,?,?)\n            ");
            $insv->execute([
                $queryId,
                $newVersion,
                $selectDesc !== '' ? $selectDesc : null,
                $prompt !== '' ? $prompt : null,
                $sql,
                $tables !== null ? json_encode($tables, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                $tags !== null ? json_encode($tags, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                $paramsJson !== null ? json_encode($paramsJson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                $checksum,
                $createdBy !== '' ? $createdBy : null,
            ]);

            $pdo->commit();
            return [
                'slug' => $slug,
                'query_id' => $queryId,
                'version' => $newVersion,
            ];
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw new RuntimeException('Falha ao salvar: ' . $exception->getMessage(), 0, $exception);
        }
    }

    private function ensureSchema(): void
    {
        $pdo = $this->pdo();
        $pdo->exec("\n            CREATE TABLE IF NOT EXISTS datasmart_queries (\n              id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,\n              slug VARCHAR(80) NOT NULL,\n              title VARCHAR(140) NOT NULL,\n              description TEXT NULL,\n              db_name VARCHAR(128) NOT NULL,\n              is_active TINYINT(1) NOT NULL DEFAULT 1,\n              current_version INT NOT NULL DEFAULT 1,\n              created_by VARCHAR(120) NULL,\n              created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n              updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n              PRIMARY KEY (id),\n              UNIQUE KEY uq_datasmart_slug (slug),\n              KEY ix_datasmart_active (is_active),\n              KEY ix_datasmart_db (db_name)\n            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4\n        ");

        $pdo->exec("\n            CREATE TABLE IF NOT EXISTS datasmart_query_versions (\n              id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,\n              query_id BIGINT UNSIGNED NOT NULL,\n              version INT NOT NULL,\n              select_desc VARCHAR(80) NULL,\n              prompt_text MEDIUMTEXT NULL,\n              sql_text MEDIUMTEXT NOT NULL,\n              tables_json JSON NULL,\n              tags_json JSON NULL,\n              params_json JSON NULL,\n              checksum_sha256 CHAR(64) NULL,\n              notes TEXT NULL,\n              last_run_at DATETIME NULL,\n              last_run_ms INT NULL,\n              last_rows INT NULL,\n              created_by VARCHAR(120) NULL,\n              created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n              PRIMARY KEY (id),\n              UNIQUE KEY uq_query_version (query_id, version),\n              KEY ix_query_id (query_id)\n            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4\n        ");

        try {
            $pdo->exec('ALTER TABLE datasmart_query_versions ADD COLUMN select_desc VARCHAR(80) NULL AFTER version');
        } catch (Throwable) {
        }
    }

    private function pdo(): PDO
    {
        return $this->pdo ??= $this->database->pdo();
    }
}
