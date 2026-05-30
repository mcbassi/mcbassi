<?php
declare(strict_types=1);

namespace App\Artifacts;

final class ArtifactPathService
{
    public function __construct(private readonly string $baseDir)
    {
    }

    public function baseDir(): string
    {
        return $this->normalizePath($this->baseDir);
    }

    public function clientSlug(string $companyName): string
    {
        $slug = strtolower(trim($companyName));
        $slug = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $slug) ?: $slug;
        $slug = preg_replace('/[^a-z0-9]+/i', '_', $slug) ?: 'cliente';
        $slug = trim($slug, '_');

        return $slug !== '' ? $slug : 'cliente';
    }

    public function sessionKey(string $responseDatetime): string
    {
        $normalized = preg_replace('/[^0-9]/', '', $responseDatetime) ?: '';

        if (strlen($normalized) >= 14) {
            return substr($normalized, 0, 8) . '_' . substr($normalized, 8, 6);
        }

        return date('Ymd_His');
    }

    public function clientDir(string $companyName): string
    {
        return $this->baseDir() . DIRECTORY_SEPARATOR . $this->clientSlug($companyName);
    }

    public function sessionDir(string $companyName, string $responseDatetime): string
    {
        return $this->clientDir($companyName) . DIRECTORY_SEPARATOR . $this->sessionKey($responseDatetime);
    }

    public function manifestDir(string $companyName, string $responseDatetime): string
    {
        return $this->sessionDir($companyName, $responseDatetime) . DIRECTORY_SEPARATOR . '_manifest';
    }

    public function manifestPath(string $companyName, string $responseDatetime): string
    {
        return $this->manifestDir($companyName, $responseDatetime) . DIRECTORY_SEPARATOR . 'manifest.json';
    }

    public function ensureBaseStructure(string $companyName, string $responseDatetime): array
    {
        $clientDir = $this->clientDir($companyName);
        $sessionDir = $this->sessionDir($companyName, $responseDatetime);
        $manifestDir = $this->manifestDir($companyName, $responseDatetime);

        $dirs = [
            $clientDir,
            $clientDir . DIRECTORY_SEPARATOR . '_manifest',
            $sessionDir,
            $manifestDir,
            $sessionDir . DIRECTORY_SEPARATOR . '00_questionario',
            $sessionDir . DIRECTORY_SEPARATOR . '10_analitica',
            $sessionDir . DIRECTORY_SEPARATOR . '10_analitica' . DIRECTORY_SEPARATOR . 'perguntas',
            $sessionDir . DIRECTORY_SEPARATOR . '10_analitica' . DIRECTORY_SEPARATOR . 'grupos',
            $sessionDir . DIRECTORY_SEPARATOR . '20_prioridades',
            $sessionDir . DIRECTORY_SEPARATOR . '20_prioridades' . DIRECTORY_SEPARATOR . 'grupos',
            $sessionDir . DIRECTORY_SEPARATOR . '30_wms_damodaran',
            $sessionDir . DIRECTORY_SEPARATOR . '40_estrategica',
            $sessionDir . DIRECTORY_SEPARATOR . '50_entrega',
        ];

        foreach ($dirs as $dir) {
            if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
                throw new \RuntimeException('Não foi possível criar o diretório: ' . $dir);
            }
        }

        return [
            'client_dir' => $clientDir,
            'session_dir' => $sessionDir,
            'manifest_dir' => $manifestDir,
            'manifest_path' => $this->manifestPath($companyName, $responseDatetime),
        ];
    }

    public function pdfRelativePath(string $stage, string $scope, string $filename): string
    {
        $stageDir = match ($stage) {
            'questionario' => '00_questionario',
            'analitica' => '10_analitica',
            'prioridades' => '20_prioridades',
            'wms_damodaran' => '30_wms_damodaran',
            'estrategica' => '40_estrategica',
            'entrega' => '50_entrega',
            default => '99_outros',
        };

        $scopeDir = match ($scope) {
            'pergunta' => 'perguntas',
            'grupo' => 'grupos',
            default => '',
        };

        $parts = [$stageDir];
        if ($scopeDir !== '') {
            $parts[] = $scopeDir;
        }
        $parts[] = $this->safeFilename($filename);

        return implode('/', $parts);
    }

    public function absoluteFromRelative(string $companyName, string $responseDatetime, string $relativePath): string
    {
        return $this->sessionDir($companyName, $responseDatetime)
            . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    }

    public function safeFilename(string $filename): string
    {
        $filename = trim($filename);

        if ($filename === '') {
            $filename = 'artifact.pdf';
        }

        $filename = preg_replace('/[^A-Za-z0-9_\-.]+/', '_', $filename) ?: 'artifact.pdf';

        if (!str_ends_with(strtolower($filename), '.pdf')) {
            $filename .= '.pdf';
        }

        return $filename;
    }

    private function normalizePath(string $path): string
    {
        return rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);
    }
}