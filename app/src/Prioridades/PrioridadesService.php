<?php
declare(strict_types=1);

namespace App\Prioridades;

use App\Diagnostico\VersionedResponseRepository;
use App\Infra\Database;
use App\Infra\Env;
use RuntimeException;
use Throwable;

final class PrioridadesService
{
    private PrioridadesRepository $repository;
    private VersionedResponseRepository $versionRepository;
    private const DEFAULT_MODEL = 'gpt-4o-mini';

    public function __construct(private readonly Database $database)
    {
        $this->repository = new PrioridadesRepository($database);
        $this->versionRepository = new VersionedResponseRepository($database->pdo());
    }

    /** @return array<string, mixed> */
    public function buildPageData(string $emailUser, ?int $selectedVersionId = null): array
    {
        $this->versionRepository->ensureSchema();
        $this->repository->ensureSchema();

        $versions = $emailUser !== '' ? $this->versionRepository->versions($emailUser) : [];
        $selectedVersion = null;
        if (($selectedVersionId ?? 0) > 0) {
            $selectedVersion = $this->versionRepository->versionById((int) $selectedVersionId, $emailUser);
        }
        if (!is_array($selectedVersion) && $versions !== []) {
            $selectedVersion = $versions[0];
        }

        $groups = $this->repository->fetchGroups();
        $storedResults = is_array($selectedVersion) ? $this->repository->fetchStoredResults((int) ($selectedVersion['id'] ?? 0)) : [];

        return [
            'versions' => $versions,
            'selectedVersion' => $selectedVersion,
            'groups' => $groups,
            'storedResults' => $storedResults,
        ];
    }

    /** @return array<string, mixed> */
    public function listResponses(int $responseSessionId, int $groupId, bool $onlyWithAiResponse): array
    {
        $group = $this->repository->fetchGroup($groupId);
        if (!is_array($group)) {
            throw new RuntimeException('Grupo de Prioridades não encontrado.');
        }

        $questionsMap = $this->repository->fetchGroupQuestions($groupId);
        $questions = array_keys($questionsMap);
        if ($questions === []) {
            throw new RuntimeException('Grupo de Prioridades sem perguntas cadastradas.');
        }

        $items = $this->repository->fetchResponsesForGroup($responseSessionId, $questions, $onlyWithAiResponse);
        $meta = $this->repository->fetchSessionMeta($responseSessionId);

        return [
            'meta' => [
                'response_session_id' => $responseSessionId,
                'company' => (string) ($meta['company_name'] ?? ''),
                'email' => (string) ($meta['email_resp'] ?? ''),
                'sess_min' => (string) ($meta['sess_min'] ?? ''),
                'priority_group_id' => $groupId,
                'priority_group_name' => (string) ($group['name'] ?? ''),
                'question_count' => count($questions),
            ],
            'items' => $items,
        ];
    }

    /** @param array<int, array<string, mixed>> $answersOverride */
    /** @return array<string, mixed> */
    public function executePriorityGroup(int $responseSessionId, int $groupId, array $answersOverride = [], bool $debug = false): array
    {
        $group = $this->repository->fetchGroup($groupId);
        if (!is_array($group)) {
            throw new RuntimeException('Grupo de Prioridades não encontrado.');
        }

        $session = $this->repository->fetchSessionMeta($responseSessionId);
        if (!is_array($session)) {
            throw new RuntimeException('Sessão selecionada não encontrada.');
        }

        $promptPrefix = trim((string) ($group['prompt_grp'] ?? ''));
        if ($promptPrefix === '') {
            throw new RuntimeException('Este grupo não tem prompt_grp preenchido.');
        }

        $questionsMap = $this->repository->fetchGroupQuestions($groupId);
        $fields = array_keys($questionsMap);
        if ($fields === []) {
            throw new RuntimeException('Grupo sem perguntas cadastradas (grupos_prioridades).');
        }

        $rows = $answersOverride !== []
            ? $this->normalizeOverrideRows($answersOverride, $fields)
            : $this->repository->fetchResponsesForGroup($responseSessionId, $fields, false);

        if ($rows === []) {
            throw new RuntimeException('Nenhuma resposta encontrada para este grupo e sessão.');
        }

        $p1Name = $promptPrefix . '_1';
        $p2Name = $promptPrefix . '_2';
        $p1Row = $this->repository->findPromptByAssistente($p1Name);
        $p2Row = $this->repository->findPromptByAssistente($p2Name);
        $p1Text = trim((string) ($p1Row['prompt_full_text'] ?? $p1Row['prompt'] ?? ''));
        $p2Text = trim((string) ($p2Row['prompt_full_text'] ?? $p2Row['prompt'] ?? ''));
        if ($p1Text === '' || $p2Text === '') {
            throw new RuntimeException("Prompt #1 ou #2 vazio. Verifique {$p1Name} e {$p2Name} na tabela prompts.");
        }

        $debugData = [
            'prompt_1' => ['name' => $p1Name, 'text' => $debug ? $p1Text : ''],
            'prompt_2' => ['name' => $p2Name, 'text' => $debug ? $p2Text : ''],
            'input_p1' => '',
            'input_p2' => '',
            'output_p1' => '',
            'output_p2_raw' => '',
            'answers_used' => $debug ? $rows : [],
        ];

        $lines = [];
        foreach ($rows as $row) {
            $label = trim((string) ($row['question_label'] ?? $row['label'] ?? ''));
            $field = trim((string) ($row['question_name'] ?? $row['field_name'] ?? ''));
            $answer = trim((string) ($row['answer'] ?? ''));
            if ($answer === '') {
                continue;
            }
            $q = $label !== '' ? $label : $field;
            $lines[] = '- ' . $q . ': ' . $answer;
        }
        $allText = implode("\n", $lines);

        $company = trim((string) ($session['company_name'] ?? ''));
        $email = trim((string) ($session['email_resp'] ?? ''));
        $sessMin = trim((string) ($session['sess_min'] ?? ''));
        $groupName = trim((string) ($group['name'] ?? ''));

        $input1 =
            "EMPRESA: {$company}\nEMAIL: {$email}\nSESSAO_MIN: {$sessMin}\nGRUPO_PRIORIDADE: {$groupName}\n\n" .
            "DADOS_CONSOLIDADOS (todas as perguntas/respostas):\n{$allText}\n\n" .
            "Tarefa: gere MEMORIA_CUMULATIVA concisa e reutilizável para o Prompt #2.\n" .
            "Regras: não invente nada; use somente os dados acima.";

        if ($debug) {
            $debugData['input_p1'] = $input1;
        }

        $memory = $this->runLlm($p1Text, $input1);
        if ($debug) {
            $debugData['output_p1'] = $memory;
        }

        $input2 =
            "EMPRESA: {$company}\nEMAIL: {$email}\nSESSAO_MIN: {$sessMin}\nGRUPO_PRIORIDADE: {$groupName}\n\n" .
            "DADOS_CONSOLIDADOS:\n{$allText}\n\n" .
            "MEMORIA_CUMULATIVA:\n{$memory}\n\n" .
            "RETORNE JSON PURO (array) com campos: prioridade, melhoria, resultado_esperado, prazo, predecessora.";

        if ($debug) {
            $debugData['input_p2'] = $input2;
        }

        $finalRaw = $this->runLlm($p2Text, $input2);
        if ($debug) {
            $debugData['output_p2_raw'] = $finalRaw;
        }

        $finalJson = $this->extractJsonArray($finalRaw);
        $this->repository->storeResult(
            $responseSessionId,
            $groupId,
            $groupName,
            $promptPrefix,
            $input2,
            $finalRaw,
            $finalJson !== null ? json_encode($finalJson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null
        );

        $result = [
            'questionnaire_idx' => $sessMin,
            'meta' => [
                'response_session_id' => $responseSessionId,
                'company' => $company,
                'email' => $email,
                'sess_min' => $sessMin,
                'priority_group_id' => $groupId,
                'priority_group_name' => $groupName,
                'prompt_prefix' => $promptPrefix,
                'prompt_1_name' => $p1Name,
                'prompt_2_name' => $p2Name,
            ],
            'answers' => $rows,
            'result_raw' => $finalRaw,
            'result_json' => $finalJson,
            'answers_source' => $answersOverride !== [] ? 'screen' : 'database',
        ];
        if ($debug) {
            $result['debug'] = $debugData;
        }
        return $result;
    }

    /** @param array<int, array<string, mixed>> $result */
    public function saveDiagPriority(int $groupId, string $questionnaireIdx, array $result): int
    {
        if ($groupId <= 0 || $questionnaireIdx === '') {
            throw new RuntimeException('Dados inválidos para salvar.');
        }
        if ($result === []) {
            throw new RuntimeException('JSON inválido (precisa ser array/obj).');
        }
        $this->repository->saveDiagPriority($questionnaireIdx, $groupId, $result);
        return 1;
    }

    /** @param array<int, array<string, mixed>> $currentPriorities */
    /** @return array<string, mixed> */
    public function generateFinalReport(int $responseSessionId, int $groupId, array $currentPriorities = []): array
    {
        $session = $this->repository->fetchSessionMeta($responseSessionId);
        if (!is_array($session)) {
            throw new RuntimeException('Sessão selecionada não encontrada.');
        }

        $group = $this->repository->fetchGroup($groupId);
        if (!is_array($group)) {
            throw new RuntimeException('Grupo de Prioridades não encontrado.');
        }

        $questionsMap = $this->repository->fetchGroupQuestions($groupId);
        $questionNames = array_keys($questionsMap);
        if ($questionNames === []) {
            throw new RuntimeException('Grupo sem perguntas cadastradas (grupos_prioridades).');
        }

        $responses = $this->repository->fetchAnalyticalResponsesForGroup($responseSessionId, $questionNames);
        if ($responses === []) {
            throw new RuntimeException('Nenhuma análise encontrada para este grupo na sessão selecionada.');
        }

        $ordered = [];
        foreach ($responses as $row) {
            $q = trim((string) ($row['question_name'] ?? ''));
            if ($q !== '') {
                $ordered[$q] = $row;
            }
        }

        $chapters = [];
        $chapterNo = 1;
        foreach ($questionNames as $questionName) {
            $row = $ordered[$questionName] ?? null;
            if (!is_array($row)) {
                continue;
            }
            $label = trim((string) ($row['question_label'] ?? $questionName));
            $resp = trim((string) ($row['prompt_response'] ?? ''));
            if ($resp === '') {
                continue;
            }
            $promptCode = trim((string) ($row['prompt_code'] ?? ''));
            $chapters[] = '## Capítulo ' . $chapterNo . ' — ' . $label
                . ($promptCode !== '' ? "

**PROMPT_CODE:** `{$promptCode}`" : '')
                . "

" . $resp;
            $chapterNo++;
        }
        if ($chapters === []) {
            throw new RuntimeException('Nenhum capítulo analítico pôde ser montado para este grupo.');
        }
        $chaptersText = implode("

", $chapters);

        $groupName = trim((string) ($group['name'] ?? ''));
        $promptPrefix = trim((string) ($group['prompt_grp'] ?? ''));
        $sessMin = trim((string) ($session['sess_min'] ?? ''));
        $company = trim((string) ($session['company_name'] ?? ''));
        $email = trim((string) ($session['email_resp'] ?? ''));

        $priorities = $this->normalizePriorityRows($currentPriorities);
        if ($priorities === []) {
            $stored = $this->repository->fetchDiagPriority($sessMin, $groupId);
            $decoded = json_decode((string) ($stored['result_json'] ?? '[]'), true);
            if (is_array($decoded)) {
                $priorities = $this->normalizePriorityRows($decoded);
            }
        }
        if ($priorities === []) {
            throw new RuntimeException('A tabela Prioridades Propostas está vazia. Execute e/ou salve o grupo antes de gerar o relatório final.');
        }

        $prioritiesJson = json_encode($priorities, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if (!is_string($prioritiesJson)) {
            $prioritiesJson = '[]';
        }
        $prioritiesTable = $this->buildPrioritiesMarkdownTable($priorities);

        $promptCandidates = [];
        if ($promptPrefix !== '') {
            $promptCandidates[] = $promptPrefix . '_3';
            $promptCandidates[] = $promptPrefix . '_final';
        }
        $promptCandidates[] = 'Finalize_priority_group_report_1';
        $promptCandidates[] = 'Finalize_analitic_report_1';

        $promptRow = null;
        foreach ($promptCandidates as $candidate) {
            $candidateRow = $this->repository->findPromptByAssistente($candidate);
            $candidateText = trim((string) ($candidateRow['prompt_full_text'] ?? $candidateRow['prompt'] ?? ''));
            if ($candidateText !== '') {
                $promptRow = $candidateRow;
                break;
            }
        }
        $finalPrompt = trim((string) ($promptRow['prompt_full_text'] ?? $promptRow['prompt'] ?? ''));
        if ($finalPrompt === '') {
            throw new RuntimeException('Prompt de finalização do grupo não encontrado na tabela prompts.');
        }

        $replacements = [
            '<<company_name>>' => $company,
            '<<email_resp>>' => $email,
            '<<sess_min>>' => $sessMin,
            '<<group_name>>' => $groupName,
            '<<group_id>>' => (string) $groupId,
            '<<prompt_prefix>>' => $promptPrefix,
            '<<relatorio_capitulos>>' => $chaptersText,
            '<<prioridades_propostas_json>>' => $prioritiesJson,
            '<<prioridades_propostas_tabela>>' => $prioritiesTable,
        ];
        $promptText = strtr($finalPrompt, $replacements);
        if ($promptText === $finalPrompt) {
            $promptText .= "

# Relatório Analítico do Grupo

" . $chaptersText
                . "

# Prioridades Propostas

" . $prioritiesTable
                . "

# Prioridades Propostas (JSON)

```json
" . $prioritiesJson . "
```
";
        }

        $summaryOnly = $this->runLlm(
            'Você é um consultor sênior. Produza apenas o capítulo final de síntese em Markdown, considerando os capítulos analíticos do grupo e a tabela de Prioridades Propostas. Não repita os capítulos anteriores nem reescreva toda a tabela.',
            $promptText,
            true
        );
        $summaryOnly = trim($summaryOnly);
        if ($summaryOnly === '') {
            throw new RuntimeException('Resumo final vazio retornado pela IA.');
        }
        if (!preg_match('/^#{1,6}\s+/m', $summaryOnly)) {
            $summaryOnly = "## Capítulo Final — Resumo Consolidado\n\n" . $summaryOnly;
        }

        $report = "# Relatório Analítico do Grupo — {$groupName}\n\n"
            . $chaptersText
            . "\n\n"
            . $summaryOnly;

        $reportDir = app_path('public/uploads/reportes');
        if (!is_dir($reportDir)) {
            @mkdir($reportDir, 0775, true);
        }
        $filename = 'relatorio_analitico_grupo_' . $responseSessionId . '_' . $groupId . '.md';
        $fullpath = rtrim($reportDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;
        $saved = @file_put_contents($fullpath, $report) !== false;

        return [
            'company' => $company,
            'email' => $email,
            'sess_min' => $sessMin,
            'version' => (string) ($session['response_datetime'] ?? ''),
            'group_id' => $groupId,
            'group_name' => $groupName,
            'count' => count($chapters),
            'report' => $report,
            'saved' => $saved,
            'file' => $saved ? url('uploads/reportes/' . $filename) : null,
        ];
    }

    /** @param array<int, array<string, mixed>> $rows @param array<int, string> $allowedFields */

    /** @param array<int, array<string, mixed>>|string|mixed $rows */
    private function normalizePriorityRows(mixed $rows): array
    {
        if (is_string($rows) && trim($rows) !== '') {
            $decoded = json_decode($rows, true);
            if (is_array($decoded)) {
                $rows = $decoded;
            }
        }

        if (!is_array($rows)) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $normalized = [
                'prioridade' => trim((string)($row['prioridade'] ?? '')),
                'melhoria' => trim((string)($row['melhoria'] ?? '')),
                'resultado_esperado' => trim((string)($row['resultado_esperado'] ?? '')),
                'prazo' => trim((string)($row['prazo'] ?? '')),
                'predecessora' => trim((string)($row['predecessora'] ?? '')),
            ];

            if ($normalized['prioridade'] === '' && $normalized['melhoria'] === '' && $normalized['resultado_esperado'] === '' && $normalized['prazo'] === '' && $normalized['predecessora'] === '') {
                continue;
            }

            $out[] = $normalized;
        }

        return $out;
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function buildPrioritiesMarkdownTable(array $rows): string
    {
        $rows = $this->normalizePriorityRows($rows);
        if ($rows === []) {
            return '';
        }

        $lines = [];
        $lines[] = '| Prioridad | Mejora propuesta | Resultado esperado | Plazo | Predecesora |';
        $lines[] = '|---|---|---|---|---|';
        foreach ($rows as $row) {
            $lines[] = sprintf(
                '| %s | %s | %s | %s | %s |',
                $this->escapeMarkdownTableCell((string)($row['prioridade'] ?? '')),
                $this->escapeMarkdownTableCell((string)($row['melhoria'] ?? '')),
                $this->escapeMarkdownTableCell((string)($row['resultado_esperado'] ?? '')),
                $this->escapeMarkdownTableCell((string)($row['prazo'] ?? '')),
                $this->escapeMarkdownTableCell((string)($row['predecessora'] ?? '')),
            );
        }
        return implode("
", $lines);
    }

    private function escapeMarkdownTableCell(string $value): string
    {
        $value = str_replace(["
", "", "
"], ' ', $value);
        return str_replace('|', '\|', trim($value));
    }

    private function normalizeOverrideRows(array $rows, array $allowedFields): array
    {
        $allowed = [];
        foreach ($allowedFields as $field) {
            $field = trim((string) $field);
            if ($field !== '') {
                $allowed[$field] = true;
            }
        }

        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $field = trim((string) ($row['question_name'] ?? $row['field_name'] ?? ''));
            $label = trim((string) ($row['question_label'] ?? $row['label'] ?? $field));
            $answer = trim((string) ($row['answer'] ?? ''));
            if ($field === '' || $answer === '' || ($allowed !== [] && !isset($allowed[$field]))) {
                continue;
            }
            $out[] = [
                'id' => isset($row['id']) ? (int) $row['id'] : null,
                'question_name' => $field,
                'question_label' => $label,
                'answer' => $answer,
                'response_datetime' => (string) ($row['response_datetime'] ?? ''),
            ];
        }

        return $out;
    }

    private function extractJsonArray(string $text): ?array
    {
        $trim = trim($text);
        if ($trim === '') {
            return null;
        }

        $decoded = json_decode($trim, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        if (preg_match('/```(?:json)?\s*(.*?)```/is', $trim, $m)) {
            $decoded = json_decode(trim((string) $m[1]), true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        $start = strpos($trim, '[');
        $end = strrpos($trim, ']');
        if ($start !== false && $end !== false && $end > $start) {
            $maybe = substr($trim, $start, $end - $start + 1);
            $decoded = json_decode($maybe, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    private function runLlm(string $promptText, string $inputText, bool $systemMode = false): string
    {
        $apiKey = trim((string) (Env::get('OPENAI_API_KEY', '') ?? ''));
        if ($apiKey === '') {
            throw new RuntimeException('OPENAI_API_KEY não configurada.');
        }

        $payload = [
            'model' => Env::get('AI_PRIORIDADES_MODEL', self::DEFAULT_MODEL) ?? self::DEFAULT_MODEL,
            'messages' => [
                ['role' => $systemMode ? 'system' : 'user', 'content' => $promptText . ($systemMode ? '' : "\n\n" . $inputText)],
            ],
            'temperature' => $systemMode ? 0.5 : 0.4,
            'max_tokens' => $systemMode ? 2000 : 1200,
        ];

        if ($systemMode) {
            $payload['messages'][] = ['role' => 'user', 'content' => $inputText];
        }

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
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $error !== '') {
            throw new RuntimeException('Falha cURL OpenAI: ' . $error);
        }

        $json = json_decode((string) $response, true);
        if (!is_array($json)) {
            throw new RuntimeException('Resposta OpenAI não é JSON: ' . substr((string) $response, 0, 800));
        }
        if ($code >= 400) {
            throw new RuntimeException((string) ($json['error']['message'] ?? ('OpenAI HTTP ' . $code)));
        }

        return trim((string) ($json['choices'][0]['message']['content'] ?? ''));
    }
}
