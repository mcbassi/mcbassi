<?php
declare(strict_types=1);

namespace App\Estrategica;

use App\AI\PromptExecutionService;
use App\Infra\Database;
use App\Infra\Env;
use App\Prompts\PromptRepository;
use App\Prompts\PromptRuntimeService;
use RuntimeException;

final class EstrategicaService
{
    private EstrategicaRepository $repository;
    private PromptExecutionService $promptExecutor;

    public function __construct(Database $database)
    {
        $this->repository = new EstrategicaRepository($database);
        $promptRepository = new PromptRepository($database);
        $runtimeService = new PromptRuntimeService($database, $promptRepository);
        $this->promptExecutor = new PromptExecutionService($database, $promptRepository, $runtimeService);
    }

    /** @return array<int, array<string,mixed>> */
    public function fetchQuestionGroups(): array
    {
        return $this->repository->fetchQuestionGroups();
    }

    /** @return array<int, array<string,mixed>> */
    public function listPriorityGroups(): array
    {
        return $this->repository->fetchPriorityGroups();
    }

    /** @return array<string,mixed> */
    public function getStatusFromGroupB64(string $groupB64): array
    {
        [$company, $email, $sessMin] = $this->parseQuestionGroupB64($groupB64);
        $row = $this->repository->ensureStatusRow($company, $email, $sessMin);

        return [
            'user' => $row['user'] ?? $company,
            'email_user' => $row['email_user'] ?? $email,
            'response_datetime' => $row['response_datetime'] ?? $sessMin,
            'resumo_ok' => $this->bitToBool($row['resumo_ok'] ?? null),
            'doc_ok' => $this->bitToBool($row['doc_ok'] ?? null),
            'apres_ok' => $this->bitToBool($row['apres_ok'] ?? null),
            'file_resumo' => $row['file_resumo'] ?? null,
            'file_doc' => $row['file_doc'] ?? null,
            'file_apres' => $row['file_apres'] ?? null,
        ];
    }

    /** @return array<string,mixed> */
    public function executePriorityGroup(string $groupB64, string $priorityGroupId, string $priorityGroupName = ''): array
    {
        [$company, $email, $sessMin] = $this->parseQuestionGroupB64($groupB64);

        $groupId = ctype_digit($priorityGroupId) ? (int) $priorityGroupId : null;
        $group = $this->repository->fetchGroupByIdOrName($groupId, trim($priorityGroupName));
        if (!is_array($group)) {
            throw new RuntimeException('Grupo de prioridades não encontrado.');
        }

        $groupName = trim((string) ($group['name'] ?? ''));
        $promptGrp = trim((string) ($group['prompt_grp'] ?? ''));
        $groupIdResolved = (int) ($group['id'] ?? 0);
        if ($groupName === '' || $groupIdResolved <= 0) {
            throw new RuntimeException('Grupo ou prompt_grp não configurado.');
        }

        $sessionInfo = $this->repository->findQuestionGroupSession($company, $email, $sessMin);
        if (!is_array($sessionInfo)) {
            throw new RuntimeException('Sessão do questionário não encontrada.');
        }
        $responseSessionId = (int) ($sessionInfo['id'] ?? 0);
        $emailUser = trim((string) ($sessionInfo['email_user'] ?? $email));
        if ($responseSessionId <= 0 || $emailUser === '') {
            throw new RuntimeException('Sessão estratégica sem vínculo válido com response_sessions/email_user.');
        }

        $questions = $this->repository->fetchGroupQuestions($groupIdResolved);
        $responsePack = $this->repository->fetchResponsesForGroup($company, $email, $sessMin, $questions);
        $items = $responsePack['items'];
        if ($items === []) {
            throw new RuntimeException('Nenhuma resposta analítica encontrada para o grupo selecionado.');
        }

        $priorityJson = $this->repository->fetchDiagPriorityJson($sessMin, $groupIdResolved);
        if ($priorityJson === null) {
            throw new RuntimeException('Não encontrei Prioridades Propostas salvas para esta sessão/grupo. Execute a IA Prioridades e salve o resultado antes de criar o Reporte Estratégico.');
        }
        $priorityArray = $this->decodePriorityJson($priorityJson);
        if ($priorityArray === []) {
            throw new RuntimeException('As Prioridades Propostas salvas estão vazias ou inválidas.');
        }

        $priorityTable = $this->priorityArrayToMarkdownTable($priorityArray);
        $chapters = $this->buildAnalyticalChapters($groupName, $items);
        $bibliography = $this->extractBibliographyFromItems($items);

        $promptInfo = $this->repository->findFirstPromptByNames([
            $promptGrp . '_estrategica',
            'Cria_resumo_estrategico_1',
        ]);
        if (!is_array($promptInfo)) {
            throw new RuntimeException('Nenhum prompt estratégico encontrado. Cadastre ' . $promptGrp . '_estrategica ou Cria_resumo_estrategico_1.');
        }

        $contextVars = [
            'company_name' => $company,
            'email_resp' => $email,
            'sess_min' => $sessMin,
            'group_name' => $groupName,
            'group_id' => (string) $groupIdResolved,
            'prompt_prefix' => $promptGrp,
            'relatorio_capitulos' => $chapters,
            'prioridades_propostas_tabela' => $priorityTable,
            'prioridades_propostas_json' => $priorityJson,
            'bibliografia_grupo' => $bibliography,
        ];
        $inputText = implode("

", [
            'CONTEXTO_ESTRATEGICO',
            'EMPRESA: ' . $company,
            'EMAIL: ' . $email,
            'SESSAO_MIN: ' . $sessMin,
            'GRUPO_PRIORIDADES: ' . $groupName,
            'PROMPT_ORIGEM: ' . (string) ($promptInfo['assistente'] ?? ''),
            'CAPITULOS_ANALITICOS_DO_GRUPO:',
            $chapters,
            'PRIORIDADES_PROPOSTAS_TABELA:',
            $priorityTable,
            'PRIORIDADES_PROPOSTAS_JSON:',
            $priorityJson,
            'BIBLIOGRAFIA_GRUPO:',
            $bibliography,
        ]);

        $execution = $this->promptExecutor->execute([
            'module' => 'estrategica',
            'prompt_name' => (string) ($promptInfo['assistente'] ?? ''),
            'prompt_text' => (string) ($promptInfo['prompt'] ?? ''),
            'email_user' => $emailUser,
            'company_name' => $company,
            'version_id' => $responseSessionId,
            'context_vars' => $contextVars,
            'input_text' => $inputText,
            'usage_context' => [
                'response_session_id' => $responseSessionId,
                'company_name' => $company,
                'email_resp' => $email,
                'email_user' => $emailUser,
                'sess_min' => $sessMin,
                'response_datetime' => (string) ($sessionInfo['response_datetime'] ?? ''),
            ],
            'instructions' => 'Você é um consultor estratégico sênior. Responda em Markdown com tom executivo e consultivo, usando os capítulos analíticos, a tabela de prioridades, SQL e documentos anexados como base de evidência.',
        ]);

        $cleanOut = $this->stripResultadosAnteriores((string) ($execution['model_response_text'] ?? ''));
        $uploadsDir = $this->repository->uploadsDir();
        $this->ensureDir($uploadsDir);

        $prefix = $this->sessMinToPrefix($sessMin);
        $fileName = $this->safeFilename($prefix . '_' . $this->slugFilename($groupName) . '_estrategico.doc');
        $fullPath = rtrim($uploadsDir, '/\\') . DIRECTORY_SEPARATOR . $fileName;

        $title = 'Reporte Estratégico - ' . $groupName;
        $this->saveWordDocHtml($fullPath, $title, $cleanOut);
        $this->repository->updateStatusResumo($company, $email, $sessMin, $cleanOut, $fileName);

        return [
            'type' => 'resumo',
            'filename' => $fileName,
            'group_name' => $groupName,
            'prompt_used' => (string) ($promptInfo['assistente'] ?? ''),
        ];
    }

    /** @return array<string,mixed> */
    public function createDocFinalConsultoria(string $groupB64): array
    {
        [$company, $email, $sessMin] = $this->parseQuestionGroupB64($groupB64);
        $uploadsDir = $this->repository->uploadsDir();
        $this->ensureDir($uploadsDir);
        $resumo = $this->repository->findResumoContent($company, $email, $sessMin, $uploadsDir);
        if (!$resumo['resumo_ok']) {
            throw new RuntimeException('Resumo ainda não disponível.');
        }
        $sessionInfo = $this->repository->findQuestionGroupSession($company, $email, $sessMin);
        if (!is_array($sessionInfo)) {
            throw new RuntimeException('Sessão do questionário não encontrada.');
        }
        $responseSessionId = (int) ($sessionInfo['id'] ?? 0);
        $emailUser = trim((string) ($sessionInfo['email_user'] ?? $email));
        $promptText = $this->repository->findPromptByLike('Cria_resumo_final_diagnostico');
        $input = "ARQUIVO_RESUMO_NOME: " . (string) $resumo['file_resumo'] . "
"
            . "EMPRESA: {$company}
EMAIL: {$email}
SESSAO_MIN: {$sessMin}

"
            . "ARQUIVO_RESUMO_CONTEUDO:
" . (string) $resumo['content'] . "
";
        $execution = $this->promptExecutor->execute([
            'module' => 'estrategica',
            'prompt_name' => 'Cria_resumo_final_diagnostico',
            'prompt_text' => $promptText,
            'email_user' => $emailUser,
            'company_name' => $company,
            'version_id' => $responseSessionId,
            'context_vars' => [
                'company_name' => $company,
                'email_resp' => $email,
                'sess_min' => $sessMin,
                'arquivo_resumo_nome' => (string) $resumo['file_resumo'],
                'arquivo_resumo_conteudo' => (string) $resumo['content'],
            ],
            'input_text' => $input,
            'usage_context' => [
                'response_session_id' => $responseSessionId,
                'company_name' => $company,
                'email_resp' => $email,
                'email_user' => $emailUser,
                'sess_min' => $sessMin,
                'response_datetime' => (string) ($sessionInfo['response_datetime'] ?? ''),
            ],
            'instructions' => 'Você é um consultor sênior. Gere um documento final de consultoria em Markdown, usando SQL, documentos anexados e placeholders se existirem.',
        ]);
        $output = $this->stripResultadosAnteriores((string) ($execution['model_response_text'] ?? ''));

        $outName = $this->safeFilename(preg_replace('/\.[a-z0-9]+$/i', '', (string) $resumo['file_resumo']) . '_FINAL.doc');
        $fullPath = rtrim($uploadsDir, '/\\') . DIRECTORY_SEPARATOR . $outName;
        $this->saveWordDocHtml($fullPath, 'Doc Final - Consultoria', $output);
        $this->repository->updateStatusDoc($company, $email, $sessMin, $output, $outName);
        return ['type' => 'doc', 'filename' => $outName];
    }

    /** @return array<string,mixed> */
    public function createPptFinalDiagnostico(string $groupB64): array
    {
        [$company, $email, $sessMin] = $this->parseQuestionGroupB64($groupB64);
        $uploadsDir = $this->repository->uploadsDir();
        $this->ensureDir($uploadsDir);
        $resumo = $this->repository->findResumoContent($company, $email, $sessMin, $uploadsDir);
        if (!$resumo['resumo_ok']) {
            throw new RuntimeException('Resumo ainda não disponível.');
        }
        $sessionInfo = $this->repository->findQuestionGroupSession($company, $email, $sessMin);
        if (!is_array($sessionInfo)) {
            throw new RuntimeException('Sessão do questionário não encontrada.');
        }
        $responseSessionId = (int) ($sessionInfo['id'] ?? 0);
        $emailUser = trim((string) ($sessionInfo['email_user'] ?? $email));
        $promptText = $this->repository->findPromptByLike('Cria_PPT_final_diagnostico');
        $input = "ARQUIVO_RESUMO_NOME: " . (string) $resumo['file_resumo'] . "
"
            . "EMPRESA: {$company}
EMAIL: {$email}
SESSAO_MIN: {$sessMin}

"
            . "ARQUIVO_RESUMO_CONTEUDO:
" . (string) $resumo['content'] . "
";
        $execution = $this->promptExecutor->execute([
            'module' => 'estrategica',
            'prompt_name' => 'Cria_PPT_final_diagnostico',
            'prompt_text' => $promptText,
            'email_user' => $emailUser,
            'company_name' => $company,
            'version_id' => $responseSessionId,
            'context_vars' => [
                'company_name' => $company,
                'email_resp' => $email,
                'sess_min' => $sessMin,
                'arquivo_resumo_nome' => (string) $resumo['file_resumo'],
                'arquivo_resumo_conteudo' => (string) $resumo['content'],
            ],
            'input_text' => $input,
            'usage_context' => [
                'response_session_id' => $responseSessionId,
                'company_name' => $company,
                'email_resp' => $email,
                'email_user' => $emailUser,
                'sess_min' => $sessMin,
                'response_datetime' => (string) ($sessionInfo['response_datetime'] ?? ''),
            ],
            'instructions' => 'Você é um consultor sênior. Gere o conteúdo estruturado para uma apresentação, usando SQL, documentos anexados e placeholders se existirem.',
        ]);
        $output = $this->stripResultadosAnteriores((string) ($execution['model_response_text'] ?? ''));

        $outName = $this->safeFilename(preg_replace('/\.[a-z0-9]+$/i', '', (string) $resumo['file_resumo']) . '_PPTX.json');
        if (!preg_match('/\.json$/i', $outName)) {
            $outName .= '.json';
        }
        $fullPath = rtrim($uploadsDir, '/\\') . DIRECTORY_SEPARATOR . $outName;
        if (file_put_contents($fullPath, $output) === false) {
            throw new RuntimeException('Falha ao gravar arquivo PPT JSON.');
        }
        $this->repository->updateStatusApres($company, $email, $sessMin, $output, $outName);
        return ['type' => 'apres', 'filename' => $outName];
    }

    /** @return array{mime:string,filename:string,path:?string,blob:?string} */
    public function downloadStatus(string $type, string $groupB64): array
    {
        [$company, $email, $sessMin] = $this->parseQuestionGroupB64($groupB64);
        return $this->repository->resolveDownload($type, $company, $email, $sessMin, $this->repository->uploadsDir());
    }

    /** @return array{path:string,filename:string} */
    public function downloadResumoDoc(string $groupB64, string $priorityGroupName): array
    {
        [$company, $email, $sessMin] = $this->parseQuestionGroupB64($groupB64);
        $status = $this->repository->getStatusRow($company, $email, $sessMin);
        $uploadsDir = $this->repository->uploadsDir();

        $statusFile = trim((string) ($status['file_resumo'] ?? ''));
        if ($statusFile !== '') {
            $statusPath = rtrim($uploadsDir, '/\\') . DIRECTORY_SEPARATOR . basename($statusFile);
            if (is_file($statusPath)) {
                return ['path' => $statusPath, 'filename' => basename($statusPath)];
            }
        }

        if ($priorityGroupName === '') {
            throw new RuntimeException('Selecione o Grupo de Prioridades.');
        }
        $prefix = $this->sessMinToPrefix($sessMin);
        $prioSlug = $this->slugFilename($priorityGroupName);
        if ($prioSlug === '') {
            throw new RuntimeException('Grupo de prioridades inválido.');
        }

        $patterns = [
            $prefix . '_' . $prioSlug . '_estrategico.doc',
            $prefix . '_' . strtolower($prioSlug) . '_estrategico.doc',
            $prefix . '_' . $prioSlug . '.doc',
            $prefix . '_' . strtolower($prioSlug) . '.doc',
            $prefix . '_' . $prioSlug . '.DOC',
        ];
        foreach ($patterns as $fileName) {
            $path = rtrim($uploadsDir, '/\\') . DIRECTORY_SEPARATOR . $fileName;
            if (is_file($path)) {
                return ['path' => $path, 'filename' => basename($path)];
            }
        }

        $glob = glob(rtrim($uploadsDir, '/\\') . DIRECTORY_SEPARATOR . $prefix . '_*.{doc,DOC}', GLOB_BRACE) ?: [];
        $prioSlugLower = strtolower($prioSlug);
        foreach ($glob as $path) {
            $nameOnly = preg_replace('/\.[dD][oO][cC]$/', '', basename($path));
            $nameSlug = strtolower($this->slugFilename((string) $nameOnly));
            if ($nameSlug !== '' && str_contains($nameSlug, $prioSlugLower)) {
                return ['path' => $path, 'filename' => basename($path)];
            }
        }

        throw new RuntimeException('Não existe arquivo em uploads para esta combinação: ' . $prefix . ' + ' . $priorityGroupName . '.');
    }

    private function buildAnalyticalChapters(string $groupName, array $items): string
    {
        $chapters = ['# Relatório Analítico do Grupo — ' . $groupName];
        $chapter = 1;
        foreach ($items as $row) {
            $label = trim((string) ($row['question_label'] ?? $row['question_name'] ?? ''));
            $answer = trim((string) ($row['answer'] ?? ''));
            $promptResponse = $this->stripResultadosAnteriores((string) ($row['prompt_response'] ?? ''));
            if ($label === '' || trim($promptResponse) === '') {
                continue;
            }
            $chapters[] = '## Capítulo ' . $chapter . ' — ' . $label;
            if ($answer !== '') {
                $chapters[] = '**Respuesta original:** ' . $answer;
                $chapters[] = '';
            }
            $chapters[] = trim($promptResponse);
            $chapters[] = '';
            $chapter++;
        }

        if ($chapter === 1) {
            throw new RuntimeException('As respostas analíticas do grupo ainda não foram geradas.');
        }

        return trim(implode("\n", $chapters));
    }

    private function extractBibliographyFromItems(array $items): string
    {
        $seen = [];
        $refs = [];
        foreach ($items as $row) {
            foreach (['prompt', 'prompt_response'] as $field) {
                $text = trim((string) ($row[$field] ?? ''));
                if ($text === '') continue;
                foreach (preg_split('/
|
|
/', $text) ?: [] as $line) {
                    $line = trim((string) $line);
                    if ($line === '') continue;
                    if (preg_match('/^\[(ARTICLE|ARTIGO|PAPER|BOOK|REPORT|DOC)\]\s+(.+)$/iu', $line) === 1) {
                        $key = mb_strtolower($line);
                        if (!isset($seen[$key])) {
                            $seen[$key] = true;
                            $refs[] = $line;
                        }
                    }
                }
            }
        }
        return $refs !== [] ? implode("
", $refs) : '[ARTICLE] Documentos analíticos del grupo ya incorporados en los capítulos anteriores';
    }

    /** @return array<int,array<string,string>> */
    private function decodePriorityJson(string $json): array
    {
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return [];
        }
        $normalized = [];
        foreach ($decoded as $row) {
            if (!is_array($row)) {
                continue;
            }
            $normalized[] = [
                'prioridade' => trim((string) ($row['prioridade'] ?? '')),
                'melhoria' => trim((string) ($row['melhoria'] ?? '')),
                'resultado_esperado' => trim((string) ($row['resultado_esperado'] ?? '')),
                'prazo' => trim((string) ($row['prazo'] ?? '')),
                'predecessora' => trim((string) ($row['predecessora'] ?? '')),
            ];
        }
        return $normalized;
    }

    private function priorityArrayToMarkdownTable(array $items): string
    {
        $lines = [
            '| Prioridad | Mejora propuesta | Resultado esperado | Plazo | Predecesora |',
            '|---|---|---|---|---|',
        ];
        foreach ($items as $row) {
            $lines[] = '| ' . $this->tableCell($row['prioridade'] ?? '')
                . ' | ' . $this->tableCell($row['melhoria'] ?? '')
                . ' | ' . $this->tableCell($row['resultado_esperado'] ?? '')
                . ' | ' . $this->tableCell($row['prazo'] ?? '')
                . ' | ' . $this->tableCell($row['predecessora'] ?? '')
                . ' |';
        }
        return implode("\n", $lines);
    }

    private function tableCell(string $value): string
    {
        $value = trim(str_replace(["\r\n", "\r", "\n"], ' ', $value));
        $value = str_replace('|', '\\|', $value);
        return $value === '' ? '—' : $value;
    }

    private function applyReplacements(string $text, array $replacements): string
    {
        return strtr($text, $replacements);
    }

    /** @return array{0:string,1:string,2:string} */
    private function parseQuestionGroupB64(string $groupB64): array
    {
        if ($groupB64 === '') {
            throw new RuntimeException('question_group_b64 obrigatório.');
        }
        $decoded = base64_decode($groupB64, true);
        if ($decoded === false || $decoded === '') {
            throw new RuntimeException('question_group_b64 inválido.');
        }
        $json = json_decode($decoded, true);
        if (!is_array($json)) {
            throw new RuntimeException('question_group_b64 inválido (json).');
        }
        $company = trim((string) ($json['c'] ?? ''));
        $email = trim((string) ($json['e'] ?? ''));
        $sessMin = trim((string) ($json['k'] ?? ''));
        if ($company === '' || $email === '' || $sessMin === '') {
            throw new RuntimeException('Dados do questionário incompletos (c/e/k).');
        }
        return [$company, $email, $sessMin];
    }

    private function runLlm(string $promptText, string $inputText): string
    {
        $apiKey = trim((string) (Env::get('OPENAI_API_KEY', '') ?? ($_SERVER['OPENAI_API_KEY'] ?? getenv('OPENAI_API_KEY') ?: '')));
        if ($apiKey === '') {
            foreach ([\app_path('config.cfg'), \app_path('Config.cfg')] as $cfgPath) {
                if (!is_file($cfgPath) || !is_readable($cfgPath)) {
                    continue;
                }
                $lines = @file($cfgPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
                foreach ($lines as $line) {
                    if (preg_match('/^\s*OPENAI_API_KEY\s*=\s*(.+)\s*$/', (string) $line, $matches) === 1) {
                        $apiKey = trim((string) ($matches[1] ?? ''), " \t\n\r\0\x0B\"'");
                        break 2;
                    }
                }
            }
        }
        if ($apiKey === '') {
            return '[ERRO] OPENAI_API_KEY não configurada.';
        }

        $payload = [
            'model' => 'gpt-4o-mini',
            'messages' => [
                ['role' => 'user', 'content' => trim($promptText) . "\n\n" . trim($inputText)],
            ],
            'temperature' => 0.35,
            'max_tokens' => 2200,
        ];

        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json; charset=utf-8',
                'Authorization: Bearer ' . $apiKey,
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 120,
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $error !== '') {
            return '[ERRO] Falha cURL: ' . $error;
        }

        $json = json_decode((string) $response, true);
        if (!is_array($json)) {
            return $status >= 400 ? '[ERRO HTTP ' . $status . '] ' . substr((string) $response, 0, 800) : (string) $response;
        }
        if ($status >= 400) {
            $message = trim((string) ($json['error']['message'] ?? ''));
            if ($message === '') {
                $message = substr((string) $response, 0, 800);
            }
            return '[ERRO HTTP ' . $status . '] ' . $message;
        }
        return trim((string) ($json['choices'][0]['message']['content'] ?? '(sem resposta)'));
    }

    private function stripResultadosAnteriores(string $text): string
    {
        $patterns = [
            '/(^|\R)RESULTADOS_ANTERIORES:\R(?:.*\R)*?^---\R?/mi',
            '/(^|\R)===\s*RESULTADO\s*ANTERIOR\s*===\R(?:.*\R)*?^---\R?/mi',
        ];
        $output = trim($text);
        foreach ($patterns as $pattern) {
            $output = preg_replace($pattern, "\n", $output) ?? $output;
        }
        return trim(preg_replace("/\n{3,}/", "\n\n", $output) ?? $output);
    }

    private function mdToHtmlBasic(string $markdown): string
    {
        $markdown = str_replace(["\r\n", "\r"], "\n", $markdown);
        $text = htmlspecialchars($markdown, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $text = preg_replace('/^\s*###\s+(.+)$/m', '<h3>$1</h3>', $text) ?? $text;
        $text = preg_replace('/^\s*##\s+(.+)$/m', '<h2>$1</h2>', $text) ?? $text;
        $text = preg_replace('/^\s*#\s+(.+)$/m', '<h1>$1</h1>', $text) ?? $text;
        $text = preg_replace('/\*\*([^*]+)\*\*/u', '<strong>$1</strong>', $text) ?? $text;

        $lines = explode("\n", $text);
        $html = [];
        $inOl = false;
        $inUl = false;
        $closeLists = static function () use (&$html, &$inOl, &$inUl): void {
            if ($inOl) { $html[] = '</ol>'; $inOl = false; }
            if ($inUl) { $html[] = '</ul>'; $inUl = false; }
        };

        foreach ($lines as $line) {
            $line = rtrim($line);
            if (trim($line) === '') { $closeLists(); continue; }
            if (preg_match('/^\s*\d+\.\s+(.+)$/', $line, $matches) === 1) {
                if (!$inOl) { $closeLists(); $html[] = '<ol>'; $inOl = true; }
                $html[] = '<li>' . $matches[1] . '</li>';
                continue;
            }
            if (preg_match('/^\s*-\s+(.+)$/', $line, $matches) === 1) {
                if (!$inUl) { $closeLists(); $html[] = '<ul>'; $inUl = true; }
                $html[] = '<li>' . $matches[1] . '</li>';
                continue;
            }
            $closeLists();
            if (preg_match('/^<h[1-3]>.*<\/h[1-3]>$/', trim($line)) === 1) {
                $html[] = $line;
            } else {
                $html[] = '<p>' . $line . '</p>';
            }
        }
        $closeLists();
        return implode("\n", $html);
    }

    private function saveWordDocHtml(string $fullPath, string $title, string $content): void
    {
        $titleEsc = htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $bodyHtml = $this->mdToHtmlBasic($content);
        $html = '<html><head><meta charset="utf-8"><title>' . $titleEsc . '</title></head><body style="font-family:Calibri,Arial,sans-serif;font-size:11pt;line-height:1.35"><h1>' . $titleEsc . '</h1>' . $bodyHtml . '</body></html>';
        if (file_put_contents($fullPath, $html) === false) {
            throw new RuntimeException('Falha ao gravar o arquivo: ' . $fullPath);
        }
    }

    private function ensureDir(string $dir): void
    {
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('Não foi possível criar diretório: ' . $dir);
        }
        if (!is_writable($dir)) {
            throw new RuntimeException('Diretório sem permissão de escrita: ' . $dir);
        }
    }

    private function safeFilename(string $name): string
    {
        $name = trim($name);
        $name = preg_replace('/[^a-zA-Z0-9_\-\.]+/', '_', $name) ?? 'file';
        $name = preg_replace('/_+/', '_', $name) ?? $name;
        return trim($name, '_');
    }

    private function sessMinToPrefix(string $sessMin): string
    {
        return str_replace([' ', ':'], ['_', '_'], trim($sessMin));
    }

    private function slugFilename(string $name): string
    {
        $value = trim($name);
        $translit = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if (is_string($translit) && $translit !== '') {
            $value = $translit;
        }
        $value = preg_replace('/\s+/', '_', $value) ?? $value;
        $value = str_replace('-', '_', $value);
        $value = preg_replace('/[^A-Za-z0-9_]/', '', $value) ?? $value;
        $value = preg_replace('/_+/', '_', $value) ?? $value;
        return trim($value, '_');
    }

    private function bitToBool(mixed $value): bool
    {
        if (is_bool($value)) return $value;
        if (is_int($value)) return $value === 1;
        if (is_string($value)) return $value === '1' || (strlen($value) > 0 && ord($value) === 1);
        return false;
    }
}
