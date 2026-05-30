<?php
require_once __DIR__ . '/_common.php';

$data = damodaram_base_data($app, 'Damodaran BI · WMS Bridge', 'wms_bridge.php');
$version = $data['damodaramSelectedVersion'];
$company = trim((string)($version['company_name'] ?? ''));
$emailResp = trim((string)($version['email_resp'] ?? ''));
$sessMin = damodaram_sess_min($version);
$rows = [];
if ($company !== '' && $emailResp !== '' && $sessMin !== '') {
    $rows = damodaram_call($data['statsPdo'], 'sp_damodaran_bi_wms_bridge', [
        $company,
        $emailResp,
        $sessMin,
        $data['damodaramYear'],
        $data['damodaramIndustry'],
    ]);
}
App\Support\View::render('damodaram/wms_bridge', array_merge($data, [
    'damodaramRows' => $rows,
    'damodaramRow' => $rows[0] ?? [],
    'bridgeCompany' => $company,
    'bridgeEmailResp' => $emailResp,
    'bridgeSessMin' => $sessMin,
]));