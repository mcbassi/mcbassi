<?php
declare(strict_types=1);

use App\AI\PromptExecutionService;
use App\Diagnostico\VersionedResponseRepository;
use App\Infra\Database;
use App\Papers\PaperFileService;
use App\Prompts\PromptRepository;
use App\Prompts\PromptRuntimeService;

ob_start();
header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/_common.php';

    if (!isset($app) || !is_array($app) || !isset($app['db'])) {
        throw new RuntimeException('App não inicializado corretamente.');
    }

    $versionId = (int)($_POST['version_id'] ?? 0);
    $year = (int)($_POST['year'] ?? 2024);
    $industry = trim((string)($_POST['industry'] ?? 'Advertising'));

    if ($versionId <= 0) {
        throw new RuntimeException('Questionário não informado.');
    }

    if ($industry === '') {
        throw new RuntimeException('Indústria não informada.');
    }

    /** @var Database $db */
    $db = $app['db'];
    if (!$db instanceof Database) {
        throw new RuntimeException('Serviço de banco inválido.');
    }

    $auth = $app['auth'] ?? null;
    if (!is_object($auth)) {
        throw new RuntimeException('Autenticação indisponível.');
    }

    $user = $auth->user();
    $emailUser = trim((string)($user->email ?? ''));

    $repo = new VersionedResponseRepository($db->pdo());
    $repo->ensureSchema();
    $version = $repo->versionById($versionId, $emailUser);

    if (!$version) {
        throw new RuntimeException('Questionário selecionado não encontrado.');
    }

    $companyName = trim((string)($version['company_name'] ?? ''));
    $emailResp = trim((string)($version['email_resp'] ?? $emailUser));
    $responseDateTime = trim((string)($version['response_datetime'] ?? ''));
    $sessMin = $responseDateTime !== '' ? substr($responseDateTime, 0, 16) : '';

    if ($companyName === '' || $emailResp === '' || $sessMin === '') {
        throw new RuntimeException('O questionário selecionado não possui dados suficientes para execução.');
    }

    $statsPdo = $db->statisticsPdo();
    $st = $statsPdo->prepare('CALL sp_damodaran_prompt_master(?,?,?,?,?)');
    $st->execute([$companyName, $emailResp, $sessMin, $year, $industry]);
    $sqlRows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    while ($st->nextRowset()) {}

    if ($sqlRows === []) {
        throw new RuntimeException('A procedure não retornou dados para os parâmetros selecionados.');
    }

    $sqlJson = json_encode(
        $sqlRows[0],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
    );

    if (!is_string($sqlJson)) {
        throw new RuntimeException('Falha ao serializar os dados do benchmark.');
    }

    $bibliography = 'Usar contexto bibliográfico amplo disponível no sistema; sem restrição temática nesta execução.';

    $promptRepo = new PromptRepository($db);
    $runtimeService = new PromptRuntimeService($db, $promptRepo);
    $execService = new PromptExecutionService($db, $promptRepo, $runtimeService, new PaperFileService());

    $promptRows = find_damodaram_prompts($db->pdo());
    if ($promptRows === []) {
        throw new RuntimeException('Nenhum prompt DAMODARAM_X foi encontrado.');
    }

    $titleMap = [
        'DAMODARAM_1' => 'Diagnóstico Ejecutivo',
        'DAMODARAM_2' => 'Análisis Profundo Consultivo',
        'DAMODARAM_3' => 'Agenda de Acción y Prioridades',
        'DAMODARAM_4' => 'Evaluación Final / Descripción Metodológica',
    ];

    $htmlParts = [];

    foreach ($promptRows as $promptRow) {
        $assistente = trim((string)($promptRow['assistente'] ?? ''));
        $sectionTitle = $titleMap[$assistente] ?? $assistente;

        $promptText = (string)($promptRow['prompt'] ?? '');
        if (trim($promptText) === '') {
            throw new RuntimeException('Prompt vazio: ' . ($assistente !== '' ? $assistente : '[sem nome]'));
        }

        $map = build_placeholder_map([
            'company_name'    => $companyName,
            'email_resp'      => $emailResp,
            'sess_min'        => $sessMin,
            'metric_year'     => (string)$year,
            'industry_name'   => $industry,
            'SQL_RESULT_JSON' => $sqlJson,
            'BIBLIO_CONTEXT'  => $bibliography,

            // aliases compatíveis com prompts antigos
            'razon_social'    => $companyName,
            'email_contacto'  => $emailResp,
        ]);

        $resolvedPrompt = strtr($promptText, $map);

        $execution = $execService->execute([
            'module' => 'damodaran_wms_bridge',
            'prompt_name' => $assistente !== '' ? $assistente : $sectionTitle,
            'prompt_text' => $resolvedPrompt,
            'email_user' => $emailUser,
            'company_name' => $companyName,
            'version_id' => $versionId,
            'context_vars' => [],
            'input_text' => '',
            'usage_context' => [
                'response_session_id' => $versionId,
                'company_name' => $companyName,
                'email_resp' => $emailResp,
                'email_user' => $emailUser,
                'response_datetime' => $responseDateTime,
                'sess_min' => $sessMin,
                'question_name' => 'damodaran_wms_bridge',
            ],
        ]);

        $text = trim((string)($execution['model_response_text'] ?? ''));

        $htmlParts[] = '<section class="mb-4">'
            . '<h2 style="margin:0 0 12px 0;">' . esc_html($sectionTitle) . '</h2>'
            . response_to_html($text)
            . '</section>';
    }

    $html = '<div class="dam-prompts-result">'
        . '<div style="margin-bottom:16px;">'
        . '<strong>Empresa:</strong> ' . esc_html($companyName)
        . ' &nbsp; <strong>Indústria:</strong> ' . esc_html($industry)
        . ' &nbsp; <strong>Ano:</strong> ' . esc_html((string)$year)
        . '</div>'
        . implode('<hr style="margin:24px 0;">', $htmlParts)
        . '</div>';

    $buffer = trim((string)ob_get_clean());
    if ($buffer !== '') {
        throw new RuntimeException($buffer);
    }

    echo json_encode([
        'ok' => true,
        'html' => $html,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;

} catch (Throwable $e) {
    $buffer = trim((string)ob_get_clean());
    $msg = $e->getMessage();

    if ($buffer !== '') {
        $msg .= ' | OUTPUT: ' . $buffer;
    }

    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $msg,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function find_damodaram_prompts(PDO $pdo): array
{
    $sql = "
        SELECT *
        FROM prompts
        WHERE assistente REGEXP '^DAMODARAM_[0-9]+$'
        ORDER BY CAST(SUBSTRING_INDEX(assistente, '_', -1) AS UNSIGNED), id
    ";

    return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function build_placeholder_map(array $values): array
{
    $map = [];

    foreach ($values as $key => $value) {
        $string = (string)$value;

        $map['{{' . $key . '}}'] = $string;
        $map['{{ ' . $key . ' }}'] = $string;

        $map['<<' . $key . '>>'] = $string;
        $map['<< ' . $key . ' >>'] = $string;
    }

    return $map;
}

function strip_code_fences(string $text): string
{
    $trim = trim($text);

    if (preg_match('/^```(?:json|markdown|md|html)?\s*(.*?)\s*```$/is', $trim, $m)) {
        return trim($m[1]);
    }

    return $trim;
}

function esc_html(string $text): string
{
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

function response_to_html(string $text): string
{
    $trim = strip_code_fences($text);

    if ($trim === '') {
        return '<div class="alert alert-warning">Sem conteúdo retornado.</div>';
    }

    if (preg_match('/<\s*(p|div|h1|h2|h3|h4|ul|ol|table|section|article|strong|em|br)\b/i', $trim)) {
        return $trim;
    }

    $json = json_decode($trim, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        return json_to_html($json);
    }

    return markdown_to_html($trim);
}

function markdown_to_html(string $text): string
{
    $lines = preg_split('/\R/', $text) ?: [];
    $html = '';
    $inUl = false;
    $inOl = false;

    foreach ($lines as $line) {
        $line = rtrim($line);

        if (trim($line) === '') {
            if ($inUl) {
                $html .= '</ul>';
                $inUl = false;
            }
            if ($inOl) {
                $html .= '</ol>';
                $inOl = false;
            }
            continue;
        }

        if (preg_match('/^###\s+(.+)$/', $line, $m)) {
            if ($inUl) { $html .= '</ul>'; $inUl = false; }
            if ($inOl) { $html .= '</ol>'; $inOl = false; }
            $html .= '<h3>' . inline_markdown_to_html($m[1]) . '</h3>';
            continue;
        }

        if (preg_match('/^##\s+(.+)$/', $line, $m)) {
            if ($inUl) { $html .= '</ul>'; $inUl = false; }
            if ($inOl) { $html .= '</ol>'; $inOl = false; }
            $html .= '<h2>' . inline_markdown_to_html($m[1]) . '</h2>';
            continue;
        }

        if (preg_match('/^#\s+(.+)$/', $line, $m)) {
            if ($inUl) { $html .= '</ul>'; $inUl = false; }
            if ($inOl) { $html .= '</ol>'; $inOl = false; }
            $html .= '<h1>' . inline_markdown_to_html($m[1]) . '</h1>';
            continue;
        }

        if (preg_match('/^\s*[-•]\s+(.+)$/u', $line, $m)) {
            if ($inOl) {
                $html .= '</ol>';
                $inOl = false;
            }
            if (!$inUl) {
                $html .= '<ul>';
                $inUl = true;
            }
            $html .= '<li>' . inline_markdown_to_html($m[1]) . '</li>';
            continue;
        }

        if (preg_match('/^\s*\d+[\.)]\s+(.+)$/u', $line, $m)) {
            if ($inUl) {
                $html .= '</ul>';
                $inUl = false;
            }
            if (!$inOl) {
                $html .= '<ol>';
                $inOl = true;
            }
            $html .= '<li>' . inline_markdown_to_html($m[1]) . '</li>';
            continue;
        }

        if ($inUl) {
            $html .= '</ul>';
            $inUl = false;
        }

        if ($inOl) {
            $html .= '</ol>';
            $inOl = false;
        }

        $html .= '<p>' . inline_markdown_to_html($line) . '</p>';
    }

    if ($inUl) {
        $html .= '</ul>';
    }

    if ($inOl) {
        $html .= '</ol>';
    }

    return $html;
}

function inline_markdown_to_html(string $text): string
{
    $text = esc_html($text);

    $text = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $text) ?? $text;
    $text = preg_replace('/\*(.+?)\*/s', '<em>$1</em>', $text) ?? $text;
    $text = preg_replace('/`(.+?)`/s', '<code>$1</code>', $text) ?? $text;

    return $text;
}

function json_to_html($data): string
{
    if (!is_array($data)) {
        if ($data === null) {
            return '<span class="text-muted">null</span>';
        }
        return nl2br(esc_html((string)$data));
    }

    if ($data === []) {
        return '<div class="text-muted">Sem dados.</div>';
    }

    // Array de objetos/arrays associativos com mesmas chaves => tabela
    if (is_list_array($data) && all_items_are_assoc_arrays($data)) {
        $columns = collect_all_columns($data);

        $html = '<div class="table-responsive"><table class="table table-sm table-bordered table-striped">';
        $html .= '<thead><tr>';
        foreach ($columns as $col) {
            $html .= '<th>' . esc_html((string)$col) . '</th>';
        }
        $html .= '</tr></thead><tbody>';

        foreach ($data as $row) {
            $html .= '<tr>';
            foreach ($columns as $col) {
                $value = $row[$col] ?? null;
                $html .= '<td>' . json_cell_to_html($value) . '</td>';
            }
            $html .= '</tr>';
        }

        $html .= '</tbody></table></div>';
        return $html;
    }

    // Objeto associativo => tabela chave/valor
    if (is_assoc_array($data)) {
        $html = '<div class="table-responsive"><table class="table table-sm table-bordered table-striped">';
        $html .= '<thead><tr><th>Campo</th><th>Valor</th></tr></thead><tbody>';

        foreach ($data as $key => $value) {
            $html .= '<tr>';
            $html .= '<th style="width:260px;">' . esc_html((string)$key) . '</th>';
            $html .= '<td>' . json_cell_to_html($value) . '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table></div>';
        return $html;
    }

    // Lista simples
    $html = '<ul>';
    foreach ($data as $item) {
        $html .= '<li>' . json_cell_to_html($item) . '</li>';
    }
    $html .= '</ul>';

    return $html;
}

function json_cell_to_html($value): string
{
    if (is_array($value)) {
        return json_to_html($value);
    }

    if ($value === null) {
        return '<span class="text-muted">null</span>';
    }

    if (is_bool($value)) {
        return $value ? 'true' : 'false';
    }

    return nl2br(esc_html((string)$value));
}

function is_assoc_array(array $arr): bool
{
    return array_keys($arr) !== range(0, count($arr) - 1);
}

function is_list_array(array $arr): bool
{
    return array_keys($arr) === range(0, count($arr) - 1);
}

function all_items_are_assoc_arrays(array $rows): bool
{
    foreach ($rows as $row) {
        if (!is_array($row) || !is_assoc_array($row)) {
            return false;
        }
    }
    return $rows !== [];
}

function collect_all_columns(array $rows): array
{
    $cols = [];
    foreach ($rows as $row) {
        foreach (array_keys($row) as $key) {
            $cols[$key] = true;
        }
    }
    return array_keys($cols);
}