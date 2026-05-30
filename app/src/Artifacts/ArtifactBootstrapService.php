<?php
declare(strict_types=1);

namespace App\Artifacts;

use App\Diagnostico\VersionedResponseRepository;
use PDO;

final class ArtifactBootstrapService
{
    public function __construct(private readonly PDO $pdo, private readonly VersionedResponseRepository $versions, private readonly ArtifactPathService $paths, private readonly ArtifactManifestService $manifest) {}

    public function bootstrap(int $versionId, string $emailUser, ?int $metricYear = null, ?string $industryName = null): array
    {
        $version = $this->versions->versionById($versionId, $emailUser);
        if (!$version) throw new \RuntimeException('Sessão/version_id não encontrado.');
        $companyName = trim((string) ($version['company_name'] ?? ''));
        $responseDatetime = trim((string) ($version['response_datetime'] ?? ''));
        $sessMin = $responseDatetime !== '' ? substr($responseDatetime, 0, 16) : '';
        $clientSlug = $this->paths->clientSlug($companyName);
        $sessionKey = $this->paths->sessionKey($responseDatetime);
        $dirs = $this->paths->ensureBaseStructure($companyName, $responseDatetime);
        $manifestPath = $dirs['manifest_path'];
        if (!is_file($manifestPath)) {
            $manifest = $this->manifest->createInitialManifest([
                'company_name' => $companyName, 'client_slug' => $clientSlug, 'version_id' => (int) ($version['id'] ?? $versionId),
                'response_session_id' => (int) ($version['id'] ?? $versionId), 'user' => (string) ($version['user'] ?? ''),
                'email_user' => (string) ($version['email_user'] ?? $emailUser), 'email_resp' => (string) ($version['email_resp'] ?? ''),
                'response_datetime' => $responseDatetime, 'sess_min' => $sessMin, 'session_key' => $sessionKey,
                'metric_year' => $metricYear, 'industry_name' => $industryName,
            ], $dirs);
            $this->manifest->save($manifestPath, $manifest);
        }
        return ['version' => $version, 'client_slug' => $clientSlug, 'session_key' => $sessionKey] + $dirs;
    }
}
