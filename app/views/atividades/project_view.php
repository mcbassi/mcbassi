<?php
declare(strict_types=1);

/** @var array<int, array<string,mixed>> $tree */
/** @var array<int, array<string,mixed>> $templatesTree */
/** @var array<int, array<string,mixed>> $activities */
/** @var array<string,mixed> $stats */

$tree = $tree ?? [];
$templatesTree = $templatesTree ?? [];
$activities = $activities ?? [];
$selectedProject = (string) ($selectedProject ?? '');
$selectedSubproject = (string) ($selectedSubproject ?? '');
$supportsDataInicio = (bool) ($supportsDataInicio ?? false);
$supportsDependencyTable = (bool) ($supportsDependencyTable ?? false);
$flashSuccess = $flashSuccess ?? null;
$flashError = $flashError ?? null;

function activity_status_options(): array
{
    return ['Planejado', 'Em andamento', 'Bloqueado', 'Em validação', 'Concluído', 'Cancelado'];
}

function activity_status_key(string $status): string
{
    $normalized = strtolower(strtr(trim($status), [
        'Á' => 'a', 'Ã' => 'a', 'Â' => 'a', 'É' => 'e', 'Ç' => 'c', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u',
        'á' => 'a', 'ã' => 'a', 'â' => 'a', 'é' => 'e', 'ç' => 'c', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
    ]));

    return match ($normalized) {
        'planejado', 'planned' => 'planned',
        'em andamento', 'in progress', 'en progreso' => 'in_progress',
        'bloqueado', 'blocked', 'bloqueada' => 'blocked',
        'em validacao', 'in validation', 'en validacion' => 'validation',
        'concluido', 'done', 'finalizado', 'completed' => 'done',
        'cancelado', 'cancelled', 'canceled' => 'canceled',
        default => 'planned',
    };
}

function activity_status_label(string $status): string
{
    return t('activity.status.' . activity_status_key($status), [], $status);
}

function activity_project_label(string $project): string
{
    return trim($project) === 'Sem projeto' ? t('activity.no_project') : $project;
}

function activity_subproject_label(string $subproject): string
{
    return trim($subproject) === 'Sem subprojeto' ? t('activity.no_subproject') : $subproject;
}

function activity_date_value(array $row, string $field): string
{
    $value = (string) ($row[$field] ?? '');
    if ($value === '' && $field === 'data_prevista_termino') {
        $value = (string) ($row['data_termino_prevista'] ?? '');
    }
    if ($value === '' && $field === 'data_real_termino') {
        $value = (string) ($row['data_termino_real'] ?? '');
    }
    return $value !== '' ? substr($value, 0, 10) : '';
}

function activity_url_for(?string $project, ?string $subproject): string
{
    $query = [];
    if ($project !== null && $project !== '') {
        $query['projeto'] = $project;
    }
    if ($subproject !== null && $subproject !== '') {
        $query['subprojeto'] = $subproject;
    }
    $qs = $query !== [] ? '?' . http_build_query($query) : '';
    return url('atividades/index.php' . $qs);
}

$csrf = csrf_token();
?>
<style>
.activity-page{display:flex;flex-direction:column;gap:1rem}.activity-hero{background:linear-gradient(135deg,#0f172a,#1d4ed8);border-radius:26px;padding:1.3rem;color:#fff;box-shadow:0 24px 50px rgba(15,23,42,.16);display:grid;grid-template-columns:1fr auto;gap:1rem;align-items:center}.activity-hero h2{margin:0;font-size:1.35rem}.activity-hero p{margin:.25rem 0 0;color:#dbeafe}.activity-actions{display:flex;gap:.6rem;flex-wrap:wrap}.activity-btn{border:0;border-radius:999px;padding:.55rem .9rem;font-weight:800;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:.35rem}.activity-btn--light{background:#fff;color:#1d4ed8}.activity-btn--primary{background:#2563eb;color:#fff}.activity-btn--ghost{background:#eef2ff;color:#3730a3}.activity-btn--danger{background:#fee2e2;color:#991b1b}.activity-btn--mini{padding:.35rem .6rem;font-size:.78rem}.activity-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.75rem}.activity-kpi{background:#fff;border:1px solid #e2e8f0;border-radius:20px;padding:.85rem;box-shadow:0 12px 30px rgba(15,23,42,.06)}.activity-kpi span{font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;color:#64748b;font-weight:800}.activity-kpi strong{display:block;font-size:1.35rem;color:#0f172a;margin-top:.25rem}.activity-layout{display:grid;grid-template-columns:310px minmax(0,1fr);gap:1rem;align-items:start}.activity-tree,.activity-grid-card{background:#fff;border:1px solid #e2e8f0;border-radius:24px;box-shadow:0 14px 35px rgba(15,23,42,.06);overflow:hidden}.activity-tree__head,.activity-grid-card__head{padding:1rem;border-bottom:1px solid #e2e8f0;background:#f8fafc}.activity-tree__head h3,.activity-grid-card__head h3{margin:0;color:#0f172a}.activity-tree__head small,.activity-grid-card__head small{color:#64748b}.activity-tree__body{max-height:68vh;overflow:auto;padding:.65rem}.activity-project{margin-bottom:.65rem}.activity-project__title{font-weight:900;color:#0f172a;padding:.6rem .65rem;border-radius:14px;background:#f1f5f9;display:flex;justify-content:space-between}.activity-subproject{display:flex;justify-content:space-between;align-items:center;padding:.55rem .65rem .55rem 1rem;border-radius:14px;text-decoration:none;color:#334155;margin:.25rem 0;border:1px solid transparent}.activity-subproject:hover{background:#eff6ff;color:#1d4ed8}.activity-subproject.is-active{background:#dbeafe;border-color:#93c5fd;color:#1d4ed8;font-weight:900}.activity-subproject span:last-child{font-size:.75rem;background:#e2e8f0;color:#334155;border-radius:999px;padding:.15rem .45rem}.activity-grid-toolbar{display:flex;gap:.5rem;flex-wrap:wrap;align-items:center}.activity-grid-wrap{overflow:auto;max-height:68vh}.activity-table{width:100%;border-collapse:separate;border-spacing:0;min-width:1780px;table-layout:fixed}.activity-table .col-id{width:72px}.activity-table .col-start{width:118px}.activity-table .col-activity{width:360px}.activity-table .col-subactivity{width:360px}.activity-table .col-status{width:96px}.activity-table .col-deps{width:220px}.activity-table .col-resp{width:170px}.activity-table .col-effort{width:96px}.activity-table .col-date{width:130px}.activity-table .col-text{width:240px}.activity-table .col-evidence{width:118px}.activity-table .col-actions{width:118px}.activity-table th{position:sticky;top:0;background:#f8fafc;z-index:2;text-align:left;color:#475569;font-size:.74rem;text-transform:uppercase;letter-spacing:.04em;border-bottom:1px solid #e2e8f0;padding:.65rem}.activity-table td{border-bottom:1px solid #e2e8f0;padding:.55rem;vertical-align:top}.activity-table tr:hover td{background:#f8fafc}.activity-input,.activity-select,.activity-textarea{width:100%;border:1px solid #cbd5e1;border-radius:12px;padding:.5rem;background:#fff;font-size:.86rem;color:#0f172a}.activity-textarea{min-height:54px;resize:vertical}.activity-cell-status{padding:.45rem!important}.activity-cell-status .activity-select{padding:.42rem .35rem;font-size:.78rem;border-radius:10px}.activity-readonly{font-size:.82rem;color:#64748b}.activity-pill{display:inline-flex;align-items:center;border-radius:999px;padding:.2rem .5rem;background:#eef2ff;color:#3730a3;font-size:.72rem;font-weight:800;margin:.1rem}.activity-alert{padding:.8rem 1rem;border-radius:18px;border:1px solid}.activity-alert--success{background:#ecfdf5;border-color:#86efac;color:#166534}.activity-alert--error{background:#fef2f2;border-color:#fecaca;color:#991b1b}.activity-empty{padding:2rem;text-align:center;color:#64748b}.activity-row-saving{opacity:.6}.activity-save-state{font-size:.72rem;color:#64748b;margin-top:.35rem}.activity-deps{max-width:260px}.activity-deps small{display:block;color:#64748b}.activity-evidence-badge{font-size:.75rem;font-weight:900;color:#0f766e;background:#ccfbf1;border-radius:999px;padding:.2rem .5rem;white-space:nowrap}@media(max-width:1100px){.activity-layout{grid-template-columns:1fr}.activity-kpis{grid-template-columns:repeat(2,minmax(0,1fr))}.activity-hero{grid-template-columns:1fr}}
</style>

<section class="activity-page" data-activities-page>
    <div class="activity-hero">
        <div>
            <h2><?= h(t('activity.title')) ?></h2>
            <p><?= h(t('activity.subtitle')) ?></p>
        </div>
        <div class="activity-actions">
            <a class="activity-btn activity-btn--light" data-shell-nav="true" href="<?= h(url('atividades/edit.php?projeto=' . rawurlencode($selectedProject) . '&subprojeto=' . rawurlencode($selectedSubproject))) ?>">＋ <?= h(t('activity.new_activity')) ?></a>
            <button class="activity-btn activity-btn--ghost" type="button" data-import-templates><?= h(t('activity.import_templates')) ?></button>
        </div>
    </div>

    <?php if ($flashSuccess): ?><div class="activity-alert activity-alert--success"><?= h((string) $flashSuccess) ?></div><?php endif; ?>
    <?php if ($flashError): ?><div class="activity-alert activity-alert--error"><?= h((string) $flashError) ?></div><?php endif; ?>

    <div class="activity-kpis">
        <div class="activity-kpi"><span><?= h(t('activity.kpi_total')) ?></span><strong><?= h((string) ($stats['total'] ?? 0)) ?></strong></div>
        <div class="activity-kpi"><span><?= h(t('activity.kpi_projects')) ?></span><strong><?= h((string) ($stats['projects'] ?? 0)) ?></strong></div>
        <div class="activity-kpi"><span><?= h(t('activity.kpi_done')) ?></span><strong><?= h((string) ($stats['done'] ?? 0)) ?></strong></div>
        <div class="activity-kpi"><span><?= h(t('activity.kpi_late')) ?></span><strong><?= h((string) ($stats['late'] ?? 0)) ?></strong></div>
    </div>

    <div class="activity-layout">
        <aside class="activity-tree">
            <div class="activity-tree__head">
                <h3><?= h(t('activity.tree_title')) ?></h3>
                <small><?= h(t('activity.tree_subtitle')) ?></small>
            </div>
            <div class="activity-tree__body">
                <?php
                $grouped = [];
                foreach ($tree as $node) {
                    $p = (string) ($node['projeto'] ?? 'Sem projeto');
                    $grouped[$p][] = $node;
                }
                ?>
                <?php if ($grouped === []): ?>
                    <div class="activity-empty"><?= h(t('activity.empty_no_activities')) ?></div>
                <?php endif; ?>
                <?php foreach ($grouped as $project => $subs): ?>
                    <div class="activity-project">
                        <div class="activity-project__title"><span>📁 <?= h(activity_project_label($project)) ?></span><span><?= count($subs) ?></span></div>
                        <?php foreach ($subs as $node): ?>
                            <?php
                            $sub = (string) ($node['subprojeto'] ?? 'Sem subprojeto');
                            $isActive = $project === $selectedProject && $sub === $selectedSubproject;
                            ?>
                            <a class="activity-subproject <?= $isActive ? 'is-active' : '' ?>" data-shell-nav="true" href="<?= h(activity_url_for($project, $sub)) ?>">
                                <span>└ <?= h(activity_subproject_label($sub)) ?></span>
                                <span><?= h((string) ($node['total'] ?? 0)) ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>

                <?php if ($templatesTree !== []): ?>
                    <hr style="border:0;border-top:1px solid #e2e8f0;margin:1rem 0">
                    <div class="activity-readonly"><strong><?= h(t('activity.templates_available')) ?></strong></div>
                    <?php foreach ($templatesTree as $tpl): ?>
                        <div class="activity-readonly" style="padding:.35rem .55rem">📌 <?= h(activity_project_label((string) $tpl['projeto'])) ?> / <?= h(activity_subproject_label((string) $tpl['subprojeto'])) ?> · <?= h((string) $tpl['total']) ?></div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </aside>

        <section class="activity-grid-card">
            <div class="activity-grid-card__head">
                <div style="display:flex;justify-content:space-between;gap:1rem;align-items:flex-start;flex-wrap:wrap">
                    <div>
                        <h3><?= h($selectedProject !== '' ? activity_project_label($selectedProject) : t('activity.all_activities')) ?></h3>
                        <small><?= h($selectedSubproject !== '' ? activity_subproject_label($selectedSubproject) : t('activity.all_subprojects')) ?> · <?= h(t('activity.records_count', ['count' => count($activities)])) ?></small>
                        <?php if (!$supportsDependencyTable): ?>
                            <br><small><?= h(t('activity.dependency_migration_hint')) ?></small>
                        <?php endif; ?>
                    </div>
                    <div class="activity-grid-toolbar">
                        <a class="activity-btn activity-btn--ghost activity-btn--mini" data-shell-nav="true" href="<?= h(url('atividades/index.php')) ?>"><?= h(t('activity.view_all')) ?></a>
                        <a class="activity-btn activity-btn--primary activity-btn--mini" data-shell-nav="true" href="<?= h(url('atividades/edit.php?projeto=' . rawurlencode($selectedProject) . '&subprojeto=' . rawurlencode($selectedSubproject))) ?>"><?= h(t('activity.new_activity')) ?></a>
                    </div>
                </div>
            </div>

            <div class="activity-grid-wrap">
                <?php if ($activities === []): ?>
                    <div class="activity-empty"><?= h(t('activity.empty_filter')) ?></div>
                <?php else: ?>
                    <table class="activity-table">
                        <colgroup>
                            <col class="col-id">
                            <?php if ($supportsDataInicio): ?><col class="col-start"><?php endif; ?>
                            <col class="col-activity">
                            <col class="col-subactivity">
                            <col class="col-status">
                            <col class="col-deps">
                            <col class="col-resp">
                            <col class="col-resp">
                            <col class="col-effort">
                            <col class="col-date">
                            <col class="col-date">
                            <col class="col-text">
                            <col class="col-text">
                            <col class="col-evidence">
                            <col class="col-actions">
                        </colgroup>
                        <thead>
                            <tr>
                                <th><?= h(t('activity.col_id')) ?></th>
                                <?php if ($supportsDataInicio): ?><th><?= h(t('activity.col_start')) ?></th><?php endif; ?>
                                <th><?= h(t('activity.col_activity')) ?></th>
                                <th><?= h(t('activity.col_subactivity')) ?></th>
                                <th><?= h(t('activity.col_status')) ?></th>
                                <th><?= h(t('activity.col_prerequisites')) ?></th>
                                <th><?= h(t('activity.col_execution_owner')) ?></th>
                                <th><?= h(t('activity.col_management_owner')) ?></th>
                                <th><?= h(t('activity.col_effort')) ?></th>
                                <th><?= h(t('activity.col_due_date')) ?></th>
                                <th><?= h(t('activity.col_real_date')) ?></th>
                                <th><?= h(t('activity.col_result')) ?></th>
                                <th><?= h(t('activity.col_difficulties')) ?></th>
                                <th><?= h(t('activity.col_evidence')) ?></th>
                                <th><?= h(t('activity.col_actions')) ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($activities as $row): ?>
                                <?php $id = (int) ($row['id'] ?? 0); ?>
                                <tr data-activity-row data-id="<?= h((string) $id) ?>">
                                    <td><strong>#<?= h((string) $id) ?></strong><input type="hidden" name="id" value="<?= h((string) $id) ?>"><input type="hidden" name="_csrf" value="<?= h($csrf) ?>"></td>
                                    <?php if ($supportsDataInicio): ?>
                                        <td><input class="activity-input" type="date" name="data_inicio" value="<?= h(activity_date_value($row, 'data_inicio')) ?>"></td>
                                    <?php endif; ?>
                                    <td><textarea class="activity-textarea" name="atividade"><?= h((string) ($row['atividade'] ?? '')) ?></textarea></td>
                                    <td><textarea class="activity-textarea" name="sub_atividade"><?= h((string) ($row['sub_atividade'] ?? '')) ?></textarea></td>
                                    <td class="activity-cell-status"><select class="activity-select" name="status_atual"><?php foreach (activity_status_options() as $status): ?><option value="<?= h($status) ?>" <?= (string) ($row['status_atual'] ?? '') === $status ? 'selected' : '' ?>><?= h(activity_status_label($status)) ?></option><?php endforeach; ?></select></td>
                                    <td class="activity-deps">
                                        <?php foreach (($row['_dependency_labels'] ?? []) as $label): ?><span class="activity-pill"><?= h((string) $label) ?></span><?php endforeach; ?>
                                        <?php if (($row['_dependency_labels'] ?? []) === [] && trim((string) ($row['dependencia'] ?? '')) !== ''): ?><small><?= h((string) $row['dependencia']) ?></small><?php endif; ?>
                                        <small><?= h(t('activity.edit_prereq_hint')) ?></small>
                                    </td>
                                    <td><input class="activity-input" name="responsavel_execucao" value="<?= h((string) ($row['responsavel_execucao'] ?? '')) ?>"></td>
                                    <td><input class="activity-input" name="responsavel_gestao" value="<?= h((string) ($row['responsavel_gestao'] ?? '')) ?>"></td>
                                    <td><input class="activity-input" type="number" min="0" name="esforco_previsto_dias" value="<?= h((string) ($row['esforco_previsto_dias'] ?? $row['tempo_previsto'] ?? '')) ?>"></td>
                                    <td><input class="activity-input" type="date" name="data_prevista_termino" value="<?= h(activity_date_value($row, 'data_prevista_termino')) ?>"></td>
                                    <td><input class="activity-input" type="date" name="data_real_termino" value="<?= h(activity_date_value($row, 'data_real_termino')) ?>"></td>
                                    <td><textarea class="activity-textarea" name="descricao_resultados"><?= h((string) ($row['descricao_resultados'] ?? '')) ?></textarea></td>
                                    <td><textarea class="activity-textarea" name="dificuldades"><?= h((string) ($row['dificuldades'] ?? '')) ?></textarea></td>
                                    <td><span class="activity-evidence-badge"><?= h((string) ($row['_evidence_count'] ?? 0)) ?> <?= h(t('activity.files_suffix')) ?></span></td>
                                    <td>
                                        <div style="display:flex;flex-direction:column;gap:.35rem">
                                            <button class="activity-btn activity-btn--primary activity-btn--mini" type="button" data-save-row><?= h(t('common.save')) ?></button>
                                            <a class="activity-btn activity-btn--ghost activity-btn--mini" data-shell-nav="true" href="<?= h(url('atividades/edit.php?id=' . $id)) ?>"><?= h(t('common.edit')) ?></a>
                                            <button class="activity-btn activity-btn--danger activity-btn--mini" type="button" data-delete-row><?= h(t('common.delete')) ?></button>
                                            <span class="activity-save-state" data-save-state></span>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </section>
    </div>
</section>

<script>
(function(){
    const root = document.querySelector('[data-activities-page]');
    if (!root) return;
    const apiUrl = <?= json_encode(url('atividades/api.php'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    const csrf = <?= json_encode($csrf, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    const selectedProject = <?= json_encode($selectedProject, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    const selectedSubproject = <?= json_encode($selectedSubproject, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    const messages = <?= json_encode([
        'invalid_response' => t('activity.js_invalid_response'),
        'generic_error' => t('common.error'),
        'saving' => t('activity.js_saving'),
        'saved' => t('activity.js_saved'),
        'confirm_delete' => t('activity.confirm_delete'),
        'confirm_import' => t('activity.confirm_import'),
        'import_done' => t('activity.import_done'),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;

    function rowPayload(row, action){
        const data = new FormData();
        data.append('_csrf', csrf);
        data.append('action', action);
        row.querySelectorAll('input[name], textarea[name], select[name]').forEach(el => {
            data.append(el.name, el.value || '');
        });
        return data;
    }

    async function post(data){
        const res = await fetch(apiUrl, {method:'POST', body:data, credentials:'same-origin'});
        const json = await res.json().catch(() => ({ok:false,message:messages.invalid_response}));
        if (!json.ok) throw new Error(json.message || messages.generic_error);
        return json;
    }

    root.addEventListener('click', async function(ev){
        const saveBtn = ev.target.closest('[data-save-row]');
        const delBtn = ev.target.closest('[data-delete-row]');
        const importBtn = ev.target.closest('[data-import-templates]');

        if (saveBtn) {
            const row = saveBtn.closest('[data-activity-row]');
            const state = row.querySelector('[data-save-state]');
            row.classList.add('activity-row-saving');
            state.textContent = messages.saving;
            try {
                await post(rowPayload(row, 'save_row'));
                state.textContent = messages.saved;
            } catch (e) {
                state.textContent = e.message;
            } finally {
                row.classList.remove('activity-row-saving');
            }
        }

        if (delBtn) {
            const row = delBtn.closest('[data-activity-row]');
            if (!confirm(messages.confirm_delete)) return;
            const data = new FormData();
            data.append('_csrf', csrf);
            data.append('action', 'delete');
            data.append('id', row.dataset.id || '0');
            try {
                await post(data);
                row.remove();
            } catch (e) {
                alert(e.message);
            }
        }

        if (importBtn) {
            if (!confirm(messages.confirm_import)) return;
            const data = new FormData();
            data.append('_csrf', csrf);
            data.append('action', 'import_templates');
            data.append('projeto', selectedProject);
            data.append('subprojeto', selectedSubproject);
            try {
                const json = await post(data);
                alert(json.message || messages.import_done);
                window.location.reload();
            } catch (e) {
                alert(e.message);
            }
        }
    });
})();
</script>
