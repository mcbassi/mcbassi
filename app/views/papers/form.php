<?php
declare(strict_types=1);

$paper = is_array($paper ?? null) ? $paper : [];
$paperIndicators = is_array($paperIndicators ?? null) ? $paperIndicators : [];
$promptContext = is_array($promptContext ?? null) ? $promptContext : null;
$id = (int) ($paper['id'] ?? 0);
$sourceTypes = [
    '' => 'Selecionar...',
    'url' => 'url',
    'relative_path' => 'relative_path',
    'local_path' => 'local_path',
    'cloud_path' => 'cloud_path',
    'openai_file_id' => 'openai_file_id',
];
?>
<div class="module-toolbar">
    <a data-shell-nav="true" class="action-pill action-pill--outline" href="<?= h(url('papers/index.php')) ?>">Voltar para a lista</a>
    <?php if ($id > 0): ?>
        <a data-shell-nav="true" class="action-pill action-pill--outline" href="<?= h(url('papers/view.php?id=' . rawurlencode((string) $id))) ?>">Ver detalhes</a>
    <?php endif; ?>
    <a data-shell-nav="true" class="action-pill action-pill--solid" href="<?= h(url('papers/form.php')) ?>">Novo Paper</a>
    <a data-shell-nav="true" class="action-pill action-pill--outline" href="<?= h(url('papers/import.php')) ?>">Importar Bibliografia</a>
    <a data-shell-nav="true" class="action-pill action-pill--outline" href="<?= h(url('papers/chapters.php')) ?>">Capítulos × Publicações</a>
</div>

<?php if ($notice !== null): ?>
    <div class="alert-banner"><?= h($notice) ?></div>
<?php endif; ?>

<?php if (($error ?? null) !== null): ?>
    <div class="alert-banner alert-banner--danger"><?= h((string) $error) ?></div>
<?php endif; ?>

<article class="module-card papers-card">
    <header class="module-card__header module-card__header--stacked">
        <div>
            <div class="entity-kicker">Bibliografia / cadastro</div>
            <h2><?= $id > 0 ? 'Editar Paper' : 'Novo Paper' ?></h2>
            <p class="muted">Cadastro principal da publicação, preservando o fluxo do legado e expondo os dados de RAG quando o registro já existe.</p>
        </div>

        <?php if ($id > 0): ?>
            <div class="entity-badges">
                <span class="rag-chip rag-chip--<?= h((string) ($paper['rag_status_tone'] ?? 'muted')) ?>"><?= h((string) ($paper['rag_status_label'] ?? 'Sem cache')) ?></span>
                <?php if (trim((string) ($paper['prompt_code'] ?? '')) !== ''): ?>
                    <span class="rag-chip rag-chip--info">Prompt <?= h((string) $paper['prompt_code']) ?></span>
                <?php endif; ?>
                <?php if (trim((string) ($paper['chapter_code'] ?? '')) !== ''): ?>
                    <span class="rag-chip rag-chip--warning"><?= h((string) $paper['chapter_code']) ?></span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </header>

    <?php if ($paperIndicators !== []): ?>
        <div class="indicator-grid indicator-grid--compact">
            <?php foreach ($paperIndicators as $indicator): ?>
                <div class="indicator-card">
                    <span class="indicator-card__label"><?= h((string) ($indicator['label'] ?? 'Indicador')) ?></span>
                    <span class="rag-chip rag-chip--<?= h((string) ($indicator['tone'] ?? 'muted')) ?>"><?= h((string) ($indicator['value'] ?? '—')) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="section-divider"></div>
    <?php endif; ?>

    <form method="post" action="<?= h(url('papers/form.php')) ?>" class="papers-form">
        <?= csrf_input() ?>
        <input type="hidden" name="id" value="<?= h((string) $id) ?>">

        <div class="questionnaire-grid">
            <label class="question-field question-field--full">
                <span class="question-field__label">Título *</span>
                <input type="text" name="title" required value="<?= h((string) ($paper['title'] ?? '')) ?>">
            </label>

            <label class="question-field question-field--full">
                <span class="question-field__label">Journal / Onde foi publicado *</span>
                <input type="text" name="journal" required value="<?= h((string) ($paper['journal'] ?? '')) ?>">
            </label>

            <label class="question-field question-field--full">
                <span class="question-field__label">Palavras-chave</span>
                <input type="text" name="keywords" value="<?= h((string) ($paper['keywords'] ?? '')) ?>" placeholder="productivity, SMEs, workplace">
            </label>

            <label class="question-field question-field--full">
                <span class="question-field__label">Key Insight</span>
                <textarea name="key_insight" rows="4"><?= h((string) ($paper['key_insight'] ?? '')) ?></textarea>
            </label>

            <label class="question-field">
                <span class="question-field__label">Nº de Citações</span>
                <input type="number" name="citation_count" min="0" value="<?= h((string) ($paper['citation_count'] ?? 0)) ?>">
            </label>

            <label class="question-field">
                <span class="question-field__label">Prompt code</span>
                <input type="text" name="prompt_code" value="<?= h((string) ($paper['prompt_code'] ?? '')) ?>" placeholder="PRD_COMP_01">
            </label>

            <label class="question-field">
                <span class="question-field__label">Capítulo</span>
                <input type="text" name="chapter_code" value="<?= h((string) ($paper['chapter_code'] ?? '')) ?>" placeholder="CAP_06">
            </label>

            <label class="question-field question-field--full">
                <span class="question-field__label">Link legado / referência</span>
                <input type="text" name="link_url" value="<?= h((string) ($paper['link_url'] ?? '')) ?>" placeholder="https://... ou caminho relativo legado">
            </label>

            <label class="question-field">
                <span class="question-field__label">Tipo da origem</span>
                <select name="file_source_type">
                    <?php foreach ($sourceTypes as $value => $label): ?>
                        <option value="<?= h($value) ?>" <?= (string) ($paper['file_source_type'] ?? '') === (string) $value ? 'selected' : '' ?>><?= h($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="question-field question-field--full">
                <span class="question-field__label">Valor da origem</span>
                <input type="text" name="file_source_value" value="<?= h((string) ($paper['file_source_value'] ?? '')) ?>" placeholder="URL, caminho relativo, caminho absoluto ou file_id">
            </label>

            <label class="question-field">
                <span class="question-field__label">Nome preferido do arquivo</span>
                <input type="text" name="file_preferred_name" value="<?= h((string) ($paper['file_preferred_name'] ?? '')) ?>" placeholder="artigo_produtividade.pdf">
            </label>

            <label class="question-field">
                <span class="question-field__label">MIME preferido</span>
                <input type="text" name="file_preferred_mime" value="<?= h((string) ($paper['file_preferred_mime'] ?? '')) ?>" placeholder="application/pdf">
            </label>

            <label class="question-field question-field--checkbox">
                <span class="question-field__label">Ativo</span>
                <input type="checkbox" name="file_enabled" value="1" <?= ((int) ($paper['file_enabled'] ?? 1)) === 1 ? 'checked' : '' ?>>
            </label>
        </div>

        <div class="inline-actions">
            <button type="submit" class="action-pill action-pill--solid">Salvar</button>
            <a data-shell-nav="true" class="action-pill action-pill--outline" href="<?= h(url('papers/index.php')) ?>">Cancelar</a>
        </div>
    </form>

    <?php if ($id > 0): ?>
        <div class="section-divider"></div>

        <section class="detail-grid">
            <article class="detail-card">
                <h3>Status RAG</h3>
                <dl class="mini-definition">
                    <div><dt>Status</dt><dd><span class="rag-chip rag-chip--<?= h((string) ($paper['rag_status_tone'] ?? 'muted')) ?>"><?= h((string) ($paper['rag_status_label'] ?? 'Sem cache')) ?></span></dd></div>
                    <div><dt>Cache ID</dt><dd><?= h((string) ($paper['cache_id'] ?? '')) ?></dd></div>
                    <div><dt>OpenAI file</dt><dd><?= h((string) ($paper['openai_file_id'] ?? '')) ?></dd></div>
                    <div><dt>Vector store</dt><dd><?= h((string) ($paper['vector_store_id'] ?? '')) ?></dd></div>
                    <div><dt>Último uso</dt><dd><?= h((string) ($paper['usage_last_at'] ?? $paper['last_used_at'] ?? '')) ?></dd></div>
                    <div><dt>Uso em prompts</dt><dd><?= h((string) ($paper['usage_count'] ?? 0)) ?></dd></div>
                </dl>
            </article>

            <article class="detail-card">
                <h3>Metadados de arquivo</h3>
                <dl class="mini-definition">
                    <div><dt>Source SHA256</dt><dd><?= h((string) ($paper['source_sha256'] ?? '')) ?></dd></div>
                    <div><dt>Original filename</dt><dd><?= h((string) ($paper['original_filename'] ?? $paper['file_preferred_name'] ?? '')) ?></dd></div>
                    <div><dt>Mime</dt><dd><?= h((string) ($paper['mime_type'] ?? $paper['file_preferred_mime'] ?? '')) ?></dd></div>
                    <div><dt>Local cache path</dt><dd><code><?= h((string) ($paper['local_cache_path'] ?? '')) ?></code></dd></div>
                    <div><dt>Source type</dt><dd><?= h((string) ($paper['file_source_type'] ?? '')) ?></dd></div>
                    <div><dt>Source value</dt><dd><code><?= h((string) ($paper['file_source_value'] ?? '')) ?></code></dd></div>
                </dl>
            </article>
        </section>

        <?php if ($promptContext !== null): ?>
            <div class="section-divider"></div>
            <section class="detail-card">
                <div class="detail-card__head">
                    <div>
                        <h3>Fluxo de prompt associado</h3>
                        <p class="muted">Ligação por <code>papers.prompt_code</code> e por uso real em <code>prompt_file_usage</code>.</p>
                    </div>
                    <a data-shell-nav="true" class="action-pill action-pill--outline" href="<?= h(url('papers/prompts.php')) ?>">Abrir visão completa</a>
                </div>

                <div class="prompt-flow-grid">
                    <article class="prompt-mini-card">
                        <span class="stat-card__label">Prompt code</span>
                        <strong class="stat-card__value stat-card__value--small"><?= h((string) ($paper['prompt_code'] ?? '—')) ?></strong>
                    </article>
                    <article class="prompt-mini-card">
                        <span class="stat-card__label">Prompts no catálogo</span>
                        <strong class="stat-card__value"><?= h((string) count($promptContext['prompt_catalog'] ?? [])) ?></strong>
                    </article>
                    <article class="prompt-mini-card">
                        <span class="stat-card__label">Uso real em IA</span>
                        <strong class="stat-card__value"><?= h((string) ($promptContext['usage_stats']['count'] ?? 0)) ?></strong>
                    </article>
                    <article class="prompt-mini-card">
                        <span class="stat-card__label">Último uso</span>
                        <strong class="stat-card__value stat-card__value--small"><?= h((string) ($promptContext['usage_stats']['latest'] ?? '—')) ?></strong>
                    </article>
                </div>
            </section>
        <?php endif; ?>
    <?php endif; ?>
</article>
