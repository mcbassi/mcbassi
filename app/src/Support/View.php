<?php
declare(strict_types=1);

namespace App\Support;

final class View
{
    public static function render(string $view, array $data = [], string $layout = 'layouts/app'): void
    {
        $viewFile = dirname(__DIR__, 2) . '/views/' . $view . '.php';
        $effectiveLayout = self::isEmbedRequest() ? 'layouts/embed' : $layout;
        $layoutFile = dirname(__DIR__, 2) . '/views/' . $effectiveLayout . '.php';

        if (!is_file($viewFile)) {
            http_response_code(500);
            echo 'View não encontrada: ' . htmlspecialchars($view, ENT_QUOTES, 'UTF-8');
            return;
        }

        extract($data, EXTR_SKIP);

        ob_start();
        require $viewFile;
        $content = (string) ob_get_clean();

        $pageTitle = (string) ($data['pageTitle'] ?? 'Lab Produtividad');
        $translatedPageTitle = I18n::get($pageTitle, [], $pageTitle);
        $safeTitle = preg_replace('/[\r\n]+/', ' ', $translatedPageTitle) ?: 'Lab Produtividad';

        header('X-Page-Title: ' . $safeTitle);
        header('X-Current-Path: ' . current_path());
        header('Vary: X-Shell-Partial');

        if (self::isShellPartialRequest()) {
            echo I18n::translateHtml($content);
            return;
        }

        require $layoutFile;
    }

    private static function isShellPartialRequest(): bool
    {
        $header = (string) ($_SERVER['HTTP_X_SHELL_PARTIAL'] ?? '');
        $query = (string) ($_GET['_partial'] ?? '');

        return $header === '1' || $query === '1';
    }

    private static function isEmbedRequest(): bool
    {
        $query = (string) ($_GET['embed'] ?? '');
        $header = (string) ($_SERVER['HTTP_X_CLIENT_EMBED'] ?? '');

        return $query === '1' || $header === '1';
    }
}
