<?php
declare(strict_types=1);

$overview = is_array($overview ?? null) ? $overview : ['stats' => [], 'rows' => [], 'recent_usage' => [], 'availability' => []];
$stats = is_array($overview['stats'] ?? null) ? $overview['stats'] : [];
$rows = is_array($overview['rows'] ?? null) ? $overview['rows'] : [];
$recentUsage = is_array($overview['recent_usage'] ?? null) ? $overview['recent_usage'] : [];
$availability = is_array($overview['availability'] ?? null) ? $overview['availability'] : [];
?>
<div class="module-toolbar">
    <a data-shell-nav="true" class="action-pill action-pill--outline" href="<?= h(url('papers/index.php')) ?>">Relação de textos</a>
    <a data-shell-nav="true" class="action-pill action-pill--outline" href="<?= h(url('papers/import.php')) ?>">Importar bibliografia</a>
    <a data-shell-nav="true" class="action-pill action-pill--solid" href="<?= h(url('papers/prompts.php')) ?>">Fluxo de prompts</a>
    <a data-shell-nav="true" class="action-pill action-pill--outline" href="<?= h(url('papers/chapters.php')) ?>">Capítulos × Publicações</a>
</div>

<?php if (($error ?? null) !== null): ?>
    <div class="alert-banner alert-banner--danger"><?= h((string) $error) ?></div>
<?php endif; ?>

<section class="stats-grid stats-grid--papers">
    <article class="stat-card">
        <span class="stat-card__label">Prompts no catálogo</span>
        <strong class="stat-card__value"><?= h((string) ($stats['total_prompts'] ?? 0)) ?></strong>
    </article>
    <article class="stat-card">
        <span class="stat-card__label">Prompt codes em papers</span>
        <strong class="stat-card__value"><?= h((string) ($stats['papers_with_prompt_code'] ?? 0)) ?></strong>
    </article>
    <article class="stat-card">
        <span class="stat-card__label">Codes com match no catálogo</span>
        <strong class="stat-card__value"><?= h((string) ($stats['prompt_codes_linked_to_catalog'] ?? 0)) ?></strong>
    </article>
    <article class="stat-card">
        <span class="stat-card__label">Logs em prompt_file_usage</span>
        <strong class="stat-card__value"><?= h((string) ($stats['prompt_usage_rows'] ?? 0)) ?></strong>
    </article>
</section>

<div class="availability-strip">
    <span class="availability-pill<?= !empty($availability['prompts']) ? ' is-on' : '' ?>">prompts</span>
    <span class="availability-pill<?= !empty($availability['prompt_file_usage']) ? ' is-on' : '' ?>">prompt_file_usage</span>
    <span class="availability-pill<?= !empty($availability['responses_detailed']) ? ' is-on' : '' ?>">responses_detailed</span>
</div>

<article class="module-card papers-card">
    <header class="module-card__header">
        <div>
            <h2>Mapa de vínculo entre prompts e papers</h2>
            <p class="muted">Aqui a ligação é feita por <code>papers.prompt_code = prompts.assistente</code> e pelos usos reais gravados em <code>prompt_file_usage</code>.</p>
        </div>
    </header>

    <?php if ($rows === []): ?>
        <div class="empty-state">
            <p>Nenhum prompt encontrado no catálogo ou não foi possível ler as tabelas necessárias.</p>
        </div>
    <?php else: ?>
        <div class="table-shell">
            <table class="data-table">
                <thead>
                <tr>
                    <th>Assistente</th>
                    <th>Função</th>
                    <th>Papers ligados</th>
                    <th>Com cache</th>
                    <th>Uso em IA</th>
                    <th>Prévia dos papers</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?= h((string) ($row['assistente'] ?? '')) ?></td>
                        <td><?= h((string) ($row['funcao'] ?? '')) ?></td>
                        <td><?= h((string) ($row['linked_papers_count'] ?? 0)) ?></td>
                        <td><?= h((string) ($row['cache_ready_count'] ?? 0)) ?></td>
                        <td><?= h((string) ($row['usage_count'] ?? 0)) ?></td>
                        <td>
                            <?php if (($row['linked_papers'] ?? []) === []): ?>
                                <span class="muted">Sem vínculo</span>
                            <?php else: ?>
                                <div class="inline-chip-list">
                                    <?php foreach (($row['linked_papers'] ?? []) as $paper): ?>
                                        <a data-shell-nav="true" class="tiny-chip tiny-chip--blue" href="<?= h(url('papers/index.php?id=' . rawurlencode((string) ($paper['id'] ?? '')))) ?>">
                                            <?= h((string) ($paper['title'] ?? 'Paper')) ?>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</article>

<article class="module-card papers-card">
    <header class="module-card__header">
        <div>
            <h2>Uso recente em IA</h2>
            <p class="muted">Linhas recentes de <code>prompt_file_usage</code>, para enxergar quando os arquivos da bibliografia realmente entram na execução.</p>
        </div>
    </header>

    <?php if ($recentUsage === []): ?>
        <div class="empty-state"><p>Sem linhas recentes em <code>prompt_file_usage</code>.</p></div>
    <?php else: ?>
        <div class="table-shell">
            <table class="data-table">
                <thead>
                <tr>
                    <th>Quando</th>
                    <th>Prompt</th>
                    <th>Paper</th>
                    <th>Empresa</th>
                    <th>E-mail</th>
                    <th>Modo</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($recentUsage as $row): ?>
                    <tr>
                        <td><?= h((string) ($row['used_at'] ?? '')) ?></td>
                        <td><?= h((string) (($row['prompt_assistente'] ?? '') !== '' ? $row['prompt_assistente'] : ($row['prompt_row_id'] ?? ''))) ?></td>
                        <td><?= h((string) ($row['paper_title'] ?? '')) ?></td>
                        <td><?= h((string) ($row['company_name'] ?? '')) ?></td>
                        <td><?= h((string) ($row['email_resp'] ?? '')) ?></td>
                        <td><?= h((string) ($row['execution_mode'] ?? 'responses_file_input')) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</article>
