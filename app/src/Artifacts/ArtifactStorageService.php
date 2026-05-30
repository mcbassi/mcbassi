<?php
declare(strict_types=1);

namespace App\Artifacts;

final class ArtifactStorageService
{
    public function __construct(private readonly ArtifactPathService $paths, private readonly ArtifactManifestService $manifest, private readonly ArtifactRepository $repository, private readonly ArtifactRenderService $renderer, private readonly ArtifactPdfService $pdf) {}

    public function save(array $sessionContext, array $payload): array
    {
        $companyName = (string) ($sessionContext['company_name'] ?? '');
        $responseDatetime = (string) ($sessionContext['response_datetime'] ?? '');
        $manifestPath = (string) ($sessionContext['manifest_path'] ?? '');
        if ($companyName === '' || $responseDatetime === '' || $manifestPath === '') throw new \RuntimeException('Contexto da sessão inválido para salvar artefato.');
        $artifactId = trim((string) ($payload['artifact_id'] ?? ''));
        if ($artifactId === '') throw new \RuntimeException('artifact_id é obrigatório.');
        $stage = trim((string) ($payload['stage'] ?? ''));
        $scope = trim((string) ($payload['scope'] ?? ''));
        $subtype = trim((string) ($payload['subtype'] ?? ''));
        $title = trim((string) ($payload['title'] ?? $artifactId));
        $contentType = trim((string) ($payload['content_type'] ?? 'html'));
        $content = (string) ($payload['content'] ?? '');
        if ($content === '') throw new \RuntimeException('content é obrigatório.');
        $relativePdfPath = $this->paths->pdfRelativePath($stage, $scope, trim((string) ($payload['filename_hint'] ?? ($artifactId . '.pdf'))));
        $absolutePdfPath = $this->paths->absoluteFromRelative($companyName, $responseDatetime, $relativePdfPath);
        $absoluteDir = dirname($absolutePdfPath);
        if (!is_dir($absoluteDir) && !mkdir($absoluteDir, 0777, true) && !is_dir($absoluteDir)) throw new \RuntimeException('Não foi possível criar o diretório do artefato: ' . $absoluteDir);
        $meta = ['empresa' => $companyName, 'sessao' => (string) ($sessionContext['sess_min'] ?? ''), 'etapa' => $stage, 'escopo' => $scope];
        $pdfBytes = $this->pdf->fromPayload($contentType, $content, $this->renderer, $title, $meta);
        if (file_put_contents($absolutePdfPath, $pdfBytes) === false) throw new \RuntimeException('Falha ao gravar PDF em disco: ' . $absolutePdfPath);
        $checksum = hash('sha256', $pdfBytes);
        $artifact = ['artifact_id'=>$artifactId,'stage'=>$stage,'scope'=>$scope,'subtype'=>$subtype,'title'=>$title,'description'=>(string)($payload['description']??''),'source_format'=>$contentType,'stored_format'=>'pdf','pdf_path'=>$relativePdfPath,'json_path'=>null,'html_path'=>null,'blob_db_field'=>$payload['blob_db_field']??null,'origin'=>['module'=>(string)($payload['module']??''),'route'=>(string)($payload['route']??''),'generator'=>(string)($payload['generator']??'')],'references'=>['group_id'=>$payload['group_id']??null,'prompt_code'=>$payload['prompt_code']??null,'question_name'=>$payload['question_name']??null,'question_label'=>$payload['question_label']??null],'usage'=>['use_for_ppt'=>!empty($payload['use_for_ppt']),'use_for_delivery'=>!empty($payload['use_for_delivery']),'use_for_rag'=>!empty($payload['use_for_rag']),'priority'=>(int)($payload['priority_order']??100)],'timestamps'=>['generated_at'=>date('Y-m-d H:i:s'),'updated_at'=>date('Y-m-d H:i:s')],'checksum'=>['sha256'=>$checksum]];
        $manifest = $this->manifest->load($manifestPath);
        $manifest = $this->manifest->upsertArtifact($manifest, $artifact);
        $this->manifest->save($manifestPath, $manifest);
        $this->repository->upsert(['response_session_id'=>(int)($sessionContext['response_session_id']??0),'version_id'=>(int)($sessionContext['version_id']??0),'company_name'=>$companyName,'email_user'=>(string)($sessionContext['email_user']??''),'email_resp'=>(string)($sessionContext['email_resp']??''),'response_datetime'=>(string)($sessionContext['response_datetime']??''),'stage'=>$stage,'scope'=>$scope,'subtype'=>$subtype,'artifact_id'=>$artifactId,'title'=>$title,'description'=>(string)($payload['description']??''),'source_format'=>$contentType,'stored_format'=>'pdf','pdf_path'=>$relativePdfPath,'json_path'=>null,'html_path'=>null,'manifest_path'=>$manifestPath,'blob_db_field'=>$payload['blob_db_field']??null,'group_id'=>$payload['group_id']??null,'prompt_code'=>$payload['prompt_code']??null,'question_name'=>$payload['question_name']??null,'question_label'=>$payload['question_label']??null,'use_for_ppt'=>!empty($payload['use_for_ppt']),'use_for_delivery'=>!empty($payload['use_for_delivery']),'use_for_rag'=>!empty($payload['use_for_rag']),'priority_order'=>(int)($payload['priority_order']??100),'checksum_sha256'=>$checksum]);
        return ['artifact_id'=>$artifactId,'pdf_path'=>$relativePdfPath,'absolute_pdf_path'=>$absolutePdfPath,'manifest_path'=>$manifestPath,'checksum_sha256'=>$checksum];
    }
}
