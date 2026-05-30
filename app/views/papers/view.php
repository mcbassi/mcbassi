<?php
declare(strict_types=1);

$paper = is_array($paper ?? null) ? $paper : null;
$promptContext = is_array($promptContext ?? null) ? $promptContext : null;
$availability = is_array($availability ?? null) ? $availability : [];
$paperIndicators = is_array($paperIndicators ?? null) ? $paperIndicators : [];

if (!function_exists('papers_fmt_value')) {
    function papers_fmt_value(mixed $value, string $fallback = '—'): string
    {
        $text = trim((string) ($value ?? ''));
        return $text === '' ? $fallback : $text;
    }
}

if (!function_exists('papers_fmt_bytes')) {
    function papers_fmt_bytes(mixed $bytes): string
    {
        if ($bytes === null || $bytes === '' || !is_numeric((string) $bytes)) {
            return '—';
        }

        $value = (float) $bytes;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = 0;

        while ($value >= 1024 && $power < count($units) - 1) {
            $value /= 1024;
            $power++;
        }

        return number_format($value, $power === 0 ? 0 : 2, ',', '.') . ' ' . $units[$power];
    }
}

if (!function_exists('papers_dt')) {
    function papers_dt(mixed $value): string
    {
        $text = trim((string) ($value ?? ''));
        return $text === '' ? '—' : $text;
    }
}
?>
<div class="module-toolbar">
    <a data-shell-nav="true" class="action-pill action-pill--outline" href="<?= h(url('papers/index.php')) ?>">Voltar para a lista</a>
    <?php if (is_array($paper)): ?>
        <a data-shell-nav="true" class="action-pill action-pill--solid" href="<?= h(url('papers/form.php?id=' . rawurlencode((string) ($paper['id'] ?? 0)))) ?>">Editar paper</a>
    <?php endif; ?>
    <a data-shell-nav="true" class="action-pill action-pill--outline" href="<?= h(url('papers/form.php')) ?>">Novo Paper</a>
    <a data-shell-nav="true" class="action-pill action-pill--outline" href="<?= h(url('papers/prompts.php')) ?>">Fluxo de prompts</a>
    <a data-shell-nav="true" class="action-pill action-pill--outline" href="<?= h(url('papers/chapters.php')) ?>">Capítulos × Publicações</a>
</div>

<?php if ($notice !== null): ?>
    <div class="alert-banner"><?= h((string) $notice) ?></div>
<?php endif; ?>

<?php if (($error ?? null) !== null): ?>
    <div class="alert-banner alert-banner--danger"><?= h((string) $error) ?></div>
<?php endif; ?>

<?php if (!is_array($paper)): ?>
    <article class="module-card papers-card">
        <header class="module-card__header">
            <div>
                <h2>Paper não encontrado</h2>
                <p class="muted">Volte para a lista da biblioteca e escolha um registro válido.</p>
            </div>
        </header>
    </article>
<?php else: ?>
    <article class="module-card papers-card">
        <header class="module-card__header module-card__header--stacked">
            <div>
                <div class="entity-kicker">Bibliografia / detalhe do paper</div>
                <h2><?= h((string) ($paper['title'] ?? 'Sem título')) ?></h2>
                <p class="muted"><?= h((string) ($paper['journal'] ?? 'Journal não informado')) ?></p>
            </div>

            <div class="entity-badges">
                <span class="rag-chip rag-chip--<?= h((string) ($paper['rag_status_tone'] ?? 'muted')) ?>"><?= h((string) ($paper['rag_status_label'] ?? 'Sem cache')) ?></span>
                <span class="rag-chip rag-chip--dark">ID <?= h((string) ($paper['id'] ?? 0)) ?></span>
                <?php if (trim((string) ($paper['prompt_code'] ?? '')) !== ''): ?>
                    <span class="rag-chip rag-chip--info">Prompt <?= h((string) $paper['prompt_code']) ?></span>
                <?php endif; ?>
                <?php if (trim((string) ($paper['chapter_code'] ?? '')) !== ''): ?>
                    <span class="rag-chip rag-chip--warning"><?= h((string) $paper['chapter_code']) ?></span>
                <?php endif; ?>
            </div>
        </header>

        <section class="stats-grid stats-grid--papers-detail">
            <article class="stat-card">
                <span class="stat-card__label">Citações</span>
                <strong class="stat-card__value"><?= h((string) ((int) ($paper['citation_count'] ?? 0))) ?></strong>
            </article>
            <article class="stat-card">
                <span class="stat-card__label">Uso em prompts</span>
                <strong class="stat-card__value"><?= h((string) ((int) ($paper['usage_count'] ?? 0))) ?></strong>
            </article>
            <article class="stat-card">
                <span class="stat-card__label">Último uso</span>
                <strong class="stat-card__value stat-card__value--small"><?= h(papers_dt($paper['usage_last_at'] ?? $paper['last_used_at'] ?? null)) ?></strong>
            </article>
            <article class="stat-card">
                <span class="stat-card__label">Última atualização</span>
                <strong class="stat-card__value stat-card__value--small"><?= h(papers_dt($paper['updated_at'] ?? $paper['created_at'] ?? null)) ?></strong>
            </article>
        </section>

        <section class="papers-details-grid">
            <article class="detail-card">
                <div class="detail-card__head">
                    <div>
                        <h3>Dados do paper</h3>
                        <p class="muted">Metadados principais da publicação, no formato da base bibliográfica.</p>
                    </div>
                </div>

                <dl class="mini-definition mini-definition--wide">
                    <div><dt>ID</dt><dd><?= h((string) ($paper['id'] ?? 0)) ?></dd></div>
                    <div><dt>Título</dt><dd><?= h(papers_fmt_value($paper['title'] ?? null)) ?></dd></div>
                    <div><dt>Journal</dt><dd><?= h(papers_fmt_value($paper['journal'] ?? null)) ?></dd></div>
                    <div><dt>Prompt code</dt><dd><code><?= h(papers_fmt_value($paper['prompt_code'] ?? null)) ?></code></dd></div>
                    <div><dt>Capítulo</dt><dd><?= h(papers_fmt_value($paper['chapter_code'] ?? null)) ?></dd></div>
                    <div><dt>Citações</dt><dd><?= h((string) ((int) ($paper['citation_count'] ?? 0))) ?></dd></div>
                    <div><dt>Keywords</dt><dd><?= h(papers_fmt_value($paper['keywords'] ?? null)) ?></dd></div>
                    <div><dt>Key insight</dt><dd class="definition-text"><?= nl2br(h(papers_fmt_value($paper['key_insight'] ?? null))) ?></dd></div>
                    <div><dt>Arquivo / link</dt><dd>
                        <?php if ((trim((string) ($paper['link_url'] ?? '')) !== '') || (trim((string) ($paper['file_source_value'] ?? '')) !== '') || (trim((string) ($paper['local_cache_path'] ?? '')) !== '')): ?>
                            <a href="<?= h(url('papers/open.php?id=' . rawurlencode((string) ($paper['id'] ?? 0)))) ?>" target="_blank" rel="noreferrer">Abrir em modo leitura</a>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </dd></div>
                    <div><dt>Criado em</dt><dd><?= h(papers_dt($paper['created_at'] ?? null)) ?></dd></div>
                    <div><dt>Atualizado em</dt><dd><?= h(papers_dt($paper['updated_at'] ?? null)) ?></dd></div>
                </dl>
            </article>

            <article class="detail-card">
                <div class="detail-card__head">
                    <div>
                        <h3>Origem do arquivo</h3>
                        <p class="muted">Como o artigo está registrado para uso em IA e em importação.</p>
                    </div>
                </div>

                <dl class="mini-definition mini-definition--wide">
                    <div><dt>file_enabled</dt><dd><?= ((int) ($paper['file_enabled'] ?? 1)) === 1 ? 'Ativo' : 'Inativo' ?></dd></div>
                    <div><dt>file_source_type</dt><dd><?= h(papers_fmt_value($paper['file_source_type'] ?? null)) ?></dd></div>
                    <div><dt>file_source_value</dt><dd><code><?= h(papers_fmt_value($paper['file_source_value'] ?? null)) ?></code></dd></div>
                    <div><dt>file_preferred_name</dt><dd><?= h(papers_fmt_value($paper['file_preferred_name'] ?? $paper['original_filename'] ?? null)) ?></dd></div>
                    <div><dt>file_preferred_mime</dt><dd><?= h(papers_fmt_value($paper['file_preferred_mime'] ?? $paper['mime_type'] ?? null)) ?></dd></div>
                    <div><dt>file_last_resolved_at</dt><dd><?= h(papers_dt($paper['file_last_resolved_at'] ?? null)) ?></dd></div>
                    <div><dt>local_cache_path</dt><dd><code><?= h(papers_fmt_value($paper['local_cache_path'] ?? null)) ?></code></dd></div>
                    <div><dt>source_type</dt><dd><?= h(papers_fmt_value($paper['source_type'] ?? null)) ?></dd></div>
                    <div><dt>source_value</dt><dd><code><?= h(papers_fmt_value($paper['source_value'] ?? null)) ?></code></dd></div>
                </dl>
            </article>

            <article class="detail-card">
                <div class="detail-card__head">
                    <div>
                        <h3>Dados de RAG / cache</h3>
                        <p class="muted">Campos herdados de <code>papers_file_cache</code> e indicadores de preparação do arquivo para IA.</p>
                    </div>
                </div>

                <dl class="mini-definition mini-definition--wide">
                    <div><dt>cache_id</dt><dd><?= h(papers_fmt_value($paper['cache_id'] ?? null)) ?></dd></div>
                    <div><dt>cache_status</dt><dd><span class="rag-chip rag-chip--<?= h((string) ($paper['rag_status_tone'] ?? 'muted')) ?>"><?= h((string) ($paper['cache_status'] ?? $paper['rag_status_label'] ?? '—')) ?></span></dd></div>
                    <div><dt>exists_flag</dt><dd><?= h(papers_fmt_value($paper['exists_flag'] ?? null)) ?></dd></div>
                    <div><dt>source_sha256</dt><dd><code><?= h(papers_fmt_value($paper['source_sha256'] ?? null)) ?></code></dd></div>
                    <div><dt>original_filename</dt><dd><?= h(papers_fmt_value($paper['original_filename'] ?? null)) ?></dd></div>
                    <div><dt>mime_type</dt><dd><?= h(papers_fmt_value($paper['mime_type'] ?? null)) ?></dd></div>
                    <div><dt>file_ext</dt><dd><?= h(papers_fmt_value($paper['file_ext'] ?? null)) ?></dd></div>
                    <div><dt>size_bytes</dt><dd><?= h(papers_fmt_bytes($paper['size_bytes'] ?? null)) ?></dd></div>
                    <div><dt>openai_file_id</dt><dd><code><?= h(papers_fmt_value($paper['openai_file_id'] ?? null)) ?></code></dd></div>
                    <div><dt>openai_file_purpose</dt><dd><?= h(papers_fmt_value($paper['openai_file_purpose'] ?? null)) ?></dd></div>
                    <div><dt>vector_store_id</dt><dd><code><?= h(papers_fmt_value($paper['vector_store_id'] ?? null)) ?></code></dd></div>
                    <div><dt>last_checked_at</dt><dd><?= h(papers_dt($paper['last_checked_at'] ?? null)) ?></dd></div>
                    <div><dt>last_used_at</dt><dd><?= h(papers_dt($paper['last_used_at'] ?? $paper['usage_last_at'] ?? null)) ?></dd></div>
                    <div><dt>created_at</dt><dd><?= h(papers_dt($paper['created_at'] ?? null)) ?></dd></div>
                    <div><dt>updated_at</dt><dd><?= h(papers_dt($paper['updated_at'] ?? null)) ?></dd></div>
                    <div><dt>last_error</dt><dd class="definition-text"><pre class="inline-pre"><?= h(papers_fmt_value($paper['last_error'] ?? null)) ?></pre></dd></div>
                </dl>
            </article>

        </section>

        <?php if ($promptContext !== null): ?>
            <div class="section-divider"></div>

            <section class="papers-details-grid">
                <article class="detail-card detail-card--span-2">
                    <div class="detail-card__head">
                        <div>
                            <h3>Fluxo de prompts associado</h3>
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

                    <div class="stacked-table-block">
                        <h4>Prompts do catálogo</h4>
                        <div class="table-shell">
                            <table class="data-table data-table--compact">
                                <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Assistente</th>
                                    <th>Função</th>
                                    <th>Descrição</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach (($promptContext['prompt_catalog'] ?? []) as $promptRow): ?>
                                    <tr>
                                        <td><?= h((string) ($promptRow['id'] ?? '')) ?></td>
                                        <td><code><?= h((string) ($promptRow['assistente'] ?? '')) ?></code></td>
                                        <td><?= h((string) ($promptRow['funcao'] ?? '')) ?></td>
                                        <td><?= h((string) ($promptRow['descricao'] ?? '')) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (($promptContext['prompt_catalog'] ?? []) === []): ?>
                                    <tr>
                                        <td colspan="4" class="table-empty">Nenhum prompt de catálogo localizado para este código.</td>
                                    </tr>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="stacked-table-block">
                        <h4>Uso recente em IA</h4>
                        <div class="table-shell">
                            <table class="data-table data-table--compact">
                                <thead>
                                <tr>
                                    <th>Quando</th>
                                    <th>Prompt</th>
                                    <th>Empresa</th>
                                    <th>Email</th>
                                    <th>Modo</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach (($promptContext['recent_usage'] ?? []) as $usageRow): ?>
                                    <tr>
                                        <td><?= h((string) ($usageRow['used_at'] ?? '')) ?></td>
                                        <td><code><?= h((string) ($usageRow['prompt_assistente'] ?? $usageRow['prompt_funcao'] ?? $usageRow['prompt_row_id'] ?? '')) ?></code></td>
                                        <td><?= h((string) ($usageRow['company_name'] ?? '')) ?></td>
                                        <td><?= h((string) ($usageRow['email_resp'] ?? '')) ?></td>
                                        <td><?= h((string) ($usageRow['execution_mode'] ?? '')) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (($promptContext['recent_usage'] ?? []) === []): ?>
                                    <tr>
                                        <td colspan="5" class="table-empty">Nenhum uso recente encontrado para este cache.</td>
                                    </tr>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </article>
            </section>
        <?php endif; ?>
    </article>
<?php endif; ?>
