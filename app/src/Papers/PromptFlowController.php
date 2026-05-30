<?php
declare(strict_types=1);

namespace App\Papers;

use App\Auth\AuthService;
use App\Support\View;
use RuntimeException;

final class PromptFlowController
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly PaperRepository $repository,
        private readonly PromptFlowRepository $promptFlowRepository
    ) {
    }

    public function index(): void
    {
        $this->auth->requireAuth();

        $error = null;
        try {
            $papers = $this->repository->all();
            $overview = $this->promptFlowRepository->overview($papers);
        } catch (RuntimeException $exception) {
            $papers = [];
            $overview = [
                'stats' => [
                    'total_prompts' => 0,
                    'papers_with_prompt_code' => 0,
                    'prompt_codes_linked_to_catalog' => 0,
                    'prompt_usage_rows' => 0,
                ],
                'rows' => [],
                'recent_usage' => [],
                'availability' => $this->promptFlowRepository->availability(),
            ];
            $error = $exception->getMessage();
        }

        View::render('papers/prompts', [
            'pageTitle' => 'Fluxo de prompts',
            'contentTitle' => 'Papers × prompts × uso em IA',
            'subtitle' => 'ProdCol',
            'user' => $this->auth->user(),
            'overview' => $overview,
            'papers' => $papers,
            'error' => $error,
        ]);
    }
}
