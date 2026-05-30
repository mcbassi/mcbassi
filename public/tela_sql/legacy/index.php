<?php declare(strict_types=1);
$app = require dirname(__DIR__, 3) . '/app/bootstrap/app.php';
$app['auth']->requireAuth();
$csrf = csrf_token();
?>
<?php
?><!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Editor SQL (DataSmart)</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="./assets/style.css" rel="stylesheet">
</head>
<body>
<div class="container-fluid py-3">
  <div class="d-flex align-items-center justify-content-between mb-2">
    <div>
      <h4 class="mb-0">Editor de Sentenças SQL <span class="badge text-bg-dark" id="dsVersionBadge">v?</span></h4>
      <div class="text-muted small">Execução via API • Catálogo em <code>form_app</code> • Target via <code>Config.cfg</code></div>
    </div>
    <div class="d-flex gap-2">
      <button class="btn btn-outline-secondary btn-sm" id="btnReloadSchema">Recarregar schema</button>
      <button class="btn btn-outline-secondary btn-sm" id="btnLoadCatalog">Catálogo</button>
    </div>
  </div>

  <div class="row g-3">
    <div class="col-12 col-lg-3">
      <div class="card shadow-sm">
        <div class="card-header bg-body">
          <div class="d-flex justify-content-between align-items-center">
            <strong>Schema</strong>
            <span class="badge text-bg-light" id="targetDbBadge">...</span>
          </div>
          <input class="form-control form-control-sm mt-2" id="tableFilter" placeholder="filtrar tabelas...">
        </div>
        <div class="card-body p-2" style="max-height:65vh; overflow:auto;">
          <div id="tablesList" class="list-group list-group-flush"></div>
        </div>
      </div>
    </div>

    <div class="col-12 col-lg-6">
      <div class="card shadow-sm">
        <div class="card-header bg-body">
          <ul class="nav nav-tabs card-header-tabs" role="tablist">
            <li class="nav-item" role="presentation">
              <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabBuilder" type="button" role="tab">Builder</button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabSQL" type="button" role="tab">SQL</button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabPrompt" type="button" role="tab">Prompt → SQL</button>
            </li>
          </ul>
        </div>

        <div class="card-body tab-content">
          <div class="tab-pane fade show active" id="tabBuilder" role="tabpanel">
            <div class="row g-2">
              <div class="col-12">
                <label class="form-label small mb-1">Tabela principal</label>
                <select class="form-select form-select-sm" id="mainTable"></select>
              </div>

              <div class="col-12">
                <div class="d-flex align-items-center justify-content-between">
                  <label class="form-label small mb-1">Campos</label>
                  <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm py-0" id="btnClearFields">Limpar seleção</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm py-0" id="btnEditSelectDesc">Editar descrição</button>
                  </div>
                </div>

                <div class="collapse" id="selectDescCollapse">
                  <div class="border rounded p-2 mb-2">
                    <label class="form-label small mb-1">Descrição curta do SELECT (máx. 80)</label>
                    <input class="form-control form-control-sm" id="selectDescBuilder" maxlength="80" placeholder="Ex.: Top clientes por faturamento">
                    <div class="form-text">Essa descrição é salva junto da versão da query (select_desc).</div>
                  </div>
                </div>

                <div class="border rounded p-2" style="max-height: 220px; overflow:auto;" id="fieldsBox">
                  <div class="text-muted small">Escolha uma tabela para listar campos.</div>
                </div>
              </div>

              <div class="col-12">
                <label class="form-label small mb-1">Filtro (WHERE) - simples</label>
                <div class="row g-2">
                  <div class="col-5"><select class="form-select form-select-sm" id="whereField"></select></div>
                  <div class="col-3">
                    <select class="form-select form-select-sm" id="whereOp">
                      <option value="=">=</option>
                      <option value="<>">&lt;&gt;</option>
                      <option value=">">&gt;</option>
                      <option value="<">&lt;</option>
                      <option value="LIKE">LIKE</option>
                      <option value="IN">IN</option>
                      <option value="BETWEEN">BETWEEN</option>
                      <option value="IS NULL">IS NULL</option>
                      <option value="IS NOT NULL">IS NOT NULL</option>
                    </select>
                  </div>
                  <div class="col-4"><input class="form-control form-control-sm" id="whereVal" placeholder="valor (ou :param)"></div>
                </div>
                <div class="form-text">Dica: use <code>:param</code> (ex.: <code>:dt_ini</code>) para parametrizar.</div>
              </div>

              <div class="col-8">
                <label class="form-label small mb-1">Ordenar por</label>
                <select class="form-select form-select-sm" id="orderBy"></select>
              </div>
              <div class="col-4">
                <label class="form-label small mb-1">Direção</label>
                <select class="form-select form-select-sm" id="orderDir">
                  <option value="ASC">ASC</option>
                  <option value="DESC">DESC</option>
                </select>
              </div>

              <div class="col-6">
                <label class="form-label small mb-1">Preview (linhas)</label>
                <input type="number" class="form-control form-control-sm" id="previewLimit" value="200" min="1" max="5000">
              </div>
              <div class="col-6">
                <label class="form-label small mb-1">Offset</label>
                <input type="number" class="form-control form-control-sm" id="previewOffset" value="0" min="0">
              </div>

              <div class="col-12 d-flex gap-2">
                <button class="btn btn-primary btn-sm" id="btnBuildToSQL">Gerar SQL</button>
                <button class="btn btn-outline-primary btn-sm" id="btnExecute">Executar (tela)</button>
                <button class="btn btn-outline-secondary btn-sm" id="btnValidate">Validar</button>
              </div>

              <div class="col-12">
                <div id="validateBox" class="small"></div>
              </div>
            </div>
          </div>

          <div class="tab-pane fade" id="tabSQL" role="tabpanel">
            <label class="form-label small mb-1">SQL (apenas SELECT; LIMIT aplicado só para tela)</label>
            <textarea id="sqlEditor" class="form-control font-monospace" rows="12" spellcheck="false"></textarea>
<div class="mt-2 border rounded p-2">
<div class="d-flex align-items-center justify-content-between mb-1"><label class="small mb-0">Placeholders &lt;&lt;VAR&gt;&gt;</label><button type="button" class="btn btn-outline-secondary btn-sm py-0" id="btnRefreshPlaceholders">Atualizar</button></div><div class="small text-muted mb-1" id="angleParamsDebug"></div>
<div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" id="autoQuoteStrings" checked><label class="form-check-label small" for="autoQuoteStrings">Auto-colocar aspas em texto (ex.: Marco → &#039;Marco&#039;)</label></div><div class="row g-2 align-items-end mb-2"><div class="col-7"><label class="small mb-1">Adicionar placeholder</label><input id="addPlaceholderName" class="form-control form-control-sm font-monospace" placeholder="EX: SETOR"></div><div class="col-5 d-grid"><button type="button" class="btn btn-outline-secondary btn-sm" id="btnAddPlaceholder">Adicionar</button></div></div><div id="angleParamsBox" class="small"></div><div class="mt-2"><label class="small mb-1">SQL final (após substituir placeholders)</label><textarea id="finalSqlBox" class="form-control form-control-sm font-monospace" rows="4" readonly></textarea></div>
</div>

            <div class="row g-2 mt-2">
              <div class="col-12 col-md-6">
                <label class="form-label small mb-1">Parâmetros JSON (opcional)</label>
                <textarea id="paramsEditor" class="form-control font-monospace" rows="6" spellcheck="false"
                  placeholder='{"dt_ini":"2026-01-01","dt_fim":"2026-01-31"}'></textarea>
              </div>
              <div class="col-12 col-md-6">
                <label class="form-label small mb-1">Salvar no Catálogo</label>
                <input class="form-control form-control-sm mb-2" id="slug" placeholder="SLUG (ex: VENDAS_TOP10)">
                <input class="form-control form-control-sm mb-2" id="title" placeholder="Título">
                <input class="form-control form-control-sm mb-2" id="selectDescSave" maxlength="80" placeholder="Descrição curta (máx. 80)">
                <textarea class="form-control form-control-sm" id="description" rows="3" placeholder="Descrição (longa)"></textarea>
                <div class="d-flex gap-2 mt-2">
                  <button class="btn btn-success btn-sm" id="btnSave">Salvar</button>
                </div>
              </div>
            </div>
          </div>

          <div class="tab-pane fade" id="tabPrompt" role="tabpanel">
            <label class="form-label small mb-1">Prompt (salvo junto da query; geração via IA você pluga depois)</label>
            <textarea id="promptEditor" class="form-control" rows="10" spellcheck="true"
              placeholder="Ex.: Liste os 20 clientes com maior faturamento no período :dt_ini a :dt_fim"></textarea>
            <div class="form-text">Nesta entrega, o prompt é armazenado junto da query. A geração automática via IA você conecta no seu motor depois.</div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-12 col-lg-3">
      <div class="card shadow-sm">
        <div class="card-header bg-body d-flex justify-content-between align-items-center">
          <strong>Resultado</strong>
          <span class="text-muted small" id="runInfo">—</span>
        </div>
        <div class="card-body p-2" style="max-height:65vh; overflow:auto;">
          <div id="resultBox" class="small text-muted">Execute uma query para ver resultados.</div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="catalogModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Catálogo DataSmart</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input class="form-control form-control-sm mb-2" id="catalogSearch" placeholder="buscar...">
        <div id="catalogList" class="list-group"></div>
      </div>
    </div>
  </div>
</div>


<script>
window.TELA_SQL_API = {
  schemaUrl: '../api/schema.php',
  validateUrl: '../api/validate.php',
  executeUrl: '../api/execute.php',
  catalogListUrl: '../api/catalog_list.php',
  catalogGetUrl: '../api/catalog_get.php',
  catalogSaveUrl: '../api/catalog_save.php',
  csrf: <?= json_encode($csrf, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
};
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="./assets/app.js?v=4.2.0"></script>
</body>
</html>
