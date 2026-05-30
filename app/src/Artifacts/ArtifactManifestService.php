<?php
declare(strict_types=1);

namespace App\Artifacts;

final class ArtifactManifestService
{
    public function createInitialManifest(array $sessionData, array $paths): array
    {
        return [
            'manifest_version' => '1.0',
            'project' => 'Produtividade_emp',
            'client' => [
                'company_name' => (string) ($sessionData['company_name'] ?? ''),
                'client_slug' => (string) ($sessionData['client_slug'] ?? ''),
            ],
            'session' => [
                'version_id' => (int) ($sessionData['version_id'] ?? 0),
                'response_session_id' => (int) ($sessionData['response_session_id'] ?? 0),
                'user' => (string) ($sessionData['user'] ?? ''),
                'email_user' => (string) ($sessionData['email_user'] ?? ''),
                'email_resp' => (string) ($sessionData['email_resp'] ?? ''),
                'response_datetime' => (string) ($sessionData['response_datetime'] ?? ''),
                'sess_min' => (string) ($sessionData['sess_min'] ?? ''),
                'session_key' => (string) ($sessionData['session_key'] ?? ''),
            ],
            'context' => [
                'metric_year' => $sessionData['metric_year'] ?? null,
                'industry_name' => $sessionData['industry_name'] ?? null,
            ],
            'paths' => [
                'client_dir' => (string) ($paths['client_dir'] ?? ''),
                'session_dir' => (string) ($paths['session_dir'] ?? ''),
            ],
            'artifacts' => [],
        ];
    }

    public function load(string $manifestPath): array
    {
        if (!is_file($manifestPath)) {
            throw new \RuntimeException('Manifest não encontrado: ' . $manifestPath);
        }
        $json = file_get_contents($manifestPath);
        $data = json_decode((string) $json, true);
        if (!is_array($data)) {
            throw new \RuntimeException('Manifest inválido: ' . $manifestPath);
        }
        $data['artifacts'] = is_array($data['artifacts'] ?? null) ? $data['artifacts'] : [];
        return $data;
    }

    public function save(string $manifestPath, array $manifest): void
    {
        $dir = dirname($manifestPath);
        if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new \RuntimeException('Não foi possível criar o diretório do manifest: ' . $dir);
        }
        $json = json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if ($json === false) {
            throw new \RuntimeException('Falha ao serializar manifest.');
        }
        if (file_put_contents($manifestPath, $json) === false) {
            throw new \RuntimeException('Falha ao gravar manifest: ' . $manifestPath);
        }
    }

    public function upsertArtifact(array $manifest, array $artifact): array
    {
        $artifacts = is_array($manifest['artifacts'] ?? null) ? $manifest['artifacts'] : [];
        $artifactId = (string) ($artifact['artifact_id'] ?? '');
        if ($artifactId === '') {
            throw new \RuntimeException('artifact_id é obrigatório no manifest.');
        }
        $found = false;
        foreach ($artifacts as $index => $existing) {
            if ((string) ($existing['artifact_id'] ?? '') === $artifactId) {
                $artifacts[$index] = $artifact;
                $found = true;
                break;
            }
        }
        if (!$found) {
            $artifacts[] = $artifact;
        }
        $manifest['artifacts'] = $artifacts;
        return $manifest;
    }
}
