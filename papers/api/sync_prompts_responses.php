<?php
declare(strict_types=1);
session_start();
require __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    echo json_encode(['ok'=>false,'error'=>'JSON inválido']); exit;
}

$company = $data['company_name'] ?? '';
$email   = $data['email_resp'] ?? '';
$sessMin = $data['sess_min'] ?? '';

if (!$company || !$email || !$sessMin) {
    echo json_encode(['ok'=>false,'error'=>'Parâmetros ausentes']); exit;
}

$sql = "
UPDATE responses_detailed rd
JOIN prompts p
  ON p.assistente = rd.prompt_code
SET rd.prompt = p.prompt
WHERE rd.company_name = :company
  AND rd.email_resp   = :email
  AND DATE_FORMAT(rd.response_datetime,'%Y-%m-%d %H:%i') = :sess_min
";

$stmt = $pdo->prepare($sql);
$stmt->execute([
    ':company'  => $company,
    ':email'    => $email,
    ':sess_min' => $sessMin,
]);

echo json_encode([
    'ok' => true,
    'updated_rows' => $stmt->rowCount()
]);
