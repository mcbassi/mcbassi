<?php
declare(strict_types=1);

namespace App\Grupos;

use App\Infra\Database;
use PDO;
use RuntimeException;
use Throwable;

final class GroupRepository
{
    private const TBL_GN = 'grupos_nome';
    private const TBL_GP = 'grupos_prioridades';
    private const TBL_FF = 'form_fields';

    private ?PDO $pdo = null;
    /** @var array<string, array<string, bool>> */
    private array $tableCols = [];

    public function __construct(private readonly Database $database)
    {
    }

    public function ensureTables(): void
    {
        foreach ([self::TBL_GN, self::TBL_GP, self::TBL_FF] as $table) {
            if (!$this->tableExists($table)) {
                throw new RuntimeException('Tabela obrigatória não encontrada: ' . $table);
            }
        }
    }

    /**
     * @return array<string, string>
     */
    public function columnsConfig(): array
    {
        $this->ensureTables();

        $colIdGn = $this->pickCol(self::TBL_GN, ['ID_Grupo', 'id_grupo', 'grupo_id', 'id'], 'id_grupo');
        $colNameGn = $this->pickCol(self::TBL_GN, ['Nome_Grupo', 'grupo_nome', 'group_name', 'nome'], 'grupo_nome');
        $colPromptGrpGn = $this->pickCol(self::TBL_GN, ['prompt_grp', 'PROMPT_GRP', 'prompt_group', 'grupo_prompt', 'prompt_grupo'], 'prompt_grp');

        $ffHasName = $this->hasCol(self::TBL_FF, 'name');
        $ffHasFieldName = $this->hasCol(self::TBL_FF, 'field_name');
        $colFfKey = $ffHasName ? 'name' : ($ffHasFieldName ? 'field_name' : 'name');
        $colFfAlt = ($ffHasName && $ffHasFieldName) ? 'field_name' : '';
        $colFfLabel = $this->pickCol(self::TBL_FF, ['label', 'Label', 'titulo', 'title'], 'label');
        $colFfSect = $this->pickCol(self::TBL_FF, ['section_code', 'Section_Code', 'secao'], 'section_code');
        $colFfSort = $this->pickCol(self::TBL_FF, ['sort_order', 'Sort_Order', 'ordem'], 'sort_order');
        $colFfPrompt = $this->pickCol(self::TBL_FF, ['prompt_code', 'Prompt_Code'], 'prompt_code');
        $colFfType = $this->pickCol(self::TBL_FF, ['type', 'Type', 'tipo'], 'type');

        $colIdGp = $this->pickCol(self::TBL_GP, ['ID_Grupo', 'id_grupo', 'grupo_id'], 'id_grupo');
        $colQGp = $this->pickCol(self::TBL_GP, ['field_name', 'name', 'question_name'], 'field_name');

        return [
            'col_id_gn' => $colIdGn,
            'col_name_gn' => $colNameGn,
            'col_prompt_grp_gn' => $colPromptGrpGn,
            'gn_has_prompt_grp' => $this->hasCol(self::TBL_GN, $colPromptGrpGn) ? '1' : '0',
            'col_id_gp' => $colIdGp,
            'col_q_gp' => $colQGp,
            'col_ff_key' => $colFfKey,
            'col_ff_alt' => $colFfAlt,
            'col_ff_label' => $colFfLabel,
            'col_ff_sect' => $colFfSect,
            'col_ff_sort' => $colFfSort,
            'col_ff_prompt' => $colFfPrompt,
            'col_ff_type' => $colFfType,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchGroups(): array
    {
        $cfg = $this->columnsConfig();

        $sql = sprintf(
            'SELECT %s AS id, %s AS name, %s AS prompt_grp FROM %s ORDER BY %s ASC',
            $this->bt($cfg['col_id_gn']),
            $this->bt($cfg['col_name_gn']),
            $cfg['gn_has_prompt_grp'] === '1' ? $this->bt($cfg['col_prompt_grp_gn']) : "''",
            $this->bt(self::TBL_GN),
            $this->bt($cfg['col_name_gn'])
        );

        return $this->pdo()->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchAllQuestions(): array
    {
        $cfg = $this->columnsConfig();

        $select = [
            $this->bt($cfg['col_ff_key']) . ' AS qkey',
            $this->bt($cfg['col_ff_label']) . ' AS qlabel',
            $this->bt($cfg['col_ff_sect']) . ' AS qsect',
            $this->bt($cfg['col_ff_sort']) . ' AS qsort',
            $this->bt($cfg['col_ff_prompt']) . ' AS prompt_code',
            $this->bt($cfg['col_ff_type']) . ' AS qtype',
        ];

        if ($cfg['col_ff_alt'] !== '') {
            $select[] = $this->bt($cfg['col_ff_alt']) . ' AS qalt';
        }

        $sql = 'SELECT ' . implode(', ', $select) . '
            FROM ' . $this->bt(self::TBL_FF) . '
            WHERE ' . $this->bt($cfg['col_ff_key']) . " IS NOT NULL
              AND " . $this->bt($cfg['col_ff_key']) . " <> ''
            ORDER BY " . $this->bt($cfg['col_ff_sort']) . ' ASC, '
                        . $this->bt($cfg['col_ff_sect']) . ' ASC, '
                        . $this->bt($cfg['col_ff_label']) . ' ASC';

        $rows = $this->pdo()->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_values(array_filter($rows, static function (array $row): bool {
            $type = strtolower(trim((string) ($row['qtype'] ?? '')));
            return !in_array($type, ['title', 'subtitle'], true);
        }));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function fetchGroup(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $cfg = $this->columnsConfig();

        $cols = [
            $this->bt($cfg['col_id_gn']) . ' AS id',
            $this->bt($cfg['col_name_gn']) . ' AS name',
        ];

        if ($cfg['gn_has_prompt_grp'] === '1') {
            $cols[] = $this->bt($cfg['col_prompt_grp_gn']) . ' AS prompt_grp';
        } else {
            $cols[] = "'' AS prompt_grp";
        }

        $st = $this->pdo()->prepare(
            'SELECT ' . implode(', ', $cols) .
            ' FROM ' . $this->bt(self::TBL_GN) .
            ' WHERE ' . $this->bt($cfg['col_id_gn']) . ' = ? LIMIT 1'
        );
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, bool>
     */
    public function fetchGroupQuestions(int $id): array
    {
        if ($id <= 0) {
            return [];
        }

        $cfg = $this->columnsConfig();

        $st = $this->pdo()->prepare(
            'SELECT ' . $this->bt($cfg['col_q_gp']) . ' AS qv FROM ' . $this->bt(self::TBL_GP) .
            ' WHERE ' . $this->bt($cfg['col_id_gp']) . ' = ?'
        );
        $st->execute([$id]);

        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $value = trim((string) ($row['qv'] ?? ''));
            if ($value !== '') {
                $out[$value] = true;
            }
        }

        return $out;
    }

    public function groupNameExists(string $name, ?int $excludeId = null): bool
    {
        $name = trim($name);
        if ($name === '') {
            return false;
        }

        $cfg = $this->columnsConfig();

        if ($excludeId !== null && $excludeId > 0) {
            $st = $this->pdo()->prepare(
                'SELECT 1 FROM ' . $this->bt(self::TBL_GN) .
                ' WHERE TRIM(' . $this->bt($cfg['col_name_gn']) . ') = TRIM(?)' .
                ' AND ' . $this->bt($cfg['col_id_gn']) . ' <> ? LIMIT 1'
            );
            $st->execute([$name, $excludeId]);
            return (bool) $st->fetchColumn();
        }

        $st = $this->pdo()->prepare(
            'SELECT 1 FROM ' . $this->bt(self::TBL_GN) .
            ' WHERE TRIM(' . $this->bt($cfg['col_name_gn']) . ') = TRIM(?) LIMIT 1'
        );
        $st->execute([$name]);

        return (bool) $st->fetchColumn();
    }

    /**
     * @param array<int, string> $questions
     */
    public function createGroup(string $name, string $promptGrp, array $questions): int
    {
        $cfg = $this->columnsConfig();

        if ($name === '') {
            throw new RuntimeException('Informe o nome do grupo.');
        }

        if ($this->groupNameExists($name)) {
            throw new RuntimeException('Já existe um grupo com esse nome.');
        }

        $questions = $this->sanitizeQuestions($questions);

        $this->pdo()->beginTransaction();

        try {
            if ($cfg['gn_has_prompt_grp'] === '1') {
                $st = $this->pdo()->prepare(
                    'INSERT INTO ' . $this->bt(self::TBL_GN) .
                    ' (' . $this->bt($cfg['col_name_gn']) . ', ' . $this->bt($cfg['col_prompt_grp_gn']) . ') VALUES (?, ?)'
                );
                $st->execute([$name, $promptGrp]);
            } else {
                $st = $this->pdo()->prepare(
                    'INSERT INTO ' . $this->bt(self::TBL_GN) .
                    ' (' . $this->bt($cfg['col_name_gn']) . ') VALUES (?)'
                );
                $st->execute([$name]);
            }

            $id = (int) $this->pdo()->lastInsertId();
            $this->replaceGroupQuestions($id, $questions, $cfg);

            $this->pdo()->commit();

            return $id;
        } catch (Throwable $throwable) {
            if ($this->pdo()->inTransaction()) {
                $this->pdo()->rollBack();
            }

            throw new RuntimeException('Erro ao criar grupo: ' . $throwable->getMessage(), 0, $throwable);
        }
    }

    /**
     * @param array<int, string> $questions
     */
    public function updateGroup(int $id, string $name, string $promptGrp, array $questions): void
    {
        $cfg = $this->columnsConfig();

        if ($id <= 0) {
            throw new RuntimeException('Grupo inválido.');
        }

        if ($name === '') {
            throw new RuntimeException('Informe o nome do grupo.');
        }

        if ($this->groupNameExists($name, $id)) {
            throw new RuntimeException('Já existe um grupo com esse nome.');
        }

        $questions = $this->sanitizeQuestions($questions);

        $this->pdo()->beginTransaction();

        try {
            if ($cfg['gn_has_prompt_grp'] === '1') {
                $st = $this->pdo()->prepare(
                    'UPDATE ' . $this->bt(self::TBL_GN) .
                    ' SET ' . $this->bt($cfg['col_name_gn']) . ' = ?, ' . $this->bt($cfg['col_prompt_grp_gn']) . ' = ?' .
                    ' WHERE ' . $this->bt($cfg['col_id_gn']) . ' = ?'
                );
                $st->execute([$name, $promptGrp, $id]);
            } else {
                $st = $this->pdo()->prepare(
                    'UPDATE ' . $this->bt(self::TBL_GN) .
                    ' SET ' . $this->bt($cfg['col_name_gn']) . ' = ?' .
                    ' WHERE ' . $this->bt($cfg['col_id_gn']) . ' = ?'
                );
                $st->execute([$name, $id]);
            }

            $delete = $this->pdo()->prepare(
                'DELETE FROM ' . $this->bt(self::TBL_GP) .
                ' WHERE ' . $this->bt($cfg['col_id_gp']) . ' = ?'
            );
            $delete->execute([$id]);

            $this->replaceGroupQuestions($id, $questions, $cfg);

            $this->pdo()->commit();
        } catch (Throwable $throwable) {
            if ($this->pdo()->inTransaction()) {
                $this->pdo()->rollBack();
            }

            throw new RuntimeException('Erro ao salvar grupo: ' . $throwable->getMessage(), 0, $throwable);
        }
    }

    public function deleteGroup(int $id): void
    {
        $cfg = $this->columnsConfig();

        if ($id <= 0) {
            throw new RuntimeException('Grupo inválido.');
        }

        $this->pdo()->beginTransaction();

        try {
            $st = $this->pdo()->prepare(
                'DELETE FROM ' . $this->bt(self::TBL_GP) .
                ' WHERE ' . $this->bt($cfg['col_id_gp']) . ' = ?'
            );
            $st->execute([$id]);

            $st = $this->pdo()->prepare(
                'DELETE FROM ' . $this->bt(self::TBL_GN) .
                ' WHERE ' . $this->bt($cfg['col_id_gn']) . ' = ?'
            );
            $st->execute([$id]);

            $this->pdo()->commit();
        } catch (Throwable $throwable) {
            if ($this->pdo()->inTransaction()) {
                $this->pdo()->rollBack();
            }

            throw new RuntimeException('Erro ao apagar grupo: ' . $throwable->getMessage(), 0, $throwable);
        }
    }

    /**
     * @param array<int, string> $questions
     * @param array<string, string> $cfg
     */
    private function replaceGroupQuestions(int $id, array $questions, array $cfg): void
    {
        if ($questions === []) {
            return;
        }

        $ins = $this->pdo()->prepare(
            'INSERT INTO ' . $this->bt(self::TBL_GP) .
            ' (' . $this->bt($cfg['col_id_gp']) . ', ' . $this->bt($cfg['col_q_gp']) . ') VALUES (?, ?)'
        );

        foreach ($questions as $question) {
            $ins->execute([$id, $question]);
        }
    }

    /**
     * @param array<int, string> $questions
     * @return array<int, string>
     */
    private function sanitizeQuestions(array $questions): array
    {
        $questions = array_values(array_unique(array_filter(array_map(static fn($value): string => trim((string) $value), $questions), static fn(string $value): bool => $value !== '')));
        return $questions;
    }

    private function tableExists(string $table): bool
    {
        try {
            $this->pdo()->query('SELECT 1 FROM ' . $this->bt($table) . ' LIMIT 1');
            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @return array<string, bool>
     */
    private function tableCols(string $table): array
    {
        if (isset($this->tableCols[$table])) {
            return $this->tableCols[$table];
        }

        try {
            $st = $this->pdo()->query('DESCRIBE ' . $this->bt($table));
            $cols = [];
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $col = (string) ($row['Field'] ?? '');
                if ($col !== '') {
                    $cols[$col] = true;
                }
            }
            return $this->tableCols[$table] = $cols;
        } catch (Throwable) {
            return $this->tableCols[$table] = [];
        }
    }

    private function hasCol(string $table, string $column): bool
    {
        return isset($this->tableCols($table)[$column]);
    }

    private function pickCol(string $table, array $candidates, string $fallback): string
    {
        $cols = $this->tableCols($table);
        foreach ($candidates as $candidate) {
            if (isset($cols[$candidate])) {
                return $candidate;
            }
        }
        return $fallback;
    }

    private function bt(string $identifier): string
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $identifier)) {
            throw new RuntimeException('Identificador inválido: ' . $identifier);
        }

        return '`' . $identifier . '`';
    }

    private function pdo(): PDO
    {
        return $this->pdo ??= $this->database->pdo();
    }
}
