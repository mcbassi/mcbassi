<?php
declare(strict_types=1);

$config = is_array($config ?? null) ? $config : ['base_path' => '', 'allowed_ext' => [], 'max_preview' => 0];
$preview = is_array($preview ?? null) ? $preview : [];
?>
<div class="module-toolbar">
    <a data-shell-nav="true" class="action-pill action-pill--outline" href="<?= h(url('papers/index.php')) ?>">Relação de textos</a>
    <a data-shell-nav="true" class="action-pill action-pill--solid" href="<?= h(url('papers/import.php')) ?>">Importador real</a>
    <a data-shell-nav="true" class="action-pill action-pill--outline" href="<?= h(url('papers/prompts.php')) ?>">Fluxo de prompts</a>
    <a data-shell-nav="true" class="action-pill action-pill--outline" href="<?= h(url('papers/chapters.php')) ?>">Capítulos × Publicações</a>
</div>

<?php if ($notice !== null): ?>
    <div class="alert-banner"><?= h((string) $notice) ?></div>
<?php endif; ?>

<?php if (($error ?? null) !== null): ?>
    <div class="alert-banner alert-banner--danger"><?= h((string) $error) ?></div>
<?php endif; ?>

<section class="stats-grid stats-grid--papers">
    <article class="stat-card">
        <span class="stat-card__label">Base path</span>
        <strong class="stat-card__value stat-card__value--small"><?= h((string) ($config['base_path'] ?? 'não definido')) ?></strong>
    </article>
    <article class="stat-card">
        <span class="stat-card__label">Extensões</span>
        <strong class="stat-card__value stat-card__value--small"><?= h(implode(', ', $config['allowed_ext'] ?? [])) ?></strong>
    </article>
    <article class="stat-card">
        <span class="stat-card__label">Preview máx.</span>
        <strong class="stat-card__value"><?= h((string) ($config['max_preview'] ?? 0)) ?></strong>
    </article>
    <article class="stat-card">
        <span class="stat-card__label">Arquivos no preview</span>
        <strong class="stat-card__value"><?= h((string) count($preview)) ?></strong>
    </article>
</section>

<article class="module-card papers-card">
    <header class="module-card__header">
        <div>
            <h2>Importador legado reorganizado</h2>
            <p class="muted">Esta tela replica a ideia do <code>papers_import.php</code> original: varrer uma pasta-base real e alimentar a tabela <code>papers</code>.</p>
        </div>
    </header>

    <form method="post" action="<?= h(url('papers/import.php')) ?>" class="papers-form">
        <?= csrf_input() ?>
        <div class="questionnaire-grid">
            <label class="question-field question-field--full">
                <span class="question-field__label">Pasta-base</span>
                <input type="text" name="path" value="<?= h((string) ($config['base_path'] ?? '')) ?>" placeholder="Ex.: C:\xampp\htdocs\Produtividade_emp\Bibliografia\upload\CR y R">
            </label>

            <div class="question-field question-field--full question-field--hint">
                <span class="question-field__label">Como funciona</span>
                <div class="helper-block">
                    <p>1. O preview varre a pasta e mostra os arquivos elegíveis.</p>
                    <p>2. A importação cria ou atualiza registros na tabela <code>papers</code> usando <code>file_source_type = relative_path</code>.</p>
                    <p>3. O cache/RAG continua separado em <code>papers_file_cache</code>.</p>
                </div>
            </div>
        </div>

        <div class="inline-actions">
            <button type="submit" name="action" value="preview" class="action-pill action-pill--outline">Atualizar preview</button>
            <button type="submit" name="action" value="import" class="action-pill action-pill--solid" onclick="return confirm('Importar/atualizar todos os arquivos encontrados?');">Importar para papers</button>
        </div>
    </form>
</article>

<article class="module-card papers-card">
    <header class="module-card__header">
        <div>
            <h2>Preview da pasta</h2>
            <p class="muted">Arquivos detectados pela mesma lógica do importador real. O preview é limitado pelo valor configurado no ambiente.</p>
        </div>
    </header>

    <?php if ($preview === []): ?>
        <div class="empty-state">
            <p>Nenhum arquivo encontrado para pré-visualização. Ajuste o <code>PAPER_IMPORT_BASE_PATH</code> no <code>.env</code> ou informe a pasta acima.</p>
        </div>
    <?php else: ?>
        <div class="table-shell">
            <table class="data-table">
                <thead>
                <tr>
                    <th>Título sugerido</th>
                    <th>Arquivo</th>
                    <th>MIME</th>
                    <th>Origem</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($preview as $row): ?>
                    <tr>
                        <td><?= h((string) ($row['title'] ?? '')) ?></td>
                        <td><?= h((string) ($row['file_preferred_name'] ?? '')) ?></td>
                        <td><?= h((string) ($row['file_preferred_mime'] ?? '')) ?></td>
                        <td><code><?= h((string) ($row['file_source_value'] ?? '')) ?></code></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</article>
