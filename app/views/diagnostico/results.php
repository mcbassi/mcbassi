<?php
declare(strict_types=1);

$overview = is_array($overview ?? null) ? $overview : [];
$sections = is_array($sections ?? null) ? $sections : [];
$pendingRequired = is_array($pendingRequired ?? null) ? $pendingRequired : [];
$versions = is_array($versions ?? null) ? $versions : [];
$companyVersions = is_array($companyVersions ?? null) ? $companyVersions : [];
$selectedVersion = is_array($selectedVersion ?? null) ? $selectedVersion : null;
$latestVersion = is_array($latestVersion ?? null) ? $latestVersion : null;

if (!function_exists('results_dt')) {
    function results_dt(?string $value): string
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

$currentCompany = trim((string) ($companyContext ?? $selectedVersion['company_name'] ?? ''));
$questionarioHref = $selectedVersion !== null
    ? url('diagnostico/index.php?version=' . rawurlencode((string) ($selectedVersion['id'] ?? 0)) . ($currentCompany !== '' ? '&company=' . rawurlencode($currentCompany) : ''))
    : url('diagnostico/index.php' . ($currentCompany !== '' ? '?company=' . rawurlencode($currentCompany) : ''));
$historyHref = url('diagnostico/history.php' . ($currentCompany !== '' ? '?company=' . rawurlencode($currentCompany) : ''));
$newSessionHref = url('diagnostico/index.php?new=1' . ($currentCompany !== '' ? '&company=' . rawurlencode($currentCompany) : ''));
?>
<div class="module-toolbar module-toolbar--questionario">
    <a data-shell-nav="true" class="action-pill action-pill--outline" href="<?= h($questionarioHref) ?>">Editar versão</a>
    <a data-shell-nav="true" class="action-pill action-pill--amber" href="<?= h($newSessionHref) ?>">Nova sessão</a>
    <a data-shell-nav="true" class="action-pill action-pill--outline" href="<?= h($historyHref) ?>">Histórico</a>
</div>

<section class="questionnaire-hero-panel">
    <div class="questionnaire-hero-panel__main">
        <h2>Resultados consolidados</h2>
        <p class="questionnaire-intro">
            <?php if ($selectedVersion !== null): ?>
                <?= h((string) ($selectedVersion['company_name'] ?? 'Sem empresa')) ?> ·
                V<?= h((string) ($selectedVersion['version_no'] ?? 1)) ?> ·
                <?= h(results_dt((string) ($selectedVersion['response_datetime'] ?? ''))) ?>
            <?php else: ?>
                Nenhuma versão disponível para exibição.
            <?php endif; ?>
        </p>
    </div>
    <div class="questionnaire-hero-panel__meta">
        <?php if ($selectedVersion !== null): ?>
            <span class="hero-meta-chip"><strong>Status:</strong> <?= h(strtoupper((string) ($selectedVersion['status'] ?? 'draft'))) ?></span>
        <?php endif; ?>
        <?php if ($latestVersion !== null): ?>
            <span class="hero-meta-chip"><strong>Versão vigente:</strong> V<?= h((string) ($latestVersion['version_no'] ?? 1)) ?></span>
        <?php endif; ?>
        <span class="hero-meta-chip"><strong>Empresa em foco:</strong> <?= h((string) ($selectedVersion['company_name'] ?? 'Sem empresa')) ?></span>
    </div>
</section>

<section class="stats-grid stats-grid--questionario">
    <article class="stat-card">
        <span class="stat-card__label">Respondidas</span>
        <strong class="stat-card__value"><?= h((string) ($overview['answered'] ?? 0)) ?> / <?= h((string) ($overview['total'] ?? 0)) ?></strong>
    </article>
    <article class="stat-card">
        <span class="stat-card__label">Obrigatórias ok</span>
        <strong class="stat-card__value"><?= h((string) ($overview['required_answered'] ?? 0)) ?> / <?= h((string) ($overview['required_total'] ?? 0)) ?></strong>
    </article>
    <article class="stat-card">
        <span class="stat-card__label">Obrigatórias pendentes</span>
        <strong class="stat-card__value"><?= h((string) ($overview['required_pending'] ?? 0)) ?></strong>
    </article>
    <article class="stat-card">
        <span class="stat-card__label">Completude (obrigatórias)</span>
        <strong class="stat-card__value"><?= h(number_format((float) ($overview['completion_pct'] ?? 0), 1, ',', '.')) ?>%</strong>
    </article>
</section>

<div class="results-dashboard">
    <div class="results-dashboard__main">
        <article class="module-card">
            <header class="module-card__header module-card__header--stacked">
                <div>
                    <h2>Painel por capítulo</h2>
                    <p class="muted">Leitura consolidada da versão selecionada, com foco no avanço obrigatório por capítulo.</p>
                </div>
            </header>

            <?php if ($sections === []): ?>
                <div class="empty-state">
                    <p>Nenhum bloco encontrado para esta versão.</p>
                </div>
            <?php else: ?>
                <div class="results-section-grid">
                    <?php foreach ($sections as $section): ?>
                        <?php
                            $pct = max(min((float) ($section['completion_pct'] ?? 0), 100.0), 0.0);
                            $isOk = $pct >= 100.0;
                        ?>
                        <article class="result-dashboard-card">
                            <div class="result-dashboard-card__top">
                                <div>
                                    <h3><?= h((string) ($section['label'] ?? 'Capítulo')) ?></h3>
                                    <p class="muted">Respondidas: <?= h((string) ($section['answered'] ?? 0)) ?> / <?= h((string) ($section['total'] ?? 0)) ?></p>
                                </div>
                                <span class="result-chip <?= $isOk ? 'result-chip--accent' : '' ?>"><?= h(number_format($pct, 1, ',', '.')) ?>%</span>
                            </div>
                            <div class="result-progress result-progress--large">
                                <span class="result-progress__bar" style="width: <?= h((string) $pct) ?>%"></span>
                            </div>
                            <div class="result-dashboard-card__meta">
                                <span>Obrigatórias: <?= h((string) ($section['required_answered'] ?? 0)) ?> / <?= h((string) ($section['required_total'] ?? 0)) ?></span>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </article>
    </div>

    <aside class="results-dashboard__side">
        <article class="module-card">
            <header class="module-card__header module-card__header--stacked">
                <div>
                    <h2>Versões desta empresa</h2>
                    <p class="muted">Troque de versão sem perder o contexto da empresa atual.</p>
                </div>
            </header>

            <?php if ($companyVersions === []): ?>
                <div class="empty-state">
                    <p>Nenhuma versão encontrada.</p>
                </div>
            <?php else: ?>
                <div class="version-selector-list">
                    <?php foreach ($companyVersions as $version): ?>
                        <?php
                            $isSelected = $selectedVersion !== null && (int) ($version['id'] ?? 0) === (int) ($selectedVersion['id'] ?? 0);
                            $status = (string) ($version['status'] ?? 'draft');
                            $badgeClass = $status === 'complete'
                                ? 'version-badge version-badge--complete'
                                : ($status === 'legacy' ? 'version-badge version-badge--legacy' : 'version-badge version-badge--draft');
                        ?>
                        <a data-shell-nav="true" class="version-selector-item <?= $isSelected ? 'is-active' : '' ?>" href="<?= h(url('diagnostico/results.php?version=' . rawurlencode((string) ($version['id'] ?? 0)) . ($currentCompany !== '' ? '&company=' . rawurlencode($currentCompany) : ''))) ?>">
                            <div class="version-selector-item__top">
                                <strong>V<?= h((string) ($version['version_no'] ?? 1)) ?></strong>
                                <span class="<?= h($badgeClass) ?>"><?= h(strtoupper($status)) ?></span>
                            </div>
                            <div class="version-selector-item__meta">
                                <?= h(results_dt((string) ($version['response_datetime'] ?? ''))) ?>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </article>

        <article class="module-card">
            <header class="module-card__header module-card__header--stacked">
                <div>
                    <h2>Pendências obrigatórias</h2>
                    <p class="muted">Somente perguntas com campo `required` entram nesta leitura.</p>
                </div>
            </header>

            <?php if ($pendingRequired === []): ?>
                <div class="empty-state">
                    <p>Nenhuma obrigatória pendente nesta versão.</p>
                </div>
            <?php else: ?>
                <ul class="pending-list">
                    <?php foreach ($pendingRequired as $item): ?>
                        <li class="pending-list__item">
                            <strong><?= h((string) ($item['section'] ?? 'Capítulo')) ?>:</strong>
                            <?= h((string) ($item['label'] ?? 'Pergunta')) ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </article>
    </aside>
</div>
