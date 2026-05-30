<?php
require __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Método inválido');
}
check_csrf();

function infer_source_type_from_value(string $value): ?string {
    $v = trim($value);
    if ($v === '') return null;
    if (preg_match('~^https?://~i', $v)) return 'url';
    if (preg_match('~^(file-[A-Za-z0-9_-]+)$~', $v)) return 'openai_file_id';
    if (preg_match('~^[A-Za-z]:[\\/]~', $v) || str_starts_with($v, '/') || str_starts_with($v, '\\\\')) return 'local_path';
    return 'relative_path';
}

function normalize_source_type(?string $type): ?string {
    $type = trim((string)$type);
    if ($type === '') return null;
    $allowed = ['url', 'relative_path', 'local_path', 'cloud_path', 'openai_file_id'];
    return in_array($type, $allowed, true) ? $type : null;
}

function infer_mime_from_name(string $name): ?string {
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    return match ($ext) {
        'pdf'  => 'application/pdf',
        'doc'  => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'txt'  => 'text/plain',
        'md'   => 'text/markdown',
        'csv'  => 'text/csv',
        'xls'  => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt'  => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'json' => 'application/json',
        'xml'  => 'application/xml',
        'html' => 'text/html',
        default => null,
    };
}

$id = (int)($_POST['id'] ?? 0);
$title = trim($_POST['title'] ?? '');
$journal = trim($_POST['journal'] ?? '');
$keywords = trim($_POST['keywords'] ?? '');
$key_insight = trim($_POST['key_insight'] ?? '');
$citation_count = (int)($_POST['citation_count'] ?? 0);
$prompt_code = trim($_POST['prompt_code'] ?? '');
$chapter_code = trim($_POST['chapter_code'] ?? '');
$link_url = trim($_POST['link_url'] ?? '');
$file_source_type = normalize_source_type($_POST['file_source_type'] ?? null);
$file_source_value = trim($_POST['file_source_value'] ?? '');
$file_enabled = isset($_POST['file_enabled']) ? 1 : 0;
$file_preferred_name = trim($_POST['file_preferred_name'] ?? '');
$file_preferred_mime = trim($_POST['file_preferred_mime'] ?? '');

if ($title === '' || $journal === '') {
    die('Título e Journal são obrigatórios.');
}

// Compatibilidade com o fluxo antigo:
// se o usuário só preencher link_url, os novos campos são inferidos automaticamente.
if ($file_source_value === '' && $link_url !== '') {
    $file_source_value = $link_url;
}
if ($file_source_type === null && $file_source_value !== '') {
    $file_source_type = infer_source_type_from_value($file_source_value);
}
if ($file_preferred_name === '' && $file_source_value !== '') {
    $file_preferred_name = basename(str_replace('\\', '/', $file_source_value));
}
if ($file_preferred_mime === '' && $file_preferred_name !== '') {
    $file_preferred_mime = infer_mime_from_name($file_preferred_name) ?? '';
}

$resolvedAt = ($file_source_value !== '') ? date('Y-m-d H:i:s') : null;

if ($id) {
    $sql = "UPDATE papers
               SET title=?,
                   journal=?,
                   key_insight=?,
                   citation_count=?,
                   keywords=?,
                   link_url=?,
                   file_source_type=?,
                   file_source_value=?,
                   file_enabled=?,
                   file_preferred_name=?,
                   file_preferred_mime=?,
                   file_last_resolved_at=?,
                   prompt_code=?,
                   chapter_code=?
             WHERE id=?";
    $pdo->prepare($sql)->execute([
        $title,
        $journal,
        $key_insight,
        $citation_count,
        $keywords,
        $link_url !== '' ? $link_url : null,
        $file_source_type,
        $file_source_value !== '' ? $file_source_value : null,
        $file_enabled,
        $file_preferred_name !== '' ? $file_preferred_name : null,
        $file_preferred_mime !== '' ? $file_preferred_mime : null,
        $resolvedAt,
        $prompt_code !== '' ? $prompt_code : null,
        $chapter_code !== '' ? $chapter_code : null,
        $id,
    ]);
} else {
    $sql = "INSERT INTO papers
                (title, journal, key_insight, citation_count, keywords, link_url,
                 file_source_type, file_source_value, file_enabled, file_preferred_name,
                 file_preferred_mime, file_last_resolved_at, prompt_code, chapter_code)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
    $pdo->prepare($sql)->execute([
        $title,
        $journal,
        $key_insight,
        $citation_count,
        $keywords,
        $link_url !== '' ? $link_url : null,
        $file_source_type,
        $file_source_value !== '' ? $file_source_value : null,
        $file_enabled,
        $file_preferred_name !== '' ? $file_preferred_name : null,
        $file_preferred_mime !== '' ? $file_preferred_mime : null,
        $resolvedAt,
        $prompt_code !== '' ? $prompt_code : null,
        $chapter_code !== '' ? $chapter_code : null,
    ]);
}

header('Location: papers_index.php');
exit;
