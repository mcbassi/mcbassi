<?php
require_once __DIR__ . '/_common.php';

$data = damodaram_base_data($app, 'Damodaran BI', 'index.php');
$rows = damodaram_call($data['statsPdo'], 'sp_damodaran_bi_overview', [$data['damodaramYear'], $data['damodaramIndustry']]);
$row = $rows[0] ?? [];
$historyRows = damodaram_call($data['statsPdo'], 'sp_damodaran_bi_history', [$data['damodaramIndustry']]);
$peerSt = $data['statsPdo']->prepare('SELECT * FROM vw_damodaran_overview_base WHERE asof_year = ? ORDER BY industry_name');
$peerSt->execute([$data['damodaramYear']]);
$peerRows = $peerSt->fetchAll(PDO::FETCH_ASSOC) ?: [];

App\Support\View::render('damodaram/index', array_merge($data, [
    'damodaramRows' => $rows,
    'damodaramRow' => $row,
    'historyRows' => $historyRows,
    'peerRows' => $peerRows,
]));
