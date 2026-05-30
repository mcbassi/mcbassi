<?php
declare(strict_types=1);

$filters = is_array($filters ?? null) ? $filters : [];
$stats = is_array($stats ?? null) ? $stats : [];
$filterOptions = is_array($filterOptions ?? null) ? $filterOptions : [];
$rows = is_array($rows ?? null) ? $rows : [];
$context = trim((string) ($context ?? ''));
$screenMode = trim((string) ($screenMode ?? ''));
$screenBase = $screenMode === 'agentes' ? 'agentes' : 'prompts';

function prompts_selected(string $value, string $current): string
{
    return trim($value) === trim($current) ? 'selected' : '';
}

$contextLabel = $context === 'analitica'
    ? 'IA Analítica'
    : ($context === 'estrategica' ? 'IA Estratégica' : 'Diagnóstico Adm.');
$contextHref = $context === 'analitica'
    ? url('analitica/index.php')
    : ($context === 'estrategica' ? url('estrategica/index.php') : url('index.php'));
$contextSuffix = $context === '' ? '' : '?context=' . rawurlencode($context);


$promptHighlightStyles = <<<'CSS'
<style>
.stats-grid--prompts{gap:10px;margin-bottom:12px}
.stats-grid--prompts .stat-card{padding:12px 14px;border-radius:16px}
.stats-grid--prompts .stat-card__label{font-size:.82rem;margin-bottom:4px}
.stats-grid--prompts .stat-card__value{font-size:1.6rem}
.prompt-row-panel__content--render{white-space:normal}
.prompt-syntax-preview{margin:0;padding:14px;border-radius:14px;background:#f8fafc;border:1px solid #dde5f0;white-space:pre-wrap;word-break:break-word;line-height:1.5;overflow:auto;max-height:360px}
.prompt-syntax{font-weight:600}
.prompt-syntax--marker{color:#0b63ce;background:rgba(11,99,206,.10);border-radius:6px;padding:0 2px}
.prompt-syntax--sql-trigger{color:#0f7a53;background:rgba(15,122,83,.12);border-radius:6px;padding:0 2px;font-weight:700}
.prompt-syntax--sql{color:#0f7a53;background:rgba(15,122,83,.06)}
.prompt-syntax--biblio{color:#a35f00;background:rgba(163,95,0,.10);border-radius:6px;padding:0 2px;font-weight:700}
</style>
CSS;
echo $promptHighlightStyles;
?>


<?php if (!empty($errorFlash)): ?>
    <article class="module-card notice-card notice-card--error">
        <strong>Erro:</strong> <?= h((string) $errorFlash) ?>
    </article>
<?php endif; ?>

<?php if (!empty($success)): ?>
    <article class="module-card notice-card notice-card--success">
        <strong>Sucesso:</strong> <?= h((string) $success) ?>
    </article>
<?php endif; ?>

<?php if (!empty($error)): ?>
    <article class="module-card notice-card notice-card--error">
        <strong>Falha de leitura:</strong> <?= h((string) $error) ?>
    </article>
<?php endif; ?>

<section class="stats-grid stats-grid--prompts">
    <article class="stat-card stat-card--soft-blue">
        <span class="stat-card__label">Prompts</span>
        <strong class="stat-card__value"><?= (int) ($stats['total'] ?? 0) ?></strong>
    </article>
    <article class="stat-card stat-card--soft-green">
        <span class="stat-card__label">Ligados ao questionário</span>
        <strong class="stat-card__value"><?= (int) ($stats['with_section'] ?? 0) ?></strong>
    </article>
    <article class="stat-card stat-card--soft-amber">
        <span class="stat-card__label">Com SQL</span>
        <strong class="stat-card__value"><?= (int) ($stats['with_sql'] ?? 0) ?></strong>
    </article>
    <article class="stat-card stat-card--soft-blue">
        <span class="stat-card__label">Com marcadores</span>
        <strong class="stat-card__value"><?= (int) ($stats['with_markers'] ?? 0) ?></strong>
    </article>
</section>

<article class="module-card">
    <header class="module-card__header">
        <div>
            <h2>Lista de prompts</h2>
            <p class="muted">Visual em blocos: metadados na linha superior, prompt principal em área maior e SQL operacional em área lateral menor.</p>
        </div>
    </header>

    <form class="control-grid control-grid--filters" method="get" action="<?= h(url($screenBase . '/index.php')) ?>">
        <?php if ($context !== ''): ?>
            <input type="hidden" name="context" value="<?= h($context) ?>">
        <?php endif; ?>

        <label class="form-field">
            <span>Buscar</span>
            <input type="text" name="q" value="<?= h((string) ($filters['q'] ?? '')) ?>" placeholder="Prompt Code, função, descrição ou texto">
        </label>

        <label class="form-field">
            <span>Prompt Code</span>
            <select name="assistente">
                <option value="">Todos</option>
                <?php foreach (($filterOptions['assistentes'] ?? []) as $option): ?>
                    <option value="<?= h((string) $option) ?>" <?= prompts_selected((string) $option, (string) ($filters['assistente'] ?? '')) ?>>
                        <?= h((string) $option) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label class="form-field">
            <span>Função</span>
            <select name="funcao">
                <option value="">Todas</option>
                <?php foreach (($filterOptions['funcoes'] ?? []) as $option): ?>
                    <option value="<?= h((string) $option) ?>" <?= prompts_selected((string) $option, (string) ($filters['funcao'] ?? '')) ?>>
                        <?= h((string) $option) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label class="form-field">
            <span>Seção</span>
            <select name="section">
                <option value="">Todas</option>
                <?php foreach (($filterOptions['sections'] ?? []) as $option): ?>
                    <option value="<?= h((string) $option) ?>" <?= prompts_selected((string) $option, (string) ($filters['section'] ?? '')) ?>>
                        <?= h((string) $option) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label class="form-field">
            <span>SQL</span>
            <select name="has_sql">
                <option value="">Todos</option>
                <option value="yes" <?= prompts_selected('yes', (string) ($filters['has_sql'] ?? '')) ?>>Com SQL</option>
                <option value="no" <?= prompts_selected('no', (string) ($filters['has_sql'] ?? '')) ?>>Sem SQL</option>
            </select>
        </label>

        <div class="prompt-filter-actions">
            <button type="submit" class="action-pill action-pill--green">Filtrar</button>
            <a data-shell-nav="true" class="action-pill action-pill--ghost" href="<?= h(url($screenBase . '/index.php') . $contextSuffix) ?>">Limpar</a>
        </div>
    </form>

    <div class="prompt-list">
        <?php if ($rows === []): ?>
            <div class="empty-table">Nenhum prompt encontrado.</div>
        <?php endif; ?>

        <?php foreach ($rows as $row): ?>
            <?php
            $editHref = url('prompts/form.php?id=' . (int) ($row['id'] ?? 0) . ($context === '' ? '' : '&context=' . rawurlencode($context)));
            $hasSql = (int) ($row['has_sql'] ?? 0) === 1;
            ?>
            <article class="prompt-row-card">
                <div class="prompt-row-card__top">
                    <div class="prompt-meta">
                        <span class="prompt-meta__label">Prompt Code</span>
                        <strong><?= h((string) ($row['assistente'] ?? '')) ?></strong>
                    </div>
                    <div class="prompt-meta">
                        <span class="prompt-meta__label">Seção</span>
                        <strong><?= h((string) ($row['section_code'] ?? '')) ?></strong>
                    </div>
                    <div class="prompt-meta">
                        <span class="prompt-meta__label">Ordem</span>
                        <strong><?= h((string) ($row['sort_order'] ?? '')) ?></strong>
                    </div>
                    <div class="prompt-meta">
                        <span class="prompt-meta__label">Função</span>
                        <strong><?= h((string) ($row['funcao'] ?? '')) ?></strong>
                    </div>
                    <div class="prompt-meta prompt-meta--wide">
                        <span class="prompt-meta__label">Descrição</span>
                        <strong><?= h((string) ($row['descricao'] ?? '')) ?></strong>
                    </div>
                    <div class="prompt-meta">
                        <span class="prompt-meta__label">Marcadores</span>
                        <strong><?= (int) ($row['marker_count'] ?? 0) ?></strong>
                    </div>
                    <div class="prompt-meta prompt-meta--actions">
                        <span class="prompt-meta__label">Ações</span>
                        <div class="table-actions prompt-actions">
                            <a data-shell-nav="off" class="action-pill action-pill--outline" href="<?= h($editHref) ?>">Editar</a>
                            <form method="post" action="<?= h(url($screenBase . '/index.php')) ?>" onsubmit="return confirm('Excluir este prompt?');">
                                <?= csrf_input() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int) ($row['id'] ?? 0) ?>">
                                <input type="hidden" name="context" value="<?= h($context) ?>">
                                <button type="submit" class="action-pill action-pill--danger">Excluir</button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="prompt-row-card__body">
                    <section class="prompt-row-panel prompt-row-panel--main">
                        <div class="prompt-row-panel__header">
                            <h3>Prompt</h3>
                        </div>
                        <div class="prompt-row-panel__content prompt-row-panel__content--render prompt-syntax-preview js-prompt-highlight"><?= h((string) (($row['prompt_full_text'] ?? '') !== '' ? $row['prompt_full_text'] : ($row['prompt_preview'] ?? ''))) ?></div>
                    </section>

                    <aside class="prompt-row-panel prompt-row-panel--sql">
                        <div class="prompt-row-panel__header">
                            <h3>SQL operacional</h3>
                            <?php if ($hasSql): ?>
                                <span class="mini-badge mini-badge--blue"><?= h((string) (($row['sql_desc'] ?? '') !== '' ? $row['sql_desc'] : 'Com SQL')) ?></span>
                            <?php else: ?>
                                <span class="mini-badge">Sem SQL</span>
                            <?php endif; ?>
                        </div>
                        <div class="prompt-row-panel__content prompt-row-panel__content--sql prompt-syntax-preview js-prompt-highlight-sql"><?= h((string) (($row['sql_block_text'] ?? '') !== '' ? $row['sql_block_text'] : 'Nenhum SQL anexado.')) ?></div>
                    </aside>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</article>

<script src="<?= h(asset('assets/js/prompts.js')) ?>"></script>
