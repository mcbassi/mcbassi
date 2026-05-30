<?php
declare(strict_types=1);

function ppt_root_path(): string {
    return dirname(__DIR__);
}

function ppt_load_config(): array {
    $cfgFile = ppt_root_path() . DIRECTORY_SEPARATOR . 'config_ppt.php';
    if (!is_file($cfgFile)) {
        throw new RuntimeException('Arquivo config_ppt.php não encontrado. Copie config_ppt.example.php para config_ppt.php e ajuste os parâmetros.');
    }
    /** @var array $cfg */
    $cfg = require $cfgFile;
    if (!is_array($cfg)) {
        throw new RuntimeException('config_ppt.php deve retornar um array.');
    }
    return $cfg;
}

function ppt_json_out(array $data, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}



function ppt_is_assoc(array $array): bool {
    return $array !== [] && array_keys($array) !== range(0, count($array) - 1);
}

function ppt_runtime_id(): string {
    try {
        return date('Ymd_His') . '_' . bin2hex(random_bytes(4));
    } catch (Throwable $e) {
        return date('Ymd_His') . '_' . substr(str_replace('.', '', uniqid('', true)), -8);
    }
}

function ppt_request_debug_enabled(): bool {
    $flag = (string)($_GET['debug'] ?? $_POST['debug'] ?? '');
    return in_array(strtolower($flag), ['1', 'true', 'yes', 'sim'], true);
}

function ppt_user_message(Throwable $e): string {
    $message = trim($e->getMessage());
    return $message !== '' ? $message : 'Erro inesperado ao processar a geração do PPT.';
}

function ppt_error_response(Throwable $e, int $status = 200, array $extra = []): never {
    $payload = array_merge([
        'ok' => false,
        'error' => ppt_user_message($e),
    ], $extra);

    if (ppt_request_debug_enabled()) {
        $payload['debug'] = [
            'exception' => get_class($e),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ];
    }

    ppt_json_out($payload, $status);
}

function ppt_output_roots(array $cfg): array {
    $roots = [];
    $configured = trim((string)($cfg['generator']['output_root'] ?? ''));
    if ($configured !== '') {
        $isWindowsPath = preg_match('~^[A-Za-z]:[\\/]~', $configured) === 1;
        if (DIRECTORY_SEPARATOR === '\\' || !$isWindowsPath) {
            $roots[] = $configured;
        }
    }
    $roots[] = ppt_root_path() . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'ppt_output';

    $clean = [];
    foreach ($roots as $root) {
        $root = rtrim(ppt_normalize_path((string)$root), DIRECTORY_SEPARATOR);
        if ($root !== '' && !in_array($root, $clean, true)) {
            $clean[] = $root;
        }
    }
    return $clean;
}

function ppt_ensure_dir(string $dir): void {
    if (is_dir($dir)) {
        return;
    }
    if (!@mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('Não foi possível criar a pasta: ' . $dir);
    }
}

function ppt_writable_output_root(array $cfg): string {
    foreach (ppt_output_roots($cfg) as $root) {
        try {
            ppt_ensure_dir($root);
            if (is_writable($root)) {
                return $root;
            }
        } catch (Throwable $e) {
            continue;
        }
    }
    throw new RuntimeException('Nenhuma pasta de saída do PPT está gravável. Ajuste generator.output_root em config_ppt.php ou permissões de storage/ppt_output.');
}

function ppt_http_post_json(string $url, array $headers, string $body, int $timeout): array {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Não foi possível iniciar cURL.');
        }
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_TIMEOUT => max(1, $timeout),
        ]);
        $resp = curl_exec($ch);
        $err = curl_error($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($resp === false) {
            throw new RuntimeException('Falha HTTP ao chamar o gerador: ' . ($err ?: 'sem detalhe retornado pelo cURL'));
        }
        return [$code, (string)$resp];
    }

    $headerText = implode("\r\n", $headers);
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => $headerText,
            'content' => $body,
            'timeout' => max(1, $timeout),
            'ignore_errors' => true,
        ],
    ]);
    $resp = @file_get_contents($url, false, $context);
    if ($resp === false) {
        $last = error_get_last();
        throw new RuntimeException('Falha HTTP ao chamar o gerador sem cURL: ' . (is_array($last) ? (string)($last['message'] ?? '') : 'sem detalhe'));
    }

    $code = 0;
    foreach (($http_response_header ?? []) as $line) {
        if (preg_match('/^HTTP\/\S+\s+(\d+)/', (string)$line, $m)) {
            $code = (int)$m[1];
        }
    }
    return [$code, (string)$resp];
}

function ppt_get_pdo(array $cfg): PDO {
    $db = $cfg['main_db'] ?? [];
    $dsn = (string)($db['dsn'] ?? '');
    $user = (string)($db['user'] ?? '');
    $pass = (string)($db['pass'] ?? '');

    if ($dsn === '') {
        throw new RuntimeException('main_db.dsn não configurado em config_ppt.php');
    }

    return new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

function ppt_generator_api_url(array $cfg, string $endpoint): string {
    $base = rtrim((string)($cfg['generator']['base_url'] ?? ''), '/');
    if ($base === '') {
        throw new RuntimeException('generator.base_url não configurado em config_ppt.php');
    }
    return $base . '/' . ltrim($endpoint, '/');
}

function ppt_call_generator(array $cfg, string $endpoint, array $payload): array {
    $url = ppt_generator_api_url($cfg, $endpoint);
    $token = trim((string)($cfg['generator']['bearer_token'] ?? ''));
    $timeout = (int)($cfg['generator']['timeout_seconds'] ?? 120);

    $headers = ['Content-Type: application/json'];
    if ($token !== '') {
        $headers[] = 'Authorization: Bearer ' . $token;
    }

    $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($encoded)) {
        throw new RuntimeException('Não foi possível codificar o payload do gerador.');
    }

    [$code, $resp] = ppt_http_post_json($url, $headers, $encoded, $timeout);
    $decoded = json_decode($resp, true);

    if ($code >= 400) {
        if (is_array($decoded)) {
            $msg = (string)($decoded['error'] ?? $decoded['message'] ?? ('HTTP ' . $code));
            throw new RuntimeException('Gerador de PPT retornou erro: ' . $msg);
        }
        throw new RuntimeException('Gerador de PPT retornou HTTP ' . $code . ': ' . substr($resp, 0, 600));
    }

    if (!is_array($decoded)) {
        throw new RuntimeException('Resposta inválida do gerador de PPT: ' . substr($resp, 0, 600));
    }

    if (array_key_exists('ok', $decoded) && !$decoded['ok']) {
        throw new RuntimeException((string)($decoded['error'] ?? $decoded['message'] ?? 'Gerador retornou ok=false.'));
    }

    return $decoded;
}

function ppt_base64url_encode(string $value): string {
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function ppt_base64url_decode(string $value): string {
    $remainder = strlen($value) % 4;
    if ($remainder > 0) {
        $value .= str_repeat('=', 4 - $remainder);
    }
    $decoded = base64_decode(strtr($value, '-_', '+/'), true);
    if ($decoded === false) {
        throw new RuntimeException('Token de caminho inválido.');
    }
    return $decoded;
}

function ppt_normalize_path(string $path): string {
    $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
    return $path;
}

function ppt_path_allowed(array $cfg, string $path): bool {
    $fileReal = realpath(ppt_normalize_path($path));
    if ($fileReal === false) {
        return false;
    }
    $fileRealNorm = str_replace('\\', '/', $fileReal);

    foreach (ppt_output_roots($cfg) as $root) {
        $rootReal = realpath(ppt_normalize_path($root));
        if ($rootReal === false) {
            continue;
        }
        $rootRealNorm = rtrim(str_replace('\\', '/', $rootReal), '/');
        if ($fileRealNorm === $rootRealNorm || str_starts_with($fileRealNorm, $rootRealNorm . '/')) {
            return true;
        }
    }

    return false;
}

function ppt_build_download_url(string $path): string {
    $token = urlencode(ppt_base64url_encode($path));
    $scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
    $scriptDir = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
    if ($scriptDir === '' || $scriptDir === '.') {
        $scriptDir = '';
    }
    return $scriptDir . '/ppt_download_proxy.php?path=' . $token;
}

function ppt_available_presentations(array $cfg): array {
    $options = $cfg['presentation_options'] ?? [];
    if (!is_array($options) || $options === []) {
        return [
            ['name' => 'TESTE_DIAGNOSTICO', 'label' => 'Teste Diagnóstico'],
        ];
    }
    return array_values(array_filter($options, static fn($row) => is_array($row) && !empty($row['name'])));
}

function ppt_xml(string $value): string {
    return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
}

function ppt_slide_text_box(int $x, int $y, int $cx, int $cy, string $text, int $fontSize = 2400, bool $bold = false): string {
    $lines = preg_split('/\R/u', $text) ?: [''];
    $runs = [];
    foreach ($lines as $idx => $line) {
        $runs[] = '<a:r><a:rPr lang="pt-BR" sz="' . $fontSize . '"' . ($bold ? ' b="1"' : '') . '/><a:t>' . ppt_xml((string)$line) . '</a:t></a:r>';
        if ($idx < count($lines) - 1) {
            $runs[] = '<a:br/>';
        }
    }
    return '<p:sp><p:nvSpPr><p:cNvPr id="' . random_int(1000, 999999) . '" name="TextBox"/><p:cNvSpPr txBox="1"/><p:nvPr/></p:nvSpPr><p:spPr><a:xfrm><a:off x="' . $x . '" y="' . $y . '"/><a:ext cx="' . $cx . '" cy="' . $cy . '"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom><a:noFill/><a:ln><a:noFill/></a:ln></p:spPr><p:txBody><a:bodyPr wrap="square" rtlCol="0"/><a:lstStyle/><a:p>' . implode('', $runs) . '</a:p></p:txBody></p:sp>';
}

function ppt_build_slide_xml(string $title, array $bullets = [], string $footer = ''): string {
    $shapeXml = ppt_slide_text_box(600000, 420000, 8000000, 800000, $title, 3600, true);
    $y = 1400000;
    $body = [];
    foreach ($bullets as $bullet) {
        $bullet = trim((string)$bullet);
        if ($bullet === '') {
            continue;
        }
        $body[] = '• ' . $bullet;
    }
    $shapeXml .= ppt_slide_text_box(760000, $y, 8200000, 4400000, implode("\n", array_slice($body, 0, 12)), 2200, false);
    if ($footer !== '') {
        $shapeXml .= ppt_slide_text_box(600000, 6300000, 8000000, 400000, $footer, 1200, false);
    }

    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<p:sld xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"><p:cSld><p:spTree><p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr><p:grpSpPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="0" cy="0"/><a:chOff x="0" y="0"/><a:chExt cx="0" cy="0"/></a:xfrm></p:grpSpPr>' . $shapeXml . '</p:spTree></p:cSld><p:clrMapOvr><a:masterClrMapping/></p:clrMapOvr></p:sld>';
}

function ppt_zip_entry(array &$entries, string $name, string $contents): void {
    $entries[] = [$name, $contents];
}

function ppt_zip_write(string $file, array $entries): void {
    $fh = @fopen($file, 'wb');
    if (!is_resource($fh)) {
        throw new RuntimeException('Não foi possível criar o arquivo PPTX: ' . $file);
    }

    $central = '';
    $offset = 0;

    foreach ($entries as $entry) {
        [$name, $contents] = $entry;
        $name = str_replace('\\', '/', (string)$name);
        $contents = (string)$contents;
        $crc = crc32($contents);
        if ($crc < 0) {
            $crc += 4294967296;
        }
        $size = strlen($contents);
        $nameLen = strlen($name);

        $local = pack('VvvvvvVVVvv', 0x04034b50, 20, 0, 0, 0, 0, $crc, $size, $size, $nameLen, 0) . $name . $contents;
        fwrite($fh, $local);

        $central .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0, 0, 0, 0, $crc, $size, $size, $nameLen, 0, 0, 0, 0, 0, $offset) . $name;
        $offset += strlen($local);
    }

    $centralOffset = $offset;
    fwrite($fh, $central);
    $centralSize = strlen($central);
    $count = count($entries);
    fwrite($fh, pack('VvvvvVVv', 0x06054b50, 0, 0, $count, $count, $centralSize, $centralOffset, 0));
    fclose($fh);
}


function ppt_collect_session_context(array $cfg, array $payload): array {
    $context = [
        'session' => [],
        'answers' => [],
        'warning' => '',
    ];

    try {
        $pdo = ppt_get_pdo($cfg);
        $versionId = (int)($payload['version_id'] ?? 0);
        $email = trim((string)($payload['email_resp'] ?? ''));
        $company = trim((string)($payload['company_name'] ?? ''));

        $session = null;
        if ($versionId > 0) {
            $stmt = $pdo->prepare('SELECT * FROM response_sessions WHERE id = ? LIMIT 1');
            $stmt->execute([$versionId]);
            $row = $stmt->fetch();
            if (is_array($row)) {
                $session = $row;
            }
        }

        if ($session === null && $email !== '' && $company !== '' && $versionId > 0) {
            $stmt = $pdo->prepare('SELECT * FROM response_sessions WHERE email_resp = ? AND company_name = ? AND version_no = ? ORDER BY response_datetime DESC, id DESC LIMIT 1');
            $stmt->execute([$email, $company, $versionId]);
            $row = $stmt->fetch();
            if (is_array($row)) {
                $session = $row;
            }
        }

        if ($session !== null) {
            $context['session'] = $session;
            $stmt = $pdo->prepare('SELECT question_name, answer FROM responses_detailed WHERE response_session_id = ? ORDER BY id ASC');
            $stmt->execute([(int)$session['id']]);
            foreach ($stmt->fetchAll() as $row) {
                $name = trim((string)($row['question_name'] ?? ''));
                if ($name !== '') {
                    $context['answers'][$name] = (string)($row['answer'] ?? '');
                }
            }
        } else {
            $context['warning'] = 'Sessão não encontrada na base local; PPT gerado apenas com os dados do formulário.';
        }
    } catch (Throwable $e) {
        $context['warning'] = 'Não foi possível ler respostas da base local: ' . $e->getMessage();
    }

    return $context;
}

function ppt_local_generate(array $cfg, array $payload, string $upstreamWarning = ''): array {
    $executionId = ppt_runtime_id();
    $root = ppt_writable_output_root($cfg);
    $outputDir = $root . DIRECTORY_SEPARATOR . $executionId;
    ppt_ensure_dir($outputDir);

    $company = trim((string)($payload['company_name'] ?? 'Empresa')) ?: 'Empresa';
    $email = trim((string)($payload['email_resp'] ?? ''));
    $presentation = trim((string)($payload['presentation_name'] ?? 'Apresentação')) ?: 'Apresentação';
    $metricYear = (string)($payload['metric_year'] ?? date('Y'));
    $industry = trim((string)($payload['industry_name'] ?? '')) ?: 'N/D';

    $context = ppt_collect_session_context($cfg, $payload);
    $session = is_array($context['session'] ?? null) ? $context['session'] : [];
    $answers = is_array($context['answers'] ?? null) ? $context['answers'] : [];

    $summary = [
        'Modelo: ' . $presentation,
        'Empresa: ' . $company,
        'Email: ' . ($email ?: 'N/D'),
        'Session ID / Version ID: ' . (string)($payload['version_id'] ?? ''),
        'Ano métrico: ' . $metricYear,
        'Indústria: ' . $industry,
    ];
    if ($session !== []) {
        $summary[] = 'Status: ' . (string)($session['status'] ?? '');
        $summary[] = 'Conclusão: ' . (string)($session['completion_pct'] ?? '0') . '%';
        $summary[] = 'Respondidas: ' . (string)($session['answered_count'] ?? '0') . ' de ' . (string)($session['total_questions'] ?? '0');
        $summary[] = 'Data da resposta: ' . (string)($session['response_datetime'] ?? '');
    }

    $answerBullets = [];
    foreach ($answers as $name => $answer) {
        $answer = trim(preg_replace('/\s+/u', ' ', (string)$answer) ?: '');
        if ($answer === '') {
            continue;
        }
        $answerBullets[] = (string)$name . ': ' . substr($answer, 0, 160);
        if (count($answerBullets) >= 10) {
            break;
        }
    }
    if ($answerBullets === []) {
        $answerBullets[] = (string)($context['warning'] ?? 'Sem respostas detalhadas disponíveis para esta sessão.');
    }

    $slides = [
        ['Diagnóstico empresarial', [$company, 'Gerado em ' . date('d/m/Y H:i'), 'Execução: ' . $executionId], 'ProdCol'],
        ['Resumo da sessão', $summary, ''],
        ['Indicadores do questionário', [
            'Conclusão: ' . (string)($session['completion_pct'] ?? 'N/D') . '%',
            'Perguntas respondidas: ' . (string)($session['answered_count'] ?? 'N/D'),
            'Total de perguntas: ' . (string)($session['total_questions'] ?? 'N/D'),
            'Pendências obrigatórias: ' . (string)($session['required_missing_count'] ?? 'N/D'),
        ], ''],
        ['Principais respostas', $answerBullets, ''],
        ['Observações técnicas', array_filter([
            $upstreamWarning !== '' ? 'Gerador externo indisponível: ' . $upstreamWarning : 'PPT gerado pelo motor local integrado.',
            (string)($context['warning'] ?? ''),
            'Arquivo gerado sem interromper a experiência do usuário.',
        ]), ''],
    ];

    $pptx = $outputDir . DIRECTORY_SEPARATOR . 'diagnostico_' . preg_replace('/[^a-z0-9_-]+/i', '_', strtolower($company)) . '.pptx';
    $zipEntries = [];

    $slideCount = count($slides);
    $overrides = '';
    for ($i = 1; $i <= $slideCount; $i++) {
        $overrides .= '<Override PartName="/ppt/slides/slide' . $i . '.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.slide+xml"/>';
    }
    ppt_zip_entry($zipEntries, '[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/ppt/presentation.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.presentation.main+xml"/><Override PartName="/ppt/slideMasters/slideMaster1.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.slideMaster+xml"/><Override PartName="/ppt/slideLayouts/slideLayout1.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.slideLayout+xml"/><Override PartName="/ppt/theme/theme1.xml" ContentType="application/vnd.openxmlformats-officedocument.theme+xml"/><Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/><Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>' . $overrides . '</Types>');
    ppt_zip_entry($zipEntries, '_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="ppt/presentation.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/></Relationships>');

    $slideIds = '';
    $rels = '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slideMaster" Target="slideMasters/slideMaster1.xml"/>';
    for ($i = 1; $i <= $slideCount; $i++) {
        $rid = $i + 1;
        $slideIds .= '<p:sldId id="' . (255 + $i) . '" r:id="rId' . $rid . '"/>';
        $rels .= '<Relationship Id="rId' . $rid . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slide" Target="slides/slide' . $i . '.xml"/>';
    }
    ppt_zip_entry($zipEntries, 'ppt/presentation.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><p:presentation xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"><p:sldMasterIdLst><p:sldMasterId id="2147483648" r:id="rId1"/></p:sldMasterIdLst><p:sldIdLst>' . $slideIds . '</p:sldIdLst><p:sldSz cx="9144000" cy="6858000" type="screen4x3"/><p:notesSz cx="6858000" cy="9144000"/><p:defaultTextStyle/></p:presentation>');
    ppt_zip_entry($zipEntries, 'ppt/_rels/presentation.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $rels . '</Relationships>');

    ppt_zip_entry($zipEntries, 'ppt/slideMasters/slideMaster1.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><p:sldMaster xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"><p:cSld><p:bg><p:bgPr><a:solidFill><a:srgbClr val="FFFFFF"/></a:solidFill><a:effectLst/></p:bgPr></p:bg><p:spTree><p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr><p:grpSpPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="0" cy="0"/><a:chOff x="0" y="0"/><a:chExt cx="0" cy="0"/></a:xfrm></p:grpSpPr></p:spTree></p:cSld><p:clrMap bg1="lt1" tx1="dk1" bg2="lt2" tx2="dk2" accent1="accent1" accent2="accent2" accent3="accent3" accent4="accent4" accent5="accent5" accent6="accent6" hlink="hlink" folHlink="folHlink"/><p:sldLayoutIdLst><p:sldLayoutId id="2147483649" r:id="rId1"/></p:sldLayoutIdLst><p:txStyles><p:titleStyle/><p:bodyStyle/><p:otherStyle/></p:txStyles></p:sldMaster>');
    ppt_zip_entry($zipEntries, 'ppt/slideMasters/_rels/slideMaster1.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slideLayout" Target="../slideLayouts/slideLayout1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/theme" Target="../theme/theme1.xml"/></Relationships>');
    ppt_zip_entry($zipEntries, 'ppt/slideLayouts/slideLayout1.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><p:sldLayout xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main" type="blank" preserve="1"><p:cSld name="Blank"><p:spTree><p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr><p:grpSpPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="0" cy="0"/><a:chOff x="0" y="0"/><a:chExt cx="0" cy="0"/></a:xfrm></p:grpSpPr></p:spTree></p:cSld><p:clrMapOvr><a:masterClrMapping/></p:clrMapOvr></p:sldLayout>');
    ppt_zip_entry($zipEntries, 'ppt/slideLayouts/_rels/slideLayout1.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slideMaster" Target="../slideMasters/slideMaster1.xml"/></Relationships>');
    ppt_zip_entry($zipEntries, 'ppt/theme/theme1.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><a:theme xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" name="ProdCol"><a:themeElements><a:clrScheme name="ProdCol"><a:dk1><a:sysClr val="windowText" lastClr="000000"/></a:dk1><a:lt1><a:sysClr val="window" lastClr="FFFFFF"/></a:lt1><a:dk2><a:srgbClr val="1F2937"/></a:dk2><a:lt2><a:srgbClr val="F8FAFC"/></a:lt2><a:accent1><a:srgbClr val="4F46E5"/></a:accent1><a:accent2><a:srgbClr val="0F172A"/></a:accent2><a:accent3><a:srgbClr val="10B981"/></a:accent3><a:accent4><a:srgbClr val="F59E0B"/></a:accent4><a:accent5><a:srgbClr val="EF4444"/></a:accent5><a:accent6><a:srgbClr val="64748B"/></a:accent6><a:hlink><a:srgbClr val="2563EB"/></a:hlink><a:folHlink><a:srgbClr val="7C3AED"/></a:folHlink></a:clrScheme><a:fontScheme name="ProdCol"><a:majorFont><a:latin typeface="Arial"/></a:majorFont><a:minorFont><a:latin typeface="Arial"/></a:minorFont></a:fontScheme><a:fmtScheme name="ProdCol"><a:fillStyleLst><a:solidFill><a:schemeClr val="phClr"/></a:solidFill></a:fillStyleLst><a:lnStyleLst><a:ln w="6350"><a:solidFill><a:schemeClr val="phClr"/></a:solidFill></a:ln></a:lnStyleLst><a:effectStyleLst><a:effectStyle><a:effectLst/></a:effectStyle></a:effectStyleLst><a:bgFillStyleLst><a:solidFill><a:schemeClr val="phClr"/></a:solidFill></a:bgFillStyleLst></a:fmtScheme></a:themeElements><a:objectDefaults/><a:extraClrSchemeLst/></a:theme>');

    foreach ($slides as $i => $slide) {
        [$title, $bullets, $footer] = $slide;
        ppt_zip_entry($zipEntries, 'ppt/slides/slide' . ($i + 1) . '.xml', ppt_build_slide_xml((string)$title, (array)$bullets, (string)$footer));
        ppt_zip_entry($zipEntries, 'ppt/slides/_rels/slide' . ($i + 1) . '.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slideLayout" Target="../slideLayouts/slideLayout1.xml"/></Relationships>');
    }

    $created = gmdate('Y-m-d\TH:i:s\Z');
    ppt_zip_entry($zipEntries, 'docProps/core.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><dc:title>' . ppt_xml('Diagnóstico empresarial - ' . $company) . '</dc:title><dc:creator>ProdCol</dc:creator><cp:lastModifiedBy>ProdCol</cp:lastModifiedBy><dcterms:created xsi:type="dcterms:W3CDTF">' . $created . '</dcterms:created><dcterms:modified xsi:type="dcterms:W3CDTF">' . $created . '</dcterms:modified></cp:coreProperties>');
    ppt_zip_entry($zipEntries, 'docProps/app.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes"><Application>ProdCol</Application><PresentationFormat>On-screen Show (4:3)</PresentationFormat><Slides>' . $slideCount . '</Slides></Properties>');

    ppt_zip_write($pptx, $zipEntries);

    $contextPath = $outputDir . DIRECTORY_SEPARATOR . 'ppt_input_context.json';
    $runtimePath = $outputDir . DIRECTORY_SEPARATOR . 'ppt_runtime_payload.json';
    file_put_contents($contextPath, json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    file_put_contents($runtimePath, json_encode(['payload' => $payload, 'execution_id' => $executionId, 'fallback_warning' => $upstreamWarning], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

    return [
        'ok' => true,
        'execution_id' => $executionId,
        'pptx' => $pptx,
        'context_json' => $contextPath,
        'runtime_json' => $runtimePath,
        'output_dir' => $outputDir,
        'mode' => $upstreamWarning !== '' ? 'local_fallback' : 'local',
        'warning' => $upstreamWarning,
    ];
}

