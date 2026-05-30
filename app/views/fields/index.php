<?php
declare(strict_types=1);
$rows = is_array($rows ?? null) ? $rows : [];
$schema = is_array($schema ?? null) ? $schema : [];
$filters = is_array($filters ?? null) ? $filters : [];
$stats = is_array($stats ?? null) ? $stats : [];
$filterOptions = is_array($filterOptions ?? null) ? $filterOptions : [];
$error = $error ?? null;
$success = $success ?? null;
$preferred = ['id', 'section_code', 'sort_order', 'label', 'name', 'field_name', 'type', 'required', 'prompt_code'];
$columns = [];
foreach ($preferred as $col) {
    if (isset($schema[$col])) {
        $columns[] = $col;
    }
}
foreach (array_keys($schema) as $col) {
    if (!in_array($col, $columns, true)) {
        $columns[] = $col;
    }
}
$displayColumns = array_slice($columns, 0, min(10, max(1, count($columns))));
?>
<style>
body .workspace__inner{max-width:none;width:calc(100vw - 320px)}
.fields-table-wrap{overflow:auto}.fields-table{min-width:1300px}.fields-table td,.fields-table th{vertical-align:top}.fields-code{font-family:Consolas,monospace;font-size:.84rem;white-space:nowrap}
</style>
<div class="module-toolbar">
    <a data-shell-nav="true" class="action-pill action-pill--outline" href="<?= h(url('fields/index.php')) ?>">Configurar Questionário</a>
    <a data-shell-nav="off" class="action-pill action-pill--amber" href="<?= h(url('fields/form.php')) ?>">Novo campo</a>
    <a data-shell-nav="true" class="action-pill action-pill--ghost" href="<?= h(url('grupos/index.php')) ?>">Editar Grupos</a>
    <a data-shell-nav="true" class="action-pill action-pill--ghost" href="<?= h(url('prompts/index.php')) ?>">Configurar Agentes</a>
</div>
<?php if (!empty($error)): ?><article class="module-card notice-card notice-card--error"><strong>Erro:</strong> <?= h((string)$error) ?></article><?php endif; ?>
<?php if (!empty($success)): ?><article class="module-card notice-card notice-card--success"><strong>Sucesso:</strong> <?= h((string)$success) ?></article><?php endif; ?>
<section class="stats-grid stats-grid--prompts">
  <article class="stat-card"><span class="stat-card__label">Campos</span><strong class="stat-card__value"><?= (int)($stats['total'] ?? 0) ?></strong></article>
  <article class="stat-card"><span class="stat-card__label">Perguntas</span><strong class="stat-card__value"><?= (int)($stats['questions'] ?? 0) ?></strong></article>
  <article class="stat-card"><span class="stat-card__label">Seções</span><strong class="stat-card__value"><?= (int)($stats['sections'] ?? 0) ?></strong></article>
  <article class="stat-card"><span class="stat-card__label">Com prompt</span><strong class="stat-card__value"><?= (int)($stats['with_prompt'] ?? 0) ?></strong></article>
</section>
<article class="module-card">
  <header class="module-card__header"><div><h2>Campos do questionário</h2><p class="muted">Tela ligada diretamente à tabela <code>form_fields</code>, preservando os campos existentes.</p></div></header>
  <form class="control-grid control-grid--filters" method="get" action="<?= h(url('fields/index.php')) ?>">
    <label class="form-field"><span>Buscar</span><input type="text" name="q" value="<?= h((string)($filters['q'] ?? '')) ?>" placeholder="Nome, label, seção ou prompt"></label>
    <label class="form-field"><span>Seção</span><select name="section"><option value="">Todas</option><?php foreach (($filterOptions['sections'] ?? []) as $option): ?><option value="<?= h((string)$option) ?>" <?= trim((string)$option)===trim((string)($filters['section'] ?? ''))?'selected':'' ?>><?= h((string)$option) ?></option><?php endforeach; ?></select></label>
    <label class="form-field"><span>Tipo</span><select name="type"><option value="">Todos</option><?php foreach (($filterOptions['types'] ?? []) as $option): ?><option value="<?= h((string)$option) ?>" <?= trim((string)$option)===trim((string)($filters['type'] ?? ''))?'selected':'' ?>><?= h((string)$option) ?></option><?php endforeach; ?></select></label>
    <div class="prompt-filter-actions"><button type="submit" class="action-pill action-pill--green">Filtrar</button><a data-shell-nav="true" class="action-pill action-pill--ghost" href="<?= h(url('fields/index.php')) ?>">Limpar</a></div>
  </form>
  <div class="fields-table-wrap">
    <table class="data-table fields-table">
      <thead><tr><?php foreach ($displayColumns as $col): ?><th><?= h($col) ?></th><?php endforeach; ?><th class="table-actions-col">Ações</th></tr></thead>
      <tbody>
        <?php if ($rows === []): ?><tr><td colspan="<?= count($displayColumns)+1 ?>" class="empty-table">Nenhum campo encontrado.</td></tr><?php endif; ?>
        <?php foreach ($rows as $row): ?><tr>
          <?php foreach ($displayColumns as $col): $val = $row[$col] ?? ''; ?>
            <td><?php if (in_array($col, ['name', 'field_name', 'prompt_code'], true)): ?><span class="fields-code"><?= h((string)$val) ?></span><?php else: ?><?= h(is_scalar($val) || $val === null ? (string)$val : json_encode($val, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)) ?><?php endif; ?></td>
          <?php endforeach; ?>
          <td class="table-actions-cell"><div class="table-actions"><a data-shell-nav="off" class="action-pill action-pill--outline" href="<?= h(url('fields/form.php?id='.(int)($row['id'] ?? 0))) ?>">Editar</a>
          <form method="post" action="<?= h(url('fields/delete.php')) ?>" onsubmit="return confirm('Excluir este campo?');"><?= csrf_input() ?><input type="hidden" name="id" value="<?= (int)($row['id'] ?? 0) ?>"><button type="submit" class="action-pill action-pill--danger">Excluir</button></form></div></td>
        </tr><?php endforeach; ?>
      </tbody>
    </table>
  </div>
</article>
<article class="module-card">
  <header class="module-card__header"><div><h2>Importar em lote</h2><p class="muted">Cole um JSON de array com os campos da tabela <code>form_fields</code>. Nada é removido automaticamente.</p></div></header>
  <form method="post" action="<?= h(url('fields/import_from_array.php')) ?>" class="prompt-edit-lite__stack"><?= csrf_input() ?>
    <span>JSON de importação</span>
    <textarea name="import_json" rows="10" placeholder='[{"section_code":"1","sort_order":1,"name":"empresa","label":"Empresa","type":"text"}]'></textarea>
    <div class="prompt-filter-actions"><button type="submit" class="action-pill action-pill--green">Importar</button></div>
  </form>
</article>
