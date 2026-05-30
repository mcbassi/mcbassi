<?php
declare(strict_types=1);

namespace App\Prompts;

use App\Diagnostico\FormFieldRepository;
use App\Diagnostico\VersionedResponseRepository;
use App\Infra\Database;
use App\Infra\Env;
use App\Papers\PaperFileService;
use App\Papers\RagRepository;
use PDO;
use RuntimeException;
use Throwable;

final class PromptRuntimeService
{
    private ?PDO $pdo = null;
    private ?PDO $statisticsPdo = null;
    private ?FormFieldRepository $fieldRepository = null;
    private ?VersionedResponseRepository $versionRepository = null;
    private ?RagRepository $ragRepository = null;
    private ?PaperFileService $paperFileService = null;

    public function __construct(
        private readonly Database $database,
        private readonly PromptRepository $promptRepository
    ) {
    }

    /**
     * @param array<string, mixed> $promptRow
     * @return array<string, mixed>
     */
    public function build(array $promptRow, string $emailUser, ?string $companyName = null, ?int $versionId = null): array
    {
        $source = $this->resolveSource($emailUser, $companyName, $versionId);
        if ($source === null) {
            return [
                'available' => false,
                'source_label' => 'Sem respostas salvas para prévia',
                'resolved_prompt' => (string) ($promptRow['prompt'] ?? ''),
                'unresolved_markers' => $this->extractMarkers((string) ($promptRow['prompt'] ?? '')),
                'marker_values' => [],
                'attachments' => [],
                'sql' => [
                    'has_sql' => false,
                    'desc' => '',
                    'sql_text' => '',
                    'rows' => [],
                    'row_count' => 0,
                    'json_text' => '',
                    'json_data' => null,
                    'error' => null,
                ],
                'source' => null,
            ];
        }

        $answers = $this->answersForSource($source, $emailUser);
        $paperMap = $this->paperMap();
        $runtime = $this->buildFromState($promptRow, $source, $answers, $paperMap, false);

        $runtime['available'] = true;
        $runtime['source_label'] = $this->sourceLabel($source);
        $runtime['source'] = $source;

        return $runtime;
    }

    /**
     * @param array<string, mixed> $promptRow
     * @return array<string, mixed>
     */
    public function buildExecution(array $promptRow, string $emailUser, ?string $companyName = null, ?int $versionId = null): array
    {
        $source = $this->resolveSource($emailUser, $companyName, $versionId);
        if ($source === null) {
            throw new RuntimeException('Sem respostas salvas para executar o prompt.');
        }

        $answers = $this->answersForSource($source, $emailUser);
        $paperMap = $this->paperMap();
        $runtime = $this->buildFromState($promptRow, $source, $answers, $paperMap, true);
        $runtime['available'] = true;
        $runtime['source_label'] = $this->sourceLabel($source);
        $runtime['source'] = $source;

        return $runtime;
    }

    /**
     * @return array<string, mixed>
     */
    public function buildContextPackage(string $context, string $emailUser, ?string $companyName = null, ?int $versionId = null, bool $onlyWithPrompt = false): array
    {
        $source = $this->resolveSource($emailUser, $companyName, $versionId);
        $fields = $emailUser !== '' ? $this->fieldRepository()->all() : [];
        $paperMap = $this->paperMap();

        if ($source === null) {
            return [
                'context' => $context,
                'selectedVersion' => null,
                'items' => [],
                'stats' => [
                    'prompt_count' => 0,
                    'ready_count' => 0,
                    'with_sql' => 0,
                    'attached_papers' => 0,
                    'unresolved_markers' => 0,
                ],
                'compiled_prompt' => '',
                'company_name' => trim((string) $companyName),
                'source_label' => 'Sem versão carregada',
            ];
        }

        $answers = $this->answersForSource($source, $emailUser);
        $items = [];

        if (trim(mb_strtolower($context)) === 'analitica') {
            $items = $this->buildAnaliticaItemsFromResponses($source, $answers, $fields, $paperMap, $emailUser, $onlyWithPrompt);
        } else {
            $dedupeByPromptCode = true;
            $seenAssistentes = [];
            foreach ($fields as $field) {
                $type = strtolower(trim((string) ($field['type'] ?? '')));
                if (in_array($type, ['title', 'subtitle'], true)) {
                    continue;
                }

                $promptCode = trim((string) ($field['prompt_code'] ?? ''));
                if ($dedupeByPromptCode && $promptCode !== '' && isset($seenAssistentes[$promptCode])) {
                    continue;
                }

                $currentAnswer = trim((string) ($answers[mb_strtolower(trim((string) ($field['name'] ?? '')))] ?? ''));
                $promptRow = $promptCode !== '' ? $this->promptRepository->findByAssistente($promptCode) : null;
                $runtime = is_array($promptRow)
                    ? $this->buildFromState($promptRow, $source, $answers, $paperMap, false)
                    : $this->emptyRuntime($source);

                if ($dedupeByPromptCode && $promptCode !== '') {
                    $seenAssistentes[$promptCode] = true;
                }

                $item = [
                    'prompt' => is_array($promptRow) ? $promptRow : [],
                    'runtime' => $runtime,
                    'field' => $field,
                    'current_answer' => $currentAnswer,
                    'status' => $this->statusForRuntime($runtime),
                    'question_name' => trim((string) ($field['name'] ?? '')),
                    'response_session_id' => (int) ($source['id'] ?? 0),
                ];

                if ($this->matchesContext($item, $context)) {
                    $items[] = $item;
                }
            }
        }

        usort($items, static function (array $left, array $right): int {
            $leftOrder = (int) ($left['field']['sort_order'] ?? 0);
            $rightOrder = (int) ($right['field']['sort_order'] ?? 0);

            if ($leftOrder === $rightOrder) {
                return strcmp((string) ($left['prompt']['assistente'] ?? ''), (string) ($right['prompt']['assistente'] ?? ''));
            }

            return $leftOrder <=> $rightOrder;
        });

        $uniquePapers = [];
        $readyCount = 0;
        $withSql = 0;
        $unresolved = 0;
        $compiledChunks = [];

        foreach ($items as $item) {
            $runtime = (array) ($item['runtime'] ?? []);
            if ((string) ($item['status'] ?? '') === 'ready') {
                $readyCount++;
            }
            if (!empty($runtime['sql']['has_sql'])) {
                $withSql++;
            }
            $unresolved += count((array) ($runtime['unresolved_markers'] ?? []));

            foreach ((array) ($runtime['attachments'] ?? []) as $attachment) {
                $paperId = (int) ($attachment['id'] ?? 0);
                $paperKey = $paperId > 0 ? 'id:' . $paperId : 'title:' . mb_strtolower(trim((string) ($attachment['title'] ?? '')));
                $uniquePapers[$paperKey] = true;
            }

            $compiledChunks[] = $this->compiledChunk($item);
        }

        return [
            'context' => $context,
            'selectedVersion' => $source,
            'items' => $items,
            'stats' => [
                'prompt_count' => count($items),
                'ready_count' => $readyCount,
                'with_sql' => $withSql,
                'attached_papers' => count($uniquePapers),
                'unresolved_markers' => $unresolved,
            ],
            'compiled_prompt' => trim(implode("\n\n---\n\n", array_filter($compiledChunks))),
            'company_name' => trim((string) ($source['company_name'] ?? '')),
            'source_label' => $this->sourceLabel($source),
        ];
    }

    /**
     * @param array<string, mixed> $promptRow
     * @param array<string, mixed> $source
     * @param array<string, string> $answers
     * @param array<string, array<string, mixed>> $paperMap
     * @return array<string, mixed>
     */
    /**
     * @param array<string, mixed> $source
     * @param array<string, string> $answers
     * @param array<int, array<string, mixed>> $fields
     * @param array<string, array<string, mixed>> $paperMap
     * @return array<int, array<string, mixed>>
     */
    private function buildAnaliticaItemsFromResponses(array $source, array $answers, array $fields, array $paperMap, string $emailUser, bool $onlyWithPrompt = false): array
    {
        $fieldMap = [];
        foreach ($fields as $field) {
            $name = trim((string) ($field['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $fieldMap[mb_strtolower($name)] = $field;
        }

        $rows = $this->responseRowsForSource($source, $emailUser);
        $items = [];

        foreach ($rows as $position => $row) {
            $questionName = trim((string) ($row['question_name'] ?? ''));
            if ($questionName === '') {
                continue;
            }

            $field = $fieldMap[mb_strtolower($questionName)] ?? [
                'id' => (int) ($row['id'] ?? 0),
                'sort_order' => $position + 1,
                'name' => $questionName,
                'label' => trim((string) ($row['question_label'] ?? $questionName)),
                'type' => 'text',
                'required' => false,
                'placeholder' => '',
                'prompt_code' => trim((string) ($row['prompt_code'] ?? '')),
                'options' => [],
                'min' => null,
                'max' => null,
                'step' => null,
            ];

            $promptCode = trim((string) ($row['prompt_code'] ?? $field['prompt_code'] ?? ''));
            $promptRow = $promptCode !== '' ? $this->promptRepository->findByAssistente($promptCode) : null;

            if (!is_array($promptRow)) {
                $storedPrompt = trim((string) ($row['prompt'] ?? ''));
                if ($storedPrompt !== '' || $promptCode !== '') {
                    $promptRow = [
                        'id' => 0,
                        'assistente' => $promptCode,
                        'funcao' => '',
                        'descricao' => '',
                        'prompt' => $storedPrompt,
                        'prompt_full_text' => $storedPrompt,
                        'updated_at' => '',
                    ];
                }
            }

            $runtime = is_array($promptRow)
                ? $this->buildFromState($promptRow, $source, $answers, $paperMap, false)
                : $this->emptyRuntime($source);

            $sessionPromptCode = trim((string) ($row['prompt_code'] ?? ''));
            $sessionPromptText = trim((string) ($row['prompt'] ?? ''));
            $hasPrompt = $sessionPromptText !== '';

            if ($onlyWithPrompt && !$hasPrompt) {
                continue;
            }

            $items[] = [
                'prompt' => is_array($promptRow) ? $promptRow : [],
                'runtime' => $runtime,
                'field' => $field,
                'current_answer' => trim((string) ($row['answer'] ?? '')),
                'status' => $this->statusForRuntime($runtime),
                'question_name' => $questionName,
                'response_session_id' => (int) ($source['id'] ?? 0),
                'row' => [
                    'id' => (int) ($row['id'] ?? 0),
                    'prompt_code' => $sessionPromptCode,
                    'prompt' => (string) ($row['prompt'] ?? ''),
                    'response_datetime' => trim((string) ($row['response_datetime'] ?? '')),
                ],
                'has_prompt' => $hasPrompt,
            ];
        }

        return $items;
    }

    /**
     * @param array<string, mixed> $source
     * @return array<int, array<string, mixed>>
     */
    private function responseRowsForSource(array $source, string $emailUser): array
    {
        $versionId = (int) ($source['id'] ?? 0);
        $isLegacy = !empty($source['is_legacy']) || strtolower(trim((string) ($source['status'] ?? ''))) === 'legacy';
        $responseDateTime = trim((string) ($source['response_datetime'] ?? ''));
        $companyName = trim((string) ($source['company_name'] ?? ''));
        $emailResp = trim((string) ($source['email_resp'] ?? ''));
        $effectiveEmailUser = trim((string) ($source['email_user'] ?? $emailUser));

        $rowsById = [];

        if (!$isLegacy && $versionId > 0 && $this->columnExists('responses_detailed', 'response_session_id')) {
            $stmt = $this->pdo()->prepare('SELECT id, question_name, question_label, answer, prompt_code, prompt, response_datetime, company_name, email_resp FROM responses_detailed WHERE response_session_id = :session_id ORDER BY id ASC');
            $stmt->execute([':session_id' => $versionId]);
            foreach (($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) as $row) {
                $rowId = (int) ($row['id'] ?? 0);
                if ($rowId > 0) {
                    $rowsById[$rowId] = $row;
                }
            }
        }

        if ($responseDateTime !== '' && $effectiveEmailUser !== '') {
            $sql = "SELECT id, question_name, question_label, answer, prompt_code, prompt, response_datetime, company_name, email_resp
                    FROM responses_detailed
                    WHERE email_user = :email_user
                      AND DATE_FORMAT(response_datetime, '%Y-%m-%d %H:%i') = :response_datetime";
            $params = [
                ':email_user' => $effectiveEmailUser,
                ':response_datetime' => $responseDateTime,
            ];

            if ($companyName !== '') {
                $sql .= ' AND COALESCE(company_name, "") = :company_name';
                $params[':company_name'] = $companyName;
            }

            if ($emailResp !== '') {
                $sql .= ' AND COALESCE(email_resp, "") = :email_resp';
                $params[':email_resp'] = $emailResp;
            }

            $sql .= ' ORDER BY id ASC';
            $stmt = $this->pdo()->prepare($sql);
            $stmt->execute($params);

            foreach (($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) as $row) {
                $rowId = (int) ($row['id'] ?? 0);
                if ($rowId > 0) {
                    $rowsById[$rowId] = $row;
                } else {
                    $rowsById[] = $row;
                }
            }
        }

        if ($rowsById === []) {
            return [];
        }

        $rows = array_values($rowsById);
        usort($rows, static function (array $left, array $right): int {
            $leftId = (int) ($left['id'] ?? 0);
            $rightId = (int) ($right['id'] ?? 0);
            if ($leftId !== $rightId) {
                return $leftId <=> $rightId;
            }

            return strcmp((string) ($left['question_name'] ?? ''), (string) ($right['question_name'] ?? ''));
        });

        return $rows;
    }

    /**
     * @param array<string, mixed> $source
     * @return array<string, mixed>
     */
    private function emptyRuntime(array $source): array
    {
        return [
            'available' => false,
            'source_label' => $this->sourceLabel($source),
            'resolved_prompt' => '',
            'unresolved_markers' => [],
            'marker_values' => [],
            'marker_count' => 0,
            'attachments' => [],
            'sql' => [
                'has_sql' => false,
                'desc' => '',
                'sql_text' => '',
                'rows' => [],
                'row_count' => 0,
                'json_text' => '',
                'json_data' => null,
                'error' => null,
            ],
            'source' => $source,
        ];
    }

    private function buildFromState(array $promptRow, array $source, array $answers, array $paperMap, bool $executeHeavy = false): array
    {
        $prompt = (string) ($promptRow['prompt_full_text'] ?? $promptRow['prompt'] ?? '');
        $promptWithoutSql = $this->stripSqlBlock($prompt);
        [$basePrompt, $attachments, $values, $missingPrompt] = $this->replaceMarkers($promptWithoutSql, $answers, $paperMap);
        $attachments = $this->mergeAttachments($attachments, $this->detectPaperReferences($promptWithoutSql, $paperMap));
        $attachments = $this->mergeAttachments($attachments, $this->detectPaperReferences($basePrompt, $paperMap));

        $sqlInfo = $executeHeavy
            ? $this->executeResolvedSqlBlock($prompt, $answers)
            : $this->previewResolvedSqlBlock($prompt, $answers);

        $missing = array_values(array_unique(array_merge(
            $missingPrompt,
            (array) ($sqlInfo['unresolved_markers'] ?? [])
        )));

        $resolvedPrompt = $basePrompt;
        if (!empty($sqlInfo['has_sql'])) {
            if ($resolvedPrompt !== '') {
                $resolvedPrompt .= "

";
            }

            $resolvedPrompt .= 'EXECUTAR SQL=';
            if (trim((string) ($sqlInfo['desc'] ?? '')) !== '') {
                $resolvedPrompt .= "
-- DESC: " . trim((string) $sqlInfo['desc']);
            }
            if (trim((string) ($sqlInfo['sql_text'] ?? '')) !== '') {
                $resolvedPrompt .= "
" . trim((string) $sqlInfo['sql_text']);
            }
        }

        $executionPrompt = $basePrompt;
        if (!empty($sqlInfo['has_sql']) && empty($sqlInfo['error']) && trim((string) ($sqlInfo['json_text'] ?? '')) !== '') {
            if ($executionPrompt !== '') {
                $executionPrompt .= "

";
            }
            $executionPrompt .= "SQL_RESULT_JSON=
" . trim((string) $sqlInfo['json_text']);
        }

        return [
            'marker_count' => count($values),
            'resolved_prompt' => trim($resolvedPrompt),
            'execution_prompt' => trim($executionPrompt),
            'unresolved_markers' => $missing,
            'marker_values' => $values,
            'attachments' => array_values($attachments),
            'sql' => $sqlInfo,
            'source' => $source,
        ];
    }

    /**
     * @param array<string, string> $answers
     * @param array<string, array<string, mixed>> $paperMap
     * @return array{0:string,1:array<string,array<string,mixed>>,2:array<string,string>,3:array<int,string>}
     */
    private function replaceMarkers(string $prompt, array $answers, array $paperMap): array
    {
        $attachments = [];
        $values = [];
        $missing = [];

        $resolved = preg_replace_callback('/<<([^>]+)>>/u', function (array $matches) use ($answers, $paperMap, &$attachments, &$values, &$missing): string {
            $raw = trim((string) ($matches[1] ?? ''));
            $key = mb_strtolower($raw);

            if (array_key_exists($key, $answers)) {
                $value = trim((string) $answers[$key]);
                $values[$raw] = $value === '' ? '[sem resposta]' : $value;
                return $value === '' ? '[sem resposta]' : $value;
            }

            if (array_key_exists($key, $paperMap)) {
                $paper = $paperMap[$key];
                $paperId = (int) ($paper['id'] ?? 0);
                $attachmentKey = $paperId > 0 ? 'id:' . $paperId : 'title:' . $key;
                $attachments[$attachmentKey] = $paper;
                $values[$raw] = '[paper] ' . trim((string) ($paper['title'] ?? $raw));
                return '[paper anexado: ' . trim((string) ($paper['title'] ?? $raw)) . ']';
            }

            $missing[] = $raw;
            $values[$raw] = '[não resolvido]';
            return '<<' . $raw . '>>';
        }, $prompt);

        return [$resolved ?? $prompt, $attachments, $values, array_values(array_unique($missing))];
    }


    /**
     * @param array<string, array<string, mixed>> $existing
     * @param array<string, array<string, mixed>> $incoming
     * @return array<string, array<string, mixed>>
     */
    private function mergeAttachments(array $existing, array $incoming): array
    {
        foreach ($incoming as $key => $paper) {
            $existing[$key] = $paper;
        }

        return $existing;
    }

    /**
     * @param array<string, array<string, mixed>> $paperMap
     * @return array<string, array<string, mixed>>
     */
    private function detectPaperReferences(string $prompt, array $paperMap): array
    {
        $attachments = [];
        $lines = preg_split('/\r\n|\r|\n/', $prompt) ?: [];

        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }

            if (preg_match('/^\[(?:ARTICLE|ARTIGO|PAPER|BOOK|REPORT|DOC)\]\s*(.+)$/iu', $line, $matches) !== 1) {
                continue;
            }

            $title = trim((string) ($matches[1] ?? ''));
            if ($title === '') {
                continue;
            }

            $key = $this->normalizePaperKey($title);
            if (!isset($paperMap[$key])) {
                continue;
            }

            $paper = $paperMap[$key];
            $paperId = (int) ($paper['id'] ?? 0);
            $attachmentKey = $paperId > 0 ? 'id:' . $paperId : 'title:' . $key;
            $attachments[$attachmentKey] = $paper;
        }

        return $attachments;
    }

    private function normalizePaperKey(string $title): string
    {
        $title = mb_strtolower(trim($title));
        $title = preg_replace('/^\[(?:article|artigo|paper|book|report|doc)\]\s*/iu', '', $title) ?? $title;
        $title = preg_replace('/[`´’‘“”"\']+/u', '', $title) ?? $title;
        $title = preg_replace('/\s+/u', ' ', $title) ?? $title;

        return trim($title);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function paperMap(): array
    {
        if (!$this->tableExists('papers')) {
            return [];
        }

        $rows = $this->pdo()->query('SELECT * FROM papers ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $map = [];

        foreach ($rows as $row) {
            $paper = $this->ragRepository()->enrich($row);
            $title = trim((string) ($paper['title'] ?? ''));
            if ($title === '') {
                continue;
            }

            $paper['file_open_href'] = (int) ($paper['id'] ?? 0) > 0
                ? \url('papers/open.php?id=' . (int) $paper['id'])
                : null;
            $paper['file_kind'] = null;

            $map[mb_strtolower($title)] = $paper;
            $map[$this->normalizePaperKey($title)] = $paper;
        }

        return $map;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveSource(string $emailUser, ?string $companyName, ?int $versionId): ?array
    {
        $emailUser = trim($emailUser);
        if ($emailUser === '') {
            return null;
        }

        $this->versionRepository()->ensureSchema();

        if (($versionId ?? 0) > 0) {
            $version = $this->versionRepository()->versionById((int) $versionId, $emailUser);
            if (is_array($version)) {
                return $version;
            }
        }

        $companyName = trim((string) $companyName);
        if ($companyName !== '') {
            foreach ($this->versionRepository()->versions($emailUser) as $version) {
                if (trim((string) ($version['company_name'] ?? '')) === $companyName) {
                    return $version;
                }
            }
        }

        return $this->versionRepository()->latestVersion($emailUser);
    }

    /**
     * @param array<string, mixed> $source
     * @return array<string, string>
     */
    private function answersForSource(array $source, string $emailUser): array
    {
        $answers = [];
        $versionId = (int) ($source['id'] ?? 0);
        $responseDateTime = trim((string) ($source['response_datetime'] ?? ''));

        if ($versionId > 0) {
            $rows = $this->versionRepository()->answersForVersion($versionId, $emailUser, $responseDateTime);
            foreach ($rows as $name => $value) {
                $answers[mb_strtolower(trim((string) $name))] = trim((string) $value);
            }
        }

        return $answers;
    }

    private function columnExists(string $table, string $column): bool
    {
        $stmt = $this->pdo()->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name');
        $stmt->execute([
            ':table_name' => $table,
            ':column_name' => $column,
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * @return array<string, mixed>
     */
    private function previewResolvedSqlBlock(string $prompt, array $answers): array
    {
        [$sql, $desc] = $this->extractSqlBlock($prompt);
        if ($sql === '') {
            return [
                'has_sql' => false,
                'desc' => '',
                'sql_text' => '',
                'rows' => [],
                'row_count' => 0,
                'json_text' => '',
                'json_data' => null,
                'unresolved_markers' => [],
                'error' => null,
            ];
        }

        [$resolvedSql, $missing] = $this->replaceAnswerMarkersInSql($sql, $answers);

        return [
            'has_sql' => true,
            'desc' => $desc,
            'sql_text' => trim($resolvedSql),
            'rows' => [],
            'row_count' => 0,
            'json_text' => '',
            'json_data' => null,
            'unresolved_markers' => $missing,
            'error' => null,
        ];
    }

    private function executeResolvedSqlBlock(string $prompt, array $answers): array
    {
        [$sql, $desc] = $this->extractSqlBlock($prompt);
        if ($sql === '') {
            return [
                'has_sql' => false,
                'desc' => '',
                'sql_text' => '',
                'rows' => [],
                'row_count' => 0,
                'json_text' => '',
                'json_data' => null,
                'unresolved_markers' => [],
                'error' => null,
            ];
        }

        [$resolvedSql, $missing] = $this->replaceAnswerMarkersInSql($sql, $answers);
        $resolvedSql = trim($resolvedSql);

        if ($missing !== []) {
            return [
                'has_sql' => true,
                'desc' => $desc,
                'sql_text' => $resolvedSql,
                'rows' => [],
                'row_count' => 0,
                'json_text' => '',
                'json_data' => null,
                'unresolved_markers' => $missing,
                'error' => 'Statistics DB: marcadores não resolvidos no SQL: ' . implode(', ', $missing),
            ];
        }

        try {
            $this->assertSelectOnly($resolvedSql);
            $resolvedSql = $this->normalizeSqlForStatistics($resolvedSql);

            $stmt = $this->statisticsPdo()->query($resolvedSql);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $jsonData = $this->buildResultadoJsonFromRows($rows);
            $jsonText = (string) json_encode($jsonData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
            if ($jsonText === '' || $jsonText === 'null') {
                throw new RuntimeException('não foi possível normalizar o retorno SQL em resultado_json.');
            }

            return [
                'has_sql' => true,
                'desc' => $desc,
                'sql_text' => $resolvedSql,
                'rows' => $rows,
                'row_count' => count($rows),
                'json_text' => $jsonText,
                'json_data' => $jsonData,
                'unresolved_markers' => [],
                'error' => null,
            ];
        } catch (Throwable $throwable) {
            return [
                'has_sql' => true,
                'desc' => $desc,
                'sql_text' => $resolvedSql,
                'rows' => [],
                'row_count' => 0,
                'json_text' => '',
                'json_data' => null,
                'unresolved_markers' => [],
                'error' => 'Statistics DB: ' . $throwable->getMessage(),
            ];
        }
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<string, mixed>
     */
    private function buildResultadoJsonFromRows(array $rows): array
    {
        if ($rows === []) {
            return [
                'rows' => [],
                'meta' => [
                    'row_count' => 0,
                    'normalized_from' => 'empty_result',
                ],
            ];
        }

        $firstRow = is_array($rows[0]) ? $rows[0] : [];
        $resultJson = trim((string) ($firstRow['resultado_json'] ?? ''));
        if ($resultJson !== '') {
            $decoded = json_decode($resultJson, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new RuntimeException('a coluna resultado_json não contém JSON válido.');
            }
            if (is_array($decoded)) {
                return $decoded;
            }
            return [
                'value' => $decoded,
                'meta' => [
                    'row_count' => count($rows),
                    'normalized_from' => 'resultado_json_scalar',
                ],
            ];
        }

        if (count($firstRow) === 1 && count($rows) === 1) {
            $singleColumn = (string) array_key_first($firstRow);
            $singleValue = $firstRow[$singleColumn] ?? null;
            if (is_string($singleValue)) {
                $decoded = json_decode(trim($singleValue), true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    return $decoded;
                }
            }
        }

        $normalizedRows = array_map(function (array $row): array {
            $normalized = [];
            foreach ($row as $key => $value) {
                if (is_scalar($value) || $value === null) {
                    $normalized[(string) $key] = $value;
                } else {
                    $normalized[(string) $key] = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }
            }
            return $normalized;
        }, $rows);

        return [
            'rows' => $normalizedRows,
            'row' => $normalizedRows[0] ?? null,
            'meta' => [
                'row_count' => count($normalizedRows),
                'column_names' => array_keys($firstRow),
                'normalized_from' => 'recordset',
            ],
        ];
    }

    private function normalizeSqlForStatistics(string $sql): string
    {
        $mainDb = trim((string) Env::get('DB_NAME', 'form_app'));
        $statsDb = trim((string) Env::get('STAT_DB_NAME', 'statistics'));

        if ($sql === '' || $mainDb === '' || $statsDb === '' || strcasecmp($mainDb, $statsDb) === 0) {
            return $sql;
        }

        $quotedMain = preg_quote($mainDb, '/');

        return preg_replace('/(?<![A-Za-z0-9_])`?' . $quotedMain . '`?\./i', $statsDb . '.', $sql) ?? $sql;
    }

    /**
     * @return array{0:string,1:string}
     */
    private function extractSqlBlock(string $prompt): array
    {
        $marker = 'EXECUTAR SQL=';
        $index = strrpos($prompt, $marker);

        if ($index === false) {
            return ['', ''];
        }

        $tail = trim((string) substr($prompt, $index + strlen($marker)));
        if ($tail === '') {
            return ['', ''];
        }

        $lines = preg_split('/\r\n|\r|\n/', $tail) ?: [];
        $desc = '';
        if ($lines !== [] && preg_match('/^\s*--\s*DESC\s*:\s*(.+)$/i', trim((string) $lines[0]), $matches) === 1) {
            $desc = trim((string) ($matches[1] ?? ''));
            array_shift($lines);
        }

        return [trim(implode("\n", $lines)), $desc];
    }

    private function stripSqlBlock(string $prompt): string
    {
        $marker = 'EXECUTAR SQL=';
        $index = strrpos($prompt, $marker);

        if ($index === false) {
            return trim($prompt);
        }

        return trim((string) substr($prompt, 0, $index));
    }

    private function assertSelectOnly(string $sql): void
    {
        $trimmed = trim($sql);
        if ($trimmed === '') {
            throw new RuntimeException('SQL vazio.');
        }

        $single = preg_replace('/;\s*$/', '', $trimmed) ?? $trimmed;
        if (strpos($single, ';') !== false) {
            throw new RuntimeException('SQL inválido: apenas uma instrução SELECT/WITH/CALL é permitida.');
        }

        if (!preg_match('/^(SELECT|WITH|CALL)\b/i', $single)) {
            throw new RuntimeException('SQL inválido: permitido apenas SELECT/WITH/CALL.');
        }

        if (preg_match('/\b(INSERT|UPDATE|DELETE|DROP|ALTER|TRUNCATE|CREATE|REPLACE)\b/i', $single)) {
            throw new RuntimeException('SQL inválido: instruções destrutivas não são permitidas.');
        }
    }

    /**
     * @return array<int, string>
     */
    private function extractMarkers(string $prompt): array
    {
        preg_match_all('/<<([^>]+)>>/u', $prompt, $matches);
        return array_values(array_unique(array_map('trim', $matches[1] ?? [])));
    }

    /**
     * @param array<string, mixed> $source
     */
    private function sourceLabel(array $source): string
    {
        $companyName = trim((string) ($source['company_name'] ?? ''));
        $responseDate = trim((string) ($source['response_datetime'] ?? ''));

        $label = $companyName !== '' ? $companyName : 'Última versão do usuário';
        if ($responseDate !== '') {
            $label .= ' · ' . $responseDate;
        }

        return $label;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function matchesContext(array $item, string $context): bool
    {
        $context = trim(mb_strtolower($context));
        if ($context === '') {
            return true;
        }

        $haystack = implode(' ', [
            (string) ($item['prompt']['assistente'] ?? ''),
            (string) ($item['prompt']['funcao'] ?? ''),
            (string) ($item['prompt']['descricao'] ?? ''),
            (string) ($item['field']['name'] ?? ''),
            (string) ($item['field']['label'] ?? ''),
        ]);
        $haystack = mb_strtolower($haystack);

        if ($context === 'analitica') {
            return str_contains($haystack, 'analit')
                || str_contains($haystack, 'captura')
                || str_contains($haystack, 'diagnost')
                || true;
        }

        if ($context === 'estrategica') {
            return str_contains($haystack, 'estrateg')
                || str_contains($haystack, 'plano')
                || str_contains($haystack, 'resumo')
                || true;
        }

        return true;
    }

    /**
     * @param array<string, mixed> $runtime
     */
    private function statusForRuntime(array $runtime): string
    {
        if (!empty($runtime['sql']['error'])) {
            return 'attention';
        }

        if (((array) ($runtime['unresolved_markers'] ?? [])) !== []) {
            return 'attention';
        }

        return 'ready';
    }

    /**
     * @param array<string, mixed> $item
     */
    private function compiledChunk(array $item): string
    {
        $question = trim((string) ($item['field']['name'] ?? ''));
        $answer = trim((string) ($item['current_answer'] ?? ''));
        $assistente = trim((string) ($item['prompt']['assistente'] ?? ''));
        $resolvedPrompt = trim((string) ($item['runtime']['resolved_prompt'] ?? ''));

        $chunk = [];
        if ($assistente !== '') {
            $chunk[] = '[' . $assistente . ']';
        }
        if ($question !== '') {
            $chunk[] = $question;
        }
        if ($answer !== '') {
            $chunk[] = 'Resposta base: ' . $answer;
        }
        if ($resolvedPrompt !== '') {
            $chunk[] = $resolvedPrompt;
        }

        return implode("\n", $chunk);
    }


    /**
     * @return array<string, mixed>
     */
    public function executeSqlPreview(string $promptBaseText, string $sqlBlockText, string $emailUser, ?string $companyName = null, ?int $versionId = null): array
    {
        $source = $this->resolveSource($emailUser, $companyName, $versionId);
        if ($source === null) {
            return [
                'ok' => false,
                'message' => 'Sem versão vigente para resolver marcadores do SQL.',
                'render_type' => 'text',
                'title' => 'Sem base',
                'resolved_sql' => '',
                'payload' => [],
            ];
        }

        $answers = $this->answersForSource($source, $emailUser);
        $resolvedSqlBlock = $this->replaceAnswerMarkersOnly($sqlBlockText, $answers);
        [$sqlText, $desc] = $this->extractSqlTextAndDesc($resolvedSqlBlock);
        [, $missingSql] = $this->replaceAnswerMarkersInSql($sqlText, $answers);

        if (trim($sqlText) === '') {
            return [
                'ok' => false,
                'message' => 'Nenhum SQL anexado.',
                'render_type' => 'text',
                'title' => 'Sem SQL',
                'resolved_sql' => '',
                'payload' => [],
            ];
        }

        if ($missingSql !== []) {
            return [
                'ok' => false,
                'message' => 'Statistics DB: marcadores não resolvidos no SQL: ' . implode(', ', $missingSql),
                'render_type' => 'text',
                'title' => $desc !== '' ? $desc : 'Erro SQL',
                'resolved_sql' => $sqlText,
                'payload' => [],
            ];
        }

        try {
            $this->assertSelectOnly($sqlText);
            $sqlText = $this->normalizeSqlForStatistics($sqlText);
            $stmt = $this->statisticsPdo()->query($sqlText);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            return [
                'ok' => true,
                'message' => '',
                'render_type' => $this->detectSqlRenderType($rows),
                'title' => $desc !== '' ? $desc : 'Resultado SQL',
                'resolved_sql' => $sqlText,
                'payload' => $this->normalizeSqlPayload($rows),
                'row_count' => count($rows),
            ];
        } catch (Throwable $throwable) {
            return [
                'ok' => false,
                'message' => 'Statistics DB: ' . $throwable->getMessage(),
                'render_type' => 'text',
                'title' => $desc !== '' ? $desc : 'Erro SQL',
                'resolved_sql' => $sqlText,
                'payload' => [],
            ];
        }
    }

    /**
     * @param array<string, string> $answers
     */
    private function replaceAnswerMarkersOnly(string $text, array $answers): string
    {
        [$resolved] = $this->replaceAnswerMarkersInSql($text, $answers);
        return $resolved;
    }

    /**
     * @param array<string, string> $answers
     * @return array{0:string,1:array<int,string>}
     */
    private function replaceAnswerMarkersInSql(string $text, array $answers): array
    {
        $missing = [];
        $resolved = preg_replace_callback('/<<([^>]+)>>/u', static function (array $matches) use ($answers, &$missing): string {
            $raw = trim((string) ($matches[1] ?? ''));
            $key = mb_strtolower($raw);
            $value = trim((string) ($answers[$key] ?? ''));
            if ($value === '') {
                $missing[] = $raw;
                return '<<' . $raw . '>>';
            }
            return $value;
        }, $text);

        return [$resolved ?? $text, array_values(array_unique($missing))];
    }

    /**
     * @return array{0:string,1:string}
     */
    private function extractSqlTextAndDesc(string $sqlBlockText): array
    {
        $sqlBlockText = trim($sqlBlockText);
        if ($sqlBlockText === '') {
            return ['', ''];
        }

        $lines = preg_split('/\r\n|\r|\n/', $sqlBlockText) ?: [];
        $desc = '';
        if ($lines !== [] && preg_match('/^\s*--\s*DESC\s*:\s*(.+)$/i', trim((string) $lines[0]), $matches) === 1) {
            $desc = trim((string) ($matches[1] ?? ''));
            array_shift($lines);
        }

        return [trim(implode("\n", $lines)), $desc];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    private function detectSqlRenderType(array $rows): string
    {
        if ($rows === []) {
            return 'text';
        }

        $first = $rows[0];
        if (count($first) === 1 && count($rows) === 1) {
            $value = (string) array_values($first)[0];
            $decoded = json_decode($value, true);

            if (json_last_error() === JSON_ERROR_NONE && (is_array($decoded) || is_object($decoded))) {
                return 'json';
            }

            return 'text';
        }

        return 'table';
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<string, mixed>
     */
    private function normalizeSqlPayload(array $rows): array
    {
        if ($rows === []) {
            return ['text' => 'A consulta não retornou linhas.'];
        }

        $first = $rows[0];
        if (count($first) === 1 && count($rows) === 1) {
            $value = (string) array_values($first)[0];
            $decoded = json_decode($value, true);

            if (json_last_error() === JSON_ERROR_NONE && (is_array($decoded) || is_object($decoded))) {
                return ['json' => $decoded];
            }

            return ['text' => $value];
        }

        return [
            'columns' => array_keys($first),
            'rows' => array_map(static function (array $row): array {
                $normalized = [];
                foreach ($row as $key => $value) {
                    if (is_scalar($value) || $value === null) {
                        $normalized[(string) $key] = $value;
                    } else {
                        $normalized[(string) $key] = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    }
                }
                return $normalized;
            }, $rows),
        ];
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
        return $this->pdo ??= $this->database->pdo();
    }

    private function statisticsPdo(): PDO
    {
        return $this->statisticsPdo ??= $this->database->statisticsPdo();
    }

    private function fieldRepository(): FormFieldRepository
    {
        return $this->fieldRepository ??= new FormFieldRepository($this->pdo());
    }

    private function versionRepository(): VersionedResponseRepository
    {
        return $this->versionRepository ??= new VersionedResponseRepository($this->pdo());
    }

    private function ragRepository(): RagRepository
    {
        return $this->ragRepository ??= new RagRepository($this->database);
    }

    private function paperFileService(): PaperFileService
    {
        return $this->paperFileService ??= new PaperFileService();
    }
}
