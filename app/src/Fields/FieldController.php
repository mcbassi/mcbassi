<?php
declare(strict_types=1);

namespace App\Fields;

use App\Auth\AuthService;
use App\Support\Request;
use App\Support\View;
use RuntimeException;

final class FieldController
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly FieldRepository $repository,
        private readonly Request $request
    ) {
    }

    public function index(): void
    {
        $this->auth->requireAuth();
        $this->renderIndex();
    }

    public function form(): void
    {
        $this->auth->requireAuth();
        if ($this->request->method() === 'POST') {
            $this->save();
            return;
        }
        $this->renderForm();
    }

    public function save(): void
    {
        $this->auth->requireAuth();
        if (!\check_csrf($this->request->input('_csrf'))) {
            flash_set('fields_error', 'CSRF inválido.');
            redirect(\url('fields/index.php'));
        }

        $payload = ['id' => (int)($this->request->input('id') ?? 0)];
        foreach ($this->repository->editableColumns() as $column) {
            $payload[$column] = $_POST[$column] ?? null;
        }

        try {
            $id = $this->repository->save($payload);
            flash_set('fields_success', 'Campo salvo com sucesso.');
            redirect(\url('fields/form.php?id='.$id));
        } catch (RuntimeException $e) {
            flash_set('fields_error', $e->getMessage());
            $suffix = $payload['id'] > 0 ? '?id='.$payload['id'] : '';
            redirect(\url('fields/form.php'.$suffix));
        }
    }

    public function delete(): void
    {
        $this->auth->requireAuth();
        if (!\check_csrf($this->request->input('_csrf'))) {
            flash_set('fields_error', 'CSRF inválido.');
            redirect(\url('fields/index.php'));
        }
        try {
            $this->repository->delete((int)($this->request->input('id') ?? 0));
            flash_set('fields_success', 'Campo removido com sucesso.');
        } catch (RuntimeException $e) {
            flash_set('fields_error', $e->getMessage());
        }
        redirect(\url('fields/index.php'));
    }

    public function importFromArray(): void
    {
        $this->auth->requireAuth();
        if (!\check_csrf($this->request->input('_csrf'))) {
            flash_set('fields_error', 'CSRF inválido.');
            redirect(\url('fields/index.php'));
        }
        try {
            $count = $this->repository->importRows((string)($_POST['import_json'] ?? ''));
            flash_set('fields_success', 'Importação concluída: '.$count.' registro(s).');
        } catch (RuntimeException $e) {
            flash_set('fields_error', $e->getMessage());
        }
        redirect(\url('fields/index.php'));
    }

    private function renderIndex(): void
    {
        try {
            $filters = [
                'q' => trim((string)($this->request->query('q', '') ?? '')),
                'section' => trim((string)($this->request->query('section', '') ?? '')),
                'type' => trim((string)($this->request->query('type', '') ?? '')),
            ];

            View::render('fields/index', [
                'rows' => $this->repository->all($filters),
                'schema' => $this->repository->schema(),
                'filters' => $filters,
                'stats' => $this->repository->stats(),
                'filterOptions' => $this->repository->filterOptions(),
                'success' => flash_get('fields_success'),
                'error' => flash_get('fields_error'),
                'pageTitle' => 'Configurar Questionário',
                'contentTitle' => 'Configurar Questionário',
                'subtitle' => 'ProdCol',
            ]);
        } catch (RuntimeException $e) {
            View::render('fields/index', [
                'rows' => [],
                'schema' => [],
                'filters' => [],
                'stats' => ['total' => 0, 'sections' => 0, 'with_prompt' => 0, 'questions' => 0],
                'filterOptions' => ['sections' => [], 'types' => []],
                'success' => flash_get('fields_success'),
                'error' => $e->getMessage(),
                'pageTitle' => 'Configurar Questionário',
                'contentTitle' => 'Configurar Questionário',
                'subtitle' => 'ProdCol',
            ]);
        }
    }

    private function renderForm(): void
    {
        $id = (int)($this->request->query('id', '0') ?? 0);
        try {
            $record = $id > 0 ? $this->repository->find($id) : null;
            if ($id > 0 && $record === null) {
                throw new RuntimeException('Campo não encontrado.');
            }

            View::render('fields/form', [
                'record' => is_array($record) ? $record : [],
                'schema' => $this->repository->schema(),
                'editableColumns' => $this->repository->editableColumns(),
                'success' => flash_get('fields_success'),
                'error' => flash_get('fields_error'),
                'pageTitle' => $id > 0 ? 'Editar Campo do Questionário' : 'Novo Campo do Questionário',
                'contentTitle' => $id > 0 ? 'Editar Campo do Questionário' : 'Novo Campo do Questionário',
                'subtitle' => 'ProdCol',
            ]);
        } catch (RuntimeException $e) {
            View::render('fields/form', [
                'record' => [],
                'schema' => [],
                'editableColumns' => [],
                'success' => flash_get('fields_success'),
                'error' => $e->getMessage(),
                'pageTitle' => 'Configurar Questionário',
                'contentTitle' => 'Configurar Questionário',
                'subtitle' => 'ProdCol',
            ]);
        }
    }
}
