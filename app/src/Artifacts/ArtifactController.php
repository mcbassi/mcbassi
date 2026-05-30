<?php
declare(strict_types=1);

namespace App\Artifacts;

use App\Auth\AuthService;
use App\Diagnostico\VersionedResponseRepository;
use App\Infra\Database;
use App\Support\Request;
use App\Support\Response;

final class ArtifactController
{
    private ArtifactPathService $paths;
    private ArtifactManifestService $manifest;
    private ArtifactRepository $repository;
    private ArtifactRenderService $renderer;
    private ArtifactPdfService $pdf;
    private ArtifactBootstrapService $bootstrapService;
    private ArtifactStorageService $storage;
    private VersionedResponseRepository $versions;

    public function __construct(private readonly AuthService $auth, private readonly Database $db, private readonly Request $request)
    {
        $baseDir = $this->resolveBaseDir();
        $this->paths = new ArtifactPathService($baseDir);
        $this->manifest = new ArtifactManifestService();
        $this->repository = new ArtifactRepository($this->db->pdo());
        $this->renderer = new ArtifactRenderService();
        $this->pdf = new ArtifactPdfService();
        $this->versions = new VersionedResponseRepository($this->db->pdo());
        $this->bootstrapService = new ArtifactBootstrapService($this->db->pdo(), $this->versions, $this->paths, $this->manifest);
        $this->storage = new ArtifactStorageService($this->paths, $this->manifest, $this->repository, $this->renderer, $this->pdf);
    }

    public function bootstrap(): never
    {
        $this->auth->requireAuth();
        try {
            $versionId = (int) ($this->request->input('version_id') ?? 0);
            $metricYear = $this->request->input('metric_year');
            $industryName = $this->request->input('industry_name');
            $result = $this->bootstrapService->bootstrap($versionId, $this->auth->user()->email, $metricYear !== null && $metricYear !== '' ? (int) $metricYear : null, $industryName !== null && $industryName !== '' ? $industryName : null);
            Response::json(['ok' => true] + $result);
        } catch (\Throwable $e) {
            Response::json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function save(): never
    {
        $this->auth->requireAuth();
        try {
            $versionId = (int) ($this->request->input('version_id') ?? 0);
            $bootstrap = $this->bootstrapService->bootstrap($versionId, $this->auth->user()->email);
            $version = $bootstrap['version'];
            $contentType = trim((string) ($this->request->input('content_type') ?? 'html'));
            $content = match ($contentType) {
                'json' => (string) ($this->request->input('content_json') ?? ''),
                'html' => (string) ($this->request->input('content_html') ?? ''),
                'markdown' => (string) ($this->request->input('content_markdown') ?? ''),
                'text' => (string) ($this->request->input('content_text') ?? ''),
                'pdf_base64' => (string) ($this->request->input('content_base64') ?? ''),
                default => throw new \RuntimeException('content_type inválido.'),
            };
            $sessionContext = ['response_session_id'=>(int)($version['id']??$versionId),'version_id'=>(int)($version['id']??$versionId),'company_name'=>(string)($version['company_name']??''),'email_user'=>(string)($version['email_user']??$this->auth->user()->email),'email_resp'=>(string)($version['email_resp']??''),'response_datetime'=>(string)($version['response_datetime']??''),'sess_min'=>substr((string)($version['response_datetime']??''),0,16),'manifest_path'=>(string)($bootstrap['manifest_path']??'')];
            $result = $this->storage->save($sessionContext, ['artifact_id'=>(string)($this->request->input('artifact_id')??''),'stage'=>(string)($this->request->input('stage')??''),'scope'=>(string)($this->request->input('scope')??''),'subtype'=>(string)($this->request->input('subtype')??''),'title'=>(string)($this->request->input('title')??''),'description'=>(string)($this->request->input('description')??''),'filename_hint'=>(string)($this->request->input('filename_hint')??''),'content_type'=>$contentType,'content'=>$content,'group_id'=>$this->request->input('group_id'),'prompt_code'=>$this->request->input('prompt_code'),'question_name'=>$this->request->input('question_name'),'question_label'=>$this->request->input('question_label'),'use_for_ppt'=>$this->request->input('use_for_ppt')==='1','use_for_delivery'=>$this->request->input('use_for_delivery')==='1','use_for_rag'=>$this->request->input('use_for_rag')==='1','priority_order'=>(int)($this->request->input('priority_order')??100),'blob_db_field'=>$this->request->input('blob_db_field'),'module'=>(string)($this->request->input('module')??'artifacts'),'route'=>(string)($this->request->input('route')??''),'generator'=>(string)($this->request->input('generator')??'ArtifactController')]);
            Response::json(['ok' => true] + $result);
        } catch (\Throwable $e) {
            Response::json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function list(): never
    {
        $this->auth->requireAuth();
        try {
            $rows = $this->repository->listByVersionId((int) ($this->request->query('version_id') ?? 0));
            Response::json(['ok' => true, 'rows' => $rows]);
        } catch (\Throwable $e) {
            Response::json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    private function resolveBaseDir(): string
    {
        $root = dirname(__DIR__, 3);
        $configFile = $root . '/config/storage.php';
        if (is_file($configFile)) {
            $config = require $configFile;
            $baseDir = (string) ($config['artifacts_base_dir'] ?? '');
            if ($baseDir !== '') return $baseDir;
        }
        return $root . '/storage/clientes';
    }
}
