<?php
declare(strict_types=1);

namespace App\Papers;

use App\Infra\Env;
use RuntimeException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use FilesystemIterator;

final class PaperImportService
{
    public function __construct(private readonly PaperRepository $repository)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function config(?string $overridePath = null): array
    {
        $basePath = trim((string) ($overridePath ?? Env::get('PAPER_IMPORT_BASE_PATH', '')));
        $ext = trim((string) Env::get('PAPER_IMPORT_ALLOWED_EXT', 'pdf,doc,docx,txt,xls,xlsx,xlsm,ppt,pptx'));
        $allowedExt = array_values(array_filter(array_map(
            static fn (string $value): string => strtolower(trim($value)),
            explode(',', $ext)
        )));

        return [
            'base_path' => $basePath,
            'allowed_ext' => $allowedExt,
            'max_preview' => (int) Env::get('PAPER_IMPORT_MAX_PREVIEW', '200'),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function preview(?string $overridePath = null): array
    {
        $config = $this->config($overridePath);
        $basePath = $config['base_path'];

        if ($basePath === '') {
            return [];
        }

        if (!is_dir($basePath)) {
            throw new RuntimeException('O diretório configurado para importação não existe: ' . $basePath);
        }

        $files = $this->iterFiles($basePath, $config['allowed_ext']);
        $rows = [];

        foreach (array_slice($files, 0, (int) $config['max_preview']) as $filePath) {
            $rows[] = $this->buildPaperFromFile($basePath, $filePath);
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    public function import(?string $overridePath = null): array
    {
        $config = $this->config($overridePath);
        $basePath = $config['base_path'];

        if ($basePath === '') {
            throw new RuntimeException('Defina PAPER_IMPORT_BASE_PATH no .env para usar o importador real.');
        }

        if (!is_dir($basePath)) {
            throw new RuntimeException('O diretório configurado para importação não existe: ' . $basePath);
        }

        $files = $this->iterFiles($basePath, $config['allowed_ext']);
        $report = [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => [],
            'total_files' => count($files),
        ];

        foreach ($files as $filePath) {
            try {
                $row = $this->buildPaperFromFile($basePath, $filePath);
                $result = $this->repository->upsertImported($row);
                if (!isset($report[$result])) {
                    $report[$result] = 0;
                }
                $report[$result]++;
            } catch (RuntimeException $exception) {
                $report['errors'][] = $exception->getMessage();
            }
        }

        return $report;
    }

    /**
     * @param array<int, string> $allowedExt
     * @return array<int, string>
     */
    private function iterFiles(string $basePath, array $allowedExt): array
    {
        $basePath = $this->normalizeLocalPath($basePath);
        $files = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($basePath, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isFile()) {
                continue;
            }

            $ext = strtolower((string) $fileInfo->getExtension());
            if ($allowedExt !== [] && !in_array($ext, $allowedExt, true)) {
                continue;
            }

            $files[] = $fileInfo->getPathname();
        }

        sort($files, SORT_NATURAL | SORT_FLAG_CASE);

        return $files;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPaperFromFile(string $basePath, string $filePath): array
    {
        $basename = basename($filePath);
        $mime = $this->detectMime($filePath);
        $relativePath = $this->relativePath($basePath, $filePath);
        $title = pathinfo($basename, PATHINFO_FILENAME);
        $title = preg_replace('/[_-]+/', ' ', $title) ?: $title;
        $title = preg_replace('/\s+/', ' ', trim((string) $title)) ?: $basename;

        return [
            'title' => mb_substr((string) $title, 0, 255),
            'journal' => null,
            'key_insight' => null,
            'citation_count' => 0,
            'keywords' => null,
            'link_url' => null,
            'file_source_type' => 'relative_path',
            'file_source_value' => mb_substr($relativePath, 0, 1000),
            'file_enabled' => 1,
            'file_preferred_name' => mb_substr($basename, 0, 255),
            'file_preferred_mime' => $mime !== null ? mb_substr($mime, 0, 150) : null,
            'file_last_resolved_at' => date('Y-m-d H:i:s'),
            'prompt_code' => null,
            'chapter_code' => null,
            'local_file_path' => $filePath,
        ];
    }

    private function relativePath(string $basePath, string $filePath): string
    {
        $baseNorm = rtrim($this->normalizeSlashes($basePath), '/');
        $fileNorm = $this->normalizeSlashes($filePath);

        if (stripos($fileNorm, $baseNorm) === 0) {
            return ltrim(substr($fileNorm, strlen($baseNorm)), '/');
        }

        return basename($fileNorm);
    }

    private function normalizeLocalPath(string $path): string
    {
        $path = trim($path);
        $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);

        return (string) preg_replace('#' . preg_quote(DIRECTORY_SEPARATOR, '#') . '+#', DIRECTORY_SEPARATOR, $path);
    }

    private function normalizeSlashes(string $path): string
    {
        return str_replace('\\', '/', $path);
    }

    private function detectMime(string $filePath): ?string
    {
        if (function_exists('mime_content_type')) {
            $mime = @mime_content_type($filePath);
            if (is_string($mime) && trim($mime) !== '') {
                return trim($mime);
            }
        }

        $ext = strtolower((string) pathinfo($filePath, PATHINFO_EXTENSION));
        $map = [
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'txt' => 'text/plain',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'xlsm' => 'application/vnd.ms-excel.sheet.macroEnabled.12',
            'ppt' => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        ];

        return $map[$ext] ?? null;
    }
}
