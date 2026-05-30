<?php
declare(strict_types=1);

namespace App\Papers;

use App\Auth\AuthService;
use App\Support\Request;
use App\Support\View;
use RuntimeException;

final class PaperImportController
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly PaperImportService $importService,
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

        $path = $this->request->query('path', '') ?? '';
        $notice = $this->request->query('notice');
        $error = null;

        try {
            $config = $this->importService->config($path !== '' ? $path : null);
            $preview = $config['base_path'] !== '' && is_dir((string) $config['base_path'])
                ? $this->importService->preview($path !== '' ? $path : null)
                : [];
        } catch (RuntimeException $exception) {
            $config = $this->importService->config($path !== '' ? $path : null);
            $preview = [];
            $error = $exception->getMessage();
        }

        View::render('papers/import', [
            'pageTitle' => 'Importar bibliografia',
            'contentTitle' => 'Importador real de bibliografia',
            'subtitle' => 'ProdCol',
            'user' => $this->auth->user(),
            'config' => $config,
            'preview' => $preview,
            'notice' => $notice !== null && $notice !== '' ? $notice : null,
            'error' => $error,
        ]);
    }

    private function handlePost(): void
    {
        if (!check_csrf($this->request->input('_csrf'))) {
            http_response_code(400);
            echo 'CSRF inválido';
            return;
        }

        $path = $this->request->input('path', '') ?? '';
        $action = $this->request->input('action', 'preview') ?? 'preview';

        try {
            if ($action === 'import') {
                $report = $this->importService->import($path !== '' ? $path : null);
                $message = sprintf(
                    'Importação concluída: %d criados, %d atualizados, %d ignorados.',
                    (int) ($report['created'] ?? 0),
                    (int) ($report['updated'] ?? 0),
                    (int) ($report['skipped'] ?? 0)
                );
                redirect(url('papers/import.php?notice=' . rawurlencode($message)));
            }

            redirect(url('papers/import.php?path=' . rawurlencode($path)));
        } catch (RuntimeException $exception) {
            redirect(url('papers/import.php?path=' . rawurlencode($path) . '&notice=' . rawurlencode($exception->getMessage())));
        }
    }
}
