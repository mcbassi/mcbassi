<?php
declare(strict_types=1);

namespace App\TelaSql;

use App\Auth\AuthService;
use App\Infra\Database;
use App\Security\Csrf;
use App\Support\Response;
use App\Support\View;
use RuntimeException;

final class SqlController
{
    private SqlExecutionService $execution;
    private SqlCatalogRepository $catalog;
    private SchemaService $schema;
    private SqlGuard $guard;

    public function __construct(
        private readonly AuthService $auth,
        Database $database
    ) {
        $this->guard = new SqlGuard();
        $this->execution = new SqlExecutionService($database, $this->guard);
        $this->catalog = new SqlCatalogRepository($database);
        $this->schema = new SchemaService($database);
    }

    public function index(): void
    {
        $this->page();
    }

    public function page(): void
    {
        $this->auth->requireAuth();
        View::render('tela_sql/index', [
            'user' => $this->auth->user(),
            'pageTitle' => 'SQL Sentences',
            'contentTitle' => 'SQL Sentences',
        ]);
    }

    public function embed(): void
    {
        $this->page();
    }

    public function validate(string $sql): never
    {
        $this->auth->requireAuth();
        $result = $this->guard->validate($sql);
        $result['named_params'] = $result['ok'] ? $this->guard->extractNamedParams($sql) : [];
        Response::json($result, $result['ok'] ? 200 : 422);
    }

    /** @param array<string,mixed> $payload */
    public function execute(string|array $payload): never
    {
        $this->auth->requireAuth();
        try {
            $payload = is_array($payload) ? $payload : ['sql' => $payload];
            $sql = (string) ($payload['sql'] ?? '');
            $params = is_array($payload['params'] ?? null) ? $payload['params'] : [];
            $mode = trim((string) ($payload['mode'] ?? 'screen'));
            $limit = (int) ($payload['limit'] ?? 200);
            $offset = (int) ($payload['offset'] ?? 0);
            Response::json($this->execution->execute($sql, $params, $mode, $limit, $offset));
        } catch (RuntimeException $exception) {
            Response::json([
                'ok' => false,
                'error' => $exception->getMessage(),
            ], 422);
        }
    }

    public function schema(): never
    {
        $this->auth->requireAuth();
        Response::json($this->schema->inspect());
    }

    public function catalogList(string $search = '', bool $onlyActive = true): never
    {
        $this->auth->requireAuth();
        Response::json([
            'ok' => true,
            'items' => $this->catalog->all($search, $onlyActive),
        ]);
    }

    public function catalogGet(string $slug): never
    {
        $this->auth->requireAuth();
        $item = $this->catalog->getBySlug($slug);
        if ($item === null) {
            Response::json(['ok' => false, 'error' => 'Não encontrado'], 404);
        }
        Response::json(['ok' => true] + $item);
    }

    /** @param array<string,mixed> $payload */
    public function catalogSave(array $payload): never
    {
        $this->auth->requireAuth();
        Csrf::requireValid($payload['_csrf'] ?? $payload['csrf_token'] ?? null);

        $validation = $this->guard->validate((string) ($payload['sql'] ?? ''));
        if (!$validation['ok']) {
            Response::json(['ok' => false, 'errors' => $validation['errors'], 'warnings' => $validation['warnings']], 422);
        }

        try {
            $record = $this->catalog->save($payload);
            Response::json(['ok' => true] + $record);
        } catch (RuntimeException $exception) {
            Response::json(['ok' => false, 'error' => $exception->getMessage()], 422);
        }
    }
}
