<?php
declare(strict_types=1);

namespace App\Atividades;

use App\Infra\Database;
use PDO;
use RuntimeException;
use Throwable;

final class ActivityRepository
{
    private const TBL_PLAN = 'atividades_projeto';
    private const TBL_TEMPLATE = 'atividades';
    private const TBL_EVIDENCE = 'atividades_evidencias';
    private const TBL_DEP = 'atividades_dependencias';

    private ?PDO $pdo = null;

    /** @var array<string, array<string, bool>> */
    private array $columns = [];

    public function __construct(private readonly Database $database)
    {
    }

    public function ensureTables(): void
    {
        foreach ([self::TBL_PLAN, self::TBL_EVIDENCE] as $table) {
            if (!$this->tableExists($table)) {
                throw new RuntimeException('Tabela obrigatória não encontrada: ' . $table);
            }
        }
    }

    public function hasDataInicio(): bool
    {
        return $this->hasColumn(self::TBL_PLAN, 'data_inicio');
    }

    public function hasDependenciesTable(): bool
    {
        return $this->tableExists(self::TBL_DEP);
    }

    /** @return array<int, array<string, mixed>> */
    public function tree(): array
    {
        $this->ensureTables();
        $sql = '
            SELECT
                COALESCE(NULLIF(TRIM(projeto), \'\'), \'Sem projeto\') AS projeto,
                COALESCE(NULLIF(TRIM(subprojeto), \'\'), \'Sem subprojeto\') AS subprojeto,
                COUNT(*) AS total,
                SUM(CASE WHEN COALESCE(status_atual, \'\') IN (\'Concluído\',\'Concluido\',\'Done\',\'Finalizado\') THEN 1 ELSE 0 END) AS concluidas
            FROM ' . $this->bt(self::TBL_PLAN) . '
            GROUP BY COALESCE(NULLIF(TRIM(projeto), \'\'), \'Sem projeto\'), COALESCE(NULLIF(TRIM(subprojeto), \'\'), \'Sem subprojeto\')
            ORDER BY projeto ASC, subprojeto ASC
        ';

        return $this->pdo()->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array<int, array<string, mixed>> */
    public function templatesTree(): array
    {
        if (!$this->tableExists(self::TBL_TEMPLATE)) {
            return [];
        }

        $sql = '
            SELECT
                COALESCE(NULLIF(TRIM(projeto), \'\'), \'Sem projeto\') AS projeto,
                COALESCE(NULLIF(TRIM(subprojeto), \'\'), \'Sem subprojeto\') AS subprojeto,
                COUNT(*) AS total
            FROM ' . $this->bt(self::TBL_TEMPLATE) . '
            GROUP BY COALESCE(NULLIF(TRIM(projeto), \'\'), \'Sem projeto\'), COALESCE(NULLIF(TRIM(subprojeto), \'\'), \'Sem subprojeto\')
            ORDER BY projeto ASC, subprojeto ASC
        ';

        return $this->pdo()->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array<int, array<string, mixed>> */
    public function activities(?string $project = null, ?string $subproject = null): array
    {
        $this->ensureTables();

        $where = [];
        $params = [];

        if ($project !== null && $project !== '') {
            $where[] = 'COALESCE(NULLIF(TRIM(projeto), \'\'), \'Sem projeto\') = :project';
            $params[':project'] = $project;
        }

        if ($subproject !== null && $subproject !== '') {
            $where[] = 'COALESCE(NULLIF(TRIM(subprojeto), \'\'), \'Sem subprojeto\') = :subproject';
            $params[':subproject'] = $subproject;
        }

        $startExpr = $this->hasDataInicio()
            ? 'COALESCE(data_inicio, data_prevista_termino, data_termino_prevista, DATE(created_at))'
            : 'COALESCE(data_prevista_termino, data_termino_prevista, DATE(created_at))';

        $sql = 'SELECT * FROM ' . $this->bt(self::TBL_PLAN);
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY ' . $startExpr . ' ASC, id ASC';

        $st = $this->pdo()->prepare($sql);
        foreach ($params as $key => $value) {
            $st->bindValue($key, $value);
        }
        $st->execute();

        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if ($rows === []) {
            return [];
        }

        $ids = array_map(static fn (array $row): int => (int) ($row['id'] ?? 0), $rows);
        $evidenceCounts = $this->evidenceCounts($ids);
        $dependencyLabels = $this->dependencyLabelsFor($ids);

        foreach ($rows as &$row) {
            $id = (int) ($row['id'] ?? 0);
            $row['_evidence_count'] = $evidenceCounts[$id] ?? 0;
            $row['_dependency_labels'] = $dependencyLabels[$id] ?? [];
        }
        unset($row);

        return $rows;
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $this->ensureTables();
        $st = $this->pdo()->prepare('SELECT * FROM ' . $this->bt(self::TBL_PLAN) . ' WHERE id = ? LIMIT 1');
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    /** @param array<string, mixed> $data */
    public function save(array $data, string $userEmail, string $userName): int
    {
        $this->ensureTables();

        $id = (int) ($data['id'] ?? 0);
        $cols = $this->editableColumns();
        $payload = [];

        foreach ($cols as $col) {
            if (array_key_exists($col, $data)) {
                $payload[$col] = $this->normalize($col, $data[$col]);
            }
        }

        if (array_key_exists('data_prevista_termino', $data)) {
            $payload['data_prevista_termino'] = $this->normalize('data_prevista_termino', $data['data_prevista_termino']);
            if ($this->hasColumn(self::TBL_PLAN, 'data_termino_prevista')) {
                $payload['data_termino_prevista'] = $payload['data_prevista_termino'];
            }
        }

        if (array_key_exists('data_real_termino', $data)) {
            $payload['data_real_termino'] = $this->normalize('data_real_termino', $data['data_real_termino']);
            if ($this->hasColumn(self::TBL_PLAN, 'data_termino_real')) {
                $payload['data_termino_real'] = $payload['data_real_termino'];
            }
        }

        if ($id > 0) {
            if ($payload === []) {
                return $id;
            }

            $sets = [];
            $params = [':id' => $id];
            foreach ($payload as $col => $value) {
                if (!$this->hasColumn(self::TBL_PLAN, $col)) {
                    continue;
                }
                $sets[] = $this->bt($col) . ' = :' . $col;
                $params[':' . $col] = $value;
            }

            if ($sets === []) {
                return $id;
            }

            $sql = 'UPDATE ' . $this->bt(self::TBL_PLAN) . ' SET ' . implode(', ', $sets) . ' WHERE id = :id';
            $st = $this->pdo()->prepare($sql);
            foreach ($params as $key => $value) {
                $st->bindValue($key, $value);
            }
            $st->execute();
            return $id;
        }

        $payload['email_resp'] = $userEmail;
        $payload['nome_usuario'] = $userName;

        foreach (['projeto', 'subprojeto', 'atividade'] as $required) {
            if (!isset($payload[$required]) || trim((string) $payload[$required]) === '') {
                $payload[$required] = $required === 'atividade' ? 'Nova atividade' : 'Sem ' . $required;
            }
        }

        $payload = array_filter(
            $payload,
            fn ($value, string $col): bool => $this->hasColumn(self::TBL_PLAN, $col),
            ARRAY_FILTER_USE_BOTH
        );

        $columns = array_keys($payload);
        $sql = 'INSERT INTO ' . $this->bt(self::TBL_PLAN) . ' (' . implode(', ', array_map([$this, 'bt'], $columns)) . ') VALUES (:' . implode(', :', $columns) . ')';
        $st = $this->pdo()->prepare($sql);
        foreach ($payload as $col => $value) {
            $st->bindValue(':' . $col, $value);
        }
        $st->execute();

        return (int) $this->pdo()->lastInsertId();
    }

    public function delete(int $id): void
    {
        if ($id <= 0) {
            return;
        }
        $st = $this->pdo()->prepare('DELETE FROM ' . $this->bt(self::TBL_PLAN) . ' WHERE id = ?');
        $st->execute([$id]);
    }

    public function importTemplates(?string $project, ?string $subproject, string $userEmail, string $userName): int
    {
        if (!$this->tableExists(self::TBL_TEMPLATE)) {
            throw new RuntimeException('Tabela de atividades pré-cadastradas não encontrada.');
        }

        $where = [];
        $params = [];

        if ($project !== null && $project !== '' && $project !== 'Sem projeto') {
            $where[] = 'COALESCE(NULLIF(TRIM(projeto), \'\'), \'Sem projeto\') = :project';
            $params[':project'] = $project;
        }

        if ($subproject !== null && $subproject !== '' && $subproject !== 'Sem subprojeto') {
            $where[] = 'COALESCE(NULLIF(TRIM(subprojeto), \'\'), \'Sem subprojeto\') = :subproject';
            $params[':subproject'] = $subproject;
        }

        $sql = 'SELECT * FROM ' . $this->bt(self::TBL_TEMPLATE);
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY COALESCE(data_prevista_termino, DATE(created_at)) ASC, id ASC';

        $st = $this->pdo()->prepare($sql);
        foreach ($params as $key => $value) {
            $st->bindValue($key, $value);
        }
        $st->execute();
        $templates = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $count = 0;
        foreach ($templates as $tpl) {
            $tplProject = trim((string) ($tpl['projeto'] ?? $project ?? '')) ?: 'Sem projeto';
            $tplSubproject = trim((string) ($tpl['subprojeto'] ?? $subproject ?? '')) ?: 'Sem subprojeto';
            $activity = trim((string) ($tpl['atividade'] ?? ''));
            $subActivity = trim((string) ($tpl['sub_atividade'] ?? ''));

            if ($activity === '') {
                continue;
            }

            if ($this->existsDuplicate($tplProject, $tplSubproject, $activity, $subActivity)) {
                continue;
            }

            $data = [];
            foreach ($this->editableColumns() as $col) {
                if (array_key_exists($col, $tpl)) {
                    $data[$col] = $tpl[$col];
                }
            }
            $data['projeto'] = $tplProject;
            $data['subprojeto'] = $tplSubproject;

            $this->save($data, $userEmail, $userName);
            $count++;
        }

        return $count;
    }

    /** @return array<int, array<string, mixed>> */
    public function dependencyCandidates(int $excludeId = 0): array
    {
        $this->ensureTables();
        $where = $excludeId > 0 ? 'WHERE id <> :id' : '';
        $sql = 'SELECT id, projeto, subprojeto, atividade, sub_atividade, status_atual FROM ' . $this->bt(self::TBL_PLAN) . ' ' . $where . ' ORDER BY projeto ASC, subprojeto ASC, atividade ASC, id ASC';
        $st = $this->pdo()->prepare($sql);
        if ($excludeId > 0) {
            $st->bindValue(':id', $excludeId, PDO::PARAM_INT);
        }
        $st->execute();
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array<int, int> */
    public function dependencyIds(int $activityId): array
    {
        if ($activityId <= 0 || !$this->hasDependenciesTable()) {
            return [];
        }
        $st = $this->pdo()->prepare('SELECT depende_de_id FROM ' . $this->bt(self::TBL_DEP) . ' WHERE atividade_id = ? ORDER BY depende_de_id ASC');
        $st->execute([$activityId]);
        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }

    /** @param array<int, int> $dependencyIds */
    public function saveDependencies(int $activityId, array $dependencyIds): void
    {
        if ($activityId <= 0 || !$this->hasDependenciesTable()) {
            return;
        }

        $dependencyIds = array_values(array_unique(array_filter(array_map('intval', $dependencyIds), static fn (int $id): bool => $id > 0 && $id !== $activityId)));

        $this->pdo()->beginTransaction();
        try {
            $del = $this->pdo()->prepare('DELETE FROM ' . $this->bt(self::TBL_DEP) . ' WHERE atividade_id = ?');
            $del->execute([$activityId]);

            if ($dependencyIds !== []) {
                $ins = $this->pdo()->prepare('INSERT IGNORE INTO ' . $this->bt(self::TBL_DEP) . ' (atividade_id, depende_de_id) VALUES (?, ?)');
                foreach ($dependencyIds as $depId) {
                    $ins->execute([$activityId, $depId]);
                }
            }
            $this->pdo()->commit();
        } catch (Throwable $e) {
            $this->pdo()->rollBack();
            throw $e;
        }
    }

    /** @return array<int, array<string, mixed>> */
    public function evidences(int $activityId): array
    {
        if ($activityId <= 0) {
            return [];
        }
        $st = $this->pdo()->prepare('SELECT * FROM ' . $this->bt(self::TBL_EVIDENCE) . ' WHERE atividade_id = ? ORDER BY uploaded_at DESC, id DESC');
        $st->execute([$activityId]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function addEvidence(int $activityId, string $filePath, string $originalName): int
    {
        $st = $this->pdo()->prepare('INSERT INTO ' . $this->bt(self::TBL_EVIDENCE) . ' (atividade_id, file_path, original_name) VALUES (?, ?, ?)');
        $st->execute([$activityId, $filePath, $originalName]);
        return (int) $this->pdo()->lastInsertId();
    }

    public function deleteEvidence(int $evidenceId): ?string
    {
        $st = $this->pdo()->prepare('SELECT file_path FROM ' . $this->bt(self::TBL_EVIDENCE) . ' WHERE id = ? LIMIT 1');
        $st->execute([$evidenceId]);
        $path = $st->fetchColumn();
        if ($path === false) {
            return null;
        }
        $del = $this->pdo()->prepare('DELETE FROM ' . $this->bt(self::TBL_EVIDENCE) . ' WHERE id = ?');
        $del->execute([$evidenceId]);
        return (string) $path;
    }

    /** @return array<string, mixed> */
    public function stats(): array
    {
        $this->ensureTables();
        $total = (int) ($this->pdo()->query('SELECT COUNT(*) FROM ' . $this->bt(self::TBL_PLAN))->fetchColumn() ?: 0);
        $done = (int) ($this->pdo()->query("SELECT COUNT(*) FROM " . $this->bt(self::TBL_PLAN) . " WHERE COALESCE(status_atual, '') IN ('Concluído','Concluido','Done','Finalizado')")->fetchColumn() ?: 0);
        $projects = (int) ($this->pdo()->query('SELECT COUNT(DISTINCT COALESCE(NULLIF(TRIM(projeto), \'\'), \'Sem projeto\')) FROM ' . $this->bt(self::TBL_PLAN))->fetchColumn() ?: 0);
        $late = (int) ($this->pdo()->query("SELECT COUNT(*) FROM " . $this->bt(self::TBL_PLAN) . " WHERE COALESCE(data_prevista_termino, data_termino_prevista) IS NOT NULL AND COALESCE(data_real_termino, data_termino_real) IS NULL AND COALESCE(data_prevista_termino, data_termino_prevista) < CURRENT_DATE()")->fetchColumn() ?: 0);

        return [
            'total' => $total,
            'done' => $done,
            'projects' => $projects,
            'late' => $late,
            'completion' => $total > 0 ? round(($done / $total) * 100, 1) : 0,
        ];
    }

    /** @return array<int, int> */
    private function evidenceCounts(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return [];
        }
        $in = implode(',', array_fill(0, count($ids), '?'));
        $st = $this->pdo()->prepare('SELECT atividade_id, COUNT(*) AS total FROM ' . $this->bt(self::TBL_EVIDENCE) . ' WHERE atividade_id IN (' . $in . ') GROUP BY atividade_id');
        $st->execute($ids);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $out[(int) $row['atividade_id']] = (int) $row['total'];
        }
        return $out;
    }

    /** @return array<int, array<int, string>> */
    private function dependencyLabelsFor(array $ids): array
    {
        if (!$this->hasDependenciesTable()) {
            return [];
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return [];
        }
        $in = implode(',', array_fill(0, count($ids), '?'));
        $sql = '
            SELECT d.atividade_id, p.id, p.atividade, p.sub_atividade
            FROM ' . $this->bt(self::TBL_DEP) . ' d
            INNER JOIN ' . $this->bt(self::TBL_PLAN) . ' p ON p.id = d.depende_de_id
            WHERE d.atividade_id IN (' . $in . ')
            ORDER BY p.id ASC
        ';
        $st = $this->pdo()->prepare($sql);
        $st->execute($ids);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $activityId = (int) $row['atividade_id'];
            $label = '#' . (int) $row['id'] . ' · ' . trim((string) ($row['atividade'] ?? ''));
            $sub = trim((string) ($row['sub_atividade'] ?? ''));
            if ($sub !== '') {
                $label .= ' / ' . $sub;
            }
            $out[$activityId][] = $label;
        }
        return $out;
    }

    private function existsDuplicate(string $project, string $subproject, string $activity, string $subActivity): bool
    {
        $sql = 'SELECT id FROM ' . $this->bt(self::TBL_PLAN) . '
            WHERE COALESCE(NULLIF(TRIM(projeto), \'\'), \'Sem projeto\') = ?
              AND COALESCE(NULLIF(TRIM(subprojeto), \'\'), \'Sem subprojeto\') = ?
              AND TRIM(COALESCE(atividade, \'\')) = ?
              AND TRIM(COALESCE(sub_atividade, \'\')) = ?
            LIMIT 1';
        $st = $this->pdo()->prepare($sql);
        $st->execute([$project, $subproject, $activity, $subActivity]);
        return (bool) $st->fetchColumn();
    }

    /** @return array<int, string> */
    private function editableColumns(): array
    {
        $cols = [
            'projeto',
            'subprojeto',
            'objetivo',
            'atividade',
            'sub_atividade',
            'dependencia',
            'responsavel_execucao',
            'responsavel_gestao',
            'tempo_previsto',
            'esforco_previsto_dias',
            'status_atual',
            'descricao_resultados',
            'dificuldades',
            'data_prevista_termino',
            'data_real_termino',
            'data_termino_prevista',
            'data_termino_real',
        ];

        if ($this->hasDataInicio()) {
            $cols[] = 'data_inicio';
        }

        return array_values(array_filter($cols, fn (string $col): bool => $this->hasColumn(self::TBL_PLAN, $col)));
    }

    private function normalize(string $col, mixed $value): mixed
    {
        if (in_array($col, ['tempo_previsto', 'esforco_previsto_dias'], true)) {
            $value = trim((string) $value);
            return $value === '' ? null : (int) $value;
        }

        if (str_starts_with($col, 'data_') || str_ends_with($col, '_prevista') || str_ends_with($col, '_real')) {
            $value = trim((string) $value);
            return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null;
        }

        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    private function tableExists(string $table): bool
    {
        try {
            $st = $this->pdo()->prepare('SHOW TABLES LIKE ?');
            $st->execute([$table]);
            return (bool) $st->fetchColumn();
        } catch (Throwable) {
            return false;
        }
    }

    private function hasColumn(string $table, string $column): bool
    {
        if (!isset($this->columns[$table])) {
            $this->columns[$table] = [];
            try {
                $st = $this->pdo()->query('DESCRIBE ' . $this->bt($table));
                foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                    $name = (string) ($row['Field'] ?? '');
                    if ($name !== '') {
                        $this->columns[$table][$name] = true;
                    }
                }
            } catch (Throwable) {
                $this->columns[$table] = [];
            }
        }

        return isset($this->columns[$table][$column]);
    }

    private function pdo(): PDO
    {
        if ($this->pdo instanceof PDO) {
            return $this->pdo;
        }
        return $this->pdo = $this->database->pdo();
    }

    private function bt(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }
}
