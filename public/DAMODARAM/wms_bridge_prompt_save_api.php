<?php
declare(strict_types=1);

use App\Diagnostico\VersionedResponseRepository;
use App\Infra\Database;
use Dompdf\Dompdf;
use Dompdf\Options;

ob_start();
header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/_common.php';

    $autoloadCandidates = [
        dirname(__DIR__, 2) . '/vendor/autoload.php',
        dirname(__DIR__, 3) . '/vendor/autoload.php',
    ];

    $autoloadLoaded = false;
    foreach ($autoloadCandidates as $autoloadFile) {
        if (is_file($autoloadFile)) {
            require_once $autoloadFile;
            $autoloadLoaded = true;
            break;
        }
    }

    if (!isset($app) || !is_array($app) || !isset($app['db'])) {
        throw new RuntimeException('App não inicializado corretamente.');
    }

    /** @var Database $db */
    $db = $app['db'];
    if (!$db instanceof Database) {
        throw new RuntimeException('Serviço de banco inválido.');
    }

    if (!$autoloadLoaded) {
        throw new RuntimeException(
            'Autoload do Composer não encontrado. Verifique vendor/autoload.php no projeto.'
        );
    }

    if (!class_exists(Dompdf::class)) {
        throw new RuntimeException(
            'Dompdf não carregado pelo autoload. Verifique se "dompdf/dompdf" foi instalado no projeto atual.'
        );
    }

    $auth = $app['auth'] ?? null;
    if (!is_object($auth)) {
        throw new RuntimeException('Autenticação indisponível.');
    }

    $authUser = $auth->user();
    $emailUser = trim((string)($authUser->email ?? ''));
    $fallbackUser = trim((string)($authUser->name ?? $authUser->user ?? $emailUser));

    $versionId = (int)($_POST['version_id'] ?? 0);
    $year = (int)($_POST['year'] ?? 2024);
    $industry = trim((string)($_POST['industry'] ?? 'Advertising'));
    $htmlBody = trim((string)($_POST['html'] ?? ''));

    if ($versionId <= 0) {
        throw new RuntimeException('Questionário não informado.');
    }
    if ($industry === '') {
        throw new RuntimeException('Indústria não informada.');
    }
    if ($htmlBody === '') {
        throw new RuntimeException('Conteúdo HTML não informado.');
    }

    $mainPdo = $db->pdo();

    $repo = new VersionedResponseRepository($mainPdo);
    $repo->ensureSchema();
    $version = $repo->versionById($versionId, $emailUser);

    if (!$version) {
        throw new RuntimeException('Questionário selecionado não encontrado.');
    }

    $companyName = trim((string)($version['company_name'] ?? ''));
    $emailResp = trim((string)($version['email_resp'] ?? $emailUser));
    $responseDateTime = trim((string)($version['response_datetime'] ?? ''));

    if ($companyName === '' || $responseDateTime === '') {
        throw new RuntimeException('Dados do questionário insuficientes para salvar.');
    }

    [$questionUser, $questionEmailUser, $exactResponseDateTime] = resolve_questionnaire_identity(
        $mainPdo,
        $fallbackUser,
        $emailUser,
        $companyName,
        $emailResp,
        $responseDateTime
    );

    $fullHtml = build_pdf_html_document($companyName, $industry, $year, $htmlBody);

    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', true);
    $options->set('defaultFont', 'DejaVu Sans');

    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($fullHtml, 'UTF-8');
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    $pdfBytes = $dompdf->output();
    if ($pdfBytes === '') {
        throw new RuntimeException('Falha ao gerar o PDF.');
    }

    $sql = "
        INSERT INTO status_questionario (
            user,
            email_user,
            response_datetime,
            prompts_analiticos,
            arquivo_damodaram
        ) VALUES (
            :user,
            :email_user,
            :response_datetime,
            :prompts_analiticos,
            :arquivo_damodaram
        )
        ON DUPLICATE KEY UPDATE
            prompts_analiticos = VALUES(prompts_analiticos),
            arquivo_damodaram = VALUES(arquivo_damodaram)
    ";

    $st = $mainPdo->prepare($sql);
    $st->bindValue(':user', $questionUser !== '' ? $questionUser : $fallbackUser, PDO::PARAM_STR);
    $st->bindValue(':email_user', $questionEmailUser !== '' ? $questionEmailUser : $emailUser, PDO::PARAM_STR);
    $st->bindValue(':response_datetime', $exactResponseDateTime, PDO::PARAM_STR);
    $st->bindValue(':prompts_analiticos', $htmlBody, PDO::PARAM_STR);
    $st->bindValue(':arquivo_damodaram', $pdfBytes, PDO::PARAM_LOB);
    $st->execute();

    $fileName = build_pdf_filename($companyName, $industry, $year);

    $buffer = trim((string)ob_get_clean());
    if ($buffer !== '') {
        throw new RuntimeException($buffer);
    }

    echo json_encode([
        'ok' => true,
        'message' => 'Resultado salvo com sucesso.',
        'filename' => $fileName,
        'pdf_base64' => base64_encode($pdfBytes),
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

function resolve_questionnaire_identity(
    PDO $pdo,
    string $fallbackUser,
    string $fallbackEmailUser,
    string $companyName,
    string $emailResp,
    string $responseDateTime
): array {
    $sessMin = substr($responseDateTime, 0, 16);

    $sql = "
        SELECT
            user,
            email_user,
            response_datetime
        FROM responses_detailed
        WHERE company_name = :company_name
          AND email_resp = :email_resp
          AND DATE_FORMAT(response_datetime, '%Y-%m-%d %H:%i') = :sess_min
        ORDER BY id
        LIMIT 1
    ";

    $st = $pdo->prepare($sql);
    $st->execute([
        ':company_name' => $companyName,
        ':email_resp' => $emailResp,
        ':sess_min' => $sessMin,
    ]);

    $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];

    $user = trim((string)($row['user'] ?? $fallbackUser));
    $emailUser = trim((string)($row['email_user'] ?? $fallbackEmailUser));
    $exactDateTime = trim((string)($row['response_datetime'] ?? $responseDateTime));

    return [$user, $emailUser, $exactDateTime];
}

function build_pdf_html_document(string $companyName, string $industry, int $year, string $bodyHtml): string
{
    $companyEsc = htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8');
    $industryEsc = htmlspecialchars($industry, ENT_QUOTES, 'UTF-8');

    return <<<HTML
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Diagnóstico Damodaran</title>
<style>
    body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; color: #222; }
    .header { margin-bottom: 20px; border-bottom: 1px solid #ccc; padding-bottom: 10px; }
    .header h1 { margin: 0 0 6px 0; font-size: 20px; }
    .header .meta { font-size: 11px; color: #555; }
    h1, h2, h3 { color: #1f3b64; }
    h2 { margin-top: 20px; font-size: 16px; }
    h3 { margin-top: 14px; font-size: 14px; }
    p { line-height: 1.45; margin: 8px 0; }
    ul, ol { margin: 8px 0 8px 20px; }
    hr { border: 0; border-top: 1px solid #ddd; margin: 18px 0; }
    table { border-collapse: collapse; width: 100%; margin: 10px 0; }
    th, td { border: 1px solid #ccc; padding: 6px; vertical-align: top; }
    .json-block { font-size: 11px; }
</style>
</head>
<body>
    <div class="header">
        <h1>Diagnóstico Damodaran</h1>
        <div class="meta">
            <strong>Empresa:</strong> {$companyEsc}
            &nbsp; | &nbsp;
            <strong>Industria:</strong> {$industryEsc}
            &nbsp; | &nbsp;
            <strong>Año:</strong> {$year}
        </div>
    </div>
    {$bodyHtml}
</body>
</html>
HTML;
}

function build_pdf_filename(string $companyName, string $industry, int $year): string
{
    $base = $companyName . '_' . $industry . '_' . $year . '_damodaran';
    $base = preg_replace('/[^A-Za-z0-9_\-]+/', '_', $base) ?? 'damodaran';
    $base = trim($base, '_');

    return ($base !== '' ? $base : 'damodaran') . '.pdf';
}