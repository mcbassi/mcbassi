<?php
declare(strict_types=1);

$availability = is_array($availability ?? null) ? $availability : [];
$filteredStats = is_array($filteredStats ?? null) ? $filteredStats : [];
$ragOptions = [
    '' => 'Todos',
    'with_cache' => 'Com cache',
    'without_cache' => 'Sem cache',
    'openai' => 'Arquivo OpenAI',
    'vector' => 'Vetorizados',
    'used' => 'Usados em prompt',
    'error' => 'Com erro',
];
$chapterOptions = ['CAP_01','CAP_02','CAP_03','CAP_04','CAP_05','CAP_06','CAP_07','CAP_08','CAP_09'];
?>
<?php if ($notice !== null): ?>
    <div class="alert-banner"><?= h($notice) ?></div>
<?php endif; ?>

<?php if (($error ?? null) !== null): ?>
    <div class="alert-banner alert-banner--danger"><?= h((string) $error) ?></div>
<?php endif; ?>

<?php if (($error ?? null) === null): ?>
    <div class="module-toolbar">
        <a data-shell-nav="true" class="action-pill action-pill--ghost" href="<?= h(url('papers/index.php')) ?>">Bibliografia</a>
        <a data-shell-nav="true" class="action-pill action-pill--outline" href="<?= h(url('papers/form.php')) ?>">Novo Paper</a>
        <a data-shell-nav="true" class="action-pill action-pill--outline" href="<?= h(url('papers/import.php')) ?>">Importar Bibliografia</a>
        <a data-shell-nav="true" class="action-pill action-pill--outline" href="<?= h(url('papers/prompts.php')) ?>">Fluxo de prompts</a>
        <a data-shell-nav="true" class="action-pill action-pill--outline" href="<?= h(url('papers/chapters.php')) ?>">Capítulos × Publicações</a>
    </div>

<?php endif; ?>

<section class="stats-grid stats-grid--papers stats-grid--papers-primary">
    <article class="stat-card">
        <span class="stat-card__label">Publicações no sistema</span>
        <strong class="stat-card__value"><?= h((string) ($stats['total'] ?? 0)) ?></strong>
    </article>
    <article class="stat-card">
        <span class="stat-card__label">Resultado do filtro</span>
        <strong class="stat-card__value"><?= h((string) ($filteredStats['total'] ?? 0)) ?></strong>
    </article>
    <article class="stat-card">
        <span class="stat-card__label">Com cache / sem cache</span>
        <strong class="stat-card__value stat-card__value--small"><?= h((string) ($filteredStats['com_cache'] ?? 0)) ?> / <?= h((string) ($filteredStats['sem_cache'] ?? 0)) ?></strong>
    </article>
    <article class="stat-card">
        <span class="stat-card__label">OpenAI / vetorizados</span>
        <strong class="stat-card__value stat-card__value--small"><?= h((string) ($filteredStats['openai'] ?? 0)) ?> / <?= h((string) ($filteredStats['vetorizados'] ?? 0)) ?></strong>
    </article>
</section>


<article class="module-card papers-card">
    <header class="module-card__header module-card__header--stacked">
        <div>
            <div class="entity-kicker">Bibliografia / catálogo principal</div>
            <h2>Relação de publicações</h2>
        </div>
    </header>

    <form method="get" action="<?= h(url('papers/index.php')) ?>" class="legacy-filter-grid">
        <label class="question-field">
            <span class="question-field__label">Buscar por título, journal ou keywords</span>
            <input type="text" name="q" value="<?= h((string) $query) ?>" placeholder="Buscar por título/journal/keywords">
        </label>

        <label class="question-field">
            <span class="question-field__label">Capítulo</span>
            <select name="chapter">
                <option value="">Capítulo...</option>
                <?php foreach ($chapterOptions as $chapterOption): ?>
                    <option value="<?= h($chapterOption) ?>" <?= (string) ($chapter ?? '') === $chapterOption ? 'selected' : '' ?>><?= h($chapterOption) ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <label class="question-field">
            <span class="question-field__label">Prompt code</span>
            <input type="text" name="prompt" value="<?= h((string) ($prompt ?? '')) ?>" placeholder="PRD_COMP_01">
        </label>

        <label class="question-field">
            <span class="question-field__label">Filtro RAG</span>
            <select name="rag">
                <?php foreach ($ragOptions as $ragValue => $ragLabel): ?>
                    <option value="<?= h($ragValue) ?>" <?= (string) ($rag ?? '') === (string) $ragValue ? 'selected' : '' ?>><?= h($ragLabel) ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <label class="question-field">
            <span class="question-field__label">Ordenar</span>
            <select name="sort">
                <option value="">Ordenar...</option>
                <option value="cit_desc" <?= (string) ($sort ?? '') === 'cit_desc' ? 'selected' : '' ?>>Citações (↓)</option>
                <option value="cit_asc" <?= (string) ($sort ?? '') === 'cit_asc' ? 'selected' : '' ?>>Citações (↑)</option>
            </select>
        </label>

        <div class="inline-actions inline-actions--end">
            <button type="submit" class="action-pill action-pill--solid">Filtrar</button>
            <a data-shell-nav="true" class="action-pill action-pill--outline" href="<?= h(url('papers/index.php')) ?>">Limpar</a>
        </div>
    </form>

    <div class="table-shell table-shell--legacy">
        <table class="data-table data-table--legacy">
            <colgroup>
                <col class="col-id">
                <col class="col-title">
                <col class="col-journal">
                <col class="col-citations">
                <col class="col-prompt">
                <col class="col-rag">
                <col class="col-usage">
                <col class="col-link">
                <col class="col-actions">
            </colgroup>
            <thead>
            <tr>
                <th>#</th>
                <th>Título / indicadores</th>
                <th>Journal</th>
                <th>Citações</th>
                <th>Prompt / capítulo</th>
                <th>RAG</th>
                <th>Uso IA</th>
                <th>Link</th>
                <th class="table-actions-col">Ações</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($papers === []): ?>
                <tr>
                    <td colspan="9" class="table-empty">Sem registros</td>
                </tr>
            <?php endif; ?>

            <?php foreach ($papers as $paper): ?>
                <?php
                    $paperId = (int) ($paper['id'] ?? 0);
                    $hasPrompt = trim((string) ($paper['prompt_code'] ?? '')) !== '';
                    $hasChapter = trim((string) ($paper['chapter_code'] ?? '')) !== '';
                ?>
                <tr>
                    <td><?= h((string) $paperId) ?></td>
                    <td>
                        <div class="paper-row-title">
                            <a data-shell-nav="true" href="<?= h(url('papers/view.php?id=' . rawurlencode((string) $paperId))) ?>"><?= h((string) ($paper['title'] ?? '')) ?></a>
                        </div>
                        <div class="table-inline-chips">
                            <?php if (!empty($paper['has_cache'])): ?><span class="rag-chip rag-chip--info">cache</span><?php endif; ?>
                            <?php if (!empty($paper['has_openai_file'])): ?><span class="rag-chip rag-chip--info">openai</span><?php endif; ?>
                            <?php if (!empty($paper['has_vector_store'])): ?><span class="rag-chip rag-chip--success">vector</span><?php endif; ?>
                            <?php if (($paper['usage_count'] ?? 0) > 0): ?><span class="rag-chip rag-chip--success">uso IA</span><?php endif; ?>
                            <?php if ((isset($paper['exists_flag']) && (int) $paper['exists_flag'] === 0)): ?><span class="rag-chip rag-chip--danger">cache ausente</span><?php endif; ?>
                        </div>
                    </td>
                    <td><?= h((string) ($paper['journal'] ?? '')) ?></td>
                    <td><?= h((string) ($paper['citation_count'] ?? 0)) ?></td>
                    <td>
                        <div><code><?= h((string) ($paper['prompt_code'] ?? '')) ?></code></div>
                        <div class="muted"><?= h((string) ($paper['chapter_code'] ?? '')) ?></div>
                    </td>
                    <td>
                        <span class="rag-chip rag-chip--<?= h((string) ($paper['rag_status_tone'] ?? 'muted')) ?>">
                            <?= h((string) ($paper['rag_status_label'] ?? 'Sem cache')) ?>
                        </span>
                        <div class="muted papers-row-subnote"><?= h((string) ($paper['cache_status'] ?? '')) ?></div>
                    </td>
                    <td>
                        <strong><?= h((string) ((int) ($paper['usage_count'] ?? 0))) ?></strong>
                        <div class="muted papers-row-subnote"><?= h((string) ($paper['usage_last_at'] ?? $paper['last_used_at'] ?? '')) ?></div>
                    </td>
                    <td class="table-link-col">
                        <?php if (!empty($paper['link_url']) || !empty($paper['file_source_value']) || !empty($paper['local_cache_path']) || !empty($paper['local_file_path'])): ?>
                            <a href="<?= h(url('papers/open.php?id=' . rawurlencode((string) $paperId))) ?>" target="_blank" rel="noreferrer">abrir</a>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                    <td class="table-actions-cell">
                        <div class="table-actions">
                        <a data-shell-nav="true" class="mini-action-link" href="<?= h(url('papers/view.php?id=' . rawurlencode((string) $paperId))) ?>">Ver</a>
                        <a data-shell-nav="true" class="mini-action-link" href="<?= h(url('papers/form.php?id=' . rawurlencode((string) $paperId))) ?>">Editar</a>
                        <form method="post" action="<?= h(url('papers/index.php')) ?>" class="inline-delete-form" onsubmit="return confirm('Excluir este registro?');">
                            <?= csrf_input() ?>
                            <input type="hidden" name="id" value="<?= h((string) $paperId) ?>">
                            <button type="submit" class="mini-action-link mini-action-link--danger">Excluir</button>
                        </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</article>
