<?php
declare(strict_types=1);

$versions = is_array($versions ?? null) ? $versions : [];
$groupedVersions = is_array($groupedVersions ?? null) ? $groupedVersions : [];
$summary = is_array($summary ?? null) ? $summary : [];
$companyFilter = (string) ($companyFilter ?? '');
$statusFilter = (string) ($statusFilter ?? '');
$processingVersion = is_array($processingVersion ?? null) ? $processingVersion : null;

if (!function_exists('hist_dt')) {
    function hist_dt(?string $value): string
    {
        if ($value === null || trim($value) === '') {
            return '—';
        }

        try {
            return (new DateTimeImmutable($value))->format('d/m/Y H:i');
        } catch (Throwable) {
            return (string) $value;
        }
    }
}

$currentCompanyContext = trim($companyFilter);
$questionarioHref = $currentCompanyContext !== ''
    ? url('diagnostico/index.php?company=' . rawurlencode($currentCompanyContext))
    : url('diagnostico/index.php');

$resultsHref = url('diagnostico/results.php');
if ($currentCompanyContext !== '') {
    foreach ($groupedVersions as $group) {
        $groupName = trim((string) ($group['company_name'] ?? ''));
        $latestGroupVersion = is_array($group['latest'] ?? null) ? $group['latest'] : null;
        if ($groupName === $currentCompanyContext && $latestGroupVersion !== null) {
            $resultsHref = url(
                'diagnostico/results.php?version=' . rawurlencode((string) ($latestGroupVersion['id'] ?? 0))
                . '&company=' . rawurlencode($currentCompanyContext)
            );
            break;
        }
    }
}
?>
<div class="module-toolbar module-toolbar--questionario">
    <a data-shell-nav="true" class="action-pill action-pill--outline" href="<?= h($questionarioHref) ?>">Responder Questionário</a>
    <a data-shell-nav="true" class="action-pill action-pill--amber" href="<?= h($resultsHref) ?>">Resultados</a>
</div>

<section class="stats-grid stats-grid--questionario">
    <article class="stat-card">
        <span class="stat-card__label">Empresas</span>
        <strong class="stat-card__value"><?= h((string) ($summary['companies'] ?? 0)) ?></strong>
    </article>
    <article class="stat-card">
        <span class="stat-card__label">Versões</span>
        <strong class="stat-card__value"><?= h((string) ($summary['total_versions'] ?? 0)) ?></strong>
    </article>
    <article class="stat-card">
        <span class="stat-card__label">No recorte</span>
        <strong class="stat-card__value"><?= h((string) ($summary['filtered_versions'] ?? 0)) ?></strong>
    </article>
    <article class="stat-card">
        <span class="stat-card__label">Versão vigente</span>
        <strong class="stat-card__value"><?= $processingVersion !== null ? 'V' . h((string) ($processingVersion['version_no'] ?? 1)) : '—' ?></strong>
    </article>
</section>

<article class="module-card">
    <header class="module-card__header module-card__header--stacked">
        <div>
            <h2>Histórico de respostas</h2>
            <p class="muted">Cada salvamento cria uma nova versão. A linha destacada como <strong>Versão vigente</strong> representa a base hoje usada pelos processamentos.</p>
        </div>
    </header>

    <form method="get" action="<?= h(url('diagnostico/history.php')) ?>" class="module-filter-bar">
        <label class="form-field">
            <span>Empresa</span>
            <input type="text" name="company" value="<?= h($companyFilter) ?>" placeholder="Filtrar por empresa">
        </label>

        <label class="form-field">
            <span>Status</span>
            <select name="status">
                <option value="">Todos</option>
                <option value="draft" <?= $statusFilter === 'draft' ? 'selected' : '' ?>>Parcial</option>
                <option value="complete" <?= $statusFilter === 'complete' ? 'selected' : '' ?>>Completa</option>
                <option value="legacy" <?= $statusFilter === 'legacy' ? 'selected' : '' ?>>Legada</option>
            </select>
        </label>

        <div class="filter-actions">
            <button class="secondary-button" type="submit">Filtrar</button>
            <a data-shell-nav="true" class="action-pill action-pill--ghost" href="<?= h(url('diagnostico/history.php')) ?>">Limpar</a>
        </div>
    </form>

    <?php if ($groupedVersions === []): ?>
        <div class="empty-state">
            <p>Nenhuma versão encontrada.</p>
        </div>
    <?php else: ?>
        <div class="history-company-list">
            <?php foreach ($groupedVersions as $group): ?>
                <?php
                    $companyName = (string) ($group['company_name'] ?? 'Sem empresa');
                    $rows = is_array($group['versions'] ?? null) ? $group['versions'] : [];
                    $latest = is_array($group['latest'] ?? null) ? $group['latest'] : null;
                    $processingInGroup = (bool) ($group['processing_in_group'] ?? false);
                ?>
                <section class="history-company-card">
                    <header class="history-company-card__header">
                        <div>
                            <h3><?= h($companyName) ?></h3>
                            <p class="muted">
                                <?= h((string) ($group['count'] ?? 0)) ?> versão(ões) ·
                                <?= h((string) ($group['complete_count'] ?? 0)) ?> completas ·
                                <?= h((string) ($group['draft_count'] ?? 0)) ?> parciais
                            </p>
                        </div>
                        <div class="history-company-card__meta">
                            <?php if ($processingInGroup): ?>
                                <span class="result-chip result-chip--accent">Versão vigente</span>
                            <?php endif; ?>
                            <?php if ($latest !== null): ?>
                                <span class="result-chip">Último salvamento: <?= h(hist_dt((string) ($latest['response_datetime'] ?? ''))) ?></span>
                            <?php endif; ?>
                        </div>
                    </header>

                    <div class="table-scroll">
                        <table class="data-table data-table--history">
                            <thead>
                                <tr>
                                    <th>Versão</th>
                                    <th>Status</th>
                                    <th>Respondidas</th>
                                    <th>Obrigatórias</th>
                                    <th>Completude</th>
                                    <th>Data</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($rows as $version): ?>
                                <?php
                                    $status = (string) ($version['status'] ?? 'draft');
                                    $isProcessingBase = (bool) ($version['is_processing_base'] ?? false);
                                    $badgeClass = $status === 'complete'
                                        ? 'version-badge version-badge--complete'
                                        : ($status === 'legacy' ? 'version-badge version-badge--legacy' : 'version-badge version-badge--draft');
                                    $requiredTotal = (int) ($version['required_total'] ?? $version['total_questions'] ?? 0);
                                    $requiredMissing = (int) ($version['required_missing_count'] ?? 0);
                                    $requiredOk = max($requiredTotal - $requiredMissing, 0);
                                ?>
                                <tr class="<?= $isProcessingBase ? 'history-row history-row--processing' : 'history-row' ?>">
                                    <td>
                                        <div class="history-version-cell">
                                            <strong>V<?= h((string) ($version['version_no'] ?? 1)) ?></strong>
                                            <?php if ($isProcessingBase): ?>
                                                <span class="history-version-marker">Versão vigente</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td><span class="<?= h($badgeClass) ?>"><?= h(strtoupper($status)) ?></span></td>
                                    <td><?= h((string) ($version['answered_count'] ?? 0)) ?> / <?= h((string) ($version['total_questions'] ?? 0)) ?></td>
                                    <td><?= h((string) $requiredOk) ?> ok · <?= h((string) $requiredMissing) ?> pend.</td>
                                    <td><?= h(number_format((float) ($version['completion_pct'] ?? 0), 1, ',', '.')) ?>%</td>
                                    <td><?= h(hist_dt((string) ($version['response_datetime'] ?? ''))) ?></td>
                                    <td class="table-actions-cell">
                                        <div class="table-actions">
                                            <a data-shell-nav="true" class="mini-action" href="<?= h(url('diagnostico/index.php?version=' . rawurlencode((string) ($version['id'] ?? 0)) . '&company=' . rawurlencode($companyName))) ?>">Editar</a>
                                            <a data-shell-nav="true" class="mini-action" href="<?= h(url('diagnostico/results.php?version=' . rawurlencode((string) ($version['id'] ?? 0)) . '&company=' . rawurlencode($companyName))) ?>">Resultados</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</article>
