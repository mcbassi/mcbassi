<?php
declare(strict_types=1);

namespace App\Artifacts;

final class ArtifactRenderService
{
    public function toHtml(string $contentType, string $payload, string $title, array $meta = []): string
    {
        $body = match ($contentType) {
            'json' => $this->jsonToHtml($payload),
            'markdown' => $this->markdownToHtml($payload),
            'text' => $this->textToHtml($payload),
            'html' => $payload,
            default => throw new \RuntimeException('content_type não suportado para renderização HTML: ' . $contentType),
        };

        return $this->wrapDocument($title, $body, $meta);
    }

    public function wrapDocument(string $title, string $body, array $meta = []): string
    {
        $metaLines = [];

        foreach ($meta as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $metaLines[] = '<strong>' . $this->esc((string) $key) . ':</strong> ' . $this->esc((string) $value);
        }

        $metaHtml = $metaLines !== []
            ? '<div class="meta">' . implode(' &nbsp; | &nbsp; ', $metaLines) . '</div>'
            : '';

        return '<!doctype html><html lang="es"><head><meta charset="utf-8"><title>' . $this->esc($title) . '</title>'
            . '<style>body{font-family:DejaVu Sans,Arial,sans-serif;font-size:12px;color:#222;margin:28px;}h1,h2,h3{color:#1f3b64;}h1{font-size:20px;margin:0 0 6px 0;}h2{font-size:16px;margin-top:18px;}h3{font-size:14px;margin-top:14px;}p{line-height:1.45;margin:8px 0;}ul,ol{margin:8px 0 8px 20px;}table{border-collapse:collapse;width:100%;margin:10px 0;}th,td{border:1px solid #ccc;padding:6px;vertical-align:top;}thead th{background:#f5f7fb;}.meta{font-size:11px;color:#555;margin-bottom:16px;border-bottom:1px solid #ddd;padding-bottom:10px;}</style>'
            . '</head><body><h1>' . $this->esc($title) . '</h1>' . $metaHtml . $body . '</body></html>';
    }

    private function jsonToHtml(string $jsonText): string
    {
        $data = json_decode($jsonText, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return '<pre>' . $this->esc($jsonText) . '</pre>';
        }

        return $this->jsonValueToHtml($data);
    }

    private function jsonValueToHtml(mixed $value): string
    {
        if (!is_array($value)) {
            if ($value === null) {
                return '<span class="text-muted">null</span>';
            }

            if (is_bool($value)) {
                return $value ? 'true' : 'false';
            }

            return nl2br($this->esc((string) $value));
        }

        if ($value === []) {
            return '<div class="text-muted">Sem dados.</div>';
        }

        if ($this->isList($value) && $this->allItemsAreAssoc($value)) {
            $columns = $this->collectColumns($value);

            $html = '<table><thead><tr>';
            foreach ($columns as $col) {
                $html .= '<th>' . $this->esc((string) $col) . '</th>';
            }
            $html .= '</tr></thead><tbody>';

            foreach ($value as $row) {
                $html .= '<tr>';
                foreach ($columns as $col) {
                    $html .= '<td>' . $this->jsonValueToHtml($row[$col] ?? null) . '</td>';
                }
                $html .= '</tr>';
            }

            $html .= '</tbody></table>';
            return $html;
        }

        if ($this->isAssoc($value)) {
            $html = '<table><thead><tr><th>Campo</th><th>Valor</th></tr></thead><tbody>';

            foreach ($value as $key => $item) {
                $html .= '<tr>';
                $html .= '<th style="width:260px;">' . $this->esc((string) $key) . '</th>';
                $html .= '<td>' . $this->jsonValueToHtml($item) . '</td>';
                $html .= '</tr>';
            }

            $html .= '</tbody></table>';
            return $html;
        }

        $html = '<ul>';
        foreach ($value as $item) {
            $html .= '<li>' . $this->jsonValueToHtml($item) . '</li>';
        }
        $html .= '</ul>';

        return $html;
    }

    private function markdownToHtml(string $text): string
    {
        $lines = preg_split('/\R/', $text) ?: [];
        $html = '';
        $inUl = false;
        $inOl = false;

        foreach ($lines as $line) {
            $line = rtrim($line);

            if (trim($line) === '') {
                if ($inUl) {
                    $html .= '</ul>';
                    $inUl = false;
                }
                if ($inOl) {
                    $html .= '</ol>';
                    $inOl = false;
                }
                continue;
            }

            if (preg_match('/^###\s+(.+)$/', $line, $m)) {
                if ($inUl) { $html .= '</ul>'; $inUl = false; }
                if ($inOl) { $html .= '</ol>'; $inOl = false; }
                $html .= '<h3>' . $this->inlineMarkdown($m[1]) . '</h3>';
                continue;
            }

            if (preg_match('/^##\s+(.+)$/', $line, $m)) {
                if ($inUl) { $html .= '</ul>'; $inUl = false; }
                if ($inOl) { $html .= '</ol>'; $inOl = false; }
                $html .= '<h2>' . $this->inlineMarkdown($m[1]) . '</h2>';
                continue;
            }

            if (preg_match('/^#\s+(.+)$/', $line, $m)) {
                if ($inUl) { $html .= '</ul>'; $inUl = false; }
                if ($inOl) { $html .= '</ol>'; $inOl = false; }
                $html .= '<h1>' . $this->inlineMarkdown($m[1]) . '</h1>';
                continue;
            }

            if (preg_match('/^\s*[-•]\s+(.+)$/u', $line, $m)) {
                if ($inOl) {
                    $html .= '</ol>';
                    $inOl = false;
                }
                if (!$inUl) {
                    $html .= '<ul>';
                    $inUl = true;
                }
                $html .= '<li>' . $this->inlineMarkdown($m[1]) . '</li>';
                continue;
            }

            if (preg_match('/^\s*\d+[\.)]\s+(.+)$/u', $line, $m)) {
                if ($inUl) {
                    $html .= '</ul>';
                    $inUl = false;
                }
                if (!$inOl) {
                    $html .= '<ol>';
                    $inOl = true;
                }
                $html .= '<li>' . $this->inlineMarkdown($m[1]) . '</li>';
                continue;
            }

            if ($inUl) {
                $html .= '</ul>';
                $inUl = false;
            }
            if ($inOl) {
                $html .= '</ol>';
                $inOl = false;
            }

            $html .= '<p>' . $this->inlineMarkdown($line) . '</p>';
        }

        if ($inUl) {
            $html .= '</ul>';
        }
        if ($inOl) {
            $html .= '</ol>';
        }

        return $html;
    }

    private function textToHtml(string $text): string
    {
        $parts = preg_split('/\R\R+/', trim($text)) ?: [];
        if ($parts === []) {
            return '<div class="text-muted">Sem conteúdo.</div>';
        }

        $html = '';
        foreach ($parts as $part) {
            $html .= '<p>' . nl2br($this->esc(trim($part))) . '</p>';
        }

        return $html;
    }

    private function inlineMarkdown(string $text): string
    {
        $text = $this->esc($text);
        $text = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $text) ?? $text;
        $text = preg_replace('/\*(.+?)\*/s', '<em>$1</em>', $text) ?? $text;
        $text = preg_replace('/`(.+?)`/s', '<code>$1</code>', $text) ?? $text;

        return $text;
    }

    private function esc(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }

    private function isAssoc(array $arr): bool
    {
        return array_keys($arr) !== range(0, count($arr) - 1);
    }

    private function isList(array $arr): bool
    {
        return array_keys($arr) === range(0, count($arr) - 1);
    }

    private function allItemsAreAssoc(array $rows): bool
    {
        foreach ($rows as $row) {
            if (!is_array($row) || !$this->isAssoc($row)) {
                return false;
            }
        }
        return $rows !== [];
    }

    private function collectColumns(array $rows): array
    {
        $cols = [];
        foreach ($rows as $row) {
            foreach (array_keys($row) as $key) {
                $cols[$key] = true;
            }
        }
        return array_keys($cols);
    }
}