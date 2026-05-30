<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/lib/common.php';

try {
    $cfg = ppt_load_config();

    $raw = file_get_contents('php://input') ?: '';
    $in = json_decode($raw, true);
    if (!is_array($in)) {
        ppt_json_out(['ok' => false, 'error' => 'JSON inválido'], 200);
    }

    $defaults = $cfg['default_values'] ?? [];

    $versionId = (int)($in['version_id'] ?? $in['response_session_id'] ?? 0);
    $payload = [
        'presentation_name' => trim((string)($in['presentation_name'] ?? '')),
        'user_id' => (int)($in['user_id'] ?? ($defaults['user_id'] ?? 1)),
        'version_id' => $versionId,
        'response_session_id' => (int)($in['response_session_id'] ?? $versionId),
        'email_resp' => trim((string)($in['email_resp'] ?? '')),
        'company_name' => trim((string)($in['company_name'] ?? '')),
        'metric_year' => (int)($in['metric_year'] ?? ($defaults['metric_year'] ?? date('Y'))),
        'industry_name' => trim((string)($in['industry_name'] ?? ($defaults['industry_name'] ?? ''))),
    ];

    foreach (['presentation_name', 'version_id', 'email_resp', 'company_name'] as $field) {
        if ($payload[$field] === '' || $payload[$field] === 0) {
            ppt_json_out(['ok' => false, 'error' => 'Campo obrigatório ausente: ' . $field], 200);
        }
    }

    $useLocalOnly = !empty($cfg['generator']['local_only']);
    $resp = null;
    $upstreamWarning = '';

    if (!$useLocalOnly) {
        try {
            $resp = ppt_call_generator($cfg, 'generate_ppt.php', $payload);
        } catch (Throwable $upstreamException) {
            $upstreamWarning = $upstreamException->getMessage();
        }
    }

    if (!is_array($resp)) {
        $resp = ppt_local_generate($cfg, $payload, $upstreamWarning);
    }

    foreach (['pptx', 'context_json', 'runtime_json'] as $field) {
        if (!empty($resp[$field]) && is_string($resp[$field]) && ppt_path_allowed($cfg, $resp[$field])) {
            $resp[$field . '_download'] = ppt_build_download_url($resp[$field]);
        }
    }

    ppt_json_out($resp);
} catch (Throwable $e) {
    ppt_error_response($e, 200);
}
