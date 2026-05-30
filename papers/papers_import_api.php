<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
@set_time_limit(0);
ini_set('memory_limit', '512M');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require __DIR__ . '/config.php';

if (!isset($pdo) || !$pdo instanceof PDO) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'PDO não disponível via config.php'], JSON_UNESCAPED_UNICODE);
    exit;
}

$BASE_PATH = 'C:\\xampp\\htdocs\\Produtividade_emp\\Bibliografia\\upload\\CR y R';
$ALLOWED_EXT = ['pdf', 'doc', 'docx', 'txt', 'xls', 'xlsx', 'xlsm', 'ppt', 'pptx'];

function normalize_slashes(string $path): string {
    return str_replace('\\', '/', $path);
}

function normalize_local_path(string $path): string {
    $path = trim($path);
    $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
    $path = preg_replace('#' . preg_quote(DIRECTORY_SEPARATOR, '#') . '+#', DIRECTORY_SEPARATOR, $path);
    return (string)$path;
}

function iter_files(string $basePath, array $allowedExt): array {
    $files = [];
    if (!is_dir($basePath)) return $files;

    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($basePath, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($it as $fileInfo) {
        if (!$fileInfo->isFile()) continue;
        $ext = strtolower((string)$fileInfo->getExtension());
        if ($allowedExt && !in_array($ext, $allowedExt, true)) continue;
        $files[] = $fileInfo->getPathname();
    }

    sort($files, SORT_NATURAL | SORT_FLAG_CASE);
    return $files;
}

function get_relative_path(string $basePath, string $filePath): string {
    $baseNorm = rtrim(normalize_slashes($basePath), '/');
    $fileNorm = normalize_slashes($filePath);

    if (stripos($fileNorm, $baseNorm) === 0) {
        return ltrim(substr($fileNorm, strlen($baseNorm)), '/');
    }

    return basename($fileNorm);
}

function detect_mime_type_safe(string $filePath): ?string {
    if (!is_file($filePath)) return null;

    if (function_exists('mime_content_type')) {
        $mime = @mime_content_type($filePath);
        if (is_string($mime) && trim($mime) !== '') {
            return trim($mime);
        }
    }

    $ext = strtolower((string)pathinfo($filePath, PATHINFO_EXTENSION));
    $map = [
        'pdf'  => 'application/pdf',
        'doc'  => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'txt'  => 'text/plain',
        'xls'  => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'xlsm' => 'application/vnd.ms-excel.sheet.macroEnabled.12',
        'ppt'  => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    ];
    return $map[$ext] ?? null;
}

function file_to_paper_row(string $basePath, string $filePath): array {
    $filePathNorm = normalize_slashes($filePath);
    $basename = basename($filePathNorm);
    $title = pathinfo($basename, PATHINFO_FILENAME);
    $parentDir = basename(dirname($filePathNorm));
    $journal = $parentDir !== '' ? $parentDir : 'Desconhecido';
    $relativePath = get_relative_path($basePath, $filePath);
    $mime = detect_mime_type_safe($filePath);

    return [
        'title'                 => mb_substr($title, 0, 512),
        'journal'               => mb_substr($journal, 0, 255),
        'key_insight'           => null,
        'citation_count'        => 0,
        'keywords'              => null,
        'link_url'              => mb_substr($relativePath, 0, 1000),
        'file_source_type'      => 'relative_path',
        'file_source_value'     => trim($relativePath),
        'file_enabled'          => 1,
        'file_preferred_name'   => mb_substr($basename, 0, 255),
        'file_preferred_mime'   => $mime ? mb_substr($mime, 0, 150) : null,
        'file_last_resolved_at' => date('Y-m-d H:i:s'),
        'prompt_code'           => null,
        'chapter_code'          => null,
    ];
}

function upsert_paper(PDO $pdo, array $paper): string {
    $existing = null;

    if (!empty($paper['file_source_type']) && !empty($paper['file_source_value'])) {
        $stmt = $pdo->prepare("
            SELECT * FROM papers
            WHERE file_source_type = :file_source_type
              AND file_source_value = :file_source_value
            LIMIT 1
        ");
        $stmt->execute([
            ':file_source_type'  => $paper['file_source_type'],
            ':file_source_value' => $paper['file_source_value'],
        ]);
        $existing = $stmt->fetch();
    }

    if (!$existing && !empty($paper['link_url'])) {
        $stmt = $pdo->prepare("SELECT * FROM papers WHERE link_url = :link_url LIMIT 1");
        $stmt->execute([':link_url' => $paper['link_url']]);
        $existing = $stmt->fetch();
    }

    if ($existing && isset($existing['id'])) {
        $stmt = $pdo->prepare("
            UPDATE papers
               SET title = :title,
                   journal = :journal,
                   link_url = :link_url,
                   file_source_type = :file_source_type,
                   file_source_value = :file_source_value,
                   file_enabled = :file_enabled,
                   file_preferred_name = :file_preferred_name,
                   file_preferred_mime = :file_preferred_mime,
                   file_last_resolved_at = :file_last_resolved_at
             WHERE id = :id
        ");
        $stmt->execute([
            ':title'                 => $paper['title'],
            ':journal'               => $paper['journal'],
            ':link_url'              => $paper['link_url'],
            ':file_source_type'      => $paper['file_source_type'],
            ':file_source_value'     => $paper['file_source_value'],
            ':file_enabled'          => $paper['file_enabled'],
            ':file_preferred_name'   => $paper['file_preferred_name'],
            ':file_preferred_mime'   => $paper['file_preferred_mime'],
            ':file_last_resolved_at' => $paper['file_last_resolved_at'],
            ':id'                    => (int)$existing['id'],
        ]);
        return 'updated';
    }

    $stmt = $pdo->prepare("
        INSERT INTO papers (
            title, journal, key_insight, citation_count, keywords, link_url,
            file_source_type, file_source_value, file_enabled, file_preferred_name,
            file_preferred_mime, file_last_resolved_at, prompt_code, chapter_code
        ) VALUES (
            :title, :journal, :key_insight, :citation_count, :keywords, :link_url,
            :file_source_type, :file_source_value, :file_enabled, :file_preferred_name,
            :file_preferred_mime, :file_last_resolved_at, :prompt_code, :chapter_code
        )
    ");
    $stmt->execute([
        ':title'                 => $paper['title'],
        ':journal'               => $paper['journal'],
        ':key_insight'           => $paper['key_insight'],
        ':citation_count'        => $paper['citation_count'],
        ':keywords'              => $paper['keywords'],
        ':link_url'              => $paper['link_url'],
        ':file_source_type'      => $paper['file_source_type'],
        ':file_source_value'     => $paper['file_source_value'],
        ':file_enabled'          => $paper['file_enabled'],
        ':file_preferred_name'   => $paper['file_preferred_name'],
        ':file_preferred_mime'   => $paper['file_preferred_mime'],
        ':file_last_resolved_at' => $paper['file_last_resolved_at'],
        ':prompt_code'           => $paper['prompt_code'],
        ':chapter_code'          => $paper['chapter_code'],
    ]);
    return 'created';
}

function build_candidate_paths(array $paperRow, string $basePath): array {
    $sourceType  = trim((string)($paperRow['file_source_type'] ?? ''));
    $sourceValue = trim((string)($paperRow['file_source_value'] ?? ''));
    $linkUrl     = trim((string)($paperRow['link_url'] ?? ''));

    $basePath = rtrim(normalize_local_path($basePath), "\\/");
    $candidates = [];

    if ($sourceType === 'relative_path' && $sourceValue !== '') {
        $rel = normalize_local_path($sourceValue);
        $candidates[] = $basePath . DIRECTORY_SEPARATOR . ltrim($rel, "\\/");
    }

    if (($sourceType === 'local_path' || $sourceType === 'cloud_path') && $sourceValue !== '') {
        $candidates[] = normalize_local_path($sourceValue);
    }

    if ($linkUrl !== '' && !preg_match('~^https?://~i', $linkUrl)) {
        $rel = normalize_local_path($linkUrl);
        $candidates[] = $basePath . DIRECTORY_SEPARATOR . ltrim($rel, "\\/");
    }

    // variações com trim extra para dados antigos/importações imperfeitas
    foreach ([$sourceValue, $linkUrl] as $value) {
        $value = trim((string)$value);
        if ($value === '' || preg_match('~^https?://~i', $value)) {
            continue;
        }
        $rel = normalize_local_path($value);
        $candidates[] = $basePath . DIRECTORY_SEPARATOR . ltrim($rel, "\\/");
    }

    $normalized = [];
    foreach ($candidates as $p) {
        $p = normalize_local_path($p);
        if ($p !== '' && !in_array($p, $normalized, true)) {
            $normalized[] = $p;
        }
    }

    return $normalized;
}

function resolve_paper_full_path(array $paperRow, string $basePath): ?string {
    $candidates = build_candidate_paths($paperRow, $basePath);

    foreach ($candidates as $path) {
        if (is_file($path)) {
            return $path;
        }
        $rp = @realpath($path);
        if ($rp !== false && is_file($rp)) {
            return $rp;
        }
    }

    error_log('[papers] id=' . (string)($paperRow['id'] ?? ''));
    error_log('[papers] title=' . (string)($paperRow['title'] ?? ''));
    error_log('[papers] sourceType=' . trim((string)($paperRow['file_source_type'] ?? '')));
    error_log('[papers] sourceValue=' . trim((string)($paperRow['file_source_value'] ?? '')));
    error_log('[papers] linkUrl=' . trim((string)($paperRow['link_url'] ?? '')));
    error_log('[papers] basePath=' . $basePath);
    error_log('[papers] candidates=' . json_encode($candidates, JSON_UNESCAPED_UNICODE));

    return null;
}

function read_docx_text(string $fullPath): string {
    if (!class_exists('ZipArchive')) return '';

    $zip = new ZipArchive();
    if ($zip->open($fullPath) !== true) return '';

    $xml = $zip->getFromName('word/document.xml');
    $zip->close();

    return $xml !== false ? trim(strip_tags($xml)) : '';
}

function read_sheet_text(string $fullPath): string {
    if (!class_exists(\PhpOffice\PhpSpreadsheet\IOFactory::class)) return '';

    try {
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($fullPath);
        $lines = [];

        foreach ($spreadsheet->getWorksheetIterator() as $ws) {
            $lines[] = '### Aba: ' . $ws->getTitle();
            $rows = $ws->toArray(null, true, true, true);
            $max = min(count($rows), 200);
            $i = 0;

            foreach ($rows as $row) {
                if ($i++ >= $max) break;
                $lines[] = implode(' | ', array_map(static fn($v): string => trim((string)$v), $row));
            }
        }

        return trim(implode("\n", $lines));
    } catch (Throwable $e) {
        return '';
    }
}

function read_pdf_text(string $fullPath): string {
    if (class_exists(\Smalot\PdfParser\Parser::class)) {
        try {
            $parser = new \Smalot\PdfParser\Parser();
            $pdf = $parser->parseFile($fullPath);
            return trim((string)$pdf->getText());
        } catch (Throwable $e) {
        }
    }
    return '';
}

function read_file_content(string $fullPath, array $paperRow): string {
    $ext = strtolower((string)pathinfo($fullPath, PATHINFO_EXTENSION));
    $content = '';

    if ($ext === 'txt') {
        $content = (string)@file_get_contents($fullPath);
    } elseif ($ext === 'docx') {
        $content = read_docx_text($fullPath);
    } elseif (in_array($ext, ['xlsx', 'xls', 'xlsm'], true)) {
        $content = read_sheet_text($fullPath);
    } elseif ($ext === 'pdf') {
        $content = read_pdf_text($fullPath);
    }

    if (trim($content) === '') {
        $content = "Título: " . (string)($paperRow['title'] ?? '') . "\n"
                 . "Journal: " . (string)($paperRow['journal'] ?? '') . "\n"
                 . "Arquivo: " . basename($fullPath) . "\n";
    }

    return mb_substr($content, 0, 12000);
}

function call_openai_keywords(string $title, string $journal, string $content): array {
    $apiKey = defined('OPENAI_API_KEY') ? OPENAI_API_KEY : getenv('OPENAI_API_KEY');
    if (!$apiKey) {
        return ['ok' => false, 'error' => 'OPENAI_API_KEY não definida.'];
    }

    $system = <<<SYS
Você é um assistente especialista em análise de artigos acadêmicos e relatórios de negócios.
Sua tarefa é extrair:
- uma lista curta de palavras-chave relevantes (no idioma do texto)
- um parágrafo curto de insight principal (key_insight)
Retorne APENAS um JSON no formato:
{"keywords":"lista, separada, por, virgulas","key_insight":"texto curto"}
SYS;

    $user = "Título: {$title}\nJournal / pasta: {$journal}\n\nConteúdo:\n\n{$content}";

    $payload = [
        'model' => 'gpt-4.1-mini',
        'messages' => [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $user],
        ],
        'temperature' => 0.3,
    ];

    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ],
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT        => 90,
    ]);

    $respBody = curl_exec($ch);
    $err      = curl_error($ch);
    $status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($respBody === false || $err) {
        return ['ok' => false, 'error' => 'Erro cURL: ' . ($err ?: 'desconhecido')];
    }

    $json = json_decode($respBody, true);
    if (!is_array($json) || empty($json['choices'][0]['message']['content'])) {
        return ['ok' => false, 'error' => "Resposta inesperada da OpenAI (HTTP {$status})"];
    }

    $contentStr = trim((string)$json['choices'][0]['message']['content']);
    $contentStr = preg_replace('/^```json/i', '', $contentStr);
    $contentStr = preg_replace('/^```/i', '', $contentStr);
    $contentStr = preg_replace('/```$/', '', $contentStr);
    $parsed = json_decode(trim($contentStr), true);

    if (!is_array($parsed)) {
        return ['ok' => false, 'error' => 'Não consegui interpretar o JSON retornado pela IA.'];
    }

    $keywords = trim((string)($parsed['keywords'] ?? ''));
    $keyInsight = trim((string)($parsed['key_insight'] ?? ''));

    if ($keywords === '' && $keyInsight === '') {
        return ['ok' => false, 'error' => 'JSON retornado sem keywords / key_insight.'];
    }

    return ['ok' => true, 'keywords' => $keywords, 'key_insight' => $keyInsight];
}

function ai_enrich_paper(PDO $pdo, array $paperRow, string $basePath): array {
    $fullPath = resolve_paper_full_path($paperRow, $basePath);

    if ($fullPath === null) {
        return [
            'ok' => false,
            'error' => 'Arquivo não encontrado para este registro.',
            'debug' => [
                'id' => $paperRow['id'] ?? null,
                'title' => $paperRow['title'] ?? null,
                'file_source_type' => $paperRow['file_source_type'] ?? null,
                'file_source_value' => $paperRow['file_source_value'] ?? null,
                'link_url' => $paperRow['link_url'] ?? null,
                'base_path' => $basePath,
                'candidates' => build_candidate_paths($paperRow, $basePath),
            ]
        ];
    }

    $content = read_file_content($fullPath, $paperRow);
    $title = (string)($paperRow['title'] ?? '');
    $journal = (string)($paperRow['journal'] ?? '');

    $ai = call_openai_keywords($title, $journal, $content);
    if (!$ai['ok']) {
        return ['ok' => false, 'error' => $ai['error'] ?? 'Falha ao chamar IA.'];
    }

    $stmt = $pdo->prepare("
        UPDATE papers
           SET keywords = :keywords,
               key_insight = :key_insight,
               citation_count = :citation_count,
               file_last_resolved_at = NOW()
         WHERE id = :id
    ");
    $stmt->execute([
        ':keywords'       => $ai['keywords'] ?: null,
        ':key_insight'    => $ai['key_insight'] ?: null,
        ':citation_count' => (int)($paperRow['citation_count'] ?? 0),
        ':id'             => (int)$paperRow['id'],
    ]);

    return [
        'ok'             => true,
        'keywords'       => $ai['keywords'],
        'key_insight'    => $ai['key_insight'],
        'citation_count' => (int)($paperRow['citation_count'] ?? 0),
        'resolved_path'  => $fullPath,
    ];
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($action === 'import') {
    try {
        $files = iter_files($BASE_PATH, $ALLOWED_EXT);
        $created = 0;
        $updated = 0;

        foreach ($files as $filePath) {
            $status = upsert_paper($pdo, file_to_paper_row($BASE_PATH, $filePath));
            if ($status === 'created') $created++;
            if ($status === 'updated') $updated++;
        }

        echo json_encode([
            'ok' => true,
            'created' => $created,
            'updated' => $updated,
            'total_files' => count($files),
        ], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

if ($action === 'run_ai') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    if ($id <= 0) {
        echo json_encode(['ok' => false, 'error' => 'ID inválido.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT * FROM papers WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        if (!$row) {
            echo json_encode(['ok' => false, 'error' => 'Registro não encontrado.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $ai = ai_enrich_paper($pdo, $row, $BASE_PATH);
        if (!$ai['ok']) {
            echo json_encode($ai, JSON_UNESCAPED_UNICODE);
            exit;
        }

        echo json_encode([
            'ok' => true,
            'keywords' => $ai['keywords'],
            'key_insight' => $ai['key_insight'],
            'citation_count' => $ai['citation_count'],
            'resolved_path' => $ai['resolved_path'] ?? null,
            'paper' => [
                'id' => $id,
                'keywords' => $ai['keywords'],
                'key_insight' => $ai['key_insight'],
            ],
        ], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}


if ($action === 'get_cache') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    if ($id <= 0) {
        echo json_encode(['ok' => false, 'error' => 'ID inválido.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT *
            FROM papers_file_cache
            WHERE paper_id = :paper_id
            LIMIT 1
        ");
        $stmt->execute([':paper_id' => $id]);
        $cache = $stmt->fetch(PDO::FETCH_ASSOC);

        echo json_encode([
            'ok' => true,
            'cache' => $cache ?: null,
        ], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        echo json_encode([
            'ok' => false,
            'error' => $e->getMessage(),
        ], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

http_response_code(400);
echo json_encode(['ok' => false, 'error' => 'Ação inválida.'], JSON_UNESCAPED_UNICODE);
exit;
