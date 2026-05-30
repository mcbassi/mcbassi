<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require dirname(__DIR__, 2) . '/app/bootstrap/app.php';

use App\AI\PromptExecutionService;
use App\Diagnostico\VersionedResponseRepository;
use App\Infra\Database;
use App\Papers\PaperFileService;
use App\Prompts\PromptRepository;
use App\Prompts\PromptRuntimeService;

try {
    /** @var array $app */
    $app = require dirname(__DIR__, 2) . '/app/bootstrap/app.php';
    if (!isset($app['auth']) || !is_object($app['auth'])) {
        throw new RuntimeException('Autenticação indisponível.');
    }
    $app['auth']->requireAuth();
    $user = $app['auth']->user();
    $emailUser = trim((string)($user->email ?? ''));

    /** @var Database $db */
    $db = $app['db'];
    if (!$db instanceof Database) {
        throw new RuntimeException('Serviço de banco inválido.');
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
    $sqlJson = json_encode($sqlRows[0], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    if (!is_string($sqlJson)) {
        throw new RuntimeException('Falha ao serializar os dados do benchmark.');
    }

    $bibliography = 'Usar contexto bibliográfico amplo disponível no sistema; sem restrição temática nesta execução.';

    $promptNames = [
        'Diagnóstico Ejecutivo' => 'prompt de diagnóstico executivo',
        'Análisis Profundo Consultivo' => 'prompt de análise profunda consultiva',
        'Agenda de Acción y Prioridades' => 'prompt de agenda de ação e prioridades',
    ];

    $promptRepo = new PromptRepository($db);
    $runtimeService = new PromptRuntimeService($db, $promptRepo);
    $execService = new PromptExecutionService($db, $promptRepo, $runtimeService, new PaperFileService());

    $htmlParts = [];
    foreach ($promptNames as $sectionTitle => $promptLookup) {
        $promptRow = find_prompt_any($db->pdo(), $promptLookup);
        if (!$promptRow) {
            throw new RuntimeException('Prompt não encontrado: ' . $promptLookup);
        }
        $promptText = (string)($promptRow['prompt'] ?? '');
        if ($promptText === '') {
            throw new RuntimeException('Prompt vazio: ' . $promptLookup);
        }

        $map = build_placeholder_map([
            'company_name' => $companyName,
            'email_resp' => $emailResp,
            'sess_min' => $sessMin,
            'metric_year' => (string)$year,
            'industry_name' => $industry,
            'SQL_RESULT_JSON' => $sqlJson,
            'BIBLIO_CONTEXT' => $bibliography,
        ]);
        $resolvedPrompt = strtr($promptText, $map);

        $execution = $execService->execute([
            'module' => 'damodaran_wms_bridge',
            'prompt_name' => (string)($promptRow['assistente'] ?? $promptLookup),
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

    $html = '<div class="dam-prompts-result">' . implode('<hr style="margin:24px 0;">', $htmlParts) . '</div>';
    echo json_encode(['ok' => true, 'html' => $html], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function find_prompt_any(PDO $pdo, string $name): ?array {
    $sql = "
        SELECT *
        FROM prompts
        WHERE LOWER(TRIM(assistente)) = LOWER(TRIM(?))
           OR LOWER(TRIM(funcao)) = LOWER(TRIM(?))
           OR LOWER(TRIM(descricao)) = LOWER(TRIM(?))
        ORDER BY id DESC
        LIMIT 1
    ";
    $st = $pdo->prepare($sql);
    $st->execute([$name, $name, $name]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function build_placeholder_map(array $values): array {
    $map = [];
    foreach ($values as $key => $value) {
        $string = (string)$value;
        $map['{{' . $key . '}}'] = $string;
        $map['<<' . $key . '>>'] = $string;
    }
    return $map;
}

function esc_html(string $text): string {
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

function response_to_html(string $text): string {
    $trim = trim($text);
    if ($trim === '') {
        return '<div class="alert alert-warning">Sem conteúdo retornado.</div>';
    }
    if (preg_match('/<\s*(p|div|h1|h2|h3|ul|ol|table|section)\b/i', $trim)) {
        return $trim;
    }
    $json = json_decode($trim, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        return json_to_html($json);
    }
    $paragraphs = preg_split('/\R\R+/', $trim) ?: [$trim];
    $html = '';
    foreach ($paragraphs as $p) {
        $lines = preg_split('/\R/', trim($p)) ?: [];
        $listItems = [];
        $normal = [];
        foreach ($lines as $line) {
            if (preg_match('/^\s*[-•]\s+(.+)$/u', $line, $m) || preg_match('/^\s*\d+[\.)]\s+(.+)$/u', $line, $m)) {
                $listItems[] = esc_html(trim($m[1]));
            } else {
                $normal[] = esc_html(trim($line));
            }
        }
        if ($normal !== []) {
            $html .= '<p>' . implode('<br>', array_filter($normal, fn($x) => $x !== '')) . '</p>';
        }
        if ($listItems !== []) {
            $html .= '<ul>';
            foreach ($listItems as $item) {
                $html .= '<li>' . $item . '</li>';
            }
            $html .= '</ul>';
        }
    }
    return $html;
}

function json_to_html($data): string {
    if (is_array($data)) {
        $isAssoc = array_keys($data) !== range(0, count($data) - 1);
        if ($isAssoc) {
            $html = '<div class="json-block">';
            foreach ($data as $key => $value) {
                $html .= '<div style="margin-bottom:10px;"><strong>' . esc_html((string)$key) . ':</strong> ' . json_to_html($value) . '</div>';
            }
            $html .= '</div>';
            return $html;
        }
        $html = '<ul>';
        foreach ($data as $item) {
            $html .= '<li>' . json_to_html($item) . '</li>';
        }
        $html .= '</ul>';
        return $html;
    }
    if ($data === null) {
        return '<span class="text-muted">null</span>';
    }
    return nl2br(esc_html((string)$data));
}
