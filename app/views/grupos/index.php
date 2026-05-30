<?php
declare(strict_types=1);
?>
<style>body .workspace__inner{max-width:none;width:calc(100vw - 320px)}</style>
<?php

$groups = is_array($groups ?? null) ? $groups : [];
$questions = is_array($questions ?? null) ? $questions : [];
$selectedId = (int) ($selectedId ?? 0);
$editing = !empty($editing);
$currName = trim((string) ($currName ?? ''));
$currPromptGrp = trim((string) ($currPromptGrp ?? ''));
$currPickedSet = is_array($currPickedSet ?? null) ? $currPickedSet : [];
$success = $success ?? null;
$error = $error ?? null;

$selectedCount = 0;
foreach ($questions as $question) {
    $key = trim((string) ($question['qkey'] ?? ''));
    if ($key !== '' && isset($currPickedSet[$key])) {
        $selectedCount++;
    }
}

$groupsBySection = [];
foreach ($questions as $question) {
    $section = trim((string) ($question['qsect'] ?? ''));
    $section = $section !== '' ? $section : 'Sem seção';
    $groupsBySection[$section][] = $question;
}
ksort($groupsBySection, SORT_NATURAL | SORT_FLAG_CASE);
?>
<div class="module-toolbar">
    <a data-shell-nav="true" class="action-pill action-pill--ghost" href="<?= h(url('grupos/index.php')) ?>">Editar Grupos</a>
    <a data-shell-nav="true" class="action-pill action-pill--ghost" href="<?= h(url('prompts/index.php')) ?>">Editar Prompts</a>
    <a data-shell-nav="true" class="action-pill action-pill--ghost" href="<?= h(url('prioridades/index.php')) ?>">IA Prioridades</a>
</div>

<?php if (!empty($error)): ?>
    <article class="module-card notice-card notice-card--error">
        <strong>Erro:</strong> <?= h((string) $error) ?>
    </article>
<?php endif; ?>

<?php if (!empty($success)): ?>
    <article class="module-card notice-card notice-card--success">
        <strong>Sucesso:</strong> <?= h((string) $success) ?>
    </article>
<?php endif; ?>

<section class="stats-grid stats-grid--groups">
    <article class="stat-card">
        <span class="stat-card__label">Grupos existentes</span>
        <strong class="stat-card__value"><?= count($groups) ?></strong>
    </article>
    <article class="stat-card">
        <span class="stat-card__label">Perguntas disponíveis</span>
        <strong class="stat-card__value"><?= count($questions) ?></strong>
    </article>
    <article class="stat-card">
        <span class="stat-card__label">Perguntas no grupo</span>
        <strong class="stat-card__value"><?= $selectedCount ?></strong>
    </article>
    <article class="stat-card">
        <span class="stat-card__label">Modo</span>
        <strong class="stat-card__value stat-card__value--small"><?= $editing ? 'Editando' : 'Novo grupo' ?></strong>
    </article>
</section>

<div class="real-groups-layout">
    <aside class="module-card real-groups-sidebar">
        <header class="module-card__header">
            <div>
                <h2>Grupos existentes</h2>
                <p class="muted">Base real: <code>grupos_nome</code>.</p>
            </div>
        </header>

        <?php if ($groups === []): ?>
            <div class="empty-table">Nenhum grupo encontrado em <code>grupos_nome</code>.</div>
        <?php else: ?>
            <div class="real-groups-list">
                <?php foreach ($groups as $group): ?>
                    <?php
                    $gid = (int) ($group['id'] ?? 0);
                    $gname = trim((string) ($group['name'] ?? ''));
                    $promptGrp = trim((string) ($group['prompt_grp'] ?? ''));
                    ?>
                    <a data-shell-nav="true" class="real-group-pill <?= $gid === $selectedId ? 'is-active' : '' ?>" href="<?= h(url('grupos/index.php?id=' . $gid)) ?>">
                        <strong><?= h($gname !== '' ? $gname : ('Grupo #' . $gid)) ?></strong>
                        <span>ID <?= $gid ?><?= $promptGrp !== '' ? ' · ' . $promptGrp : '' ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </aside>

    <section class="real-groups-main">
        <article class="module-card">
            <header class="module-card__header">
                <div>
                    <h2><?= $editing ? 'Editar grupo' : 'Novo grupo' ?></h2>
                    <p class="muted">Relaciona perguntas do questionário ao grupo, substituindo a lista atual ao salvar.</p>
                </div>
                <?php if ($editing): ?>
                    <a data-shell-nav="true" class="action-pill action-pill--outline" href="<?= h(url('grupos/index.php')) ?>">Novo grupo</a>
                <?php endif; ?>
            </header>

            <form method="post" action="<?= h(url('grupos/index.php')) ?>" class="real-groups-form">
                <?= csrf_input() ?>
                <input type="hidden" name="action" value="<?= $editing ? 'save' : 'create' ?>">
                <input type="hidden" name="id_grupo" value="<?= h((string) $selectedId) ?>">

                <div class="real-groups-form-grid">
                    <label class="form-field">
                        <span>Nome do grupo</span>
                        <input type="text" name="nome_grupo" value="<?= h($currName) ?>" placeholder="Ex: Prioridade — Segurança / Compliance">
                    </label>

                    <label class="form-field">
                        <span>prompt_grp</span>
                        <input type="text" name="prompt_grp" value="<?= h($currPromptGrp) ?>" placeholder="Código/identificador do grupo de prompts">
                    </label>
                </div>

                <div class="real-groups-form-actions">
                    <button type="submit" class="action-pill action-pill--green"><?= $editing ? 'Salvar grupo' : 'Criar grupo' ?></button>
                    <?php if ($editing): ?>
                        <button type="submit" class="action-pill action-pill--danger" name="action" value="delete" onclick="return confirm('Apagar este grupo?');">Apagar grupo</button>
                    <?php endif; ?>
                </div>

                <div class="real-groups-questions-card">
                    <div class="real-groups-questions-head">
                        <div>
                            <h3>Perguntas do grupo</h3>
                            <p class="muted">Base real: <code>grupos_prioridades</code> + <code>form_fields</code>.</p>
                        </div>
                        <div class="real-groups-question-tools">
                            <button type="button" class="action-pill action-pill--ghost js-mark-all-questions">Marcar todas</button>
                            <button type="button" class="action-pill action-pill--ghost js-mark-none-questions">Desmarcar</button>
                        </div>
                    </div>

                    <?php if ($questions === []): ?>
                        <div class="empty-table">Nenhuma pergunta encontrada em <code>form_fields</code>.</div>
                    <?php else: ?>
                        <div class="real-groups-section-list">
                            <?php foreach ($groupsBySection as $section => $sectionQuestions): ?>
                                <section class="real-groups-section">
                                    <header class="real-groups-section__header">
                                        <h4><?= h($section) ?></h4>
                                        <span><?= count($sectionQuestions) ?> pergunta(s)</span>
                                    </header>

                                    <div class="real-groups-question-list">
                                        <?php foreach ($sectionQuestions as $question): ?>
                                            <?php
                                            $qkey = trim((string) ($question['qkey'] ?? ''));
                                            $qlabel = trim((string) ($question['qlabel'] ?? ''));
                                            $qalt = trim((string) ($question['qalt'] ?? ''));
                                            $promptCode = trim((string) ($question['prompt_code'] ?? ''));
                                            $isChecked = $qkey !== '' && isset($currPickedSet[$qkey]);
                                            ?>
                                            <label class="real-groups-question-item">
                                                <input type="checkbox" name="questions[]" value="<?= h($qkey) ?>" <?= $isChecked ? 'checked' : '' ?>>
                                                <div class="real-groups-question-copy">
                                                    <strong><?= h($qlabel !== '' ? $qlabel : $qkey) ?></strong>
                                                    <span><?= h($qkey) ?><?= $qalt !== '' ? ' · alt: ' . $qalt : '' ?><?= $promptCode !== '' ? ' · prompt: ' . $promptCode : '' ?></span>
                                                </div>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </section>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </form>
        </article>
    </section>
</div>

<script>
(function () {
    const root = document.querySelector('.js-shell-content') || document;
    const markAll = root.querySelector('.js-mark-all-questions');
    const markNone = root.querySelector('.js-mark-none-questions');
    const checkboxes = Array.from(root.querySelectorAll('input[type="checkbox"][name="questions[]"]'));

    markAll?.addEventListener('click', function () {
        checkboxes.forEach(function (checkbox) { checkbox.checked = true; });
    });

    markNone?.addEventListener('click', function () {
        checkboxes.forEach(function (checkbox) { checkbox.checked = false; });
    });
})();
</script>
