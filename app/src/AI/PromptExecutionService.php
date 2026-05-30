<?php
declare(strict_types=1);

namespace App\AI;

use App\Infra\Database;
use App\Infra\Env;
use App\Papers\PaperFileService;
use App\Prompts\PromptRepository;
use App\Prompts\PromptRuntimeService;
use PDO;
use RuntimeException;
use Throwable;

final class PromptExecutionService
{
    private const FILE_PURPOSE = 'user_data';
    private const DEFAULT_MODEL = 'gpt-4o-mini';
    private const MAX_OUTPUT_TOKENS = 2400;
    private const SUPPORTED_FILE_EXTENSIONS = ['art','bat','brf','c','cls','css','csv','diff','doc','docx','dot','eml','es','h','hs','htm','html','hwp','hwpx','ics','ifb','java','js','json','keynote','ksh','ltx','mail','markdown','md','mht','mhtml','mjs','nws','odt','pages','patch','pdf','pl','pm','pot','ppa','pps','ppt','pptx','pwz','py','rst','rtf','scala','sh','shtml','srt','sty','svg','svgz','tex','text','txt','vcf','vtt','wiz','xla','xlb','xlc','xlm','xls','xlsx','xlt','xlw','xml','yaml','yml'];

    private ?PDO $pdo = null;

    public function __construct(
        private readonly Database $database,
        private readonly PromptRepository $promptRepository,
        private readonly PromptRuntimeService $runtimeService,
        private readonly PaperFileService $paperFileService = new PaperFileService()
    ) {
    }

    /**
     * @param array<string,mixed> $request
     * @return array<string,mixed>
     */
    public function execute(array $request): array
    {
        $this->ensureSchema();

        $module = trim((string) ($request['module'] ?? 'generic'));
        $emailUser = trim((string) ($request['email_user'] ?? ''));
        $companyName = trim((string) ($request['company_name'] ?? ''));
        $versionId = (int) ($request['version_id'] ?? 0);
        $inputText = trim((string) ($request['input_text'] ?? ''));
        $contextVars = is_array($request['context_vars'] ?? null) ? (array) $request['context_vars'] : [];
        $usageContext = is_array($request['usage_context'] ?? null) ? (array) $request['usage_context'] : [];
        $instructions = trim((string) ($request['instructions'] ?? ''));
        $forceRefresh = !empty($request['force_refresh_openai_ids']);

        if ($emailUser === '') {
            throw new RuntimeException('email_user é obrigatório para executar o prompt.');
        }
        if ($versionId <= 0) {
            throw new RuntimeException('version_id é obrigatório para executar o prompt.');
        }

        $promptRow = $this->resolvePromptRow($request);
        $promptName = trim((string) ($promptRow['assistente'] ?? ($request['prompt_name'] ?? 'ad_hoc')));
        $promptOriginal = trim((string) ($promptRow['prompt_full_text'] ?? $promptRow['prompt'] ?? ''));
        if ($promptOriginal === '') {
            throw new RuntimeException('Prompt vazio para execução.');
        }

        $contextMarkers = $this->normalizeContextMarkers($contextVars);
        $promptPreResolved = $contextMarkers !== [] ? strtr($promptOriginal, $contextMarkers) : $promptOriginal;
        $promptForRuntime = $promptRow;
        $promptForRuntime['prompt'] = $promptPreResolved;
        $promptForRuntime['prompt_full_text'] = $promptPreResolved;

        $startedAt = microtime(true);
        $runtime = $this->runtimeService->buildExecution(
            $promptForRuntime,
            $emailUser,
            $companyName !== '' ? $companyName : null,
            $versionId > 0 ? $versionId : null
        );

        if (!empty($runtime['sql']['error'])) {
            throw new RuntimeException((string) $runtime['sql']['error']);
        }
        if (!empty($runtime['sql']['has_sql']) && trim((string) ($runtime['sql']['json_text'] ?? '')) === '') {
            throw new RuntimeException('A rotina não conseguiu normalizar o retorno SQL em resultado_json.');
        }

        $promptResolved = trim((string) ($runtime['execution_prompt'] ?? $runtime['resolved_prompt'] ?? ''));
        if ($contextMarkers !== []) {
            $promptResolved = strtr($promptResolved, $contextMarkers);
        }
        if ($promptResolved === '' && $inputText === '') {
            throw new RuntimeException('Prompt resolvido vazio para execução.');
        }

        $attachments = is_array($runtime['attachments'] ?? null) ? (array) $runtime['attachments'] : [];
        $preparedFiles = [];
        if ($attachments !== []) {
            $preparedFiles = $this->materializePromptFiles($attachments, $usageContext, $forceRefresh);
        }

        try {
            $responseText = $this->callOpenAIWithFiles($promptResolved, $inputText, $preparedFiles, $instructions !== '' ? $instructions : null);
        } catch (Throwable $openAiError) {
            if (!$this->isMissingOpenAiFilesError($openAiError)) {
                throw $openAiError;
            }
            $this->invalidatePreparedFilesCache($preparedFiles, $openAiError->getMessage());
            $preparedFiles = $this->materializePromptFiles($attachments, $usageContext, true);
            $responseText = $this->callOpenAIWithFiles($promptResolved, $inputText, $preparedFiles, $instructions !== '' ? $instructions : null);
        }

        $timing = (int) round((microtime(true) - $startedAt) * 1000);
        $result = [
            'ok' => true,
            'module' => $module,
            'prompt_name' => $promptName,
            'prompt_original' => $promptOriginal,
            'prompt_resolved' => $promptResolved,
            'input_text' => $inputText,
            'placeholders_resolved' => (array) ($runtime['marker_values'] ?? []),
            'unresolved_markers' => (array) ($runtime['unresolved_markers'] ?? []),
            'sql' => (array) ($runtime['sql'] ?? []),
            'document_references_found' => array_values(array_map(static fn(array $a): string => (string) ($a['title'] ?? 'Arquivo'), $attachments)),
            'documents_attached' => $preparedFiles,
            'bibliography_text' => trim((string) ($contextVars['bibliografia_grupo'] ?? '')),
            'model_request_summary' => [
                'model' => Env::get('AI_ANALITICA_MODEL', self::DEFAULT_MODEL) ?? self::DEFAULT_MODEL,
                'attached_count' => count($preparedFiles),
                'has_sql' => !empty($runtime['sql']['has_sql']),
            ],
            'model_response_text' => $responseText,
            'timing_ms' => $timing,
            'warnings' => (array) ($runtime['unresolved_markers'] ?? []),
            'errors' => [],
            'meta' => [
                'version_id' => $versionId,
                'company_name' => $companyName,
                'email_user' => $emailUser,
            ],
        ];

        $this->logExecution($result, $usageContext);
        return $result;
    }

    /** @param array<string,mixed> $request @return array<string,mixed> */
    private function resolvePromptRow(array $request): array
    {
        $promptName = trim((string) ($request['prompt_name'] ?? ''));
        $promptText = trim((string) ($request['prompt_text'] ?? ''));
        $promptRow = is_array($request['prompt_row'] ?? null) ? (array) $request['prompt_row'] : null;

        if (is_array($promptRow) && trim((string) ($promptRow['prompt_full_text'] ?? $promptRow['prompt'] ?? '')) !== '') {
            return $promptRow;
        }

        if ($promptName !== '') {
            $row = $this->promptRepository->findByAssistente($promptName);
            if (is_array($row)) {
                return $row;
            }
        }

        if ($promptText !== '') {
            return [
                'assistente' => $promptName !== '' ? $promptName : 'ad_hoc',
                'prompt' => $promptText,
                'prompt_full_text' => $promptText,
            ];
        }

        throw new RuntimeException('Prompt não informado para execução.');
    }

    /** @param array<string,mixed> $vars @return array<string,string> */
    private function normalizeContextMarkers(array $vars): array
    {
        $map = [];
        foreach ($vars as $key => $value) {
            if (is_array($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
            } elseif (is_object($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
            }
            if (!is_scalar($value) && $value !== null) {
                continue;
            }
            $string = trim((string) $value);
            $rawKey = trim((string) $key);
            if ($rawKey === '') {
                continue;
            }
            $marker = str_starts_with($rawKey, '<<') ? $rawKey : '<<' . $rawKey . '>>';
            $map[$marker] = $string;
        }
        return $map;
    }

    private function ensureSchema(): void
    {
        if (!$this->tableExists('prompt_execution_log')) {
            try {
                $this->pdo()->exec('CREATE TABLE IF NOT EXISTS `prompt_execution_log` (
                    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `module` VARCHAR(50) NOT NULL,
                    `company_name` VARCHAR(255) NULL,
                    `email_user` VARCHAR(255) NULL,
                    `sess_min` VARCHAR(16) NULL,
                    `prompt_name` VARCHAR(255) NULL,
                    `prompt_original` LONGTEXT NULL,
                    `prompt_resolved` LONGTEXT NULL,
                    `sql_executed_json` LONGTEXT NULL,
                    `documents_json` LONGTEXT NULL,
                    `warnings_json` LONGTEXT NULL,
                    `result_excerpt` LONGTEXT NULL,
                    `status` VARCHAR(20) NOT NULL DEFAULT "ok",
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
            } catch (Throwable) {
            }
        }
    }

    /** @param array<string,mixed> $result @param array<string,mixed> $usageContext */
    private function logExecution(array $result, array $usageContext): void
    {
        if (!$this->tableExists('prompt_execution_log')) {
            return;
        }
        $available = $this->tableColumns('prompt_execution_log');
        if ($available === []) {
            return;
        }
        $payload = [
            'module' => (string) ($result['module'] ?? 'generic'),
            'company_name' => (string) (($usageContext['company_name'] ?? $result['meta']['company_name'] ?? '') ?: ''),
            'email_user' => (string) (($usageContext['email_user'] ?? $result['meta']['email_user'] ?? '') ?: ''),
            'sess_min' => (string) (($usageContext['sess_min'] ?? '') ?: ''),
            'prompt_name' => (string) ($result['prompt_name'] ?? ''),
            'prompt_original' => (string) ($result['prompt_original'] ?? ''),
            'prompt_resolved' => (string) ($result['prompt_resolved'] ?? ''),
            'sql_executed_json' => json_encode($result['sql'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'documents_json' => json_encode($result['documents_attached'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'warnings_json' => json_encode($result['warnings'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'result_excerpt' => mb_substr((string) ($result['model_response_text'] ?? ''), 0, 4000),
            'status' => !empty($result['ok']) ? 'ok' : 'error',
            'created_at' => date('Y-m-d H:i:s'),
        ];
        $columns = [];
        foreach ($payload as $column => $value) {
            if (isset($available[$column])) {
                $columns[$column] = $value;
            }
        }
        if ($columns === []) {
            return;
        }
        try {
            $sql = 'INSERT INTO `prompt_execution_log` (`' . implode('`,`', array_keys($columns)) . '`) VALUES (:' . implode(',:', array_keys($columns)) . ')';
            $st = $this->pdo()->prepare($sql);
            foreach ($columns as $column => $value) {
                $st->bindValue(':' . $column, $value);
            }
            $st->execute();
        } catch (Throwable) {
        }
    }

    /** @param array<int,array<string,mixed>> $attachments @param array<string,mixed> $usageContext @return array<int,array<string,mixed>> */
    private function materializePromptFiles(array $attachments, array $usageContext, bool $forceRefreshOpenAiIds = false): array
    {
        $resolvedFiles = [];
        foreach ($attachments as $paper) {
            if (!is_array($paper)) {
                continue;
            }
            $cacheId = !empty($paper['cache_id']) ? (int) $paper['cache_id'] : null;
            $openaiFileId = trim((string) ($paper['openai_file_id'] ?? ''));
            if (!$forceRefreshOpenAiIds && $openaiFileId !== '') {
                $resolvedFiles[] = [
                    'title' => (string) ($paper['title'] ?? 'Arquivo'),
                    'openai_file_id' => $openaiFileId,
                    'cache_id' => $cacheId,
                    'paper_id' => (int) ($paper['id'] ?? 0),
                    'source_type' => (string) ($paper['file_source_type'] ?? ''),
                    'source_value' => (string) ($paper['file_source_value'] ?? ''),
                ];
                $this->touchCacheById($cacheId);
                $this->logPromptFileUsage($usageContext, $paper, $cacheId, $openaiFileId);
                continue;
            }
            $resolved = $this->paperFileService->resolve($paper);
            if (($resolved['kind'] ?? '') === 'external' && !empty($resolved['url'])) {
                $url = (string) $resolved['url'];
                if (!$this->isSupportedOpenAiFileReference($url, (string) ($paper['title'] ?? 'Arquivo'))) {
                    $this->markCacheUnsupported($cacheId, (int) ($paper['id'] ?? 0), 'Arquivo ignorado no RAG: tipo não suportado pela OpenAI (' . basename(parse_url($url, PHP_URL_PATH) ?: $url) . ').');
                    continue;
                }
                $resolvedFiles[] = [
                    'title' => (string) ($paper['title'] ?? 'Arquivo'),
                    'file_url' => $url,
                    'cache_id' => $cacheId,
                    'paper_id' => (int) ($paper['id'] ?? 0),
                    'source_type' => 'external_url',
                    'source_value' => $url,
                ];
                $this->logPromptFileUsage($usageContext, $paper, $cacheId, '');
                continue;
            }
            $path = (string) ($resolved['path'] ?? '');
            if ($path === '' || !is_file($path)) {
                throw new RuntimeException('Arquivo bibliográfico não localizado para: ' . (string) ($paper['title'] ?? 'Sem título'));
            }
            if (!$this->isSupportedOpenAiFileReference($path, (string) ($resolved['label'] ?? basename($path)))) {
                $this->markCacheUnsupported($cacheId, (int) ($paper['id'] ?? 0), 'Arquivo ignorado no RAG: tipo não suportado pela OpenAI (' . basename($path) . ').');
                continue;
            }
            $sha = hash_file('sha256', $path);
            if (!is_string($sha) || $sha === '') {
                throw new RuntimeException('Falha ao calcular hash SHA-256 do arquivo: ' . basename($path));
            }
            $cache = $this->fetchCacheBySha($sha);
            if ($cache === null && !empty($paper['id'])) {
                $cache = $this->fetchCacheByPaperId((int) $paper['id']);
            }
            if (!$forceRefreshOpenAiIds && is_array($cache) && trim((string) ($cache['openai_file_id'] ?? '')) !== '') {
                $openaiFileId = trim((string) ($cache['openai_file_id'] ?? ''));
                $cacheId = isset($cache['cache_id']) ? (int) $cache['cache_id'] : $cacheId;
                $this->touchCacheById($cacheId);
            } else {
                $upload = $this->uploadFileToOpenAI($path, (string) ($resolved['label'] ?? basename($path)), self::FILE_PURPOSE);
                $openaiFileId = trim((string) ($upload['id'] ?? ''));
                $cacheId = $this->upsertCacheRow([
                    'paper_id' => (int) ($paper['id'] ?? 0),
                    'source_sha256' => $sha,
                    'original_filename' => (string) ($resolved['label'] ?? basename($path)),
                    'mime_type' => (string) ($resolved['mime'] ?? 'application/octet-stream'),
                    'file_ext' => (string) ($resolved['ext'] ?? strtolower((string) pathinfo($path, PATHINFO_EXTENSION))),
                    'size_bytes' => (int) (@filesize($path) ?: 0),
                    'local_cache_path' => $path,
                    'source_type' => (string) ($paper['file_source_type'] ?? 'local_file'),
                    'source_value' => (string) ($paper['file_source_value'] ?? $path),
                    'openai_file_id' => $openaiFileId,
                    'openai_file_purpose' => self::FILE_PURPOSE,
                    'vector_store_id' => (string) ($paper['vector_store_id'] ?? ''),
                    'cache_status' => 'ready',
                    'last_error' => null,
                    'last_used_at' => date('Y-m-d H:i:s'),
                    'exists_flag' => 1,
                ]);
            }
            $resolvedFiles[] = [
                'title' => (string) ($paper['title'] ?? 'Arquivo'),
                'openai_file_id' => $openaiFileId,
                'cache_id' => $cacheId,
                'paper_id' => (int) ($paper['id'] ?? 0),
                'source_type' => (string) ($paper['file_source_type'] ?? 'local_file'),
                'source_value' => (string) ($paper['file_source_value'] ?? $path),
            ];
            $this->logPromptFileUsage($usageContext, $paper, $cacheId, $openaiFileId);
        }
        return $resolvedFiles;
    }

    private function callOpenAIWithFiles(string $promptText, string $inputText, array $preparedFiles, ?string $instructions = null): string
    {
        $apiKey = $this->openAiApiKey();
        if ($apiKey === '') {
            throw new RuntimeException('OPENAI_API_KEY não configurada.');
        }
        $content = [];
        foreach ($preparedFiles as $file) {
            if (!empty($file['openai_file_id'])) {
                $content[] = ['type' => 'input_file', 'file_id' => (string) $file['openai_file_id']];
            } elseif (!empty($file['file_url'])) {
                $content[] = ['type' => 'input_file', 'file_url' => (string) $file['file_url']];
            }
        }
        $fullText = trim($promptText);
        if (trim($inputText) !== '') {
            $fullText .= "\n\n" . trim($inputText);
        }
        $content[] = ['type' => 'input_text', 'text' => $fullText];
        $payload = [
            'model' => Env::get('AI_ANALITICA_MODEL', self::DEFAULT_MODEL) ?? self::DEFAULT_MODEL,
            'instructions' => $instructions !== null && $instructions !== ''
                ? $instructions
                : 'Você é um assistente analítico e técnico. Responda de forma objetiva, estruturada e formatada em Markdown, usando os dados, o resultado do SQL e os documentos anexados como base de evidência.',
            'input' => [[
                'role' => 'user',
                'content' => $content,
            ]],
            'max_output_tokens' => self::MAX_OUTPUT_TOKENS,
        ];
        $ch = curl_init('https://api.openai.com/v1/responses');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            CURLOPT_TIMEOUT => 240,
        ]);
        $response = curl_exec($ch);
        $err = curl_error($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($response === false) {
            throw new RuntimeException('Erro na chamada OpenAI: ' . $err);
        }
        $data = json_decode((string) $response, true);
        $text = is_array($data) ? $this->extractResponseOutputText($data) : '';
        if ($http < 200 || $http >= 300 || $text === '') {
            $message = is_array($data) ? trim((string) ($data['error']['message'] ?? '')) : '';
            if ($message === '' && is_array($data) && !empty($data['incomplete_details']['reason'])) {
                $message = 'Resposta incompleta: ' . (string) $data['incomplete_details']['reason'];
            }
            if ($message === '') {
                $message = substr((string) $response, 0, 700);
            }
            throw new RuntimeException('Erro da API OpenAI (' . $http . '): ' . $message);
        }
        return trim($text);
    }

    private function extractResponseOutputText(array $data): string
    {
        $out = trim((string) ($data['output_text'] ?? ''));
        if ($out !== '') {
            return $out;
        }
        $parts = [];
        foreach ((array) ($data['output'] ?? []) as $item) {
            if (!is_array($item) || (string) ($item['type'] ?? '') !== 'message') {
                continue;
            }
            foreach ((array) ($item['content'] ?? []) as $piece) {
                if (!is_array($piece)) {
                    continue;
                }
                if ((string) ($piece['type'] ?? '') === 'output_text') {
                    $txt = trim((string) ($piece['text'] ?? ''));
                    if ($txt !== '') {
                        $parts[] = $txt;
                    }
                }
            }
        }
        return trim(implode("\n\n", $parts));
    }

    // ===== Helpers copied/adapted from AnaliticaExecutionService =====
    private function openAiApiKey(): string
    {
        return trim((string) (Env::get('OPENAI_API_KEY', '') ?? ''));
    }
    private function pdo(): PDO { return $this->pdo ??= $this->database->pdo(); }
    private function tableExists(string $table): bool { try { $this->pdo()->query('SELECT 1 FROM `' . $table . '` LIMIT 1'); return true; } catch (Throwable) { return false; } }
    /** @return array<string,bool> */
    private function tableColumns(string $table): array { try { $st = $this->pdo()->query('DESCRIBE `' . $table . '`'); $out=[]; foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) { $n=(string)($r['Field']??''); if($n!=='') $out[$n]=true; } return $out; } catch (Throwable) { return []; } }
    private function columnExists(string $table, string $column): bool { return isset($this->tableColumns($table)[$column]); }
    private function sessionMinute(string $responseDateTime): ?string { $responseDateTime = trim($responseDateTime); return $responseDateTime !== '' ? substr($responseDateTime, 0, 16) : null; }

    private function isSupportedOpenAiFileReference(string $pathOrUrl, string $fallbackName = ''): bool
    {
        $candidate = trim($pathOrUrl) !== '' ? $pathOrUrl : $fallbackName;
        $path = parse_url($candidate, PHP_URL_PATH);
        if (!is_string($path) || trim($path) === '') {
            $path = $candidate;
        }
        $ext = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
        if ($ext === '' && $fallbackName !== '') {
            $ext = strtolower((string) pathinfo($fallbackName, PATHINFO_EXTENSION));
        }
        return $ext !== '' && in_array($ext, self::SUPPORTED_FILE_EXTENSIONS, true);
    }

    private function fetchCacheBySha(string $sha): ?array
    {
        if (!$this->tableExists('papers_file_cache') || !$this->columnExists('papers_file_cache', 'source_sha256')) {
            return null;
        }
        $st = $this->pdo()->prepare('SELECT * FROM `papers_file_cache` WHERE `source_sha256` = ? LIMIT 1');
        $st->execute([$sha]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }
    private function fetchCacheByPaperId(int $paperId): ?array
    {
        if ($paperId <= 0 || !$this->tableExists('papers_file_cache') || !$this->columnExists('papers_file_cache', 'paper_id')) {
            return null;
        }
        $st = $this->pdo()->prepare('SELECT * FROM `papers_file_cache` WHERE `paper_id` = ? ORDER BY `cache_id` DESC LIMIT 1');
        $st->execute([$paperId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }
    private function upsertCacheRow(array $payload): ?int
    {
        if (!$this->tableExists('papers_file_cache')) {
            return null;
        }
        $existing = null;
        if (!empty($payload['source_sha256'])) {
            $existing = $this->fetchCacheBySha((string) $payload['source_sha256']);
        }
        if ($existing === null && !empty($payload['paper_id'])) {
            $existing = $this->fetchCacheByPaperId((int) $payload['paper_id']);
        }
        $available = $this->tableColumns('papers_file_cache');
        $data = [];
        foreach ($payload as $column => $value) {
            if (isset($available[$column])) {
                $data[$column] = $value;
            }
        }
        if ($data === []) {
            return is_array($existing) && isset($existing['cache_id']) ? (int) $existing['cache_id'] : null;
        }
        if (is_array($existing) && isset($existing['cache_id'])) {
            $assignments = [];
            $params = [':cache_id' => (int) $existing['cache_id']];
            foreach ($data as $column => $value) {
                $assignments[] = '`' . $column . '` = :' . $column;
                $params[':' . $column] = $value;
            }
            $sql = 'UPDATE `papers_file_cache` SET ' . implode(', ', $assignments) . ' WHERE `cache_id` = :cache_id';
            $st = $this->pdo()->prepare($sql);
            $st->execute($params);
            return (int) $existing['cache_id'];
        }
        $columns = array_keys($data);
        $sql = 'INSERT INTO `papers_file_cache` (`' . implode('`,`', $columns) . '`) VALUES (:' . implode(',:', $columns) . ')';
        $st = $this->pdo()->prepare($sql);
        foreach ($data as $column => $value) {
            $st->bindValue(':' . $column, $value);
        }
        $st->execute();
        return (int) $this->pdo()->lastInsertId();
    }
    private function touchCacheById(?int $cacheId): void { if (($cacheId ?? 0) <= 0 || !$this->tableExists('papers_file_cache') || !$this->columnExists('papers_file_cache', 'last_used_at')) return; try { $st=$this->pdo()->prepare('UPDATE `papers_file_cache` SET `last_used_at` = ? WHERE `cache_id` = ?'); $st->execute([date('Y-m-d H:i:s'), $cacheId]); } catch (Throwable) {} }
    private function invalidatePreparedFilesCache(array $preparedFiles, string $reason): void { foreach ($preparedFiles as $file) { if (!is_array($file)) continue; $this->invalidateCacheEntry(!empty($file['cache_id'])?(int)$file['cache_id']:0,!empty($file['paper_id'])?(int)$file['paper_id']:0,trim((string)($file['openai_file_id']??'')),$reason); } }
    private function invalidateCacheEntry(int $cacheId, int $paperId, string $openaiFileId, string $reason): void
    {
        if (!$this->tableExists('papers_file_cache')) return;
        $available = $this->tableColumns('papers_file_cache');
        if ($available === []) return;
        $sets=[]; $params=[':checked_at'=>date('Y-m-d H:i:s')];
        if (isset($available['openai_file_id'])) $sets[]='`openai_file_id` = NULL';
        if (isset($available['cache_status'])) { $sets[]='`cache_status` = :cache_status'; $params[':cache_status']='pending'; }
        if (isset($available['last_error'])) { $sets[]='`last_error` = :last_error'; $params[':last_error']=mb_substr('openai_file_id inválido no projeto/chave atual. ' . trim($reason),0,65535); }
        if (isset($available['last_checked_at'])) $sets[]='`last_checked_at` = :checked_at';
        if ($sets===[]) return;
        try {
            if ($cacheId > 0 && isset($available['cache_id'])) { $params[':cache_id']=$cacheId; $sql='UPDATE `papers_file_cache` SET '.implode(', ',$sets).' WHERE `cache_id` = :cache_id'; $st=$this->pdo()->prepare($sql); $st->execute($params); return; }
            if ($paperId > 0 && isset($available['paper_id'])) { $params[':paper_id']=$paperId; $sql='UPDATE `papers_file_cache` SET '.implode(', ',$sets).' WHERE `paper_id` = :paper_id'; $st=$this->pdo()->prepare($sql); $st->execute($params); return; }
            if ($openaiFileId !== '' && isset($available['openai_file_id'])) { $params[':openai_file_id']=$openaiFileId; $sql='UPDATE `papers_file_cache` SET '.implode(', ',$sets).' WHERE `openai_file_id` = :openai_file_id'; $st=$this->pdo()->prepare($sql); $st->execute($params); }
        } catch (Throwable) {}
    }
    private function isMissingOpenAiFilesError(Throwable $throwable): bool
    {
        $message = mb_strtolower(trim($throwable->getMessage()));
        return $message !== '' && str_contains($message, 'erro da api openai (404)') && str_contains($message, 'files') && str_contains($message, 'not found');
    }
    private function logPromptFileUsage(array $usageContext, array $paper, ?int $cacheId, string $openaiFileId): void
    {
        if (!$this->tableExists('prompt_file_usage')) return;
        $available = $this->tableColumns('prompt_file_usage');
        if ($available === []) return;
        $payload = [
            'response_detailed_id' => $usageContext['prompt_row_id'] ?? null,
            'company_name' => $usageContext['company_name'] ?? null,
            'email_resp' => $usageContext['email_resp'] ?? null,
            'sess_min' => $usageContext['sess_min'] ?? $this->sessionMinute((string) ($usageContext['response_datetime'] ?? '')),
            'prompt_row_id' => $usageContext['prompt_row_id'] ?? null,
            'paper_title' => (string) ($paper['title'] ?? ''),
            'source_type' => (string) ($paper['file_source_type'] ?? ''),
            'source_value' => (string) ($paper['file_source_value'] ?? ''),
            'cache_id' => $cacheId,
            'openai_file_id' => $openaiFileId !== '' ? $openaiFileId : null,
            'execution_mode' => 'responses_file_input',
            'created_at' => date('Y-m-d H:i:s'),
            'used_at' => date('Y-m-d H:i:s'),
        ];
        $columns=[]; foreach($payload as $column=>$value){ if(isset($available[$column])) $columns[$column]=$value; }
        if($columns===[]) return;
        try { $sql='INSERT INTO `prompt_file_usage` (`'.implode('`,`',array_keys($columns)).'`) VALUES (:'.implode(',:',array_keys($columns)).')'; $st=$this->pdo()->prepare($sql); foreach($columns as $column=>$value){ $st->bindValue(':'.$column,$value);} $st->execute(); } catch(Throwable) {}
    }
    private function markCacheUnsupported(?int $cacheId, int $paperId, string $reason): void
    {
        if (!$this->tableExists('papers_file_cache')) return;
        $available = $this->tableColumns('papers_file_cache');
        if ($available === []) return;
        $sets=[]; $params=[];
        if (isset($available['cache_status'])) { $sets[]='`cache_status` = :cache_status'; $params[':cache_status']='ignored'; }
        if (isset($available['last_error'])) { $sets[]='`last_error` = :last_error'; $params[':last_error']=mb_substr($reason,0,65535); }
        if (isset($available['last_checked_at'])) { $sets[]='`last_checked_at` = :last_checked_at'; $params[':last_checked_at']=date('Y-m-d H:i:s'); }
        if (isset($available['openai_file_id'])) { $sets[]='`openai_file_id` = NULL'; }
        if ($sets===[]) return;
        try {
            if (($cacheId ?? 0) > 0 && isset($available['cache_id'])) { $params[':cache_id']=(int)$cacheId; $sql='UPDATE `papers_file_cache` SET '.implode(', ',$sets).' WHERE `cache_id` = :cache_id'; $st=$this->pdo()->prepare($sql); $st->execute($params); return; }
            if ($paperId > 0 && isset($available['paper_id'])) { $params[':paper_id']=$paperId; $sql='UPDATE `papers_file_cache` SET '.implode(', ',$sets).' WHERE `paper_id` = :paper_id'; $st=$this->pdo()->prepare($sql); $st->execute($params); }
        } catch (Throwable) {}
    }
    private function uploadFileToOpenAI(string $path, string $filename, string $purpose): array
    {
        $apiKey = $this->openAiApiKey();
        if ($apiKey === '') throw new RuntimeException('OPENAI_API_KEY não configurada.');
        $mime = mime_content_type($path) ?: 'application/octet-stream';
        $curlFile = curl_file_create($path, $mime, $filename);
        $ch = curl_init('https://api.openai.com/v1/files');
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$apiKey],CURLOPT_POSTFIELDS=>['purpose'=>$purpose,'file'=>$curlFile],CURLOPT_TIMEOUT=>180]);
        $response = curl_exec($ch); $err = curl_error($ch); $http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
        if ($response === false) throw new RuntimeException('Erro ao subir arquivo para OpenAI: '.$err);
        $data = json_decode((string)$response,true); $fileId=is_array($data)?trim((string)($data['id']??'')):'';
        if ($http < 200 || $http >= 300 || $fileId === '') { $message=is_array($data)?trim((string)($data['error']['message']??'')):''; if($message==='') $message=substr((string)$response,0,500); throw new RuntimeException('Erro ao subir arquivo para OpenAI ('.$http.'): '.$message); }
        return $data;
    }
}
