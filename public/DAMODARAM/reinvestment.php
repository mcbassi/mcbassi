<?php
require_once __DIR__ . '/_common.php';

$data = damodaram_base_data($app, 'Damodaran BI · Reinvestment / Working Capital', 'reinvestment.php');
$rows = damodaram_call($data['statsPdo'], 'sp_damodaran_bi_reinvestment_wc', [$data['damodaramYear'], $data['damodaramIndustry']]);
$row = $rows[0] ?? [];

App\Support\View::render('damodaram/reinvestment', array_merge($data, [
    'damodaramRows' => $rows,
    'damodaramRow' => $row,
]));
