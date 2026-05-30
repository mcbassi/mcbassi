<?php
declare(strict_types=1);

namespace App\Fields;

use App\Infra\Database;
use PDO;
use RuntimeException;
use Throwable;

final class FieldRepository
{
    private const TABLE = 'form_fields';
    private ?PDO $pdo = null;
    /** @var array<string, array<string, mixed>>|null */
    private ?array $schema = null;

    public function __construct(private readonly Database $database)
    {
    }

    public function ensureTable(): void
    {
        try {
            $this->pdo()->query('SELECT 1 FROM `'.self::TABLE.'` LIMIT 1');
        } catch (Throwable $e) {
            throw new RuntimeException('A tabela form_fields não foi encontrada na base atual.');
        }
    }

    /** @return array<string, array<string, mixed>> */
    public function schema(): array
    {
        if ($this->schema !== null) {
            return $this->schema;
        }
        $this->ensureTable();
        $st = $this->pdo()->query('DESCRIBE `'.self::TABLE.'`');
        $schema = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $name = (string)($row['Field'] ?? '');
            if ($name === '') {
                continue;
            }
            $type = strtolower((string)($row['Type'] ?? 'text'));
            $schema[$name] = [
                'name' => $name,
                'type' => $type,
                'nullable' => strtoupper((string)($row['Null'] ?? 'YES')) === 'YES',
                'key' => (string)($row['Key'] ?? ''),
                'default' => $row['Default'] ?? null,
                'extra' => strtolower((string)($row['Extra'] ?? '')),
            ];
        }
        return $this->schema = $schema;
    }

    /** @return array<int, array<string, mixed>> */
    public function all(array $filters = []): array
    {
        $schema = $this->schema();
        $where = [];
        $params = [];

        $q = trim((string)($filters['q'] ?? ''));
        if ($q !== '') {
            $parts = [];
            foreach (['name', 'field_name', 'label', 'section_code', 'prompt_code', 'type'] as $col) {
                if (isset($schema[$col])) {
                    $parts[] = '`'.$col.'` LIKE :q';
                }
            }
            if ($parts !== []) {
                $where[] = '('.implode(' OR ', $parts).')';
                $params[':q'] = '%'.$q.'%';
            }
        }

        $section = trim((string)($filters['section'] ?? ''));
        if ($section !== '' && isset($schema['section_code'])) {
            $where[] = '`section_code` = :section';
            $params[':section'] = $section;
        }

        $type = trim((string)($filters['type'] ?? ''));
        if ($type !== '' && isset($schema['type'])) {
            $where[] = '`type` = :type';
            $params[':type'] = $type;
        }

        $sql = 'SELECT * FROM `'.self::TABLE.'`';
        if ($where !== []) {
            $sql .= ' WHERE '.implode(' AND ', $where);
        }
        $sql .= ' ORDER BY '.$this->orderBy();

        $st = $this->pdo()->prepare($sql);
        foreach ($params as $k => $v) {
            $st->bindValue($k, $v);
        }
        $st->execute();

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function total(): int
    {
        $this->ensureTable();
        return (int)($this->pdo()->query('SELECT COUNT(*) FROM `'.self::TABLE.'`')->fetchColumn() ?: 0);
    }

    /** @return array<string, mixed> */
    public function stats(): array
    {
        $schema = $this->schema();
        $stats = [
            'total' => $this->total(),
            'sections' => 0,
            'with_prompt' => 0,
            'questions' => 0,
        ];
        if (isset($schema['section_code'])) {
            $stats['sections'] = (int)($this->pdo()->query('SELECT COUNT(DISTINCT `section_code`) FROM `'.self::TABLE.'` WHERE TRIM(COALESCE(`section_code`, "")) <> ""')->fetchColumn() ?: 0);
        }
        if (isset($schema['prompt_code'])) {
            $stats['with_prompt'] = (int)($this->pdo()->query('SELECT COUNT(*) FROM `'.self::TABLE.'` WHERE TRIM(COALESCE(`prompt_code`, "")) <> ""')->fetchColumn() ?: 0);
        }
        if (isset($schema['type'])) {
            $stats['questions'] = (int)($this->pdo()->query("SELECT COUNT(*) FROM `".self::TABLE."` WHERE COALESCE(`type`, '') NOT IN ('title', 'subtitle')")->fetchColumn() ?: 0);
        }
        return $stats;
    }

    /** @return array<string, array<int, string>> */
    public function filterOptions(): array
    {
        $schema = $this->schema();
        $out = ['sections' => [], 'types' => []];
        if (isset($schema['section_code'])) {
            $out['sections'] = array_values(array_filter(array_map('strval', $this->pdo()->query('SELECT DISTINCT `section_code` FROM `'.self::TABLE.'` WHERE TRIM(COALESCE(`section_code`, "")) <> "" ORDER BY `section_code` ASC')->fetchAll(PDO::FETCH_COLUMN) ?: [])));
        }
        if (isset($schema['type'])) {
            $out['types'] = array_values(array_filter(array_map('strval', $this->pdo()->query('SELECT DISTINCT `type` FROM `'.self::TABLE.'` WHERE TRIM(COALESCE(`type`, "")) <> "" ORDER BY `type` ASC')->fetchAll(PDO::FETCH_COLUMN) ?: [])));
        }
        return $out;
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        $this->schema();
        $st = $this->pdo()->prepare('SELECT * FROM `'.self::TABLE.'` WHERE `id` = ? LIMIT 1');
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    /** @param array<string, mixed> $data */
    public function save(array $data): int
    {
        $schema = $this->schema();
        $id = (int)($data['id'] ?? 0);
        $payload = [];
        foreach ($this->editableColumns() as $col) {
            if (!array_key_exists($col, $data)) {
                continue;
            }
            $payload[$col] = $this->normalizeValue($schema[$col], $data[$col]);
        }
        if ($payload === []) {
            throw new RuntimeException('Nenhum campo foi informado para salvar.');
        }

        if ($id > 0) {
            $parts = [];
            $params = [':id' => $id];
            foreach ($payload as $col => $value) {
                $parts[] = '`'.$col.'` = :'.$col;
                $params[':'.$col] = $value;
            }
            $sql = 'UPDATE `'.self::TABLE.'` SET '.implode(', ', $parts).' WHERE `id` = :id';
            $st = $this->pdo()->prepare($sql);
            foreach ($params as $k => $v) {
                $st->bindValue($k, $v);
            }
            $st->execute();
            return $id;
        }

        $cols = array_keys($payload);
        $sql = 'INSERT INTO `'.self::TABLE.'` (`'.implode('`,`', $cols).'`) VALUES (:'.implode(',:', $cols).')';
        $st = $this->pdo()->prepare($sql);
        foreach ($payload as $col => $value) {
            $st->bindValue(':'.$col, $value);
        }
        $st->execute();
        return (int)$this->pdo()->lastInsertId();
    }

    public function delete(int $id): void
    {
        if ($id <= 0) {
            throw new RuntimeException('Registro inválido para exclusão.');
        }
        $st = $this->pdo()->prepare('DELETE FROM `'.self::TABLE.'` WHERE `id` = ?');
        $st->execute([$id]);
    }

    public function importRows(string $json): int
    {
        $json = trim($json);
        if ($json === '') {
            throw new RuntimeException('Cole um JSON de array para importar.');
        }
        $data = json_decode($json, true);
        if (!is_array($data)) {
            throw new RuntimeException('JSON inválido para importação.');
        }
        $count = 0;
        foreach ($data as $row) {
            if (!is_array($row)) {
                continue;
            }
            $this->save($row);
            $count++;
        }
        return $count;
    }

    /** @return array<int, string> */
    public function editableColumns(): array
    {
        $schema = $this->schema();
        $skip = ['id'];
        $priority = ['section_code', 'sort_order', 'label', 'name', 'field_name', 'type', 'required', 'placeholder', 'prompt_code', 'options_json', 'min_value', 'max_value', 'step'];
        $cols = [];
        foreach ($priority as $col) {
            if (isset($schema[$col]) && !in_array($col, $skip, true)) {
                $cols[] = $col;
            }
        }
        foreach (array_keys($schema) as $col) {
            if (in_array($col, $skip, true) || in_array($col, $cols, true)) {
                continue;
            }
            $cols[] = $col;
        }
        return $cols;
    }

    private function orderBy(): string
    {
        $schema = $this->schema();
        $parts = [];
        foreach (['section_code', 'sort_order', 'id'] as $col) {
            if (isset($schema[$col])) {
                $parts[] = '`'.$col.'` ASC';
            }
        }
        return $parts !== [] ? implode(', ', $parts) : '`id` ASC';
    }

    /** @param array<string, mixed> $meta */
    private function normalizeValue(array $meta, mixed $value): mixed
    {
        if ($value === '') {
            return $meta['nullable'] ? null : '';
        }
        $type = (string)($meta['type'] ?? '');
        if (str_contains($type, 'int')) {
            return is_numeric($value) ? (int)$value : null;
        }
        if (preg_match('/decimal|float|double/', $type)) {
            return is_numeric($value) ? (float)$value : null;
        }
        return is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : (string)$value;
    }

    private function pdo(): PDO
    {
        return $this->pdo ??= $this->database->pdo();
    }
}
