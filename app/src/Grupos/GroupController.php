<?php
declare(strict_types=1);

namespace App\Grupos;

use App\Auth\AuthService;
use App\Support\Request;
use App\Support\View;
use RuntimeException;

final class GroupController
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly GroupRepository $repository,
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

        $this->renderPage();
    }

    private function handlePost(): void
    {
        if (!\check_csrf($this->request->input('_csrf'))) {
            flash_set('groups_error', 'CSRF inválido.');
            redirect(\url('grupos/index.php'));
        }

        $action = trim((string) ($this->request->input('action') ?? 'save'));
        $id = (int) ($this->request->input('id_grupo') ?? 0);
        $name = trim((string) ($this->request->input('nome_grupo') ?? ''));
        $promptGrp = trim((string) ($this->request->input('prompt_grp') ?? ''));
        $picked = $_POST['questions'] ?? [];
        if (!is_array($picked)) {
            $picked = [];
        }

        try {
            if ($action === 'create') {
                $id = $this->repository->createGroup($name, $promptGrp, array_map('strval', $picked));
                flash_set('groups_success', 'Grupo salvo com sucesso.');
                redirect(\url('grupos/index.php?id=' . $id));
            }

            if ($action === 'save') {
                $this->repository->updateGroup($id, $name, $promptGrp, array_map('strval', $picked));
                flash_set('groups_success', 'Grupo salvo com sucesso.');
                redirect(\url('grupos/index.php?id=' . $id));
            }

            if ($action === 'delete') {
                $this->repository->deleteGroup($id);
                flash_set('groups_success', 'Grupo apagado com sucesso.');
                redirect(\url('grupos/index.php?deleted=1'));
            }

            throw new RuntimeException('Ação inválida.');
        } catch (RuntimeException $exception) {
            flash_set('groups_error', $exception->getMessage());
            $suffix = $id > 0 ? '?id=' . $id : '';
            redirect(\url('grupos/index.php' . $suffix));
        }
    }

    private function renderPage(): void
    {
        try {
            $groups = $this->repository->fetchGroups();
            $questions = $this->repository->fetchAllQuestions();
            $selectedId = (int) ($this->request->query('id', '0') ?? 0);

            $editing = $selectedId > 0;
            $currGroup = $editing ? $this->repository->fetchGroup($selectedId) : null;
            if ($editing && $currGroup === null) {
                $editing = false;
                $selectedId = 0;
            }

            $currPickedSet = $editing ? $this->repository->fetchGroupQuestions($selectedId) : [];
            $currName = $editing ? (string) ($currGroup['name'] ?? '') : '';
            $currPromptGrp = $editing ? (string) ($currGroup['prompt_grp'] ?? '') : '';

            View::render('grupos/index', [
                'user' => $this->auth->user(),
                'groups' => $groups,
                'questions' => $questions,
                'selectedId' => $selectedId,
                'editing' => $editing,
                'currName' => $currName,
                'currPromptGrp' => $currPromptGrp,
                'currPickedSet' => $currPickedSet,
                'success' => flash_get('groups_success'),
                'error' => flash_get('groups_error'),
                'pageTitle' => 'Editar Grupos',
                'contentTitle' => 'Editar Grupos',
                'subtitle' => 'ProdCol',
            ]);
        } catch (RuntimeException $exception) {
            View::render('grupos/index', [
                'user' => $this->auth->user(),
                'groups' => [],
                'questions' => [],
                'selectedId' => 0,
                'editing' => false,
                'currName' => '',
                'currPromptGrp' => '',
                'currPickedSet' => [],
                'success' => flash_get('groups_success'),
                'error' => $exception->getMessage(),
                'pageTitle' => 'Editar Grupos',
                'contentTitle' => 'Editar Grupos',
                'subtitle' => 'ProdCol',
            ]);
        }
    }
}
