<?php
declare(strict_types=1);

namespace App\Artifacts;

use PDO;

final class ArtifactRepository
{
    public function __construct(private readonly PDO $pdo) {}
    public function ensureSchema(): void
    {
        $sql = file_get_contents(dirname(__DIR__, 3) . '/database/migrations/20260416_create_artifact_files.sql');
        if ($sql === false) throw new \RuntimeException('Migration SQL não encontrada para artifact_files.');
        $this->pdo->exec($sql);
    }
    public function upsert(array $row): void
    {
        $this->ensureSchema();
        $sql = "INSERT INTO artifact_files (
            response_session_id, version_id, company_name, email_user, email_resp, response_datetime,
            stage, scope, subtype, artifact_id, title, description,
            source_format, stored_format, pdf_path, json_path, html_path, manifest_path, blob_db_field,
            group_id, prompt_code, question_name, question_label,
            use_for_ppt, use_for_delivery, use_for_rag, priority_order, checksum_sha256
        ) VALUES (
            :response_session_id, :version_id, :company_name, :email_user, :email_resp, :response_datetime,
            :stage, :scope, :subtype, :artifact_id, :title, :description,
            :source_format, :stored_format, :pdf_path, :json_path, :html_path, :manifest_path, :blob_db_field,
            :group_id, :prompt_code, :question_name, :question_label,
            :use_for_ppt, :use_for_delivery, :use_for_rag, :priority_order, :checksum_sha256
        ) ON DUPLICATE KEY UPDATE
            version_id = VALUES(version_id), company_name = VALUES(company_name), email_user = VALUES(email_user),
            email_resp = VALUES(email_resp), response_datetime = VALUES(response_datetime), stage = VALUES(stage),
            scope = VALUES(scope), subtype = VALUES(subtype), title = VALUES(title), description = VALUES(description),
            source_format = VALUES(source_format), stored_format = VALUES(stored_format), pdf_path = VALUES(pdf_path),
            json_path = VALUES(json_path), html_path = VALUES(html_path), manifest_path = VALUES(manifest_path),
            blob_db_field = VALUES(blob_db_field), group_id = VALUES(group_id), prompt_code = VALUES(prompt_code),
            question_name = VALUES(question_name), question_label = VALUES(question_label), use_for_ppt = VALUES(use_for_ppt),
            use_for_delivery = VALUES(use_for_delivery), use_for_rag = VALUES(use_for_rag), priority_order = VALUES(priority_order),
            checksum_sha256 = VALUES(checksum_sha256)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':response_session_id' => (int) ($row['response_session_id'] ?? 0), ':version_id' => $row['version_id'] !== null ? (int) $row['version_id'] : null,
            ':company_name' => (string) ($row['company_name'] ?? ''), ':email_user' => (string) ($row['email_user'] ?? ''),
            ':email_resp' => $row['email_resp'] ?? null, ':response_datetime' => (string) ($row['response_datetime'] ?? ''),
            ':stage' => (string) ($row['stage'] ?? ''), ':scope' => (string) ($row['scope'] ?? ''), ':subtype' => (string) ($row['subtype'] ?? ''),
            ':artifact_id' => (string) ($row['artifact_id'] ?? ''), ':title' => (string) ($row['title'] ?? ''), ':description' => $row['description'] ?? null,
            ':source_format' => (string) ($row['source_format'] ?? 'html'), ':stored_format' => (string) ($row['stored_format'] ?? 'pdf'),
            ':pdf_path' => (string) ($row['pdf_path'] ?? ''), ':json_path' => $row['json_path'] ?? null, ':html_path' => $row['html_path'] ?? null,
            ':manifest_path' => $row['manifest_path'] ?? null, ':blob_db_field' => $row['blob_db_field'] ?? null,
            ':group_id' => $row['group_id'] !== null ? (int) $row['group_id'] : null, ':prompt_code' => $row['prompt_code'] ?? null,
            ':question_name' => $row['question_name'] ?? null, ':question_label' => $row['question_label'] ?? null,
            ':use_for_ppt' => !empty($row['use_for_ppt']) ? 1 : 0, ':use_for_delivery' => !empty($row['use_for_delivery']) ? 1 : 0,
            ':use_for_rag' => !empty($row['use_for_rag']) ? 1 : 0, ':priority_order' => (int) ($row['priority_order'] ?? 100),
            ':checksum_sha256' => $row['checksum_sha256'] ?? null,
        ]);
    }
    public function listByVersionId(int $versionId): array
    {
        $this->ensureSchema();
        $stmt = $this->pdo->prepare('SELECT * FROM artifact_files WHERE version_id = ? ORDER BY stage, scope, priority_order, id');
        $stmt->execute([$versionId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
