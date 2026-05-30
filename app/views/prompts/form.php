<?php
declare(strict_types=1);

$editData = is_array($editData ?? null) ? $editData : [];
$isEditing = !empty($editData['id']);
$formFields = is_array($formFields ?? null) ? $formFields : [];
$papers = is_array($papers ?? null) ? $papers : [];
$sqlOptions = is_array($sqlOptions ?? null) ? $sqlOptions : [];
$metadata = is_array($metadata ?? null) ? $metadata : [];
$preview = is_array($preview ?? null) ? $preview : [];
$context = trim((string) ($context ?? ''));
$screenMode = trim((string) ($screenMode ?? ''));
$screenBase = $screenMode === 'agentes' ? 'agentes' : 'prompts';
$companyContext = trim((string) ($companyContext ?? ''));
$versionContext = (int) ($versionContext ?? 0);

function prompts_selected(string $value, string $current): string
{
    return trim($value) === trim($current) ? 'selected' : '';
}

$contextLabel = $context === 'analitica'
    ? 'IA Analítica'
    : ($context === 'estrategica' ? 'IA Estratégica' : 'Diagnóstico Adm.');
$contextHref = $context === 'analitica'
    ? url('analitica/index.php')
    : ($context === 'estrategica' ? url('estrategica/index.php') : url($screenBase . '/index.php'));
$contextTail = [];
if ($context !== '') {
    $contextTail[] = 'context=' . rawurlencode($context);
}
if ($companyContext !== '') {
    $contextTail[] = 'company=' . rawurlencode($companyContext);
}
if ($versionContext > 0) {
    $contextTail[] = 'version=' . $versionContext;
}
$contextQuery = $contextTail === [] ? '' : '?' . implode('&', $contextTail);
$markerValues = is_array($preview['marker_values'] ?? null) ? $preview['marker_values'] : [];
$missingMarkers = is_array($preview['unresolved_markers'] ?? null) ? $preview['unresolved_markers'] : [];
$attachments = is_array($preview['attachments'] ?? null) ? $preview['attachments'] : [];
$markerNames = is_array($editData['marker_names'] ?? null) ? $editData['marker_names'] : [];
$sqlBlockText = trim((string) ($editData['sql_block_text'] ?? ''));
$promptBaseText = (string) ($editData['prompt_base_text'] ?? $editData['prompt'] ?? '');
$hasSql = $sqlBlockText !== '';
$sqlStatusLabel = $hasSql ? ((string) (($editData['sql_desc'] ?? '') !== '' ? $editData['sql_desc'] : 'SQL anexado')) : 'Sem SQL';
$promptOriginalText = $promptBaseText;
if ($sqlBlockText !== '') {
    $promptOriginalText = rtrim($promptBaseText) . "\n\nEXECUTAR SQL=\n" . $sqlBlockText;
}
$promptOriginalText = trim((string) $promptOriginalText);
$promptReadyBaseText = trim((string) ($preview['resolved_prompt'] ?? 'Sem base para montar o prompt pronto.'));
$promptReadyText = $promptReadyBaseText;
if ($sqlBlockText !== '') {
    $promptReadyText .= "\n\nEXECUTAR SQL=\n" . $sqlBlockText;
}
?>
<style>
.prompt-edit-lite{display:grid;gap:18px}
.prompt-edit-lite__header p{margin:6px 0 0}
.prompt-edit-lite__meta{display:grid;grid-template-columns:190px 190px minmax(0,1fr);gap:14px}
.prompt-edit-lite__tools{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr) 280px;gap:14px}
.prompt-edit-lite__main{display:grid;grid-template-columns:minmax(0,1.85fr) 300px;gap:16px;align-items:start}
.prompt-edit-lite__field,.prompt-edit-lite__stack{display:grid;gap:6px;min-width:0}
.prompt-edit-lite__field span,.prompt-edit-lite__stack span{font-size:.92rem;color:#4d6079;font-weight:700}
.prompt-edit-lite__field input,.prompt-edit-lite__field select,.prompt-edit-lite__stack textarea{
    width:100%;min-width:0;border:1px solid #cfdae8;border-radius:12px;background:#fff;padding:11px 13px;color:#13243d;font:inherit
}
.prompt-edit-lite__card{border:1px solid #e5ebf3;border-radius:18px;background:#fff;padding:14px;box-shadow:0 4px 12px rgba(15,23,42,.03);min-width:0}
.prompt-edit-lite__card h3{margin:0 0 10px;font-size:1.08rem;color:#14233b}
.prompt-edit-lite__actions{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
.prompt-edit-lite__chips{display:flex;gap:8px;flex-wrap:wrap;margin:0 0 12px}
.prompt-edit-lite__chip{display:inline-flex;align-items:center;border-radius:999px;padding:6px 10px;font-size:.82rem;font-weight:700;background:#eef3fb;color:#28415f}
.prompt-edit-lite__chip--sql{background:#fff2da;color:#9a6200}
.prompt-edit-lite__chip--missing{background:#fff1ef;color:#b42318}
.prompt-edit-lite__chip--marker{background:#edf5ff;color:#0b4f93}
.prompt-edit-lite__summary{display:grid;gap:8px;color:#53657d;font-size:.92rem}
.prompt-edit-lite__prompt{min-height:520px;max-height:520px;overflow:auto;resize:vertical;line-height:1.45;white-space:pre-wrap;word-break:break-word}
.prompt-edit-lite__sql{min-height:220px;max-height:220px;overflow:auto;resize:none;line-height:1.45;white-space:pre-wrap;word-break:break-word}
.prompt-edit-lite__pickers{display:grid;gap:14px}

.prompt-edit-lite__sql-card{display:grid;gap:12px}
.prompt-edit-lite__sql-result{border:1px solid #e5ebf3;border-radius:14px;background:#fbfcfe;padding:12px}
.prompt-edit-lite__sql-result-header{display:flex;justify-content:space-between;align-items:center;gap:10px;margin-bottom:8px}
.prompt-edit-lite__sql-result-body{max-height:280px;overflow:auto;border:1px solid #d7e0ec;border-radius:12px;background:#fff;padding:10px}
.prompt-edit-lite__sql-result-body pre{margin:0;white-space:pre-wrap;word-break:break-word;font:12px/1.45 Consolas,monospace;color:#17324d}
.prompt-edit-lite__sql-result-body table{width:100%;border-collapse:collapse;font-size:.86rem}
.prompt-edit-lite__sql-result-body th,.prompt-edit-lite__sql-result-body td{border:1px solid #dce4ef;padding:6px 8px;text-align:left;vertical-align:top}
.prompt-edit-lite__sql-result-body th{background:#f1f5fb;color:#20354f;position:sticky;top:0}
.prompt-edit-lite__picker-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:8px}
@media (max-width:1260px){
    .prompt-edit-lite__meta,.prompt-edit-lite__tools,.prompt-edit-lite__main{grid-template-columns:1fr}
    .prompt-edit-lite__prompt{min-height:360px;max-height:360px}
}

.prompt-edit-lite__prompt-highlight{min-height:180px;max-height:260px;overflow:auto;border:1px solid #d7e0ec;border-radius:14px;background:#f8fafc;padding:12px 14px;white-space:pre-wrap;word-break:break-word;line-height:1.5}
.prompt-edit-lite__legend{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px}
.prompt-edit-lite__chip--legend-marker{background:rgba(11,99,206,.10);color:#0b63ce}
.prompt-edit-lite__chip--legend-sql{background:rgba(15,122,83,.12);color:#0f7a53}
.prompt-edit-lite__chip--legend-biblio{background:rgba(163,95,0,.10);color:#a35f00}
.prompt-syntax{font-weight:600}
.prompt-syntax--marker{color:#0b63ce;background:rgba(11,99,206,.10);border-radius:6px;padding:0 2px}
.prompt-syntax--sql-trigger{color:#0f7a53;background:rgba(15,122,83,.12);border-radius:6px;padding:0 2px;font-weight:700}
.prompt-syntax--sql{color:#0f7a53;background:rgba(15,122,83,.06)}
.prompt-syntax--biblio{color:#a35f00;background:rgba(163,95,0,.10);border-radius:6px;padding:0 2px;font-weight:700}

/* PATCH_MARCADOR_PERGUNTA_V3: texto curto de referência da pergunta selecionada */
.prompt-edit-lite__field-reference{
    display:block;
    height:16px;
    line-height:16px;
    max-height:16px;
    overflow:hidden;
    text-overflow:ellipsis;
    white-space:nowrap;
    font-size:.72rem;
    font-weight:500;
    color:#64748b;
    margin-top:-2px;
}

</style>



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

<article class="module-card prompt-edit-lite-card">
    <form id="prompt-editor-form" class="prompt-edit-lite" method="post" action="<?= h(url($screenBase . '/form.php')) ?>">
        <?= csrf_input() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= h((string) ($editData['id'] ?? '')) ?>">
        <input type="hidden" name="context" value="<?= h($context) ?>">
        <input type="hidden" name="return_to" value="form">
        <input type="hidden" name="company_context" value="<?= h($companyContext) ?>">
        <input type="hidden" name="version_context" value="<?= h((string) $versionContext) ?>">
        <input type="hidden" name="assistente" value="<?= h((string) ($editData['assistente'] ?? '')) ?>">
        <input type="hidden" name="funcao" value="<?= h((string) ($editData['funcao'] ?? '')) ?>">
        <input type="hidden" name="descricao" value="<?= h((string) ($editData['descricao'] ?? '')) ?>">
        <input type="hidden" name="sql_desc" value="<?= h((string) ($editData['sql_desc'] ?? '')) ?>">
        <input type="hidden" name="sql_text" value="">
        <input type="hidden" name="sql_preview" value="">


        <div class="prompt-edit-lite__tools">
            <section class="prompt-edit-lite__card">
                <h3>Perguntas do questionário</h3>
                <label class="prompt-edit-lite__field">
                    <span>Inserir marcador</span>
                    <select id="prompt-field-picker" onchange="(function(s){var r=document.getElementById('prompt-field-reference');var o=s.options[s.selectedIndex]||null;var t='';if(o&&o.value){t=(o.getAttribute('data-question-label')||'').trim();if(!t){t=(o.textContent||'').replace(/^\s*\[[^\]]*\]\s*/,'').trim();}}if(r){r.textContent=t||'\u00a0';r.setAttribute('title',t);}})(this)">
                        <option value="">Selecione uma pergunta</option>
                        <?php foreach ($formFields as $field): ?>
                            <?php
                            $fieldName = (string) ($field['name'] ?? '');
                            $fieldLabel = trim((string) ($field['label'] ?? ''));
                            ?>
                            <option value="<?= h($fieldName) ?>" data-question-label="<?= h($fieldLabel) ?>">
                                [<?= h((string) ($field['section_code'] ?? '')) ?>] <?= h($fieldName) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small id="prompt-field-reference" class="prompt-edit-lite__field-reference js-field-reference" title="">&nbsp;</small>
                </label>
                <div class="prompt-edit-lite__actions">
                    <button type="button" class="action-pill action-pill--outline js-insert-field">Inserir pergunta</button>
                </div>
            </section>

            <section class="prompt-edit-lite__card">
                <h3>Bibliografia</h3>
                <label class="prompt-edit-lite__field">
                    <span>Inserir paper</span>
                    <select id="prompt-paper-picker">
                        <option value="">Selecione um paper</option>
                        <?php foreach ($papers as $paper): ?>
                            <option value="<?= h((string) ($paper['title'] ?? '')) ?>">
                                [#<?= (int) ($paper['id'] ?? 0) ?>] <?= h((string) ($paper['title'] ?? '')) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <div class="prompt-edit-lite__actions">
                    <button type="button" class="action-pill action-pill--outline js-insert-paper">Inserir paper</button>
                </div>
            </section>

            <aside class="prompt-edit-lite__card prompt-edit-lite__summary-card">
                <div class="prompt-edit-lite__summary">
                    <span><strong>Marcadores detectados:</strong> <span class="js-marker-count"><?= count(array_unique(array_merge($markerNames, $missingMarkers))) ?></span></span>
                </div>
                <div class="prompt-edit-lite__chips js-marker-list">
                    <?php if ($markerNames === [] && $missingMarkers === []): ?>
                        <span class="prompt-edit-lite__chip">Sem marcadores</span>
                    <?php else: ?>
                        <?php foreach (array_unique(array_merge($markerNames, $missingMarkers)) as $markerName): ?>
                            <span class="prompt-edit-lite__chip prompt-edit-lite__chip--marker"><?= h((string) $markerName) ?></span>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </aside>
        </div>

        <div class="prompt-edit-lite__main">
            <section class="prompt-edit-lite__card">
                <div class="prompt-edit-lite__chips">
                    <button type="button" class="prompt-edit-lite__chip prompt-edit-lite__chip--tab js-prompt-view-tab is-active" data-target="original">Original</button>
                    <button type="button" class="prompt-edit-lite__chip prompt-edit-lite__chip--tab js-prompt-view-tab" data-target="ready">Prompt pronto</button>
                    <button type="button" class="prompt-edit-lite__chip prompt-edit-lite__chip--tab js-prompt-view-tab" data-target="sql-result">Resultado SQL</button>
                </div>
                <div class="prompt-edit-lite__legend">
                    <span class="prompt-edit-lite__chip prompt-edit-lite__chip--legend-marker">Marcadores &lt;&lt;...&gt;&gt;</span>
                    <span class="prompt-edit-lite__chip prompt-edit-lite__chip--legend-sql">Chamadas SQL</span>
                    <span class="prompt-edit-lite__chip prompt-edit-lite__chip--legend-biblio">Bibliografia</span>
                </div>

                <div class="prompt-edit-lite__viewer-panel js-prompt-view-panel is-active" data-panel="original">
                    <label class="prompt-edit-lite__stack">
                        <span>Prompt original</span>
                        <textarea id="prompt-text" name="prompt" class="prompt-edit-lite__prompt" rows="18" required><?= h($promptOriginalText) ?></textarea>
                    </label>
                    <label class="prompt-edit-lite__stack">
                        <span>Visualização com realce</span>
                        <div id="prompt-text-highlight" class="prompt-edit-lite__prompt-highlight js-prompt-highlight-live" data-source-id="prompt-text"><?= h($promptOriginalText) ?></div>
                    </label>
                </div>

                <div class="prompt-edit-lite__viewer-panel js-prompt-view-panel" data-panel="ready">
                    <label class="prompt-edit-lite__stack">
                        <span>Prompt pronto</span>
                        <div id="prompt-ready-view" class="prompt-edit-lite__prompt-highlight js-prompt-highlight" data-base-text="<?= h($promptReadyBaseText) ?>" data-raw-text="<?= h($promptReadyText) ?>"><?= h($promptReadyText) ?></div>
                    </label>
                </div>

                <div class="prompt-edit-lite__viewer-panel js-prompt-view-panel" data-panel="sql-result">
                    <label class="prompt-edit-lite__stack">
                        <span class="js-sql-result-title">Resultado SQL</span>
                        <div id="prompt-sql-result" class="prompt-edit-lite__sql-result-body js-sql-result-body"></div>
                    </label>
                </div>
            </section>

            <aside class="prompt-edit-lite__card prompt-edit-lite__sql-card">
                <div class="prompt-edit-lite__sql-header">
                    <div>
                        <h3>SQL operacional</h3>
                        <span class="muted">Consulta disponível</span>
                    </div>
                    <span class="prompt-edit-lite__chip prompt-edit-lite__chip--sql js-sql-status-text"><?= h($sqlStatusLabel) ?></span>
                </div>

                <label class="prompt-edit-lite__field">
                    <select id="prompt-sql-picker">
                        <option value="">Nenhum SQL</option>
                        <?php foreach ($sqlOptions as $sqlOption): ?>
                            <?php
                            $desc = (string) ($sqlOption['select_desc'] ?? '');
                            $sqlText = (string) ($sqlOption['sql_text'] ?? '');
                            $selectedDesc = trim((string) ($editData['sql_desc'] ?? ''));
                            ?>
                            <option value="<?= h($desc) ?>" data-sql="<?= h($sqlText) ?>" <?= prompts_selected($desc, $selectedDesc) ?>>
                                <?= h($desc) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <div class="prompt-edit-lite__picker-actions">
                    <button type="button" class="action-pill action-pill--ghost js-attach-sql">Anexar</button>
                    <button type="button" class="action-pill action-pill--ghost js-detach-sql">Remover</button>
                </div>

                <label class="prompt-edit-lite__stack">
                    <span>Bloco SQL</span>
                    <textarea id="prompt-sql-preview" class="prompt-edit-lite__sql" rows="8"><?= h($sqlBlockText) ?></textarea>
                </label>

                <div class="prompt-edit-lite__picker-actions">
                    <button type="button" class="action-pill action-pill--outline js-exec-sql">Exec. SQL</button>
                </div>
            </aside>
        </div>

        <div class="prompt-edit-lite__footer">
            <button type="submit" class="action-pill action-pill--green">Salvar</button>
            <a data-shell-nav="true" class="action-pill action-pill--ghost" href="<?= h(url($screenBase . '/index.php') . $contextQuery) ?>">Retornar</a>
        </div>
    </form>
</article>

<script src="<?= h(asset('assets/js/prompts.js')) ?>"></script>
<script>
/* PATCH_MARCADOR_PERGUNTA_V3: script local para não depender de cache do JS externo */
(function () {
    function bindPromptFieldReference(scope) {
        var root = scope || document;
        var picker = root.querySelector ? root.querySelector('#prompt-field-picker') : null;
        var reference = root.querySelector ? root.querySelector('#prompt-field-reference') : null;

        if (!picker || !reference || picker.__fieldReferenceBound) {
            return;
        }

        function updateReference() {
            var option = picker.options[picker.selectedIndex] || null;
            var text = '';

            if (option && option.value) {
                text = (option.getAttribute('data-question-label') || '').trim();
                if (!text) {
                    text = (option.textContent || '').replace(/^\s*\[[^\]]*\]\s*/, '').trim();
                }
            }

            reference.textContent = text || '\u00a0';
            reference.setAttribute('title', text);
        }

        picker.addEventListener('change', updateReference);
        picker.__fieldReferenceBound = true;
        updateReference();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { bindPromptFieldReference(document); }, { once: true });
    } else {
        bindPromptFieldReference(document);
    }

    window.bindPromptFieldReference = bindPromptFieldReference;
})();
</script>
