<?php
declare(strict_types=1);

namespace App\Papers;

use App\Auth\AuthService;
use App\Support\Request;
use App\Support\Response;
use App\Support\View;
use RuntimeException;

final class PaperController
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly PaperRepository $repository,
        private readonly PromptFlowRepository $promptFlowRepository,
        private readonly PaperFileService $fileService,
        private readonly Request $request
    ) {
    }

    public function index(): void
    {
        $this->auth->requireAuth();

        if ($this->request->method() === 'POST') {
            $this->handleDelete();
            return;
        }

        $query = trim((string) ($this->request->query('q', '') ?? ''));
        $chapter = trim((string) ($this->request->query('chapter', '') ?? ''));
        $prompt = trim((string) ($this->request->query('prompt', '') ?? ''));
        $sort = trim((string) ($this->request->query('sort', '') ?? ''));
        $rag = trim((string) ($this->request->query('rag', '') ?? ''));
        $notice = $this->request->query('notice');
        $error = null;

        try {
            $papers = $this->repository->searchLegacy($query, $chapter, $prompt, $sort);
            if ($rag !== '') {
                $papers = array_values(array_filter($papers, fn (array $paper): bool => $this->paperMatchesRagFilter($paper, $rag)));
            }

            $stats = $this->repository->stats();
            $filteredStats = $this->summarizeRows($papers);
            $availability = $this->repository->availability();
        } catch (RuntimeException $exception) {
            $papers = [];
            $stats = $this->emptySummary();
            $filteredStats = $this->emptySummary();
            $availability = [
                'papers' => false,
                'papers_file_cache' => false,
                'prompt_file_usage' => false,
            ];
            $error = $exception->getMessage();
        }

        View::render('papers/index', [
            'pageTitle' => 'Bibliografia',
            'contentTitle' => 'Bibliografia',
            'subtitle' => 'ProdCol',
            'user' => $this->auth->user(),
            'papers' => $papers,
            'query' => $query,
            'chapter' => $chapter,
            'prompt' => $prompt,
            'sort' => $sort,
            'rag' => $rag,
            'stats' => $stats,
            'filteredStats' => $filteredStats,
            'notice' => $notice !== null && $notice !== '' ? $notice : null,
            'error' => $error,
            'availability' => $availability,
        ]);
    }


public function chapters(): void
{
    $this->auth->requireAuth();

    if ($this->request->method() === 'POST') {
        $this->handleChapterAssignment();
        return;
    }

    $search = trim((string) ($this->request->query('q', '') ?? ''));
    $notice = $this->request->query('notice');
    $error = null;
    $tree = [
        'chapters' => [],
        'papers' => [],
    ];

    try {
        $tree = $this->repository->chapterTreeData($search);
    } catch (RuntimeException $exception) {
        $error = $exception->getMessage();
    }

    View::render('papers/chapters', [
        'pageTitle' => 'Capítulos × Publicações',
        'contentTitle' => 'Capítulos × Publicações',
        'subtitle' => 'ProdCol',
        'user' => $this->auth->user(),
        'tree' => $tree,
        'query' => $search,
        'notice' => $notice !== null && $notice !== '' ? $notice : null,
        'error' => $error,
    ]);
}

private function handleChapterAssignment(): never
{
    if (!\check_csrf($this->request->input('_csrf'))) {
        Response::json([
            'ok' => false,
            'message' => 'CSRF inválido.',
        ], 422);
    }

    $paperId = (int) ($this->request->input('paper_id', '0') ?? 0);
    $chapterCode = trim((string) ($this->request->input('chapter_code', '') ?? ''));
    $chapterCode = $chapterCode === '' || $chapterCode === '__NONE__' ? null : $chapterCode;

    if ($paperId <= 0) {
        Response::json([
            'ok' => false,
            'message' => 'Paper inválido.',
        ], 422);
    }

    try {
        $this->repository->assignChapter($paperId, $chapterCode);

        Response::json([
            'ok' => true,
            'message' => 'Capítulo vinculado com sucesso.',
            'paper_id' => $paperId,
            'chapter_code' => $chapterCode,
            'chapter_label' => $chapterCode ?? 'Sem capítulo',
        ]);
    } catch (RuntimeException $exception) {
        Response::json([
            'ok' => false,
            'message' => $exception->getMessage(),
        ], 422);
    }
}

    public function view(): void
    {
        $this->auth->requireAuth();

        $id = (int) ($this->request->query('id', '0') ?? 0);
        $notice = $this->request->query('notice');
        $error = null;
        $paper = null;
        $promptContext = null;
        $availability = $this->repository->availability();

        try {
            if ($id <= 0) {
                throw new RuntimeException('Paper inválido.');
            }

            $paper = $this->repository->find($id);
            if (!is_array($paper)) {
                throw new RuntimeException('Publicação não encontrada.');
            }

            $promptContext = $this->promptFlowRepository->paperContext($paper);
        } catch (RuntimeException $exception) {
            $error = $exception->getMessage();
        }

        View::render('papers/view', [
            'pageTitle' => is_array($paper) ? ('Paper · ' . (string) ($paper['title'] ?? '')) : 'Paper',
            'contentTitle' => 'Bibliografia',
            'subtitle' => 'ProdCol',
            'user' => $this->auth->user(),
            'paper' => $paper,
            'paperIndicators' => is_array($paper) ? $this->paperIndicators($paper) : [],
            'promptContext' => $promptContext,
            'availability' => $availability,
            'notice' => $notice !== null && $notice !== '' ? $notice : null,
            'error' => $error,
        ]);
    }


    public function open(): void
    {
        $this->auth->requireAuth();

        $id = (int) ($this->request->query('id', '0') ?? 0);
        if ($id <= 0) {
            http_response_code(404);
            echo 'Paper inválido.';
            return;
        }

        try {
            $paper = $this->repository->find($id);
            if (!is_array($paper)) {
                throw new RuntimeException('Publicação não encontrada.');
            }

            $resolved = $this->fileService->resolve($paper);
            $mode = $this->fileService->previewMode($resolved);

            if ($mode === 'external') {
                $this->fileService->redirectExternal($resolved);
            }

            if ($mode === 'pdf') {
                $this->fileService->streamInline($resolved);
            }

            $preview = $this->fileService->buildPreview($paper, $resolved);

            if (in_array($mode, ['binary_office', 'binary'], true)) {
                $this->fileService->streamInline($resolved);
            }

            View::render('papers/file_preview', [
                'pageTitle' => 'Visualizar arquivo',
                'contentTitle' => 'Bibliografia',
                'subtitle' => 'ProdCol',
                'user' => $this->auth->user(),
                'paper' => $paper,
                'preview' => $preview,
            ]);
        } catch (RuntimeException $exception) {
            View::render('papers/file_preview', [
                'pageTitle' => 'Visualizar arquivo',
                'contentTitle' => 'Bibliografia',
                'subtitle' => 'ProdCol',
                'user' => $this->auth->user(),
                'paper' => null,
                'preview' => [
                    'mode' => 'error',
                    'message' => $exception->getMessage(),
                    'title' => 'Arquivo não disponível',
                    'file_name' => '',
                ],
            ]);
        }
    }

    public function form(): void
    {
        $this->auth->requireAuth();

        if ($this->request->method() === 'POST') {
            $this->handleSave();
            return;
        }

        $id = (int) ($this->request->query('id', '0') ?? 0);
        $notice = $this->request->query('notice');
        $error = null;
        $paper = [
            'id' => 0,
            'title' => '',
            'journal' => '',
            'key_insight' => '',
            'citation_count' => 0,
            'keywords' => '',
            'link_url' => '',
            'file_source_type' => '',
            'file_source_value' => '',
            'file_enabled' => 1,
            'file_preferred_name' => '',
            'file_preferred_mime' => '',
            'prompt_code' => '',
            'chapter_code' => '',
        ];

        try {
            if ($id > 0) {
                $paper = $this->repository->find($id) ?? $paper;
                if ((int) ($paper['id'] ?? 0) <= 0) {
                    $error = 'Publicação não encontrada.';
                }
            }

            $promptContext = (int) ($paper['id'] ?? 0) > 0 ? $this->promptFlowRepository->paperContext($paper) : null;
        } catch (RuntimeException $exception) {
            $promptContext = null;
            $error = $exception->getMessage();
        }

        View::render('papers/form', [
            'pageTitle' => $id > 0 ? 'Editar Paper' : 'Novo Paper',
            'contentTitle' => 'Bibliografia',
            'subtitle' => 'ProdCol',
            'user' => $this->auth->user(),
            'paper' => $paper,
            'paperIndicators' => (int) ($paper['id'] ?? 0) > 0 ? $this->paperIndicators($paper) : [],
            'promptContext' => $promptContext,
            'notice' => $notice !== null && $notice !== '' ? $notice : null,
            'error' => $error,
        ]);
    }

    private function paperMatchesRagFilter(array $paper, string $rag): bool
    {
        return match ($rag) {
            'with_cache' => !empty($paper['has_cache']),
            'without_cache' => empty($paper['has_cache']),
            'openai' => !empty($paper['has_openai_file']),
            'vector' => !empty($paper['has_vector_store']),
            'used' => (int) ($paper['usage_count'] ?? 0) > 0,
            'error' => in_array((string) ($paper['cache_status'] ?? ''), ['error', 'failed'], true),
            default => true,
        };
    }

    private function handleSave(): void
    {
        if (!check_csrf($this->request->input('_csrf'))) {
            http_response_code(400);
            echo 'CSRF inválido';
            return;
        }

        try {
            $id = $this->repository->save([
                'id' => $this->request->input('id', '0'),
                'title' => $this->request->input('title', ''),
                'journal' => $this->request->input('journal', ''),
                'key_insight' => $this->request->input('key_insight', ''),
                'citation_count' => $this->request->input('citation_count', '0'),
                'keywords' => $this->request->input('keywords', ''),
                'link_url' => $this->request->input('link_url', ''),
                'file_source_type' => $this->request->input('file_source_type', ''),
                'file_source_value' => $this->request->input('file_source_value', ''),
                'file_enabled' => $this->request->has('file_enabled') ? '1' : null,
                'file_preferred_name' => $this->request->input('file_preferred_name', ''),
                'file_preferred_mime' => $this->request->input('file_preferred_mime', ''),
                'prompt_code' => $this->request->input('prompt_code', ''),
                'chapter_code' => $this->request->input('chapter_code', ''),
            ]);

            redirect(url('papers/view.php?id=' . rawurlencode((string) $id) . '&notice=' . rawurlencode('Registro salvo com sucesso.')));
        } catch (RuntimeException $exception) {
            redirect(url('papers/form.php?notice=' . rawurlencode($exception->getMessage())));
        }
    }

    private function handleDelete(): void
    {
        if (!check_csrf($this->request->input('_csrf'))) {
            http_response_code(400);
            echo 'CSRF inválido';
            return;
        }

        try {
            $id = $this->request->input('id', '0');
            $this->repository->delete($id);
            redirect(url('papers/index.php?notice=' . rawurlencode('Registro excluído com sucesso.')));
        } catch (RuntimeException $exception) {
            redirect(url('papers/index.php?notice=' . rawurlencode($exception->getMessage())));
        }
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<string, int>
     */
    private function summarizeRows(array $rows): array
    {
        $summary = $this->emptySummary();

        $summary['total'] = count($rows);

        foreach ($rows as $row) {
            if (!empty($row['has_cache'])) {
                $summary['com_cache']++;
            } else {
                $summary['sem_cache']++;
            }

            if (!empty($row['has_openai_file'])) {
                $summary['openai']++;
            }

            if (!empty($row['has_vector_store'])) {
                $summary['vetorizados']++;
            }

            if ((int) ($row['usage_count'] ?? 0) > 0) {
                $summary['usados_em_prompt']++;
            }

            if (trim((string) ($row['prompt_code'] ?? '')) !== '') {
                $summary['com_prompt']++;
            }

            if (trim((string) ($row['chapter_code'] ?? '')) !== '') {
                $summary['com_capitulo']++;
            }

            if (in_array(strtolower((string) ($row['cache_status'] ?? '')), ['error', 'failed'], true)) {
                $summary['com_erro']++;
            }

            if ((string) ($row['exists_flag'] ?? '') === '0' || (isset($row['exists_flag']) && (int) $row['exists_flag'] === 0)) {
                $summary['cache_ausente']++;
            }
        }

        return $summary;
    }

    /**
     * @return array<string, int>
     */
    private function emptySummary(): array
    {
        return [
            'total' => 0,
            'com_cache' => 0,
            'sem_cache' => 0,
            'openai' => 0,
            'vetorizados' => 0,
            'usados_em_prompt' => 0,
            'com_prompt' => 0,
            'com_capitulo' => 0,
            'com_erro' => 0,
            'cache_ausente' => 0,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function paperIndicators(array $paper): array
    {
        $cacheStatus = strtolower(trim((string) ($paper['cache_status'] ?? '')));
        $existsFlag = (string) ($paper['exists_flag'] ?? '') !== '' ? (int) $paper['exists_flag'] : null;

        return [
            [
                'label' => 'Cadastro',
                'value' => (int) ($paper['id'] ?? 0) > 0 ? 'OK' : 'Pendente',
                'tone' => (int) ($paper['id'] ?? 0) > 0 ? 'success' : 'muted',
            ],
            [
                'label' => 'Cache local',
                'value' => !empty($paper['has_cache']) ? 'Sim' : 'Não',
                'tone' => !empty($paper['has_cache']) ? 'info' : 'muted',
            ],
            [
                'label' => 'Arquivo OpenAI',
                'value' => !empty($paper['has_openai_file']) ? 'Sim' : 'Não',
                'tone' => !empty($paper['has_openai_file']) ? 'info' : 'muted',
            ],
            [
                'label' => 'Vector store',
                'value' => !empty($paper['has_vector_store']) ? 'Sim' : 'Não',
                'tone' => !empty($paper['has_vector_store']) ? 'success' : 'muted',
            ],
            [
                'label' => 'Usado em prompts',
                'value' => (int) ($paper['usage_count'] ?? 0) > 0 ? 'Sim' : 'Não',
                'tone' => (int) ($paper['usage_count'] ?? 0) > 0 ? 'success' : 'muted',
            ],
            [
                'label' => 'Cache pronto',
                'value' => $cacheStatus === 'ready' ? 'Sim' : ($cacheStatus !== '' ? strtoupper($cacheStatus) : '—'),
                'tone' => $cacheStatus === 'ready' ? 'success' : ($cacheStatus === 'error' || $cacheStatus === 'failed' ? 'danger' : 'warning'),
            ],
            [
                'label' => 'Cache ausente',
                'value' => $existsFlag === 0 ? 'Sim' : 'Não',
                'tone' => $existsFlag === 0 ? 'danger' : 'muted',
            ],
            [
                'label' => 'Prompt vinculado',
                'value' => trim((string) ($paper['prompt_code'] ?? '')) !== '' ? 'Sim' : 'Não',
                'tone' => trim((string) ($paper['prompt_code'] ?? '')) !== '' ? 'info' : 'muted',
            ],
        ];
    }
}
