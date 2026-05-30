<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';

if (!function_exists('damodaram_base_data')) {
    throw new RuntimeException('Função damodaram_base_data() não disponível no módulo DAMODARAM.');
}

$base = damodaram_base_data($app, 'Damodaran BI - CRUD', 'crud.php');
/** @var PDO $pdo */
$pdo = $base['statsPdo'];

$year = (int)($base['damodaramYear'] ?? 2024);
$industry = (string)($base['damodaramIndustry'] ?? 'Advertising');
$msg = '';

function dam_snapshot(PDO $pdo, int $year, string $industry): ?array
{
    $sql = "
        SELECT
            fs.id,
            fs.asof_year,
            fs.number_of_firms,
            ddi.industry_name
        FROM fact_damodaran_snapshot fs
        INNER JOIN dim_damodaran_industry ddi
            ON ddi.id = fs.damodaran_industry_id
        WHERE fs.asof_year = ?
          AND ddi.industry_name = ?
        LIMIT 1
    ";
    $st = $pdo->prepare($sql);
    $st->execute([$year, $industry]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function dam_metrics(PDO $pdo, int $snapshotId): array
{
    $sql = "
        SELECT
            f.id,
            dm.metric_code,
            dm.metric_name,
            dm.metric_group,
            f.metric_value,
            f.metric_value_text
        FROM fact_damodaran_metric f
        INNER JOIN dim_metric dm
            ON dm.id = f.metric_id
        WHERE f.snapshot_id = ?
        ORDER BY dm.metric_group, dm.metric_code
    ";
    $st = $pdo->prepare($sql);
    $st->execute([$snapshotId]);
    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function dam_metric_options(PDO $pdo): array
{
    $sql = "
        SELECT id, metric_code, metric_name, metric_group
        FROM dim_metric
        ORDER BY metric_group, metric_code
    ";
    return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    $year = (int)($_POST['year'] ?? $year);
    $industry = trim((string)($_POST['industry'] ?? $industry));

    if ($action === 'update_snapshot') {
        $snapshotId = (int)($_POST['snapshot_id'] ?? 0);
        $numberOfFirms = trim((string)($_POST['number_of_firms'] ?? ''));

        $st = $pdo->prepare("
            UPDATE fact_damodaran_snapshot
            SET number_of_firms = ?
            WHERE id = ?
        ");
        $st->execute([$numberOfFirms === '' ? null : $numberOfFirms, $snapshotId]);
        $msg = 'Snapshot atualizado.';
    }

    if ($action === 'update_metric') {
        $factId = (int)($_POST['fact_id'] ?? 0);
        $metricValue = trim((string)($_POST['metric_value'] ?? ''));
        $metricText = trim((string)($_POST['metric_value_text'] ?? ''));

        $st = $pdo->prepare("
            UPDATE fact_damodaran_metric
            SET metric_value = ?, metric_value_text = ?
            WHERE id = ?
        ");
        $st->execute([
            $metricValue === '' ? null : $metricValue,
            $metricText === '' ? null : $metricText,
            $factId
        ]);
        $msg = 'Métrica atualizada.';
    }

    if ($action === 'delete_metric') {
        $factId = (int)($_POST['fact_id'] ?? 0);
        $st = $pdo->prepare("DELETE FROM fact_damodaran_metric WHERE id = ?");
        $st->execute([$factId]);
        $msg = 'Métrica excluída.';
    }

    if ($action === 'insert_metric') {
        $snapshotId = (int)($_POST['snapshot_id'] ?? 0);
        $metricId = (int)($_POST['metric_id'] ?? 0);
        $metricValue = trim((string)($_POST['metric_value'] ?? ''));
        $metricText = trim((string)($_POST['metric_value_text'] ?? ''));

        $st = $pdo->prepare("
            SELECT asof_year, damodaran_industry_id
            FROM fact_damodaran_snapshot
            WHERE id = ?
            LIMIT 1
        ");
        $st->execute([$snapshotId]);
        $snap = $st->fetch(PDO::FETCH_ASSOC);

        if ($snap) {
            $ins = $pdo->prepare("
                INSERT INTO fact_damodaran_metric
                (snapshot_id, metric_year, damodaran_industry_id, metric_id, metric_value, metric_value_text)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $ins->execute([
                $snapshotId,
                $snap['asof_year'],
                $snap['damodaran_industry_id'],
                $metricId,
                $metricValue === '' ? null : $metricValue,
                $metricText === '' ? null : $metricText
            ]);
            $msg = 'Métrica incluída.';
        }
    }
}

$snapshot = dam_snapshot($pdo, $year, $industry);
$metrics = $snapshot ? dam_metrics($pdo, (int)$snapshot['id']) : [];
$options = dam_metric_options($pdo);

$base['damodaramYear'] = $year;
$base['damodaramIndustry'] = $industry;

\App\Support\View::render('damodaram/crud', array_merge($base, [
    'snapshot' => $snapshot,
    'metrics' => $metrics,
    'options' => $options,
    'msg' => $msg,
]));