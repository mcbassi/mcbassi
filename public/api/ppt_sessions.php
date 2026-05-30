<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/lib/common.php';

try {
    $cfg = ppt_load_config();
    $pdo = ppt_get_pdo($cfg);

    $company = trim((string)($_GET['company_name'] ?? ''));
    $email = trim((string)($_GET['email_resp'] ?? ''));
    $limit = (int)($cfg['sessions_limit'] ?? 200);
    if ($limit <= 0 || $limit > 1000) {
        $limit = 200;
    }

    $sql = "SELECT
                id AS response_session_id,
                id AS version_id,
                version_no AS version_no,
                company_name,
                email_resp,
                response_datetime,
                user,
                answered_count,
                total_questions,
                completion_pct,
                status
            FROM response_sessions
            WHERE 1=1";
    $params = [];

    if ($company !== '') {
        $sql .= ' AND company_name LIKE ?';
        $params[] = '%' . $company . '%';
    }
    if ($email !== '') {
        $sql .= ' AND email_resp LIKE ?';
        $params[] = '%' . $email . '%';
    }

    $sql .= ' ORDER BY response_datetime DESC LIMIT ' . (int)$limit;

    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll();

    ppt_json_out([
        'ok' => true,
        'rows' => $rows,
        'count' => count($rows),
    ]);
} catch (Throwable $e) {
    ppt_json_out([
        'ok' => false,
        'error' => $e->getMessage(),
    ], 500);
}
