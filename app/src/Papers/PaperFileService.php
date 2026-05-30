<?php
declare(strict_types=1);

namespace App\Papers;

use App\Infra\Env;
use RuntimeException;
use ZipArchive;
use DOMDocument;
use DOMXPath;

final class PaperFileService
{
    /**
     * @return array<string, mixed>
     */
    public function resolve(array $paper): array
    {
        $externalUrl = $this->firstExternalUrl($paper);
        if ($externalUrl !== null) {
            return [
                'kind' => 'external',
                'url' => $externalUrl,
                'label' => $this->preferredFileName($paper),
                'mime' => null,
                'ext' => null,
                'path' => null,
            ];
        }

        foreach ($this->candidatePaths($paper) as $candidate) {
            $path = $this->normalizeLocalPath($candidate);
            if ($path === '' || !is_file($path)) {
                continue;
            }

            $ext = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
            $mime = $this->detectMime($path, $paper, $ext);

            return [
                'kind' => 'local',
                'path' => $path,
                'ext' => $ext,
                'mime' => $mime,
                'label' => $this->preferredFileName($paper, basename($path)),
            ];
        }

        $fallback = $this->findByBasename($paper);
        if ($fallback !== null && is_file($fallback)) {
            $ext = strtolower((string) pathinfo($fallback, PATHINFO_EXTENSION));
            $mime = $this->detectMime($fallback, $paper, $ext);

            return [
                'kind' => 'local',
                'path' => $fallback,
                'ext' => $ext,
                'mime' => $mime,
                'label' => $this->preferredFileName($paper, basename($fallback)),
            ];
        }

        throw new RuntimeException('Arquivo não localizado para esta publicação.');
    }

    public function previewMode(array $resolved): string
    {
        if (($resolved['kind'] ?? '') === 'external') {
            return 'external';
        }

        $ext = strtolower((string) ($resolved['ext'] ?? ''));
        $mime = strtolower((string) ($resolved['mime'] ?? ''));

        if ($ext === 'pdf' || str_contains($mime, 'pdf')) {
            return 'pdf';
        }

        if (in_array($ext, ['docx'], true)) {
            return 'docx';
        }

        if (in_array($ext, ['xlsx', 'xlsm'], true)) {
            return 'xlsx';
        }

        if (in_array($ext, ['csv', 'txt'], true) || str_starts_with($mime, 'text/')) {
            return 'text';
        }

        if (in_array($ext, ['doc', 'xls'], true)) {
            return 'binary_office';
        }

        return 'binary';
    }

    public function redirectExternal(array $resolved): never
    {
        $url = (string) ($resolved['url'] ?? '');
        if ($url === '') {
            throw new RuntimeException('Link externo inválido.');
        }

        header('Location: ' . $url, true, 302);
        exit;
    }

    public function streamInline(array $resolved): never
    {
        $path = (string) ($resolved['path'] ?? '');
        if ($path === '' || !is_file($path)) {
            throw new RuntimeException('Arquivo não encontrado no disco.');
        }

        $mime = (string) ($resolved['mime'] ?? 'application/octet-stream');
        $label = $this->safeInlineName((string) ($resolved['label'] ?? basename($path)));

        header('Content-Type: ' . $mime);
        header('Content-Length: ' . (string) filesize($path));
        header('Content-Disposition: inline; filename="' . $label . '"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, max-age=0, no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');

        readfile($path);
        exit;
    }

    /**
     * @return array<string, mixed>
     */
    public function buildPreview(array $paper, array $resolved): array
    {
        $mode = $this->previewMode($resolved);

        return match ($mode) {
            'docx' => [
                'mode' => 'docx',
                'title' => (string) ($paper['title'] ?? $resolved['label'] ?? 'Documento Word'),
                'file_name' => (string) ($resolved['label'] ?? ''),
                'sections' => $this->previewDocx((string) $resolved['path']),
            ],
            'xlsx' => [
                'mode' => 'xlsx',
                'title' => (string) ($paper['title'] ?? $resolved['label'] ?? 'Planilha Excel'),
                'file_name' => (string) ($resolved['label'] ?? ''),
                'rows' => $this->previewSpreadsheet((string) $resolved['path']),
            ],
            'text' => [
                'mode' => 'text',
                'title' => (string) ($paper['title'] ?? $resolved['label'] ?? 'Arquivo de texto'),
                'file_name' => (string) ($resolved['label'] ?? ''),
                'content' => $this->previewText((string) $resolved['path']),
            ],
            default => [
                'mode' => 'binary',
                'title' => (string) ($paper['title'] ?? $resolved['label'] ?? 'Arquivo'),
                'file_name' => (string) ($resolved['label'] ?? ''),
                'message' => 'O navegador abriu o arquivo em modo somente leitura.',
            ],
        };
    }

    /**
     * @return array<int, string>
     */
    private function candidatePaths(array $paper): array
    {
        $candidates = [];
        $baseRoots = $this->candidateBaseRoots();

        $push = static function (array &$target, ?string $value): void {
            $text = trim((string) ($value ?? ''));
            if ($text === '') {
                return;
            }

            $text = html_entity_decode(rawurldecode($text), ENT_QUOTES, 'UTF-8');
            $target[] = $text;
        };

        foreach ([
            $paper['local_cache_path'] ?? null,
            $paper['local_file_path'] ?? null,
            $paper['cache_local_path'] ?? null,
        ] as $directPath) {
            $push($candidates, $directPath);
        }

        $sourceType = strtolower(trim((string) ($paper['file_source_type'] ?? $paper['source_type'] ?? '')));
        $sourceValue = trim((string) ($paper['file_source_value'] ?? $paper['source_value'] ?? ''));

        if ($sourceValue !== '') {
            $push($candidates, $sourceValue);

            if ($sourceType === 'relative_path' || $sourceType === '' || $sourceType === 'cloud_path') {
                foreach ($baseRoots as $baseRoot) {
                    $push($candidates, $this->joinPath($baseRoot, $sourceValue));
                }
            }

            if ($sourceType === 'local_path') {
                $push($candidates, $sourceValue);
            }
        }

        $linkUrl = trim((string) ($paper['link_url'] ?? ''));
        if ($linkUrl !== '' && !$this->looksLikeUrl($linkUrl)) {
            $push($candidates, $linkUrl);
            foreach ($baseRoots as $baseRoot) {
                $push($candidates, $this->joinPath($baseRoot, $linkUrl));
            }
        }

        $preferredName = trim((string) ($paper['file_preferred_name'] ?? ''));
        if ($preferredName !== '') {
            foreach ($baseRoots as $baseRoot) {
                $push($candidates, $this->joinPath($baseRoot, $preferredName));
            }
        }

        return array_values(array_unique($candidates));
    }

    private function firstExternalUrl(array $paper): ?string
    {
        foreach ([
            $paper['link_url'] ?? null,
            (($paper['file_source_type'] ?? null) === 'url') ? ($paper['file_source_value'] ?? null) : null,
            (($paper['source_type'] ?? null) === 'url') ? ($paper['source_value'] ?? null) : null,
        ] as $candidate) {
            $text = trim((string) ($candidate ?? ''));
            if ($this->looksLikeUrl($text)) {
                return $text;
            }
        }

        return null;
    }

    private function looksLikeUrl(string $value): bool
    {
        return $value !== '' && filter_var($value, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * @return array<int, string>
     */
    private function candidateBaseRoots(): array
    {
        $roots = [];
        $push = static function (array &$target, string $value): void {
            $value = trim($value);
            if ($value !== '') {
                $target[] = $value;
            }
        };

        $envBase = trim((string) Env::get('PAPER_IMPORT_BASE_PATH', ''));

        // A raiz oficial da bibliografia neste projeto é Bibliografia/upload.
        // Não tentamos raízes amplas para evitar caminhos falsos e perda de desempenho.
        if ($envBase !== '') {
            $push($roots, $envBase);
        } else {
            $push($roots, dirname(\app_path(), 2) . DIRECTORY_SEPARATOR . 'Bibliografia' . DIRECTORY_SEPARATOR . 'upload');
            $push($roots, dirname(\app_path()) . DIRECTORY_SEPARATOR . 'Bibliografia' . DIRECTORY_SEPARATOR . 'upload');
            $push($roots, \app_path('Bibliografia/upload'));
        }

        return array_values(array_unique(array_map([$this, 'normalizeLocalPath'], $roots)));
    }

    private function joinPath(string $base, string $relative): string
    {
        $relative = trim($relative, " \t\n\r\0\x0B\"'");
        if ($relative === '') {
            return $base;
        }

        $normalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative);

        if ($this->isAbsolutePath($normalized)) {
            return $normalized;
        }

        return rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($normalized, DIRECTORY_SEPARATOR);
    }

    private function isAbsolutePath(string $path): bool
    {
        return preg_match('#^(?:[A-Za-z]:\\\\|[A-Za-z]:/|/)#', $path) === 1;
    }

    private function normalizeLocalPath(string $path): string
    {
        $trimmed = trim($path, " \t\n\r\0\x0B\"'");
        if ($trimmed === '') {
            return '';
        }

        $normalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $trimmed);
        $normalized = preg_replace('#' . preg_quote(DIRECTORY_SEPARATOR, '#') . '+#', DIRECTORY_SEPARATOR, $normalized) ?: $normalized;
        $real = realpath($normalized);

        return $real !== false ? $real : $normalized;
    }

    private function detectMime(string $path, array $paper, string $ext): string
    {
        $preferredMime = trim((string) ($paper['file_preferred_mime'] ?? $paper['mime_type'] ?? ''));
        if ($preferredMime !== '') {
            return $preferredMime;
        }

        if (function_exists('mime_content_type')) {
            $detected = @mime_content_type($path);
            if (is_string($detected) && $detected !== '') {
                return $detected;
            }
        }

        return match ($ext) {
            'pdf' => 'application/pdf',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'xlsm' => 'application/vnd.ms-excel.sheet.macroEnabled.12',
            'doc' => 'application/msword',
            'xls' => 'application/vnd.ms-excel',
            'csv' => 'text/csv; charset=UTF-8',
            'txt' => 'text/plain; charset=UTF-8',
            default => 'application/octet-stream',
        };
    }

    private function preferredFileName(array $paper, ?string $fallback = null): string
    {
        foreach ([
            $paper['file_preferred_name'] ?? null,
            $paper['original_filename'] ?? null,
            $fallback,
            $paper['title'] ?? null,
        ] as $candidate) {
            $text = trim((string) ($candidate ?? ''));
            if ($text !== '') {
                return $text;
            }
        }

        return 'arquivo';
    }

    private function safeInlineName(string $name): string
    {
        $name = preg_replace('/[^\w.\- ]+/u', '_', $name) ?: 'arquivo';
        return trim($name) === '' ? 'arquivo' : $name;
    }

    private function findByBasename(array $paper): ?string
    {
        $preferredNames = array_values(array_unique(array_filter([
            trim((string) ($paper['file_preferred_name'] ?? '')),
            basename((string) ($paper['file_source_value'] ?? '')),
            basename((string) ($paper['local_cache_path'] ?? '')),
        ], static fn (string $value): bool => $value !== '')));

        if ($preferredNames === []) {
            return null;
        }

        foreach ($this->candidateBaseRoots() as $baseRoot) {
            if ($baseRoot === '' || !is_dir($baseRoot)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($baseRoot, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::LEAVES_ONLY
            );

            foreach ($iterator as $fileInfo) {
                if (!$fileInfo->isFile()) {
                    continue;
                }

                if (in_array($fileInfo->getFilename(), $preferredNames, true)) {
                    return $fileInfo->getPathname();
                }
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function previewDocx(string $path): array
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('A extensão ZIP do PHP não está habilitada para pré-visualizar arquivos DOCX.');
        }

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('Não foi possível abrir o DOCX para leitura.');
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if (!is_string($xml) || trim($xml) === '') {
            return [];
        }

        $dom = new DOMDocument();
        $dom->loadXML($xml, LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        $paragraphs = [];
        foreach ($xpath->query('//w:body/w:p') ?: [] as $paragraphNode) {
            $parts = [];
            foreach ($xpath->query('.//w:t', $paragraphNode) ?: [] as $textNode) {
                $parts[] = trim((string) $textNode->textContent);
            }

            $line = trim(implode(' ', array_filter($parts, static fn (string $part): bool => $part !== '')));
            if ($line !== '') {
                $paragraphs[] = $line;
            }

            if (count($paragraphs) >= 300) {
                break;
            }
        }

        return $paragraphs;
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function previewSpreadsheet(string $path): array
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('A extensão ZIP do PHP não está habilitada para pré-visualizar planilhas XLSX.');
        }

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('Não foi possível abrir a planilha para leitura.');
        }

        $sharedStrings = [];
        $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
        if (is_string($sharedXml) && trim($sharedXml) !== '') {
            $dom = new DOMDocument();
            $dom->loadXML($sharedXml, LIBXML_NOERROR | LIBXML_NOWARNING);
            foreach ($dom->getElementsByTagName('t') as $node) {
                $sharedStrings[] = (string) $node->textContent;
            }
        }

        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        if (!is_string($sheetXml) || trim($sheetXml) === '') {
            return [];
        }

        $dom = new DOMDocument();
        $dom->loadXML($sheetXml, LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        $rows = [];
        foreach ($xpath->query('//x:worksheet/x:sheetData/x:row') ?: [] as $rowNode) {
            $row = [];
            foreach ($xpath->query('./x:c', $rowNode) ?: [] as $cellNode) {
                $type = (string) $cellNode->getAttribute('t');
                $valueNode = $xpath->query('./x:v', $cellNode)?->item(0);
                $value = $valueNode ? (string) $valueNode->textContent : '';
                if ($type === 's') {
                    $index = (int) $value;
                    $value = $sharedStrings[$index] ?? '';
                }
                $row[] = trim($value);
            }
            if ($row !== []) {
                $rows[] = $row;
            }
            if (count($rows) >= 100) {
                break;
            }
        }

        return $rows;
    }

    private function previewText(string $path): string
    {
        $content = @file_get_contents($path);
        if (!is_string($content)) {
            throw new RuntimeException('Não foi possível ler o arquivo em modo texto.');
        }

        if (!mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'ISO-8859-1');
        }

        return mb_substr($content, 0, 30000);
    }
}
