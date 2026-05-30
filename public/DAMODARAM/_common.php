<?php
declare(strict_types=1);

use App\Diagnostico\VersionedResponseRepository;
use App\Infra\Database;
use App\Support\View;

$app = require dirname(__DIR__, 2) . '/app/bootstrap/app.php';
$app['auth']->requireAuth();

$dbService = $app['db'] ?? null;
if (!$dbService instanceof Database) {
    throw new RuntimeException('Serviço de banco inválido para o módulo DAMODARAM.');
}

function damodaram_stats_pdo(Database $db): PDO {
    return $db->statisticsPdo();
}

function damodaram_main_pdo(Database $db): PDO {
    return $db->pdo();
}

function damodaram_years(PDO $pdo): array {
    return $pdo->query("SELECT DISTINCT asof_year FROM vw_damodaran_overview_base ORDER BY asof_year DESC")->fetchAll(PDO::FETCH_COLUMN) ?: [];
}

function damodaram_industries(PDO $pdo, ?int $year = null): array {
    if ($year) {
        $st = $pdo->prepare("SELECT DISTINCT industry_name FROM vw_damodaran_overview_base WHERE asof_year = ? ORDER BY industry_name");
        $st->execute([$year]);
        return $st->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }
    return $pdo->query("SELECT DISTINCT industry_name FROM vw_damodaran_overview_base ORDER BY industry_name")->fetchAll(PDO::FETCH_COLUMN) ?: [];
}

function damodaram_call(PDO $pdo, string $proc, array $params = []): array {
    $placeholders = implode(',', array_fill(0, count($params), '?'));
    $st = $pdo->prepare("CALL " . $proc . "($placeholders)");
    $st->execute(array_values($params));
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    while ($st->nextRowset()) {}
    return $rows;
}

function damodaram_versions(Database $db, string $emailUser): array {
    $repo = new VersionedResponseRepository($db->pdo());
    $repo->ensureSchema();
    return $emailUser !== '' ? $repo->versions($emailUser) : [];
}

function damodaram_version_by_id(Database $db, string $emailUser, int $versionId): ?array {
    $repo = new VersionedResponseRepository($db->pdo());
    $repo->ensureSchema();
    if ($versionId <= 0) return null;
    return $repo->versionById($versionId, $emailUser);
}

function damodaram_sess_min(?array $version): string {
    $dt = trim((string)($version['response_datetime'] ?? ''));
    return $dt !== '' ? substr($dt, 0, 16) : '';
}

function damodaram_base_data(array $app, string $pageTitle, string $activePage): array {
    /** @var Database $db */
    $db = $app['db'];
    $statsPdo = damodaram_stats_pdo($db);
    $auth = $app['auth'];
    $emailUser = trim((string)$auth->user()->email);
    $versions = damodaram_versions($db, $emailUser);

    $year = (int)($_GET['year'] ?? 2024);
    $years = damodaram_years($statsPdo);
    if ($years !== [] && !in_array($year, array_map('intval', $years), true)) {
        $year = (int)$years[0];
    }

    $industries = damodaram_industries($statsPdo, $year);
    $industry = trim((string)($_GET['industry'] ?? ($industries[0] ?? 'Advertising')));
    if ($industries !== [] && !in_array($industry, $industries, true)) {
        $industry = (string)$industries[0];
    }

    $selectedVersionId = (int)($_GET['version'] ?? 0);
    if ($selectedVersionId <= 0 && $versions !== []) {
        $selectedVersionId = (int)($versions[0]['id'] ?? 0);
    }
    $selectedVersion = damodaram_version_by_id($db, $emailUser, $selectedVersionId);
    if ($selectedVersion === null && $versions !== []) {
        $selectedVersion = $versions[0];
        $selectedVersionId = (int)($selectedVersion['id'] ?? 0);
    }

    return [
        'app' => $app,
        'statsPdo' => $statsPdo,
        'pageTitle' => $pageTitle,
        'contentTitle' => $pageTitle,
        'subtitle' => 'ProdCol',
        'damodaramActivePage' => $activePage,
        'damodaramYear' => $year,
        'damodaramYears' => $years,
        'damodaramIndustry' => $industry,
        'damodaramIndustries' => $industries,
        'damodaramVersions' => $versions,
        'damodaramSelectedVersionId' => $selectedVersionId,
        'damodaramSelectedVersion' => $selectedVersion,
    ];
}