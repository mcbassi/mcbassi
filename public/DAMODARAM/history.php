<?php
require_once __DIR__ . '/_common.php';

$data = damodaram_base_data($app, 'Damodaran BI · History', 'history.php');
$rows = damodaram_call($data['statsPdo'], 'sp_damodaran_bi_history', [$data['damodaramIndustry']]);

App\Support\View::render('damodaram/history', array_merge($data, [
    'damodaramRows' => $rows,
]));
