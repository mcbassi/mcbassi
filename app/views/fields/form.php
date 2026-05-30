<?php
declare(strict_types=1);
$record = is_array($record ?? null) ? $record : [];
$schema = is_array($schema ?? null) ? $schema : [];
$editableColumns = is_array($editableColumns ?? null) ? $editableColumns : [];
$error = $error ?? null;
$success = $success ?? null;
$isEditing = !empty($record['id']);
function field_input_control(array $meta, mixed $value): string {
    $name = (string)($meta['name'] ?? '');
    $type = strtolower((string)($meta['type'] ?? 'text'));
    $escapedName = h($name);
    $escapedValue = h(is_scalar($value) || $value === null ? (string)$value : json_encode($value, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
    if (str_contains($type, 'text') || str_contains($type, 'json')) {
        return '<textarea name="'.$escapedName.'" rows="4">'.$escapedValue.'</textarea>';
    }
    if (preg_match('/tinyint\(1\)|bool/', $type)) {
        $current = (string)$value;
        return '<select name="'.$escapedName.'"><option value="">—</option><option value="1"'.($current === '1' ? ' selected' : '').'>1</option><option value="0"'.($current === '0' ? ' selected' : '').'>0</option></select>';
    }
    return '<input type="text" name="'.$escapedName.'" value="'.$escapedValue.'">';
}
?>
<style>
body .workspace__inner{max-width:none;width:calc(100vw - 320px)}
.fields-form-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px}.fields-form-card{display:grid;gap:8px}.fields-form-card span{font-size:.9rem;color:#4d6079;font-weight:700}.fields-form-card input,.fields-form-card select,.fields-form-card textarea{width:100%;border:1px solid #cfdae8;border-radius:12px;background:#fff;padding:11px 13px;color:#13243d;font:inherit}
@media (max-width:1200px){.fields-form-grid{grid-template-columns:1fr}}
</style>
<div class="module-toolbar">
    <a data-shell-nav="true" class="action-pill action-pill--outline" href="<?= h(url('fields/index.php')) ?>">Voltar à lista</a>
    <a data-shell-nav="off" class="action-pill action-pill--amber" href="<?= h(url('fields/form.php')) ?>">Novo campo</a>
    <a data-shell-nav="true" class="action-pill action-pill--ghost" href="<?= h(url('prompts/index.php')) ?>">Configurar Agentes</a>
</div>
<?php if (!empty($error)): ?><article class="module-card notice-card notice-card--error"><strong>Erro:</strong> <?= h((string)$error) ?></article><?php endif; ?>
<?php if (!empty($success)): ?><article class="module-card notice-card notice-card--success"><strong>Sucesso:</strong> <?= h((string)$success) ?></article><?php endif; ?>
<article class="module-card">
  <header class="module-card__header"><div><h2><?= $isEditing ? 'Editar campo' : 'Novo campo' ?></h2><p class="muted">Todos os campos reais da tabela <code>form_fields</code> ficam disponíveis para edição.</p></div></header>
  <form method="post" action="<?= h(url('fields/form.php')) ?>">
    <?= csrf_input() ?>
    <input type="hidden" name="id" value="<?= h((string)($record['id'] ?? '')) ?>">
    <div class="fields-form-grid">
      <?php foreach ($editableColumns as $column): $meta = $schema[$column] ?? ['name' => $column, 'type' => 'text']; ?>
        <label class="fields-form-card"><span><?= h($column) ?> <small>(<?= h((string)($meta['type'] ?? '')) ?>)</small></span><?= field_input_control($meta, $record[$column] ?? ($meta['default'] ?? '')) ?></label>
      <?php endforeach; ?>
    </div>
    <div class="prompt-filter-actions" style="margin-top:16px"><button type="submit" class="action-pill action-pill--green">Salvar</button></div>
  </form>
</article>
