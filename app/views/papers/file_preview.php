<?php
declare(strict_types=1);

$paper = is_array($paper ?? null) ? $paper : null;
$preview = is_array($preview ?? null) ? $preview : ['mode' => 'error', 'message' => 'Pré-visualização indisponível.'];
$mode = (string) ($preview['mode'] ?? 'error');
?>
<div class="module-toolbar">
    <a data-shell-nav="true" class="action-pill action-pill--outline" href="<?= h(url('papers/index.php')) ?>">Voltar para a lista</a>
    <?php if (is_array($paper) && (int) ($paper['id'] ?? 0) > 0): ?>
        <a data-shell-nav="true" class="action-pill action-pill--outline" href="<?= h(url('papers/view.php?id=' . rawurlencode((string) ($paper['id'] ?? 0)))) ?>">Voltar ao paper</a>
    <?php endif; ?>
</div>

<article class="module-card papers-card">
    <header class="module-card__header module-card__header--stacked">
        <div>
            <div class="entity-kicker">Bibliografia / visualização somente leitura</div>
            <h2><?= h((string) ($preview['title'] ?? 'Arquivo')) ?></h2>
            <p class="muted"><?= h((string) ($preview['file_name'] ?? '')) ?></p>
        </div>
    </header>

    <?php if ($mode === 'error'): ?>
        <div class="alert-banner alert-banner--danger"><?= h((string) ($preview['message'] ?? 'Não foi possível abrir o arquivo.')) ?></div>
    <?php elseif ($mode === 'docx'): ?>
        <div class="viewer-note">Visualização somente leitura de documento Word.</div>
        <div class="doc-preview">
            <?php foreach (($preview['sections'] ?? []) as $paragraph): ?>
                <p><?= h((string) $paragraph) ?></p>
            <?php endforeach; ?>
            <?php if (($preview['sections'] ?? []) === []): ?>
                <p class="muted">Nenhum conteúdo legível foi encontrado no DOCX.</p>
            <?php endif; ?>
        </div>
    <?php elseif ($mode === 'xlsx'): ?>
        <div class="viewer-note">Visualização somente leitura de planilha Excel.</div>
        <?php foreach (($preview['sheets'] ?? []) as $sheet): ?>
            <section class="viewer-sheet">
                <h3><?= h((string) ($sheet['name'] ?? 'Planilha')) ?></h3>
                <div class="table-shell">
                    <table class="data-table data-table--compact">
                        <tbody>
                        <?php foreach (($sheet['rows'] ?? []) as $row): ?>
                            <tr>
                                <?php foreach ($row as $cell): ?>
                                    <td><?= h((string) $cell) ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        <?php endforeach; ?>
        <?php if (($preview['sheets'] ?? []) === []): ?>
            <p class="muted">Nenhuma aba legível foi encontrada na planilha.</p>
        <?php endif; ?>
    <?php elseif ($mode === 'text'): ?>
        <div class="viewer-note">Visualização somente leitura do conteúdo textual.</div>
        <pre class="text-preview"><?= h((string) ($preview['text'] ?? '')) ?></pre>
    <?php else: ?>
        <div class="alert-banner"><?= h((string) ($preview['message'] ?? 'O navegador abriu o arquivo em uma aba separada.')) ?></div>
    <?php endif; ?>
</article>
