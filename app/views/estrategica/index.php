<?php
declare(strict_types=1);

$groupOptions = is_array($groupOptions ?? null) ? $groupOptions : [];
$apiUrl = trim((string) ($apiUrl ?? url('estrategica/api.php')));
$statusUrl = trim((string) ($statusUrl ?? url('api/status_questionario.php')));
$listUrl = trim((string) ($listUrl ?? url('grupos/estrategicas_list.php')));
$pageUrl = trim((string) ($pageUrl ?? url('estrategica/index.php')));
$assetsBaseUrl = rtrim(trim((string) ($assetsBaseUrl ?? asset('assets/img'))), '/');

$groupOptionsHtml = '';
$isFirst = true;
foreach ($groupOptions as $group) {
    $company = trim((string) ($group['company_name'] ?? ''));
    $email = trim((string) ($group['email_resp'] ?? ''));
    $sessMin = trim((string) ($group['sess_min'] ?? ''));
    $lastRaw = trim((string) ($group['last_datetime'] ?? ''));
    $lastLabel = $lastRaw;
    if ($lastRaw !== '') {
        try {
            $lastLabel = (new DateTime($lastRaw))->format('d/m/Y H:i');
        } catch (Throwable) {
            $lastLabel = $lastRaw;
        }
    }
    $payload = ['c' => $company, 'e' => $email, 'k' => $sessMin];
    $value = base64_encode(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    $label = sprintf('%s (%s) — %s: %s', $company, $email, t('strategic.last_answer'), $lastLabel);
    $selected = $isFirst ? ' selected' : '';
    $isFirst = false;
    $groupOptionsHtml .= sprintf('<option value="%s"%s>%s</option>', h($value), $selected, h($label));
}
?>
<style>
.estrategica-card{padding:24px 28px;background:#f3f4f6;border-radius:18px}
.estrategica-panel{background:#fff;border:none;border-radius:14px;box-shadow:0 4px 16px rgba(15,23,42,.06)}
.estrategica-panel .estrategica-body{padding:1.5rem 1.75rem}
.estrategica-title{font-weight:600;display:flex;align-items:center;gap:.5rem;margin:0 0 1rem;color:#111827;font-size:1.35rem}
.estrategica-title::before{content:"📊";font-size:1.2rem}
.estrategica-label{font-weight:500;color:#111827}
.estrategica-status .alert{border-radius:999px;padding-top:.45rem;padding-bottom:.45rem;font-size:.92rem}
.estrategica-btn{border-radius:999px}
.estrategica-btn--success{background:linear-gradient(135deg,#22c55e,#16a34a);border-color:#16a34a;color:#fff}
.estrategica-btn--success:hover{filter:brightness(.96);color:#fff}
.estrategica-table th{font-weight:600;border-bottom:2px solid #dee2e6}
.estrategica-table td{vertical-align:middle}
.estrategica-status-icon{height:64px;width:auto;transition:transform .2s ease}
.estrategica-status-icon:hover{transform:scale(1.08)}
.estrategica-actions{display:flex;gap:.5rem;flex-wrap:wrap}
</style>

<article class="module-card estrategica-card">
  <section class="estrategica-panel">
    <div class="estrategica-body">
      <h2 class="estrategica-title"><?= h(t('strategic.execute_prompts')) ?></h2>

      <div id="status" class="estrategica-status mb-2"></div>

      <form id="frm" class="row g-3 align-items-end" onsubmit="return false;">
        <div class="col-lg-6">
          <label class="form-label estrategica-label"><?= h(t('strategic.questionnaire_group')) ?></label>
          <select id="group" class="form-select" required>
            <option value="">— <?= h(t('common.select')) ?> —</option>
            <?= $groupOptionsHtml ?>
          </select>
        </div>

        <div class="col-lg-6">
          <label class="form-label estrategica-label"><?= h(t('strategic.priority_group')) ?></label>
          <select id="priority_group" class="form-select" required>
            <option value="">— <?= h(t('common.select')) ?> —</option>
          </select>
        </div>

        <div class="col-12">
          <div class="estrategica-actions">
            <button id="btnExecIA" class="btn estrategica-btn estrategica-btn--success" type="button"><?= h(t('strategic.create_report')) ?></button>
            <button id="btnDocFinal" class="btn estrategica-btn estrategica-btn--success" type="button" disabled><?= h(t('strategic.create_final_doc')) ?></button>
            <button id="btnPptFinal" class="btn estrategica-btn estrategica-btn--success" type="button" disabled><?= h(t('strategic.create_powerpoint')) ?></button>
          </div>
        </div>
      </form>

      <div class="mt-4">
        <table class="table align-middle text-center w-100 estrategica-table">
          <thead>
            <tr>
              <th class="py-3 fs-5"><?= h(t('strategic.summary')) ?></th>
              <th class="py-3 fs-5"><?= h(t('strategic.doc')) ?></th>
              <th class="py-3 fs-5"><?= h(t('strategic.presentation')) ?></th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td id="stResumo" class="py-4"></td>
              <td id="stDoc" class="py-4"></td>
              <td id="stApre" class="py-4"></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </section>
</article>

<script>
(function(){
  if (!window.__estrategica) window.__estrategica = {};
  const S = window.__estrategica;
  const root = document.querySelector('.js-shell-content') || document;
  const apiUrl = <?= json_encode($apiUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  const statusUrl = <?= json_encode($statusUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  const listUrl = <?= json_encode($listUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  const pageUrl = <?= json_encode($pageUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  const assetsBaseUrl = <?= json_encode($assetsBaseUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

  const I18N = <?= json_encode([
    'select_option' => '— ' . t('common.select') . ' —',
    'api_not_json' => t('strategic.api_not_json'),
    'processing' => t('common.processing'),
    'cannot_load_groups' => t('strategic.cannot_load_groups'),
    'open_summary' => t('strategic.open_summary'),
    'open_doc' => t('strategic.open_doc'),
    'select_group_and_priority' => t('strategic.select_group_and_priority'),
    'select_group_first' => t('strategic.select_group_first'),
    'doc_not_available' => t('strategic.doc_not_available'),
    'presentation_not_available' => t('strategic.presentation_not_available'),
    'select_group' => t('strategic.select_group'),
    'select_priority_group' => t('strategic.select_priority_group'),
    'executing_group' => '🤖 ' . t('strategic.executing_group'),
    'executed_successfully' => '✅ ' . t('strategic.executed_successfully'),
    'summary_not_available' => t('strategic.summary_not_available'),
    'generating_final_doc' => '📝 ' . t('strategic.generating_final_doc'),
  ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

  const $ = (s) => root.querySelector(s);
  const esc = (s) => String(s ?? '').replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;').replaceAll('"','&quot;').replaceAll("'", '&#039;');
  const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';
  const imgUrl = (name) => assetsBaseUrl.replace(/\/$/, '') + '/' + name + '?v=1';

  function postJson(url, payload){
    const act = payload && payload.action ? String(payload.action) : '';
    let finalUrl = url;
    if (act && url.indexOf('action=') === -1) {
      finalUrl = finalUrl + (finalUrl.indexOf('?') >= 0 ? '&' : '?') + 'action=' + encodeURIComponent(act);
    }
    const reqBody = Object.assign({}, payload || {}, {csrf_token: csrf(), _ts: Date.now()});
    return fetch(finalUrl, {
      method: 'POST',
      credentials: 'same-origin',
      cache: 'no-store',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': csrf(),
        'X-Requested-With': 'XMLHttpRequest',
        'Cache-Control': 'no-store'
      },
      body: JSON.stringify(reqBody)
    }).then(async (res) => {
      const text = await res.text();
      let data = null;
      try { data = JSON.parse(text); } catch(e) { throw new Error(I18N.api_not_json); }
      if (!res.ok || !data || data.ok === false) {
        throw new Error((data && data.error) ? data.error : ('HTTP ' + res.status));
      }
      return data;
    });
  }

  function setLoading(loading, msg=''){
    ['#btnExecIA','#btnDocFinal','#btnPptFinal','#group','#priority_group'].forEach((sel) => {
      const el = $(sel); if (el) el.disabled = !!loading;
    });
    const statusBox = $('#status');
    if (!statusBox) return;
    statusBox.innerHTML = loading ? '<div class="alert alert-info py-2 mb-0 text-center">' + esc(msg || I18N.processing) + '</div>' : '';
  }

  function renderStatusCell(el, ok, iconOk, iconNo, linkHref, buttonText){
    if (!el) return;
    const src = ok ? imgUrl(iconOk) : imgUrl(iconNo);
    const img = '<div style="padding:15px 15px 8px 15px;"><img src="' + src + '" class="estrategica-status-icon" alt=""></div>';
    if (ok && linkHref && buttonText) {
      el.innerHTML = '<div class="d-flex flex-column align-items-center gap-2">' + img + '<a href="' + linkHref + '" target="_blank" rel="noopener" class="btn btn-sm btn-success">' + esc(buttonText) + '</a></div>';
      return;
    }
    el.innerHTML = img;
  }

  function getPriorityName(){
    const sel = $('#priority_group');
    if (!sel) return '';
    const opt = sel.selectedOptions && sel.selectedOptions[0];
    const txt = opt ? String(opt.text || '').trim() : '';
    return txt && !txt.startsWith('—') ? txt : '';
  }

  function buildDownloadUrl(type){
    const groupVal = ($('#group')?.value || '');
    if (!groupVal) return null;
    if (type === 'resumo') {
      const prioName = getPriorityName();
      if (!prioName) return null;
      return pageUrl + '?action=download_resumo_doc&question_group_b64=' + encodeURIComponent(groupVal)
        + '&priority_group_name=' + encodeURIComponent(prioName)
        + '&csrf_token=' + encodeURIComponent(csrf());
    }
    return apiUrl + '?action=download_status&type=' + encodeURIComponent(type)
      + '&question_group_b64=' + encodeURIComponent(groupVal)
      + '&csrf_token=' + encodeURIComponent(csrf());
  }

  function paintAllPending(){
    renderStatusCell($('#stResumo'), false, 'icon_transparente_1_2.png', 'icon_transparente_1_1.png');
    renderStatusCell($('#stDoc'), false, 'icon_transparente_2_2.png', 'icon_transparente_2_1.png');
    renderStatusCell($('#stApre'), false, 'icon_transparente_3_2.png', 'icon_transparente_3_1.png');
    S.lastStatus = {resumo_ok:false, doc_ok:false, apres_ok:false};
    updateButtonsByStatus();
  }

  function updateButtonsByStatus(){
    const hasGroup = !!($('#group')?.value || '');
    const btnDoc = $('#btnDocFinal');
    const btnPpt = $('#btnPptFinal');
    if (btnDoc) btnDoc.disabled = !(hasGroup && S.lastStatus && S.lastStatus.resumo_ok);
    if (btnPpt) btnPpt.disabled = !(hasGroup && S.lastStatus && S.lastStatus.doc_ok);
  }

  async function loadPriorityGroups(){
    const sel = $('#priority_group');
    if (!sel) return;
    try {
      const res = await fetch(listUrl + (listUrl.indexOf('?') >= 0 ? '&' : '?') + '_ts=' + Date.now(), {credentials:'same-origin', cache:'no-store'});
      const data = await res.json();
      const arr = Array.isArray(data.items) ? data.items : [];
      sel.innerHTML = '<option value="">' + esc(I18N.select_option) + '</option>' + arr.map((g) => '<option value="' + esc(g.id) + '">' + esc(g.name) + '</option>').join('');
      if (!sel.value) {
        const first = Array.from(sel.options).find((o) => (o.value || '').trim() !== '');
        if (first) sel.value = first.value;
      }
    } catch (e) {
      const statusBox = $('#status');
      if (statusBox) statusBox.innerHTML = '<div class="alert alert-warning py-2 mb-0 text-center">⚠️ ' + esc(I18N.cannot_load_groups) + '</div>';
    }
  }

  async function refreshStatus(){
    const groupVal = $('#group')?.value || '';
    if (!groupVal) { paintAllPending(); return; }
    try {
      const data = await postJson(statusUrl, {action:'get_status', question_group_b64: groupVal});
      const st = data.status || {};
      const toBool = (v) => v === true || v === 1 || v === '1' || (typeof v === 'string' && v.length > 0 && v.charCodeAt(0) === 1);
      S.lastStatus = {resumo_ok: toBool(st.resumo_ok), doc_ok: toBool(st.doc_ok), apres_ok: toBool(st.apres_ok)};
      renderStatusCell($('#stResumo'), S.lastStatus.resumo_ok, 'icon_transparente_1_2.png', 'icon_transparente_1_1.png', buildDownloadUrl('resumo'), I18N.open_summary);
      renderStatusCell($('#stDoc'), S.lastStatus.doc_ok, 'icon_transparente_2_2.png', 'icon_transparente_2_1.png', buildDownloadUrl('doc'), I18N.open_doc);
      renderStatusCell($('#stApre'), S.lastStatus.apres_ok, 'icon_transparente_3_2.png', 'icon_transparente_3_1.png');
      updateButtonsByStatus();
    } catch (e) {
      paintAllPending();
    }
  }

  function openDownloadOrWarn(type){
    const url = buildDownloadUrl(type);
    if (!url) {
      alert(type === 'resumo' ? I18N.select_group_and_priority : I18N.select_group_first);
      return;
    }
    if (type === 'doc' && !(S.lastStatus && S.lastStatus.resumo_ok)) {
      alert(I18N.doc_not_available);
      return;
    }
    if (type === 'apres' && !(S.lastStatus && S.lastStatus.doc_ok)) {
      alert(I18N.presentation_not_available);
      return;
    }
    window.open(url, '_blank', 'noopener');
  }

  async function onExecGrupo(){
    const groupVal = $('#group')?.value || '';
    const prioId = ($('#priority_group')?.value || '').trim();
    if (!groupVal) return alert(I18N.select_group);
    if (!prioId) return alert(I18N.select_priority_group);
    setLoading(true, I18N.executing_group);
    try {
      await postJson(apiUrl, {action:'exec_priority_group', question_group_b64: groupVal, priority_group_id: prioId});
      const box = $('#status');
      if (box) box.innerHTML = '<div class="alert alert-success py-2 mb-0 text-center">' + esc(I18N.executed_successfully) + '</div>';
      await refreshStatus();
    } catch (e) {
      const box = $('#status');
      if (box) box.innerHTML = '<div class="alert alert-danger py-2 mb-0 text-center">⚠️ ' + esc(e.message || e) + '</div>';
    } finally {
      setLoading(false);
      updateButtonsByStatus();
    }
  }

  async function onCreateDocFinal(){
    const groupVal = $('#group')?.value || '';
    if (!groupVal) return alert(I18N.select_group_first);
    if (!(S.lastStatus && S.lastStatus.resumo_ok)) return alert(I18N.summary_not_available);
    setLoading(true, I18N.generating_final_doc);
    try {
      await postJson(apiUrl, {action:'create_doc_final_consultoria', question_group_b64: groupVal});
      await refreshStatus();
      openDownloadOrWarn('doc');
    } catch (e) {
      alert(e.message || String(e));
    } finally {
      setLoading(false);
      updateButtonsByStatus();
    }
  }

  S.rehydrate = async function(){
    await loadPriorityGroups();
    const sel = $('#group');
    if (sel && !sel.value) {
      const first = Array.from(sel.options).find((o) => (o.value || '').trim() !== '');
      if (first) sel.value = first.value;
    }
    await refreshStatus();
  };

  if (!S.bound) {
    S.bound = true;
    document.addEventListener('click', (ev) => {
      const btn = ev.target && ev.target.closest ? ev.target.closest('button') : null;
      if (!btn) return;
      if (btn.id === 'btnExecIA') { ev.preventDefault(); onExecGrupo(); }
      if (btn.id === 'btnDocFinal') { ev.preventDefault(); onCreateDocFinal(); }
      if (btn.id === 'btnPptFinal') { ev.preventDefault(); openDownloadOrWarn('apres'); }
    });
    document.addEventListener('change', (ev) => {
      const el = ev.target;
      if (el && (el.id === 'group' || el.id === 'priority_group')) refreshStatus();
    });
    window.addEventListener('pageshow', () => { if (S.rehydrate) S.rehydrate(); });
    document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'visible' && S.rehydrate) S.rehydrate(); });
  }

  S.rehydrate();
})();
</script>
