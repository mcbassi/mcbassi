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

final class AnaliticaExecutionService
{
    private const FILE_PURPOSE = 'user_data';
    private const DEFAULT_MODEL = 'gpt-4o-mini';
    private const MAX_OUTPUT_TOKENS = 2200;
    private const SUPPORTED_FILE_EXTENSIONS = ['art','bat','brf','c','cls','css','csv','diff','doc','docx','dot','eml','es','h','hs','htm','html','hwp','hwpx','ics','ifb','java','js','json','keynote','ksh','ltx','mail','markdown','md','mht','mhtml','mjs','nws','odt','pages','patch','pdf','pl','pm','pot','ppa','pps','ppt','pptx','pwz','py','rst','rtf','scala','sh','shtml','srt','sty','svg','svgz','tex','text','txt','vcf','vtt','wiz','xla','xlb','xlc','xlm','xls','xlsx','xlt','xlw','xml','yaml','yml'];

    private ?PDO $pdo = null;
    private PromptExecutionService $promptExecutionService;

    public function __construct(
        private readonly Database $database,
        private readonly PromptRuntimeService $runtimeService,
        private readonly PaperFileService $paperFileService = new PaperFileService()
    ) {
        $promptRepository = new PromptRepository($database);
        $this->promptExecutionService = new PromptExecutionService($database, $promptRepository, $runtimeService, $paperFileService);
    }

    /**
     * @param array<string, mixed> $package
     * @return array<string, mixed>
     */
    public function execute(array $package, string $emailUser, ?string $questionName = null): array
    {
        $this->ensureSchema();

        $selectedVersion = is_array($package['selectedVersion'] ?? null) ? $package['selectedVersion'] : null;
        if ($selectedVersion === null) {
            throw new RuntimeException('Nenhuma versão vigente foi encontrada para executar a IA Analítica.');
        }

        $items = is_array($package['items'] ?? null) ? $package['items'] : [];
        if ($items === []) {
            throw new RuntimeException('Nenhum prompt operacional foi montado para esta versão.');
        }

        $results = [];
        $executed = 0;
        $failed = 0;

        foreach ($items as $item) {
            $itemQuestion = trim((string) ($item['question_name'] ?? $item['field']['name'] ?? ''));
            if ($questionName !== null && $questionName !== '' && $itemQuestion !== $questionName) {
                continue;
            }

            $result = $this->executeItem($item, $selectedVersion, $emailUser);
            $results[] = $result;
            if (!empty($result['ok'])) {
                $executed++;
            } else {
                $failed++;
            }
        }

        if ($questionName !== null && $questionName !== '' && $results === []) {
            throw new RuntimeException('O prompt selecionado não foi localizado no pacote atual.');
        }

        return [
            'results' => $results,
            'summary' => [
                'executed' => $executed,
                'failed' => $failed,
                'total' => count($results),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $item
     * @param array<string, mixed> $selectedVersion
     * @return array<string, mixed>
     */
    private function executeItem(array $item, array $selectedVersion, string $emailUser): array
    {
        $prompt = is_array($item['prompt'] ?? null) ? $item['prompt'] : [];
        $field = is_array($item['field'] ?? null) ? $item['field'] : [];
        $questionName = trim((string) ($item['question_name'] ?? $field['name'] ?? ''));
        $responseSessionId = (int) ($item['response_session_id'] ?? $selectedVersion['id'] ?? 0);
        $companyName = trim((string) ($selectedVersion['company_name'] ?? ''));
        $emailResp = trim((string) ($selectedVersion['email_resp'] ?? $emailUser));
        $promptCode = trim((string) ($prompt['assistente'] ?? $field['prompt_code'] ?? ''));
        $sourceDateTime = trim((string) ($selectedVersion['response_datetime'] ?? ''));

        try {
            $promptRowId = $this->responseDetailedId($responseSessionId, $questionName);
            $execution = $this->promptExecutionService->execute([
                'module' => 'analitica',
                'prompt_name' => $promptCode,
                'prompt_text' => (string) ($prompt['prompt_full_text'] ?? $prompt['prompt'] ?? ''),
                'email_user' => $emailUser,
                'company_name' => $companyName,
                'version_id' => $responseSessionId,
                'context_vars' => [],
                'input_text' => '',
                'usage_context' => [
                    'response_session_id' => $responseSessionId,
                    'company_name' => $companyName,
                    'email_resp' => $emailResp,
                    'email_user' => $emailUser,
                    'response_datetime' => $sourceDateTime,
                    'sess_min' => substr($sourceDateTime, 0, 16),
                    'prompt_row_id' => $promptRowId,
                    'question_name' => $questionName,
                ],
            ]);

            $responseText = trim((string) ($execution['model_response_text'] ?? ''));
            $promptSnapshot = trim((string) ($execution['prompt_original'] ?? ($prompt['prompt_full_text'] ?? $prompt['prompt'] ?? '')));

            $this->storePromptExecution(
                $responseSessionId,
                $questionName,
                $promptCode,
                $promptSnapshot,
                $responseText,
                $sourceDateTime,
                $emailUser,
                $emailResp,
                $companyName
            );

            return [
                'ok' => true,
                'question_name' => $questionName,
                'prompt_code' => $promptCode,
                'message' => 'Execução concluída.',
                'response_text' => $responseText,
                'files' => (array) ($execution['documents_attached'] ?? []),
                'sql' => (array) ($execution['sql'] ?? []),
            ];
        } catch (Throwable $throwable) {
            return [
                'ok' => false,
                'question_name' => $questionName,
                'prompt_code' => $promptCode,
                'message' => $throwable->getMessage(),
                'response_text' => '',
                'files' => [],
            ];
        }
    }

    /**
     * @param array<int, array<string, mixed>> $attachments
     * @param array<string, mixed> $usageContext
     * @return array<int, array<string, mixed>>
     */
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
                $upload = $this->uploadFileToOpenAI(
                    $path,
                    (string) ($resolved['label'] ?? basename($path)),
                    self::FILE_PURPOSE
                );
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

    /**
     * @return array<string, mixed>|null
     */
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

    /**
     * @return array<string, mixed>|null
     */
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

    private function touchCacheById(?int $cacheId): void
    {
        if (($cacheId ?? 0) <= 0 || !$this->tableExists('papers_file_cache') || !$this->columnExists('papers_file_cache', 'last_used_at')) {
            return;
        }
        try {
            $st = $this->pdo()->prepare('UPDATE `papers_file_cache` SET `last_used_at` = ? WHERE `cache_id` = ?');
            $st->execute([date('Y-m-d H:i:s'), $cacheId]);
        } catch (Throwable) {
        }
    }

    /**
     * @param array<int, array<string, mixed>> $preparedFiles
     */
    private function invalidatePreparedFilesCache(array $preparedFiles, string $reason): void
    {
        foreach ($preparedFiles as $file) {
            if (!is_array($file)) {
                continue;
            }
            $cacheId = !empty($file['cache_id']) ? (int) $file['cache_id'] : 0;
            $paperId = !empty($file['paper_id']) ? (int) $file['paper_id'] : 0;
            $openaiFileId = trim((string) ($file['openai_file_id'] ?? ''));
            $this->invalidateCacheEntry($cacheId, $paperId, $openaiFileId, $reason);
        }
    }

    private function invalidateCacheEntry(int $cacheId, int $paperId, string $openaiFileId, string $reason): void
    {
        if (!$this->tableExists('papers_file_cache')) {
            return;
        }

        $available = $this->tableColumns('papers_file_cache');
        if ($available === []) {
            return;
        }

        $sets = [];
        $params = [':checked_at' => date('Y-m-d H:i:s')];

        if (isset($available['openai_file_id'])) {
            $sets[] = '`openai_file_id` = NULL';
        }
        if (isset($available['cache_status'])) {
            $sets[] = '`cache_status` = :cache_status';
            $params[':cache_status'] = 'pending';
        }
        if (isset($available['last_error'])) {
            $sets[] = '`last_error` = :last_error';
            $params[':last_error'] = mb_substr('openai_file_id inválido no projeto/chave atual. ' . trim($reason), 0, 65535);
        }
        if (isset($available['last_checked_at'])) {
            $sets[] = '`last_checked_at` = :checked_at';
        }

        if ($sets === []) {
            return;
        }

        try {
            if ($cacheId > 0 && isset($available['cache_id'])) {
                $params[':cache_id'] = $cacheId;
                $sql = 'UPDATE `papers_file_cache` SET ' . implode(', ', $sets) . ' WHERE `cache_id` = :cache_id';
                $st = $this->pdo()->prepare($sql);
                $st->execute($params);
                return;
            }

            if ($paperId > 0 && isset($available['paper_id'])) {
                $params[':paper_id'] = $paperId;
                $sql = 'UPDATE `papers_file_cache` SET ' . implode(', ', $sets) . ' WHERE `paper_id` = :paper_id';
                $st = $this->pdo()->prepare($sql);
                $st->execute($params);
                return;
            }

            if ($openaiFileId !== '' && isset($available['openai_file_id'])) {
                $params[':openai_file_id'] = $openaiFileId;
                $sql = 'UPDATE `papers_file_cache` SET ' . implode(', ', $sets) . ' WHERE `openai_file_id` = :openai_file_id';
                $st = $this->pdo()->prepare($sql);
                $st->execute($params);
            }
        } catch (Throwable) {
        }
    }

    private function isMissingOpenAiFilesError(Throwable $throwable): bool
    {
        $message = mb_strtolower(trim($throwable->getMessage()));
        if ($message === '') {
            return false;
        }

        return str_contains($message, 'erro da api openai (404)')
            && str_contains($message, 'files')
            && str_contains($message, 'not found');
    }

    private function logPromptFileUsage(array $usageContext, array $paper, ?int $cacheId, string $openaiFileId): void
    {
        if (!$this->tableExists('prompt_file_usage')) {
            return;
        }

        $available = $this->tableColumns('prompt_file_usage');
        if ($available === []) {
            return;
        }

        $payload = [
            'response_detailed_id' => $usageContext['prompt_row_id'] ?? null,
            'company_name' => $usageContext['company_name'] ?? null,
            'email_resp' => $usageContext['email_resp'] ?? null,
            'sess_min' => $this->sessionMinute((string) ($usageContext['response_datetime'] ?? '')),
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
            $sql = 'INSERT INTO `prompt_file_usage` (`' . implode('`,`', array_keys($columns)) . '`) VALUES (:' . implode(',:', array_keys($columns)) . ')';
            $st = $this->pdo()->prepare($sql);
            foreach ($columns as $column => $value) {
                $st->bindValue(':' . $column, $value);
            }
            $st->execute();
        } catch (Throwable) {
        }
    }

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

    private function markCacheUnsupported(?int $cacheId, int $paperId, string $reason): void
    {
        if (!$this->tableExists('papers_file_cache')) {
            return;
        }
        $available = $this->tableColumns('papers_file_cache');
        if ($available === []) {
            return;
        }
        $sets = [];
        $params = [];
        if (isset($available['cache_status'])) {
            $sets[] = '`cache_status` = :cache_status';
            $params[':cache_status'] = 'ignored';
        }
        if (isset($available['last_error'])) {
            $sets[] = '`last_error` = :last_error';
            $params[':last_error'] = mb_substr($reason, 0, 65535);
        }
        if (isset($available['last_checked_at'])) {
            $sets[] = '`last_checked_at` = :last_checked_at';
            $params[':last_checked_at'] = date('Y-m-d H:i:s');
        }
        if (isset($available['openai_file_id'])) {
            $sets[] = '`openai_file_id` = NULL';
        }
        if ($sets === []) {
            return;
        }
        try {
            if (($cacheId ?? 0) > 0 && isset($available['cache_id'])) {
                $params[':cache_id'] = (int) $cacheId;
                $sql = 'UPDATE `papers_file_cache` SET ' . implode(', ', $sets) . ' WHERE `cache_id` = :cache_id';
                $st = $this->pdo()->prepare($sql);
                $st->execute($params);
                return;
            }
            if ($paperId > 0 && isset($available['paper_id'])) {
                $params[':paper_id'] = $paperId;
                $sql = 'UPDATE `papers_file_cache` SET ' . implode(', ', $sets) . ' WHERE `paper_id` = :paper_id';
                $st = $this->pdo()->prepare($sql);
                $st->execute($params);
            }
        } catch (Throwable) {
        }
    }

    private function uploadFileToOpenAI(string $path, string $filename, string $purpose): array
    {
        $apiKey = $this->openAiApiKey();
        if ($apiKey === '') {
            throw new RuntimeException('OPENAI_API_KEY não configurada.');
        }

        $mime = mime_content_type($path) ?: 'application/octet-stream';
        $curlFile = curl_file_create($path, $mime, $filename);

        $ch = curl_init('https://api.openai.com/v1/files');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
            ],
            CURLOPT_POSTFIELDS => [
                'purpose' => $purpose,
                'file' => $curlFile,
            ],
            CURLOPT_TIMEOUT => 180,
        ]);

        $response = curl_exec($ch);
        $err = curl_error($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            throw new RuntimeException('Erro ao subir arquivo para OpenAI: ' . $err);
        }

        $data = json_decode((string) $response, true);
        $fileId = is_array($data) ? trim((string) ($data['id'] ?? '')) : '';
        if ($http < 200 || $http >= 300 || $fileId === '') {
            $message = is_array($data) ? trim((string) ($data['error']['message'] ?? '')) : '';
            if ($message === '') {
                $message = substr((string) $response, 0, 500);
            }
            throw new RuntimeException('Erro ao subir arquivo para OpenAI (' . $http . '): ' . $message);
        }

        return $data;
    }

    /**
     * @param array<int, array<string, mixed>> $preparedFiles
     */
    private function callOpenAIWithFiles(string $promptText, array $preparedFiles): string
    {
        $apiKey = $this->openAiApiKey();
        if ($apiKey === '') {
            throw new RuntimeException('OPENAI_API_KEY não configurada.');
        }

        $content = [];
        foreach ($preparedFiles as $file) {
            if (!empty($file['openai_file_id'])) {
                $content[] = [
                    'type' => 'input_file',
                    'file_id' => (string) $file['openai_file_id'],
                ];
            } elseif (!empty($file['file_url'])) {
                $content[] = [
                    'type' => 'input_file',
                    'file_url' => (string) $file['file_url'],
                ];
            }
        }

        $content[] = [
            'type' => 'input_text',
            'text' => $promptText,
        ];

        $payload = [
            'model' => Env::get('AI_ANALITICA_MODEL', self::DEFAULT_MODEL) ?? self::DEFAULT_MODEL,
            'instructions' => 'Você é um assistente analítico e técnico. Responda de forma objetiva, estruturada e formatada em Markdown, usando as respostas do questionário, o resultado do SQL e os documentos anexados como base de evidência.',
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

    private function storePromptExecution(
        int $responseSessionId,
        string $questionName,
        string $promptCode,
        string $promptSnapshot,
        string $responseText,
        string $responseDateTime,
        string $emailUser,
        string $emailResp,
        string $companyName
    ): void
    {
        $sets = [];
        $baseParams = [
            ':question_name' => $questionName,
        ];

        if ($this->columnExists('responses_detailed', 'prompt')) {
            $sets[] = '`prompt` = :prompt';
            $baseParams[':prompt'] = $promptSnapshot;
        }
        if ($this->columnExists('responses_detailed', 'prompt_code')) {
            $sets[] = '`prompt_code` = :prompt_code';
            $baseParams[':prompt_code'] = $promptCode;
        }
        if ($this->columnExists('responses_detailed', 'prompt_response')) {
            $sets[] = '`prompt_response` = :prompt_response';
            $baseParams[':prompt_response'] = $responseText;
        }
        if ($this->columnExists('responses_detailed', 'prompt_executed_at')) {
            $sets[] = '`prompt_executed_at` = :prompt_executed_at';
            $baseParams[':prompt_executed_at'] = date('Y-m-d H:i:s');
        }
        if ($sets === []) {
            return;
        }

        $sqlPrefix = 'UPDATE `responses_detailed` SET ' . implode(', ', $sets) . ' WHERE ';
        $updated = 0;

        if ($responseSessionId > 0 && $this->columnExists('responses_detailed', 'response_session_id')) {
            $params = $baseParams + [':response_session_id' => $responseSessionId];
            $sql = $sqlPrefix . '`response_session_id` = :response_session_id AND `question_name` = :question_name';
            $st = $this->pdo()->prepare($sql);
            $st->execute($params);
            $updated = (int) $st->rowCount();
        }

        if ($updated > 0) {
            return;
        }

        $sessionMinute = $this->sessionMinute($responseDateTime);
        if ($sessionMinute !== null && $questionName !== '') {
            $where = [
                '`question_name` = :question_name',
                "DATE_FORMAT(`response_datetime`, '%Y-%m-%d %H:%i') = :sess_min",
            ];
            $params = $baseParams + [':sess_min' => $sessionMinute];

            if ($this->columnExists('responses_detailed', 'email_user') && trim($emailUser) !== '') {
                $where[] = '`email_user` = :email_user';
                $params[':email_user'] = trim($emailUser);
            }
            if ($this->columnExists('responses_detailed', 'email_resp') && trim($emailResp) !== '') {
                $where[] = '`email_resp` = :email_resp';
                $params[':email_resp'] = trim($emailResp);
            }
            if ($this->columnExists('responses_detailed', 'company_name') && trim($companyName) !== '') {
                $where[] = '`company_name` = :company_name';
                $params[':company_name'] = trim($companyName);
            }

            $sql = $sqlPrefix . implode(' AND ', $where);
            $st = $this->pdo()->prepare($sql);
            $st->execute($params);
        }
    }

    private function responseDetailedId(int $responseSessionId, string $questionName): ?int
    {
        if ($responseSessionId <= 0 || $questionName === '' || !$this->columnExists('responses_detailed', 'response_session_id')) {
            return null;
        }
        $st = $this->pdo()->prepare('SELECT `id` FROM `responses_detailed` WHERE `response_session_id` = ? AND `question_name` = ? ORDER BY `id` DESC LIMIT 1');
        $st->execute([$responseSessionId, $questionName]);
        $value = $st->fetchColumn();
        return $value !== false ? (int) $value : null;
    }

    private function ensureSchema(): void
    {
        if ($this->tableExists('responses_detailed') && !$this->columnExists('responses_detailed', 'prompt_response')) {
            $this->pdo()->exec('ALTER TABLE `responses_detailed` ADD COLUMN `prompt_response` LONGTEXT NULL');
        }
        if ($this->tableExists('responses_detailed') && !$this->columnExists('responses_detailed', 'prompt')) {
            $this->pdo()->exec('ALTER TABLE `responses_detailed` ADD COLUMN `prompt` LONGTEXT NULL');
        }
        if ($this->tableExists('responses_detailed') && !$this->columnExists('responses_detailed', 'prompt_executed_at')) {
            $this->pdo()->exec('ALTER TABLE `responses_detailed` ADD COLUMN `prompt_executed_at` DATETIME NULL AFTER `prompt_response`');
        }
    }

    private function sessionMinute(string $responseDateTime): ?string
    {
        $responseDateTime = trim($responseDateTime);
        return $responseDateTime !== '' ? substr($responseDateTime, 0, 16) : null;
    }

    private function openAiApiKey(): string
    {
        return trim((string) (Env::get('OPENAI_API_KEY', '') ?? ''));
    }

    /**
     * @return array<string, bool>
     */
    private function tableColumns(string $table): array
    {
        try {
            $st = $this->pdo()->query('DESCRIBE `' . $table . '`');
            $columns = [];
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $name = (string) ($row['Field'] ?? '');
                if ($name !== '') {
                    $columns[$name] = true;
                }
            }
            return $columns;
        } catch (Throwable) {
            return [];
        }
    }

    private function tableExists(string $table): bool
    {
        try {
            $this->pdo()->query('SELECT 1 FROM `' . $table . '` LIMIT 1');
            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function columnExists(string $table, string $column): bool
    {
        return isset($this->tableColumns($table)[$column]);
    }

    private function pdo(): PDO
    {
        return $this->pdo ??= $this->database->pdo();
    }
}
