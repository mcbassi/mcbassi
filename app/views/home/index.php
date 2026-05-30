<?php
declare(strict_types=1);
?>
<?php if (!$auth->check()): ?>
    <div class="module-toolbar">
        <span class="action-pill action-pill--ghost">Aguardando autenticação</span>
    </div>

    <article class="module-card">
        <header class="module-card__header">
            <div>
                <h2>Login de acesso</h2>
                <p class="muted">Use a URL de entrada do legado para abrir a sessão.</p>
            </div>
        </header>

        <div class="empty-state">
            <p>Entrada esperada:</p>
            <pre class="code"><?= h(url('index.php')) ?>?user=marco&amp;email=mcbassi%40grupohdi.com&amp;nivel=BPO</pre>
        </div>
    </article>
<?php else: ?>
    <div class="module-toolbar">
        <a data-shell-nav="true" class="action-pill action-pill--outline" href="<?= h(url('diagnostico/index.php')) ?>">Editar Última</a>
        <a data-shell-nav="true" class="action-pill action-pill--amber" href="<?= h(url('diagnostico/index.php')) ?>">Nova Sessão (Baseada na anterior)</a>
        <a data-shell-nav="off" class="action-pill action-pill--danger" href="<?= h(url('tela_sql/index.php')) ?>">Avaliar Status</a>
    </div>

    <article class="module-card">
        <header class="module-card__header">
            <div>
                <h2>Dashboard — Respostas</h2>
                <p class="muted">Canvas central alinhado ao legado, com atalhos operacionais e leitura rápida do estado atual.</p>
            </div>
        </header>

        <section class="control-row">
            <label class="form-field">
                <span>Empresa</span>
                <select>
                    <option>Keller1</option>
                    <option selected>Grupo HDI</option>
                    <option>ProdCol</option>
                </select>
            </label>
        </section>

        <section class="stats-grid">
            <article class="stat-card">
                <span class="stat-card__label">Sessões</span>
                <strong class="stat-card__value">4</strong>
            </article>
            <article class="stat-card">
                <span class="stat-card__label">Empresas</span>
                <strong class="stat-card__value">1</strong>
            </article>
            <article class="stat-card">
                <span class="stat-card__label">Última sessão</span>
                <strong class="stat-card__value stat-card__value--small">Keller1 — 30/12/2025 20:23</strong>
            </article>
            <article class="stat-card">
                <span class="stat-card__label">Completude (última)</span>
                <strong class="stat-card__value">96.1%</strong>
            </article>
        </section>

        
<?php endif; ?>
