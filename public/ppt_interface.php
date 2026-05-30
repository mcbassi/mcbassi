<?php
declare(strict_types=1);
require dirname(__DIR__) . '/lib/common.php';

function ppt_ui_lang(): string {
    $lang = strtolower(trim((string)($_GET['lang'] ?? '')));
    if ($lang === '') {
        $accept = strtolower((string)($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''));
        $lang = substr($accept, 0, 2);
    } else {
        $lang = substr(str_replace('_', '-', $lang), 0, 2);
    }
    return in_array($lang, ['pt', 'es', 'en'], true) ? $lang : 'pt';
}

function ppt_i18n_messages(string $lang): array {
    $messages = [
        'pt' => [
            'html_lang' => 'pt-br',
            'title' => 'Gerador de PPT',
            'subtitle' => 'Interface interna para acionar o motor validado do PPT.',
            'search_sessions' => 'Buscar sessões do questionário',
            'company' => 'Empresa',
            'response_email' => 'Email da resposta',
            'search' => 'Buscar',
            'version' => 'Versão',
            'date' => 'Data',
            'generate_presentation' => 'Gerar apresentação',
            'template' => 'Modelo',
            'session_id' => 'ID da sessão',
            'user_id' => 'ID do usuário',
            'metric_year' => 'Ano da métrica',
            'industry' => 'Setor',
            'generate_ppt' => 'Gerar PPT',
            'result' => 'Resultado',
            'no_execution' => 'Nenhuma execução ainda.',
            'status_debug' => 'Status / Debug',
            'ready' => 'Pronto.',
            'loading_sessions' => 'Carregando sessões...',
            'loading' => 'Carregando...',
            'error' => 'Erro',
            'error_loading_sessions' => 'Erro ao carregar sessões.',
            'no_sessions_found' => 'Nenhuma sessão encontrada.',
            'sessions_loaded' => 'Sessões carregadas: ',
            'selected_session' => 'Sessão selecionada: ',
            'generating_ppt' => 'Gerando PPT...',
            'running' => 'Executando...',
            'error_generating' => 'Erro ao gerar',
            'generation_failed' => 'Falha na geração.',
            'download_ppt' => 'Baixar PPT',
            'download_context' => 'Baixar ppt_input_context.json',
            'download_runtime' => 'Baixar ppt_runtime_payload.json',
            'output_dir' => 'Pasta de saída',
            'no_link' => 'Nenhum link disponível',
            'ppt_success' => 'PPT gerado com sucesso.',
        ],
        'es' => [
            'html_lang' => 'es',
            'title' => 'Generador de PPT',
            'subtitle' => 'Interfaz interna para activar el motor validado de PPT.',
            'search_sessions' => 'Buscar sesiones del cuestionario',
            'company' => 'Empresa',
            'response_email' => 'Correo de la respuesta',
            'search' => 'Buscar',
            'version' => 'Versión',
            'date' => 'Fecha',
            'generate_presentation' => 'Generar presentación',
            'template' => 'Modelo',
            'session_id' => 'ID de sesión',
            'user_id' => 'ID de usuario',
            'metric_year' => 'Año de la métrica',
            'industry' => 'Industria',
            'generate_ppt' => 'Generar PPT',
            'result' => 'Resultado',
            'no_execution' => 'Ninguna ejecución todavía.',
            'status_debug' => 'Estado / Debug',
            'ready' => 'Listo.',
            'loading_sessions' => 'Cargando sesiones...',
            'loading' => 'Cargando...',
            'error' => 'Error',
            'error_loading_sessions' => 'Error al cargar sesiones.',
            'no_sessions_found' => 'No se encontraron sesiones.',
            'sessions_loaded' => 'Sesiones cargadas: ',
            'selected_session' => 'Sesión seleccionada: ',
            'generating_ppt' => 'Generando PPT...',
            'running' => 'Ejecutando...',
            'error_generating' => 'Error al generar',
            'generation_failed' => 'Fallo en la generación.',
            'download_ppt' => 'Descargar PPT',
            'download_context' => 'Descargar ppt_input_context.json',
            'download_runtime' => 'Descargar ppt_runtime_payload.json',
            'output_dir' => 'Carpeta de salida',
            'no_link' => 'Ningún enlace disponible',
            'ppt_success' => 'PPT generado correctamente.',
        ],
        'en' => [
            'html_lang' => 'en',
            'title' => 'PPT Generator',
            'subtitle' => 'Internal interface to run the validated PPT engine.',
            'search_sessions' => 'Search questionnaire sessions',
            'company' => 'Company',
            'response_email' => 'Response email',
            'search' => 'Search',
            'version' => 'Version',
            'date' => 'Date',
            'generate_presentation' => 'Generate presentation',
            'template' => 'Template',
            'session_id' => 'Session ID',
            'user_id' => 'User ID',
            'metric_year' => 'Metric year',
            'industry' => 'Industry',
            'generate_ppt' => 'Generate PPT',
            'result' => 'Result',
            'no_execution' => 'No execution yet.',
            'status_debug' => 'Status / Debug',
            'ready' => 'Ready.',
            'loading_sessions' => 'Loading sessions...',
            'loading' => 'Loading...',
            'error' => 'Error',
            'error_loading_sessions' => 'Error loading sessions.',
            'no_sessions_found' => 'No sessions found.',
            'sessions_loaded' => 'Sessions loaded: ',
            'selected_session' => 'Selected session: ',
            'generating_ppt' => 'Generating PPT...',
            'running' => 'Running...',
            'error_generating' => 'Error generating',
            'generation_failed' => 'Generation failed.',
            'download_ppt' => 'Download PPT',
            'download_context' => 'Download ppt_input_context.json',
            'download_runtime' => 'Download ppt_runtime_payload.json',
            'output_dir' => 'Output dir',
            'no_link' => 'No link available',
            'ppt_success' => 'PPT generated successfully.',
        ],
    ];
    return $messages[$lang] ?? $messages['pt'];
}

function ppt_t(string $key): string {
    global $pptUi;
    return (string)($pptUi[$key] ?? $key);
}

$pptLang = ppt_ui_lang();
$pptUi = ppt_i18n_messages($pptLang);
$cfg = ppt_load_config();
$presentations = ppt_available_presentations($cfg);
$defaults = $cfg['default_values'] ?? [];
?><!doctype html>
<html lang="<?= htmlspecialchars(ppt_t('html_lang')) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars(ppt_t('title')) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body{background:#f5f7fb}
    .card-soft{border:0;border-radius:16px;box-shadow:0 8px 30px rgba(15,23,42,.08)}
    .table-wrap{max-height:480px;overflow:auto}
    .session-row{cursor:pointer}
    .session-row.active{background:#e8f1ff}
    .mono{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:.88rem}
    .status-box{white-space:pre-wrap;background:#0f172a;color:#e2e8f0;border-radius:12px;padding:12px;min-height:120px}
  </style>
</head>
<body>
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h1 class="h3 mb-1"><?= htmlspecialchars(ppt_t('title')) ?></h1>
      <div class="text-muted"><?= htmlspecialchars(ppt_t('subtitle')) ?></div>
    </div>
  </div>

  <div class="row g-4">
    <div class="col-lg-7">
      <div class="card card-soft">
        <div class="card-body">
          <h2 class="h5 mb-3"><?= htmlspecialchars(ppt_t('search_sessions')) ?></h2>
          <form id="sessionFilterForm" class="row g-2 mb-3">
            <div class="col-md-5">
              <input type="text" class="form-control" name="company_name" placeholder="<?= htmlspecialchars(ppt_t('company')) ?>">
            </div>
            <div class="col-md-5">
              <input type="text" class="form-control" name="email_resp" placeholder="<?= htmlspecialchars(ppt_t('response_email')) ?>">
            </div>
            <div class="col-md-2 d-grid">
              <button class="btn btn-primary" type="submit"><?= htmlspecialchars(ppt_t('search')) ?></button>
            </div>
          </form>

          <div class="table-wrap border rounded-3">
            <table class="table table-sm table-hover mb-0" id="sessionsTable">
              <thead class="table-light sticky-top">
                <tr>
                  <th>ID</th>
                  <th><?= htmlspecialchars(ppt_t('version')) ?></th>
                  <th><?= htmlspecialchars(ppt_t('company')) ?></th>
                  <th>Email</th>
                  <th><?= htmlspecialchars(ppt_t('date')) ?></th>
                  <th>%</th>
                </tr>
              </thead>
              <tbody></tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-5">
      <div class="card card-soft mb-4">
        <div class="card-body">
          <h2 class="h5 mb-3"><?= htmlspecialchars(ppt_t('generate_presentation')) ?></h2>
          <form id="generateForm" class="row g-3">
            <div class="col-12">
              <label class="form-label"><?= htmlspecialchars(ppt_t('template')) ?></label>
              <select class="form-select" name="presentation_name">
                <?php foreach ($presentations as $p): ?>
                  <option value="<?= htmlspecialchars((string)$p['name']) ?>"><?= htmlspecialchars((string)($p['label'] ?? $p['name'])) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label"><?= htmlspecialchars(ppt_t('company')) ?></label>
              <input type="text" class="form-control" name="company_name" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Email</label>
              <input type="email" class="form-control" name="email_resp" required>
            </div>
            <div class="col-md-6">
              <label class="form-label"><?= htmlspecialchars(ppt_t('session_id')) ?></label>
              <input type="number" class="form-control" name="version_id" required>
            </div>
            <div class="col-md-6">
              <label class="form-label"><?= htmlspecialchars(ppt_t('user_id')) ?></label>
              <input type="number" class="form-control" name="user_id" value="<?= (int)($defaults['user_id'] ?? 1) ?>" required>
            </div>
            <div class="col-md-6">
              <label class="form-label"><?= htmlspecialchars(ppt_t('metric_year')) ?></label>
              <input type="number" class="form-control" name="metric_year" value="<?= (int)($defaults['metric_year'] ?? date('Y')) ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label"><?= htmlspecialchars(ppt_t('industry')) ?></label>
              <input type="text" class="form-control" name="industry_name" value="<?= htmlspecialchars((string)($defaults['industry_name'] ?? '')) ?>">
            </div>
            <div class="col-12 d-grid">
              <button class="btn btn-success" type="submit"><?= htmlspecialchars(ppt_t('generate_ppt')) ?></button>
            </div>
          </form>
        </div>
      </div>

      <div class="card card-soft mb-4">
        <div class="card-body">
          <h2 class="h5 mb-3"><?= htmlspecialchars(ppt_t('result')) ?></h2>
          <div id="resultBox" class="small text-muted"><?= htmlspecialchars(ppt_t('no_execution')) ?></div>
        </div>
      </div>

      <div class="card card-soft">
        <div class="card-body">
          <h2 class="h5 mb-3"><?= htmlspecialchars(ppt_t('status_debug')) ?></h2>
          <div id="statusBox" class="status-box"><?= htmlspecialchars(ppt_t('ready')) ?></div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
const PPT_UI = <?= json_encode($pptUi, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const tableBody = document.querySelector('#sessionsTable tbody');
const filterForm = document.getElementById('sessionFilterForm');
const generateForm = document.getElementById('generateForm');
const statusBox = document.getElementById('statusBox');
const resultBox = document.getElementById('resultBox');

function setStatus(text){ statusBox.textContent = text; }

function esc(v){
  return String(v ?? '').replace(/[&<>"']/g, s => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[s]));
}

async function loadSessions(params = new URLSearchParams()) {
  setStatus(PPT_UI.loading_sessions);
  tableBody.innerHTML = '<tr><td colspan="6" class="text-center p-3">' + esc(PPT_UI.loading) + '</td></tr>';
  const res = await fetch('api/ppt_sessions.php?' + params.toString(), {credentials:'same-origin'});
  const data = await res.json();
  if (!data.ok) {
    tableBody.innerHTML = '<tr><td colspan="6" class="text-danger p-3">' + esc(data.error || PPT_UI.error) + '</td></tr>';
    setStatus(PPT_UI.error_loading_sessions);
    return;
  }
  if (!data.rows.length) {
    tableBody.innerHTML = '<tr><td colspan="6" class="text-muted p-3">' + esc(PPT_UI.no_sessions_found) + '</td></tr>';
    setStatus(PPT_UI.no_sessions_found);
    return;
  }
  tableBody.innerHTML = data.rows.map(row => `
    <tr class="session-row" data-company="${esc(row.company_name)}" data-email="${esc(row.email_resp)}" data-version="${esc(row.version_id)}" data-version-no="${esc(row.version_no || '')}">
      <td>${esc(row.response_session_id)}</td>
      <td>${esc(row.version_no || row.version_id)}</td>
      <td>${esc(row.company_name)}</td>
      <td>${esc(row.email_resp)}</td>
      <td>${esc(row.response_datetime)}</td>
      <td>${esc(row.completion_pct)}</td>
    </tr>
  `).join('');
  setStatus(PPT_UI.sessions_loaded + data.count);
}

filterForm.addEventListener('submit', async (e) => {
  e.preventDefault();
  const params = new URLSearchParams(new FormData(filterForm));
  await loadSessions(params);
});

tableBody.addEventListener('click', (e) => {
  const row = e.target.closest('.session-row');
  if (!row) return;
  document.querySelectorAll('.session-row').forEach(r => r.classList.remove('active'));
  row.classList.add('active');
  generateForm.company_name.value = row.dataset.company || '';
  generateForm.email_resp.value = row.dataset.email || '';
  generateForm.version_id.value = row.dataset.version || '';
  setStatus(PPT_UI.selected_session + row.dataset.company + ' / v' + (row.dataset.versionNo || row.dataset.version) + ' | ID ' + row.dataset.version);
});

generateForm.addEventListener('submit', async (e) => {
  e.preventDefault();
  const body = Object.fromEntries(new FormData(generateForm).entries());
  body.user_id = Number(body.user_id || 1);
  body.version_id = Number(body.version_id || 0);
  body.metric_year = Number(body.metric_year || 0);
  setStatus(PPT_UI.generating_ppt);
  resultBox.innerHTML = '<div class="text-muted">' + esc(PPT_UI.running) + '</div>';

  const res = await fetch('api/ppt_generate.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    credentials: 'same-origin',
    body: JSON.stringify(body)
  });
  const data = await res.json();
  if (!data.ok) {
    resultBox.innerHTML = '<div class="text-danger">' + esc(data.error || PPT_UI.error_generating) + '</div>';
    setStatus(PPT_UI.generation_failed);
    return;
  }

  const items = [];
  if (data.pptx_download) items.push(`<li><a href="${esc(data.pptx_download)}">${esc(PPT_UI.download_ppt)}</a></li>`);
  if (data.context_json_download) items.push(`<li><a href="${esc(data.context_json_download)}">${esc(PPT_UI.download_context)}</a></li>`);
  if (data.runtime_json_download) items.push(`<li><a href="${esc(data.runtime_json_download)}">${esc(PPT_UI.download_runtime)}</a></li>`);

  resultBox.innerHTML = `
    <div class="mb-2"><strong>Execution ID:</strong> ${esc(data.execution_id)}</div>
    <div class="mb-2 mono"><strong>PPT:</strong> ${esc(data.pptx || '')}</div>
    <div class="mb-2 mono"><strong>${esc(PPT_UI.output_dir)}:</strong> ${esc(data.output_dir || '')}</div>
    <ul class="mb-0">${items.join('') || ('<li>' + esc(PPT_UI.no_link) + '</li>')}</ul>
  `;
  setStatus(PPT_UI.ppt_success);
});

loadSessions();
</script>
</body>
</html>
