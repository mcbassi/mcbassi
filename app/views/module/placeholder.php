<?php
declare(strict_types=1);
?>
<div class="module-toolbar">
    <a data-shell-nav="true" class="action-pill action-pill--outline" href="<?= h(url('index.php')) ?>">Voltar ao dashboard</a>
    <a data-shell-nav="true" class="action-pill action-pill--amber" href="<?= h(url('tela_sql/index.php')) ?>">Abrir SQL Sentences</a>
    <?php if (in_array($module, ['analitica', 'estrategica'], true)): ?>
        <a data-shell-nav="true" class="action-pill action-pill--ghost" href="<?= h(url('prompts/index.php?context=' . rawurlencode($module))) ?>">Editar Prompts</a>
        <a data-shell-nav="true" class="action-pill action-pill--ghost" href="<?= h(url('prompts/form.php?context=' . rawurlencode($module))) ?>">Novo Prompt</a>
    <?php endif; ?>
</div>

<article class="module-card">
    <header class="module-card__header">
        <div>
            <h2><?= h($title) ?></h2>
            <p class="muted">Usuário: <?= h($user->name) ?> · Nível: <?= h($user->nivel) ?></p>
        </div>
    </header>

    <section class="placeholder-hero">
        <div class="placeholder-hero__copy">
            <h3>Módulo preparado dentro do shell novo</h3>
            <p>O container flutuante, o menu lateral e o topo já seguem o look-and-feel do legado. A próxima etapa é migrar a regra real deste módulo.</p>
        </div>

        <div class="placeholder-hero__panel">
            <div class="placeholder-kpi">
                <span>Status</span>
                <strong>Pronto para migração</strong>
            </div>
            <div class="placeholder-kpi">
                <span>Módulo</span>
                <strong><?= h(ucfirst($module)) ?></strong>
            </div>
        </div>
    </section>

    <section class="placeholder-grid">
        <article class="mini-card">
            <h4>Layout</h4>
            <p>O conteúdo já renderiza dentro da área central branca, mantendo a navegação lateral fixa.</p>
        </article>
        <article class="mini-card">
            <h4>Bootstrap</h4>
            <p>Sessão, base path, CSRF e autenticação por query string continuam ativos.</p>
        </article>
        <article class="mini-card">
            <h4>Próxima ação</h4>
            <p>Migrar os formulários, queries e serviços do legado para este módulo.</p>
        </article>
    </section>
</article>
