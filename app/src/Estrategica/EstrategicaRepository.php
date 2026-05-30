<?php
declare(strict_types=1);

namespace App\Estrategica;

use App\Infra\Database;
use PDO;
use RuntimeException;

final class EstrategicaRepository
{
    private PDO $pdo;
    private array $columnCache = [];

    public function __construct(Database $database)
    {
        $this->pdo = $database->pdo();
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    /** @return array<int, array<string,mixed>> */
    public function fetchQuestionGroups(int $limit = 500): array
    {
        $sql = "
            SELECT
              company_name,
              email_resp,
              DATE_FORMAT(response_datetime, '%Y-%m-%d %H:%i') AS sess_min,
              MAX(response_datetime) AS last_datetime
            FROM responses_detailed
            GROUP BY company_name, email_resp, sess_min
            ORDER BY last_datetime DESC
            LIMIT :lim
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array<int, array{id:int,name:string,prompt_grp:string}> */
    public function fetchPriorityGroups(): array
    {
        $sql = "SELECT id_grupo AS id, grupo_nome AS name, COALESCE(prompt_grp,'') AS prompt_grp FROM grupos_nome ORDER BY grupo_nome ASC";
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array<string,mixed>|null */
    public function fetchGroupByIdOrName(?int $groupId, string $groupName = ''): ?array
    {
        if (($groupId ?? 0) > 0) {
            $stmt = $this->pdo->prepare('SELECT id_grupo AS id, grupo_nome AS name, COALESCE(prompt_grp,\'\') AS prompt_grp FROM grupos_nome WHERE id_grupo = ? LIMIT 1');
            $stmt->execute([$groupId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        }
        if ($groupName !== '') {
            $stmt = $this->pdo->prepare('SELECT id_grupo AS id, grupo_nome AS name, COALESCE(prompt_grp,\'\') AS prompt_grp FROM grupos_nome WHERE grupo_nome = ? LIMIT 1');
            $stmt->execute([$groupName]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        }
        return null;
    }

    /** @return list<string> */
    public function fetchGroupQuestions(int $groupId): array
    {
        $questionColumn = $this->pickColumn('grupos_prioridades', ['question_name', 'field_name', 'name', 'question'], 'field_name');
        $sql = 'SELECT ' . $this->bt($questionColumn) . ' AS qname FROM grupos_prioridades WHERE id_grupo = ? ORDER BY ' . $this->bt($questionColumn) . ' ASC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$groupId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return array_values(array_filter(array_map(static fn(array $row): string => trim((string) ($row['qname'] ?? '')), $rows)));
    }

    /** @return array{assistente:string,prompt:string}|null */
    public function findFirstPromptByNames(array $names): ?array
    {
        $stmt = $this->pdo->prepare('SELECT assistente, prompt FROM prompts WHERE LOWER(TRIM(assistente)) = LOWER(?) LIMIT 1');
        foreach ($names as $name) {
            $name = trim((string) $name);
            if ($name === '') {
                continue;
            }
            $stmt->execute([$name]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            if (is_array($row) && trim((string) ($row['prompt'] ?? '')) !== '') {
                return [
                    'assistente' => trim((string) ($row['assistente'] ?? $name)),
                    'prompt' => trim((string) ($row['prompt'] ?? '')),
                ];
            }
        }
        return null;
    }

    public function findPromptByLike(string $prefix): string
    {
        $stmt = $this->pdo->prepare('SELECT prompt FROM prompts WHERE LOWER(TRIM(assistente)) LIKE LOWER(?) LIMIT 1');
        $stmt->execute(['%' . trim($prefix) . '%']);
        $prompt = trim((string) ($stmt->fetchColumn() ?: ''));
        if ($prompt === '') {
            throw new RuntimeException("Prompt '{$prefix}' não encontrado ou vazio.");
        }
        return $prompt;
    }

    /** @return array{company:string,email:string,sess_min:string,version_datetime:string,items:array<int,array<string,mixed>>} */
    public function fetchResponsesForGroup(string $company, string $email, string $sessMin, array $questionNames = []): array
    {
        $dtColumn = $this->pickColumn('responses_detailed', ['response_datetime', 'created_at'], 'response_datetime');
        $fieldColumn = $this->pickColumn('responses_detailed', ['question_name'], 'question_name');
        $labelColumn = $this->pickColumn('responses_detailed', ['question_label'], 'question_label');
        $answerColumn = $this->pickColumn('responses_detailed', ['answer'], 'answer');
        $promptRespColumn = $this->pickColumn('responses_detailed', ['prompt_response', 'llm_response', 'response_text'], 'prompt_response');
        $promptColumn = $this->pickColumn('responses_detailed', ['prompt'], 'prompt');
        $promptCodeColumn = $this->pickColumn('responses_detailed', ['prompt_code'], 'prompt_code');

        $start = $this->sessMinToDateTime($sessMin);
        $versionStmt = $this->pdo->prepare(
            'SELECT MAX(' . $this->bt($dtColumn) . ') AS version_datetime'
            . ' FROM responses_detailed'
            . ' WHERE company_name = ? AND email_resp = ?'
            . ' AND DATE_FORMAT(' . $this->bt($dtColumn) . ',\'%Y-%m-%d %H:%i\') = ?'
        );
        $versionStmt->execute([$company, $email, $sessMin]);
        $versionDateTime = trim((string) ($versionStmt->fetchColumn() ?: $start));

        $sql = 'SELECT '
            . $this->bt($fieldColumn) . ' AS question_name, '
            . $this->bt($labelColumn) . ' AS question_label, '
            . $this->bt($answerColumn) . ' AS answer, '
            . $this->bt($promptRespColumn) . ' AS prompt_response'
            . ' FROM responses_detailed'
            . ' WHERE company_name = ? AND email_resp = ? AND ' . $this->bt($dtColumn) . ' = ?';

        $params = [$company, $email, $versionDateTime];
        if ($questionNames !== []) {
            $placeholders = implode(',', array_fill(0, count($questionNames), '?'));
            $sql .= ' AND ' . $this->bt($fieldColumn) . ' IN (' . $placeholders . ')';
            $params = array_merge($params, array_values($questionNames));
        }

        $sql .= ' ORDER BY ' . $this->bt($fieldColumn) . ' ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return [
            'company' => $company,
            'email' => $email,
            'sess_min' => $sessMin,
            'version_datetime' => $versionDateTime,
            'items' => $items,
        ];
    }


    /** @return array<string,mixed>|null */
    public function findQuestionGroupSession(string $company, string $email, string $sessMin): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, email_user, email_resp, company_name, response_datetime
             FROM response_sessions
             WHERE company_name = ? AND email_resp = ? AND DATE_FORMAT(response_datetime,'%Y-%m-%d %H:%i') = ?
             ORDER BY response_datetime DESC, id DESC
             LIMIT 1"
        );
        try {
            $stmt->execute([$company, $email, $sessMin]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (is_array($row)) {
                $row['sess_min'] = $sessMin;
                return $row;
            }
        } catch (\Throwable) {
        }

        $fallback = $this->pdo->prepare(
            "SELECT MAX(response_session_id) AS id, MAX(email_user) AS email_user, email_resp, company_name, MAX(response_datetime) AS response_datetime
             FROM responses_detailed
             WHERE company_name = ? AND email_resp = ? AND DATE_FORMAT(response_datetime,'%Y-%m-%d %H:%i') = ?
             GROUP BY company_name, email_resp
             LIMIT 1"
        );
        $fallback->execute([$company, $email, $sessMin]);
        $row = $fallback->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }
        $row['sess_min'] = $sessMin;
        return $row;
    }

    public function fetchDiagPriorityJson(string $questionnaireIdx, int $groupId): ?string
    {
        $stmt = $this->pdo->prepare(
            'SELECT result_json'
            . ' FROM diag_priority'
            . ' WHERE questionnaire_idx = ? AND group_id = ?'
            . ' ORDER BY group_id DESC LIMIT 1'
        );
        $stmt->execute([$questionnaireIdx, $groupId]);
        $json = $stmt->fetchColumn();
        if (!is_string($json) || trim($json) === '') {
            return null;
        }
        return trim($json);
    }

    /** @return array<string,mixed> */
    public function ensureStatusRow(string $user, string $email, string $sessMin): array
    {
        $row = $this->getStatusRow($user, $email, $sessMin);
        if ($row !== []) {
            return $row;
        }

        $dtFull = $this->sessMinToDateTime($sessMin);
        $stmt = $this->pdo->prepare('INSERT INTO status_questionario (user, email_user, response_datetime, resumo_ok, doc_ok, apres_ok) VALUES (?, ?, ?, b\'0\', b\'0\', b\'0\')');
        $stmt->execute([$user, $email, $dtFull]);
        return $this->getStatusRow($user, $email, $sessMin);
    }

    /** @return array<string,mixed> */
    public function getStatusRow(string $user, string $email, string $sessMin): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT user, email_user, response_datetime, resumo_ok, doc_ok, apres_ok, file_resumo, file_doc, file_apres, arquivo_resumo, arquivo_doc, arquivo_apres
             FROM status_questionario
             WHERE user = ? AND email_user = ? AND DATE_FORMAT(response_datetime,'%Y-%m-%d %H:%i') = ?
             ORDER BY response_datetime DESC
             LIMIT 1"
        );
        $stmt->execute([$user, $email, $sessMin]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: [];
    }

    public function updateStatusResumo(string $user, string $email, string $sessMin, string $content, string $fileName): void
    {
        $this->ensureStatusRow($user, $email, $sessMin);
        $stmt = $this->pdo->prepare('UPDATE status_questionario SET resumo_ok = b\'1\', file_resumo = ?, arquivo_resumo = ? WHERE user = ? AND email_user = ? AND DATE_FORMAT(response_datetime,\'%Y-%m-%d %H:%i\') = ? LIMIT 1');
        $stmt->execute([$fileName, $content, $user, $email, $sessMin]);
    }

    public function updateStatusDoc(string $user, string $email, string $sessMin, string $content, string $fileName): void
    {
        $this->ensureStatusRow($user, $email, $sessMin);
        $stmt = $this->pdo->prepare('UPDATE status_questionario SET doc_ok = b\'1\', file_doc = ?, arquivo_doc = ? WHERE user = ? AND email_user = ? AND DATE_FORMAT(response_datetime,\'%Y-%m-%d %H:%i\') = ? LIMIT 1');
        $stmt->execute([$fileName, $content, $user, $email, $sessMin]);
    }

    public function updateStatusApres(string $user, string $email, string $sessMin, string $content, string $fileName): void
    {
        $this->ensureStatusRow($user, $email, $sessMin);
        $stmt = $this->pdo->prepare('UPDATE status_questionario SET apres_ok = b\'1\', file_apres = ?, arquivo_apres = ? WHERE user = ? AND email_user = ? AND DATE_FORMAT(response_datetime,\'%Y-%m-%d %H:%i\') = ? LIMIT 1');
        $stmt->execute([$fileName, $content, $user, $email, $sessMin]);
    }

    /** @return array{file_resumo:string,content:string,resumo_ok:bool,doc_ok:bool} */
    public function findResumoContent(string $user, string $email, string $sessMin, string $uploadsDir): array
    {
        $row = $this->getStatusRow($user, $email, $sessMin);
        if ($row === []) {
            throw new RuntimeException('Registro de status não encontrado.');
        }

        $file = trim((string) ($row['file_resumo'] ?? ''));
        $blob = $row['arquivo_resumo'] ?? null;
        $content = '';

        if ($file !== '') {
            $path = rtrim($uploadsDir, '/\\') . DIRECTORY_SEPARATOR . basename($file);
            if (is_file($path)) {
                $content = (string) file_get_contents($path);
            }
        }
        if ($content === '' && is_string($blob) && $blob !== '') {
            $content = $blob;
        }
        if ($content === '') {
            throw new RuntimeException('Resumo não encontrado (arquivo/BLOB).');
        }

        return [
            'file_resumo' => $file,
            'content' => $content,
            'resumo_ok' => $this->bitToBool($row['resumo_ok'] ?? null),
            'doc_ok' => $this->bitToBool($row['doc_ok'] ?? null),
        ];
    }

    /** @return array{mime:string,filename:string,path:?string,blob:?string} */
    public function resolveDownload(string $type, string $user, string $email, string $sessMin, string $uploadsDir): array
    {
        $row = $this->getStatusRow($user, $email, $sessMin);
        if ($row === []) {
            throw new RuntimeException('Registro de status não encontrado.');
        }

        $map = [
            'doc' => ['file' => 'file_doc', 'blob' => 'arquivo_doc', 'fallback' => 'doc.doc'],
            'apres' => ['file' => 'file_apres', 'blob' => 'arquivo_apres', 'fallback' => 'PPTX.json'],
            'resumo' => ['file' => 'file_resumo', 'blob' => 'arquivo_resumo', 'fallback' => 'resumo.doc'],
        ];
        if (!isset($map[$type])) {
            throw new RuntimeException('Tipo inválido.');
        }

        $file = trim((string) ($row[$map[$type]['file']] ?? ''));
        if ($file === '') {
            $file = $map[$type]['fallback'];
        }
        $path = rtrim($uploadsDir, '/\\') . DIRECTORY_SEPARATOR . basename($file);
        if (is_file($path)) {
            return [
                'mime' => $type === 'apres' ? 'application/json; charset=utf-8' : $this->mimeForDocFilename($file),
                'filename' => basename($file),
                'path' => $path,
                'blob' => null,
            ];
        }

        $blob = $row[$map[$type]['blob']] ?? null;
        if (is_string($blob) && $blob !== '') {
            return [
                'mime' => $type === 'apres' ? 'application/json; charset=utf-8' : $this->mimeForDocFilename($file),
                'filename' => basename($file),
                'path' => null,
                'blob' => $blob,
            ];
        }

        throw new RuntimeException('Arquivo não encontrado.');
    }

    public function uploadsDir(): string
    {
        if (function_exists('app_path')) {
            return (string) \app_path('public/uploads');
        }
        return dirname(__DIR__, 3) . '/public/uploads';
    }

    /** @return array<string,bool> */
    private function tableColumns(string $table): array
    {
        if (isset($this->columnCache[$table])) {
            return $this->columnCache[$table];
        }
        $stmt = $this->pdo->query('DESCRIBE ' . $this->bt($table));
        $columns = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $field = trim((string) ($row['Field'] ?? ''));
            if ($field !== '') {
                $columns[$field] = true;
            }
        }
        $this->columnCache[$table] = $columns;
        return $columns;
    }

    private function pickColumn(string $table, array $candidates, string $fallback): string
    {
        $columns = $this->tableColumns($table);
        foreach ($candidates as $candidate) {
            if (isset($columns[$candidate])) {
                return $candidate;
            }
        }
        return $fallback;
    }

    private function bt(string $identifier): string
    {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $identifier)) {
            throw new RuntimeException('Identificador inválido: ' . $identifier);
        }
        return '`' . $identifier . '`';
    }

    private function sessMinToDateTime(string $sessMin): string
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}$/', $sessMin)) {
            throw new RuntimeException('sess_min inválido: ' . $sessMin);
        }
        return $sessMin . ':00';
    }

    private function bitToBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value)) {
            return $value === 1;
        }
        if (is_string($value)) {
            return $value === '1' || (strlen($value) > 0 && ord($value) === 1);
        }
        return false;
    }

    private function mimeForDocFilename(string $filename): string
    {
        return strtolower(pathinfo($filename, PATHINFO_EXTENSION)) === 'doc'
            ? 'application/msword'
            : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
    }
}
