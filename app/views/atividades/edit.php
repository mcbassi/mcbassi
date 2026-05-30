<?php
declare(strict_types=1);

/** @var array<string,mixed> $row */
/** @var array<int,array<string,mixed>> $dependencyCandidates */
/** @var array<int,int> $dependencyIds */
/** @var array<int,array<string,mixed>> $evidences */

$row = $row ?? [];
$dependencyCandidates = $dependencyCandidates ?? [];
$dependencyIds = $dependencyIds ?? [];
$evidences = $evidences ?? [];
$supportsDataInicio = (bool) ($supportsDataInicio ?? false);
$supportsDependencyTable = (bool) ($supportsDependencyTable ?? false);
$id = (int) ($row['id'] ?? 0);
$project = (string) ($row['projeto'] ?? '');
$subproject = (string) ($row['subprojeto'] ?? '');

function activity_edit_date(array $row, string $field): string
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

function activity_back_url(string $project, string $subproject): string
{
    $query = [];
    if ($project !== '') {
        $query['projeto'] = $project;
    }
    if ($subproject !== '') {
        $query['subprojeto'] = $subproject;
    }
    return url('atividades/index.php' . ($query ? '?' . http_build_query($query) : ''));
}

$statusOptions = ['Planejado', 'Em andamento', 'Bloqueado', 'Em validação', 'Concluído', 'Cancelado'];

function activity_edit_status_key(string $status): string
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

function activity_edit_status_label(string $status): string
{
    return t('activity.status.' . activity_edit_status_key($status), [], $status);
}
?>
<style>
.activity-edit{display:flex;flex-direction:column;gap:1rem}.activity-edit-hero{background:linear-gradient(135deg,#111827,#2563eb);border-radius:26px;padding:1.3rem;color:#fff;display:flex;justify-content:space-between;gap:1rem;align-items:center;box-shadow:0 24px 50px rgba(15,23,42,.16)}.activity-edit-hero h2{margin:0}.activity-edit-hero p{margin:.25rem 0 0;color:#dbeafe}.activity-card{background:#fff;border:1px solid #e2e8f0;border-radius:24px;box-shadow:0 14px 35px rgba(15,23,42,.06);overflow:hidden}.activity-card__head{padding:1rem;background:#f8fafc;border-bottom:1px solid #e2e8f0}.activity-card__body{padding:1rem}.activity-form-grid{display:grid;grid-template-columns:repeat(12,1fr);gap:.85rem}.activity-field{display:flex;flex-direction:column;gap:.25rem}.activity-field label{font-size:.78rem;font-weight:900;color:#475569;text-transform:uppercase;letter-spacing:.04em}.activity-field input,.activity-field select,.activity-field textarea{border:1px solid #cbd5e1;border-radius:14px;padding:.65rem;background:#fff;font-size:.92rem;color:#0f172a}.activity-field textarea{min-height:110px;resize:vertical}.col-2{grid-column:span 2}.col-3{grid-column:span 3}.col-4{grid-column:span 4}.col-6{grid-column:span 6}.col-12{grid-column:span 12}.activity-actions{display:flex;gap:.6rem;flex-wrap:wrap}.activity-btn{border:0;border-radius:999px;padding:.62rem 1rem;font-weight:900;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:.35rem}.activity-btn--primary{background:#2563eb;color:#fff}.activity-btn--ghost{background:#eef2ff;color:#3730a3}.activity-btn--danger{background:#fee2e2;color:#991b1b}.activity-alert{padding:.8rem 1rem;border-radius:18px;border:1px solid}.activity-alert--success{background:#ecfdf5;border-color:#86efac;color:#166534}.activity-alert--error{background:#fef2f2;border-color:#fecaca;color:#991b1b}.activity-evidence-list{display:flex;flex-direction:column;gap:.5rem}.activity-evidence-item{display:flex;justify-content:space-between;gap:1rem;align-items:center;padding:.75rem;border:1px solid #e2e8f0;border-radius:16px;background:#f8fafc}.activity-evidence-item a{font-weight:800;color:#1d4ed8}.activity-dep-select{min-height:180px}.activity-help{font-size:.82rem;color:#64748b}@media(max-width:900px){.activity-form-grid{grid-template-columns:1fr}.col-2,.col-3,.col-4,.col-6,.col-12{grid-column:span 1}.activity-edit-hero{align-items:flex-start;flex-direction:column}}
</style>

<section class="activity-edit">
    <div class="activity-edit-hero">
        <div>
            <h2><?= h($id > 0 ? t('activity.edit_activity_id', ['id' => $id]) : t('activity.new_activity')) ?></h2>
            <p><?= h($project !== '' ? $project : t('activity.new_project')) ?><?= $subproject !== '' ? ' · ' . h($subproject) : '' ?></p>
        </div>
        <div class="activity-actions">
            <a class="activity-btn activity-btn--ghost" data-shell-nav="true" href="<?= h(activity_back_url($project, $subproject)) ?>">← <?= h(t('activity.back_to_grid')) ?></a>
        </div>
    </div>

    <?php if ($msg = flash_get('success')): ?><div class="activity-alert activity-alert--success"><?= h($msg) ?></div><?php endif; ?>
    <?php if ($msg = flash_get('error')): ?><div class="activity-alert activity-alert--error"><?= h($msg) ?></div><?php endif; ?>

    <form class="activity-card" method="post" action="<?= h(url('atividades/save.php')) ?>">
        <?= csrf_input() ?>
        <input type="hidden" name="id" value="<?= h((string) $id) ?>">
        <div class="activity-card__head"><strong><?= h(t('activity.activity_data')) ?></strong></div>
        <div class="activity-card__body">
            <div class="activity-form-grid">
                <div class="activity-field col-4"><label><?= h(t('activity.field_project')) ?></label><input name="projeto" value="<?= h($project) ?>" required></div>
                <div class="activity-field col-4"><label><?= h(t('activity.field_subproject')) ?></label><input name="subprojeto" value="<?= h($subproject) ?>"></div>
                <div class="activity-field col-4"><label><?= h(t('activity.field_status')) ?></label><select name="status_atual"><?php foreach ($statusOptions as $status): ?><option value="<?= h($status) ?>" <?= (string) ($row['status_atual'] ?? 'Planejado') === $status ? 'selected' : '' ?>><?= h(activity_edit_status_label($status)) ?></option><?php endforeach; ?></select></div>

                <?php if ($supportsDataInicio): ?><div class="activity-field col-3"><label><?= h(t('activity.field_start_date')) ?></label><input type="date" name="data_inicio" value="<?= h(activity_edit_date($row, 'data_inicio')) ?>"></div><?php endif; ?>
                <div class="activity-field col-3"><label><?= h(t('activity.field_effort_days')) ?></label><input type="number" min="0" name="esforco_previsto_dias" value="<?= h((string) ($row['esforco_previsto_dias'] ?? $row['tempo_previsto'] ?? '')) ?>"></div>
                <div class="activity-field col-3"><label><?= h(t('activity.field_due_date')) ?></label><input type="date" name="data_prevista_termino" value="<?= h(activity_edit_date($row, 'data_prevista_termino')) ?>"></div>
                <div class="activity-field col-3"><label><?= h(t('activity.field_real_date')) ?></label><input type="date" name="data_real_termino" value="<?= h(activity_edit_date($row, 'data_real_termino')) ?>"></div>

                <div class="activity-field col-6"><label><?= h(t('activity.field_execution_owner')) ?></label><input name="responsavel_execucao" value="<?= h((string) ($row['responsavel_execucao'] ?? '')) ?>"></div>
                <div class="activity-field col-6"><label><?= h(t('activity.field_management_owner')) ?></label><input name="responsavel_gestao" value="<?= h((string) ($row['responsavel_gestao'] ?? '')) ?>"></div>

                <div class="activity-field col-12"><label><?= h(t('activity.field_objective')) ?></label><textarea name="objetivo"><?= h((string) ($row['objetivo'] ?? '')) ?></textarea></div>
                <div class="activity-field col-6"><label><?= h(t('activity.field_activity')) ?></label><textarea name="atividade" required><?= h((string) ($row['atividade'] ?? '')) ?></textarea></div>
                <div class="activity-field col-6"><label><?= h(t('activity.field_subactivity')) ?></label><textarea name="sub_atividade"><?= h((string) ($row['sub_atividade'] ?? '')) ?></textarea></div>
                <div class="activity-field col-12"><label><?= h(t('activity.field_text_dependency')) ?></label><textarea name="dependencia"><?= h((string) ($row['dependencia'] ?? '')) ?></textarea></div>

                <div class="activity-field col-6"><label><?= h(t('activity.field_result_description')) ?></label><textarea name="descricao_resultados"><?= h((string) ($row['descricao_resultados'] ?? '')) ?></textarea></div>
                <div class="activity-field col-6"><label><?= h(t('activity.field_difficulties')) ?></label><textarea name="dificuldades"><?= h((string) ($row['dificuldades'] ?? '')) ?></textarea></div>

                <div class="activity-field col-12">
                    <label><?= h(t('activity.field_linked_prerequisites')) ?></label>
                    <?php if ($supportsDependencyTable): ?>
                        <select class="activity-dep-select" name="dependencies[]" multiple>
                            <?php foreach ($dependencyCandidates as $candidate): ?>
                                <?php
                                $candidateId = (int) ($candidate['id'] ?? 0);
                                $label = '#' . $candidateId . ' · ' . trim((string) ($candidate['projeto'] ?? '')) . ' / ' . trim((string) ($candidate['subprojeto'] ?? '')) . ' · ' . trim((string) ($candidate['atividade'] ?? ''));
                                $sub = trim((string) ($candidate['sub_atividade'] ?? ''));
                                if ($sub !== '') {
                                    $label .= ' / ' . $sub;
                                }
                                ?>
                                <option value="<?= h((string) $candidateId) ?>" <?= in_array($candidateId, $dependencyIds, true) ? 'selected' : '' ?>><?= h($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="activity-help"><?= h(t('activity.multi_select_hint')) ?></div>
                    <?php else: ?>
                        <div class="activity-help"><?= h(t('activity.dependency_migration_hint')) ?> <code>database/migrations/2026_05_10_atividades_project_view.sql</code></div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="activity-actions" style="margin-top:1rem">
                <button class="activity-btn activity-btn--primary" type="submit"><?= h(t('activity.save_activity')) ?></button>
                <a class="activity-btn activity-btn--ghost" data-shell-nav="true" href="<?= h(activity_back_url($project, $subproject)) ?>"><?= h(t('activity.back_to_grid')) ?></a>
            </div>
        </div>
    </form>

    <?php if ($id > 0): ?>
        <section class="activity-card">
            <div class="activity-card__head"><strong><?= h(t('activity.execution_evidence')) ?></strong></div>
            <div class="activity-card__body">
                <form method="post" action="<?= h(url('atividades/upload_evidence.php')) ?>" enctype="multipart/form-data" class="activity-actions" style="align-items:center;margin-bottom:1rem">
                    <?= csrf_input() ?>
                    <input type="hidden" name="atividade_id" value="<?= h((string) $id) ?>">
                    <input type="file" name="evidencias[]" multiple>
                    <button class="activity-btn activity-btn--primary" type="submit"><?= h(t('activity.upload_evidence')) ?></button>
                </form>

                <?php if ($evidences === []): ?>
                    <div class="activity-help"><?= h(t('activity.no_evidence')) ?></div>
                <?php else: ?>
                    <div class="activity-evidence-list">
                        <?php foreach ($evidences as $evidence): ?>
                            <?php $filePath = (string) ($evidence['file_path'] ?? ''); ?>
                            <div class="activity-evidence-item">
                                <div>
                                    <a href="<?= h(asset($filePath)) ?>" target="_blank" rel="noopener"><?= h((string) ($evidence['original_name'] ?? basename($filePath))) ?></a>
                                    <div class="activity-help"><?= h((string) ($evidence['uploaded_at'] ?? '')) ?></div>
                                </div>
                                <form method="post" action="<?= h(url('atividades/delete_evidence.php')) ?>" onsubmit="return confirm(<?= h(json_encode(t('activity.confirm_remove_evidence'), JSON_UNESCAPED_UNICODE)) ?>)">
                                    <?= csrf_input() ?>
                                    <input type="hidden" name="atividade_id" value="<?= h((string) $id) ?>">
                                    <input type="hidden" name="evidence_id" value="<?= h((string) ($evidence['id'] ?? 0)) ?>">
                                    <button class="activity-btn activity-btn--danger" type="submit"><?= h(t('common.remove', [], t('common.delete'))) ?></button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>
</section>
