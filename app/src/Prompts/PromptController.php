<?php
declare(strict_types=1);

namespace App\Prompts;

use App\Auth\AuthService;
use App\Support\Request;
use App\Support\View;
use RuntimeException;

final class PromptController
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly PromptRepository $repository,
        private readonly PromptRuntimeService $runtimeService,
        private readonly Request $request
    ) {
    }

    public function index(): void
    {
        $this->auth->requireAuth();

        if ($this->request->method() === 'POST') {
            $this->handlePost();
            return;
        }

        $this->renderList();
    }

    public function form(): void
    {
        $this->auth->requireAuth();

        if ($this->request->method() === 'POST') {
            $this->handlePost();
            return;
        }

        $this->renderForm();
    }

    private function handlePost(): void
    {
        $action = trim((string) ($this->request->input('action') ?? 'save'));

        if ($action === 'execute_sql') {
            $this->handleExecuteSql();
            return;
        }

        if (!\check_csrf($this->request->input('_csrf'))) {
            flash_set('prompts_error', 'CSRF inválido.');
            redirect(\url('prompts/index.php'));
        }

        $context = $this->normalizeContext((string) ($this->request->input('context') ?? ''));
        $returnTo = trim((string) ($this->request->input('return_to') ?? 'form'));

        try {
            if ($action === 'delete') {
                $id = (int) ($this->request->input('id') ?? 0);
                $this->repository->delete($id);
                flash_set('prompts_success', 'Prompt excluído com sucesso.');
                redirect(\url('prompts/index.php') . ($context === '' ? '' : '?context=' . rawurlencode($context)));
            }

            $id = $this->repository->save([
                'id' => (int) ($this->request->input('id') ?? 0),
                'assistente' => $this->request->input('assistente') ?? '',
                'funcao' => $this->request->input('funcao') ?? '',
                'descricao' => $this->request->input('descricao') ?? '',
                'prompt' => $this->request->input('prompt') ?? '',
                'sql_preview' => $this->request->input('sql_preview') ?? '',
                'sql_desc' => $this->request->input('sql_desc') ?? '',
                'sql_text' => $this->request->input('sql_text') ?? '',
            ]);

            flash_set('prompts_success', 'Prompt salvo com sucesso.');

            if ($returnTo === 'list') {
                redirect(\url('prompts/index.php') . ($context === '' ? '' : '?context=' . rawurlencode($context)));
            }

            redirect(\url('prompts/form.php?id=' . $id . ($context === '' ? '' : '&context=' . rawurlencode($context))));
        } catch (RuntimeException $exception) {
            flash_set('prompts_error', $exception->getMessage());

            $id = (int) ($this->request->input('id') ?? 0);
            if ($returnTo === 'list') {
                redirect(\url('prompts/index.php') . ($context === '' ? '' : '?context=' . rawurlencode($context)));
            }

            $redirect = \url('prompts/form.php');
            $query = [];
            if ($id > 0) {
                $query[] = 'id=' . $id;
            }
            if ($context !== '') {
                $query[] = 'context=' . rawurlencode($context);
            }
            if ($query !== []) {
                $redirect .= '?' . implode('&', $query);
            }

            redirect($redirect);
        }
    }


    private function handleExecuteSql(): void
    {
        if (!\check_csrf($this->request->input('_csrf'))) {
            $this->jsonOut(['ok' => false, 'message' => 'CSRF inválido.'], 403);
        }

        $promptText = (string) ($this->request->input('prompt') ?? '');
        $sqlBlockText = (string) ($this->request->input('sql_block') ?? '');
        $company = trim((string) ($this->request->input('company_context') ?? ''));
        $versionId = (int) ($this->request->input('version_context') ?? 0);

        $result = $this->runtimeService->executeSqlPreview(
            $promptText,
            $sqlBlockText,
            $this->auth->user()->email,
            $company !== '' ? $company : null,
            $versionId > 0 ? $versionId : null
        );

        $this->jsonOut($result, !empty($result['ok']) ? 200 : 422);
    }

    private function jsonOut(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    private function renderList(): void
    {
        $filters = [
            'q' => trim((string) ($this->request->query('q', '') ?? '')),
            'assistente' => trim((string) ($this->request->query('assistente', '') ?? '')),
            'funcao' => trim((string) ($this->request->query('funcao', '') ?? '')),
            'section' => trim((string) ($this->request->query('section', '') ?? '')),
            'has_sql' => trim((string) ($this->request->query('has_sql', '') ?? '')),
        ];

        $context = $this->normalizeContext((string) ($this->request->query('context', '') ?? ''));
        $screenMode = $this->normalizeScreen((string) ($this->request->query('screen', '') ?? ''));

        try {
            $rows = $this->repository->search($filters);
            $stats = $this->repository->stats();
            $filterOptions = $this->repository->filterOptions();
        } catch (RuntimeException $exception) {
            View::render('prompts/index', [
                'rows' => [],
                'filters' => $filters,
                'stats' => ['total' => 0, 'with_section' => 0, 'with_sql' => 0, 'with_markers' => 0],
                'filterOptions' => ['assistentes' => [], 'funcoes' => [], 'sections' => []],
                'error' => $exception->getMessage(),
                'success' => flash_get('prompts_success'),
                'errorFlash' => flash_get('prompts_error'),
                'context' => $context,
                'screenMode' => $screenMode,
                'pageTitle' => $screenMode === 'agentes' ? 'Configurar Agentes' : 'Editar Prompts',
                'contentTitle' => $screenMode === 'agentes' ? 'Configurar Agentes' : 'Editar Prompts',
            ]);
            return;
        }

        View::render('prompts/index', [
            'rows' => $rows,
            'filters' => $filters,
            'stats' => $stats,
            'filterOptions' => $filterOptions,
            'error' => null,
            'success' => flash_get('prompts_success'),
            'errorFlash' => flash_get('prompts_error'),
            'context' => $context,
            'screenMode' => $screenMode,
            'pageTitle' => $screenMode === 'agentes' ? 'Configurar Agentes' : 'Editar Prompts',
            'contentTitle' => $screenMode === 'agentes' ? 'Configurar Agentes' : 'Editar Prompts',
        ]);
    }

    private function renderForm(): void
    {
        $id = (int) ($this->request->query('id', '0') ?? 0);
        $context = $this->normalizeContext((string) ($this->request->query('context', '') ?? ''));
        $company = trim((string) ($this->request->query('company', '') ?? ''));
        $versionId = (int) ($this->request->query('version', '0') ?? 0);
        $screenMode = $this->normalizeScreen((string) ($this->request->query('screen', '') ?? ''));

        try {
            $editData = $id > 0 ? $this->repository->find($id) : null;
            if ($id > 0 && $editData === null) {
                throw new RuntimeException('Prompt não encontrado.');
            }

            $editData = is_array($editData) ? $editData : [
                'id' => 0,
                'assistente' => '',
                'funcao' => '',
                'descricao' => '',
                'prompt' => '',
            ];

            $metadata = $this->repository->metadataForPromptCode((string) ($editData['assistente'] ?? ''));
            $preview = $this->runtimeService->build($editData, $this->auth->user()->email, $company !== '' ? $company : null, $versionId > 0 ? $versionId : null);

            View::render('prompts/form', [
                'editData' => $editData,
                'formFields' => $this->repository->formFields(),
                'papers' => $this->repository->papers(),
                'sqlOptions' => $this->repository->sqlOptions(),
                'metadata' => $metadata,
                'preview' => $preview,
                'context' => $context,
                'companyContext' => $company,
                'versionContext' => $versionId,
                'screenMode' => $screenMode,
                'success' => flash_get('prompts_success'),
                'errorFlash' => flash_get('prompts_error'),
                'pageTitle' => $screenMode === 'agentes' ? 'Configurar Agentes' : ($id > 0 ? 'Editar Prompt' : 'Novo Prompt'),
                'contentTitle' => $screenMode === 'agentes' ? 'Configurar Agentes' : ($id > 0 ? 'Editar Prompt' : 'Novo Prompt'),
            ]);
        } catch (RuntimeException $exception) {
            View::render('prompts/form', [
                'editData' => ['id' => 0, 'assistente' => '', 'funcao' => '', 'descricao' => '', 'prompt' => ''],
                'formFields' => [],
                'papers' => [],
                'sqlOptions' => [],
                'metadata' => [],
                'preview' => [
                    'available' => false,
                    'source_label' => 'Falha ao carregar prévia',
                    'resolved_prompt' => '',
                    'unresolved_markers' => [],
                    'marker_values' => [],
                    'attachments' => [],
                    'sql' => ['has_sql' => false, 'desc' => '', 'rows' => [], 'row_count' => 0, 'error' => null],
                ],
                'context' => $context,
                'companyContext' => $company,
                'versionContext' => $versionId,
                'screenMode' => $screenMode,
                'errorFlash' => $exception->getMessage(),
                'success' => null,
                'pageTitle' => $screenMode === 'agentes' ? 'Configurar Agentes' : 'Editar Prompt',
                'contentTitle' => $screenMode === 'agentes' ? 'Configurar Agentes' : 'Editar Prompt',
            ]);
        }
    }

    private function normalizeContext(string $context): string
    {
        $context = trim(mb_strtolower($context));
        return in_array($context, ['analitica', 'estrategica'], true) ? $context : '';
    }

    private function normalizeScreen(string $screen): string
    {
        $screen = trim(mb_strtolower($screen));
        return $screen === 'agentes' ? 'agentes' : '';
    }
}
