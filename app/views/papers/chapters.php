<?php
declare(strict_types=1);

$tree = is_array($tree ?? null) ? $tree : ['chapters' => [], 'unassigned' => ['papers' => []], 'papers' => []];
$chapters = is_array($tree['chapters'] ?? null) ? $tree['chapters'] : [];
$unassigned = is_array($tree['unassigned'] ?? null) ? $tree['unassigned'] : ['code' => '__NONE__', 'label' => 'Sem capítulo', 'papers' => [], 'count' => 0];
$allPapers = is_array($tree['papers'] ?? null) ? $tree['papers'] : [];
?>
<?php if ($notice !== null): ?>
    <div class="alert-banner"><?= h($notice) ?></div>
<?php endif; ?>

<?php if (($error ?? null) !== null): ?>
    <div class="alert-banner alert-banner--danger"><?= h((string) $error) ?></div>
<?php endif; ?>

<div class="module-toolbar">
    <a data-shell-nav="true" class="action-pill action-pill--ghost" href="<?= h(url('papers/index.php')) ?>">Bibliografia</a>
    <a data-shell-nav="true" class="action-pill action-pill--outline" href="<?= h(url('papers/form.php')) ?>">Novo Paper</a>
    <a data-shell-nav="true" class="action-pill action-pill--outline" href="<?= h(url('papers/import.php')) ?>">Importar Bibliografia</a>
    <a data-shell-nav="true" class="action-pill action-pill--outline" href="<?= h(url('papers/prompts.php')) ?>">Fluxo de prompts</a>
    <a data-shell-nav="true" class="action-pill action-pill--solid" href="<?= h(url('papers/chapters.php')) ?>">Capítulos × Publicações</a>
</div>

<article class="module-card">
    <header class="module-card__header module-card__header--stacked">
        <div>
            <div class="entity-kicker">Bibliografia / vínculo operacional</div>
            <h2>Capítulos × Publicações</h2>
            <p class="muted">Arraste as publicações para um capítulo. O vínculo é salvo em <code>papers.chapter_code</code> e pode ser remanejado entre capítulos.</p>
        </div>
    </header>

    <form method="get" action="<?= h(url('papers/chapters.php')) ?>" class="legacy-filter-grid legacy-filter-grid--chapters">
        <label class="question-field question-field--full">
            <span class="question-field__label">Buscar publicações pelo nome</span>
            <input type="text" name="q" value="<?= h((string) ($query ?? '')) ?>" placeholder="Filtrar painel lateral por título ou journal">
        </label>

        <div class="inline-actions inline-actions--end">
            <button type="submit" class="action-pill action-pill--solid">Filtrar</button>
            <a data-shell-nav="true" class="action-pill action-pill--outline" href="<?= h(url('papers/chapters.php')) ?>">Limpar</a>
        </div>
    </form>

    <div class="chapter-board" data-chapter-board data-assign-url="<?= h(url('papers/chapters.php')) ?>" data-csrf="<?= h(csrf_token()) ?>">
        <section class="chapter-tree-panel">
            <div class="tree-panel-head">
                <div>
                    <h3>Árvore de capítulos</h3>
                    <p class="muted">Os capítulos são a base da árvore. Itens já vinculados aparecem carregados aqui.</p>
                </div>
            </div>

            <div class="chapter-tree">
                <?php foreach ($chapters as $chapter): ?>
                    <?php $chapterCode = (string) ($chapter['code'] ?? ''); ?>
                    <article class="chapter-node" data-drop-zone="true" data-chapter-code="<?= h($chapterCode) ?>">
                        <header class="chapter-node__head">
                            <button type="button" class="chapter-toggle" data-tree-toggle aria-expanded="true">
                                <span class="chapter-toggle__label"><?= h((string) ($chapter['label'] ?? $chapterCode)) ?></span>
                                <span class="chapter-toggle__count" data-chapter-count><?= h((string) ((int) ($chapter['count'] ?? 0))) ?></span>
                            </button>
                        </header>
                        <ul class="chapter-node__list" data-chapter-list>
                            <?php foreach ((array) ($chapter['papers'] ?? []) as $paper): ?>
                                <li class="chapter-paper" draggable="true" data-paper-id="<?= h((string) ($paper['id'] ?? 0)) ?>" data-paper-title="<?= h((string) ($paper['title'] ?? '')) ?>" data-current-chapter="<?= h($chapterCode) ?>">
                                    <span class="chapter-paper__title"><?= h((string) ($paper['title'] ?? '')) ?></span>
                                    <span class="chapter-paper__meta"><?= h((string) ($paper['journal'] ?? '')) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </article>
                <?php endforeach; ?>

                <article class="chapter-node chapter-node--unassigned" data-drop-zone="true" data-chapter-code="__NONE__">
                    <header class="chapter-node__head">
                        <button type="button" class="chapter-toggle" data-tree-toggle aria-expanded="true">
                            <span class="chapter-toggle__label"><?= h((string) ($unassigned['label'] ?? 'Sem capítulo')) ?></span>
                            <span class="chapter-toggle__count" data-chapter-count><?= h((string) ((int) ($unassigned['count'] ?? 0))) ?></span>
                        </button>
                    </header>
                    <ul class="chapter-node__list" data-chapter-list>
                        <?php foreach ((array) ($unassigned['papers'] ?? []) as $paper): ?>
                            <li class="chapter-paper" draggable="true" data-paper-id="<?= h((string) ($paper['id'] ?? 0)) ?>" data-paper-title="<?= h((string) ($paper['title'] ?? '')) ?>" data-current-chapter="__NONE__">
                                <span class="chapter-paper__title"><?= h((string) ($paper['title'] ?? '')) ?></span>
                                <span class="chapter-paper__meta"><?= h((string) ($paper['journal'] ?? '')) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </article>
            </div>
        </section>

        <aside class="chapter-library-panel">
            <div class="tree-panel-head">
                <div>
                    <h3>Publicações</h3>
                    <p class="muted">Painel lateral com todas as publicações filtradas por nome.</p>
                </div>
                <span class="rag-chip rag-chip--info"><?= h((string) count($allPapers)) ?> itens</span>
            </div>

            <div class="library-list" data-library-list>
                <?php foreach ($allPapers as $paper): ?>
                    <?php
                        $paperId = (int) ($paper['id'] ?? 0);
                        $chapterCode = trim((string) ($paper['chapter_code'] ?? ''));
                    ?>
                    <div class="library-paper" draggable="true" data-paper-id="<?= h((string) $paperId) ?>" data-paper-title="<?= h((string) ($paper['title'] ?? '')) ?>" data-current-chapter="<?= h($chapterCode !== '' ? $chapterCode : '__NONE__') ?>">
                        <strong class="library-paper__title"><?= h((string) ($paper['title'] ?? '')) ?></strong>
                        <div class="library-paper__meta"><?= h((string) ($paper['journal'] ?? '')) ?></div>
                        <div class="library-paper__footer">
                            <span class="rag-chip rag-chip--warning paper-badge" data-paper-badge-id="<?= h((string) $paperId) ?>">
                                <?= h($chapterCode !== '' ? $chapterCode : 'Sem capítulo') ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </aside>
    </div>
</article>

<script>
(function () {
    const board = document.querySelector('[data-chapter-board]');
    if (!board) {
        return;
    }

    const assignUrl = board.dataset.assignUrl;
    const csrfToken = board.dataset.csrf || '';
    let dragging = null;

    function setStatus(message, isError) {
        let node = document.querySelector('.chapter-board-status');
        if (!node) {
            node = document.createElement('div');
            node.className = 'chapter-board-status';
            board.prepend(node);
        }
        node.textContent = message;
        node.classList.toggle('is-error', Boolean(isError));
    }

    function updateBadge(paperId, chapterCode) {
        document.querySelectorAll('[data-paper-badge-id="' + paperId + '"]').forEach(function (badge) {
            badge.textContent = chapterCode && chapterCode !== '__NONE__' ? chapterCode : 'Sem capítulo';
        });
    }

    function createTreeItem(paperId, title, meta, chapterCode) {
        const item = document.createElement('li');
        item.className = 'chapter-paper';
        item.draggable = true;
        item.dataset.paperId = String(paperId);
        item.dataset.paperTitle = title || '';
        item.dataset.currentChapter = chapterCode || '__NONE__';

        const titleSpan = document.createElement('span');
        titleSpan.className = 'chapter-paper__title';
        titleSpan.textContent = title || '';

        const metaSpan = document.createElement('span');
        metaSpan.className = 'chapter-paper__meta';
        metaSpan.textContent = meta || '';

        item.appendChild(titleSpan);
        item.appendChild(metaSpan);

        bindDrag(item);
        return item;
    }

    function refreshCounts() {
        document.querySelectorAll('[data-drop-zone]').forEach(function (zone) {
            const list = zone.querySelector('[data-chapter-list]');
            const count = list ? list.querySelectorAll('.chapter-paper').length : 0;
            const label = zone.querySelector('[data-chapter-count]');
            if (label) {
                label.textContent = String(count);
            }
        });
    }

    function removeExistingTreeEntries(paperId) {
        document.querySelectorAll('.chapter-paper[data-paper-id="' + paperId + '"]').forEach(function (node) {
            node.remove();
        });
    }

    function bindDrag(node) {
        node.addEventListener('dragstart', function () {
            dragging = {
                paperId: String(node.dataset.paperId || ''),
                title: String(node.dataset.paperTitle || node.textContent || '').trim(),
                currentChapter: String(node.dataset.currentChapter || '__NONE__'),
                meta: String((node.querySelector('.chapter-paper__meta') || {}).textContent || '')
            };
            node.classList.add('is-dragging');
        });

        node.addEventListener('dragend', function () {
            node.classList.remove('is-dragging');
        });
    }

    document.querySelectorAll('.chapter-paper, .library-paper').forEach(function (node) {
        node.addEventListener('dragstart', function () {
            dragging = {
                paperId: String(node.dataset.paperId || ''),
                title: String(node.dataset.paperTitle || node.textContent || '').trim(),
                currentChapter: String(node.dataset.currentChapter || '__NONE__'),
                meta: String((node.querySelector('.chapter-paper__meta') || node.querySelector('.library-paper__meta') || {}).textContent || '')
            };
            node.classList.add('is-dragging');
        });

        node.addEventListener('dragend', function () {
            node.classList.remove('is-dragging');
        });
    });

    document.querySelectorAll('[data-drop-zone]').forEach(function (zone) {
        zone.addEventListener('dragover', function (event) {
            event.preventDefault();
            zone.classList.add('is-drop-target');
        });

        zone.addEventListener('dragleave', function () {
            zone.classList.remove('is-drop-target');
        });

        zone.addEventListener('drop', async function (event) {
            event.preventDefault();
            zone.classList.remove('is-drop-target');

            if (!dragging || !dragging.paperId) {
                return;
            }

            const targetChapter = String(zone.dataset.chapterCode || '__NONE__');
            if (dragging.currentChapter === targetChapter) {
                setStatus('A publicação já está neste capítulo.', false);
                return;
            }

            const formData = new FormData();
            formData.append('_csrf', csrfToken);
            formData.append('paper_id', dragging.paperId);
            formData.append('chapter_code', targetChapter);

            try {
                const response = await fetch(assignUrl, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                const payload = await response.json();
                if (!response.ok || !payload.ok) {
                    throw new Error(payload.message || 'Falha ao vincular capítulo.');
                }

                removeExistingTreeEntries(dragging.paperId);

                const list = zone.querySelector('[data-chapter-list]');
                if (list) {
                    list.appendChild(createTreeItem(
                        dragging.paperId,
                        dragging.title,
                        dragging.meta,
                        targetChapter
                    ));
                }

                document.querySelectorAll('[data-paper-id="' + dragging.paperId + '"]').forEach(function (node) {
                    node.dataset.currentChapter = targetChapter;
                });

                updateBadge(dragging.paperId, targetChapter);
                refreshCounts();
                setStatus(payload.message || 'Vínculo salvo.', false);
                dragging.currentChapter = targetChapter;
            } catch (error) {
                setStatus(error.message || 'Falha ao vincular capítulo.', true);
            }
        });
    });

    document.querySelectorAll('[data-tree-toggle]').forEach(function (button) {
        button.addEventListener('click', function () {
            const expanded = button.getAttribute('aria-expanded') !== 'false';
            const node = button.closest('.chapter-node');
            if (!node) {
                return;
            }
            node.classList.toggle('is-collapsed', expanded);
            button.setAttribute('aria-expanded', expanded ? 'false' : 'true');
        });
    });

    refreshCounts();
})();
</script>
