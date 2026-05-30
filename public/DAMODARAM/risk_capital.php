<?php
require_once __DIR__ . '/_common.php';

$data = damodaram_base_data($app, 'Damodaran BI · Risk / Capital', 'risk_capital.php');
$rows = damodaram_call($data['statsPdo'], 'sp_damodaran_bi_risk_capital', [$data['damodaramYear'], $data['damodaramIndustry']]);
$row = $rows[0] ?? [];

App\Support\View::render('damodaram/risk_capital', array_merge($data, [
    'damodaramRows' => $rows,
    'damodaramRow' => $row,
]));
