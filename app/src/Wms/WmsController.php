<?php
declare(strict_types=1);

namespace App\Wms;

use App\Auth\AuthService;
use App\Infra\Database;
use App\Support\Request;
use App\Support\View;
use RuntimeException;

final class WmsController
{
    private WmsDashboardService $service;

    public function __construct(
        private readonly AuthService $auth,
        private readonly Database $database,
        private readonly Request $request
    ) {
        $this->service = new WmsDashboardService(new WmsRepository($database));
    }

    public function dashboard(): void
    {
        $this->auth->requireAuth();

        View::render('wms/dashboard', [
            'user' => $this->auth->user(),
            'pageTitle' => 'WMS - BI',
            'contentTitle' => 'WMS - BI (statistics)',
            'subtitle' => 'ProdCol',
        ]);
    }

    public function dashboardApi(): void
    {
        $this->auth->requireAuth();

        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

        $token = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if (!\check_csrf($token)) {
            $this->json(['ok' => false, 'error' => 'CSRF inválido ou ausente. Recarregue a página.'], 403);
        }

        $raw = file_get_contents('php://input') ?: '';
        $input = json_decode($raw, true);
        if (!is_array($input)) {
            $this->json(['ok' => false, 'error' => 'JSON inválido.'], 400);
        }

        $tab = trim((string) ($input['tab'] ?? ''));
        if ($tab === '') {
            $this->json(['ok' => false, 'error' => 'tab requerido.'], 400);
        }

        try {
            $this->json($this->service->handle($tab, $input));
        } catch (RuntimeException $exception) {
            $this->json(['ok' => false, 'error' => $exception->getMessage()], 400);
        } catch (\Throwable $throwable) {
            $this->json(['ok' => false, 'error' => 'EXCEPTION: ' . $throwable->getMessage()], 500);
        }
    }

    private function json(array $payload, int $status = 200): never
    {
        http_response_code($status);
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
