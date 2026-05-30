window.DS_SQL_VER = 'v4.2.0'; console.log('Tela SQL DataSmart', window.DS_SQL_VER);
window.addEventListener('error', (e)=>{
  const box = document.getElementById('angleParamsBox');
  const dbg = document.getElementById('angleParamsDebug');
  if (dbg) dbg.textContent = 'JS error: ' + (e.message || 'unknown');
  if (box) box.innerHTML = '<div class="text-danger small">JS error: ' + (e.message || 'unknown') + '</div>';
});
window.addEventListener('unhandledrejection', (e)=>{
  const box = document.getElementById('angleParamsBox');
  const dbg = document.getElementById('angleParamsDebug');
  const msg = (e && e.reason && (e.reason.message||String(e.reason))) ? (e.reason.message||String(e.reason)) : 'unhandled rejection';
  if (dbg) dbg.textContent = 'Promise error: ' + msg;
  if (box) box.innerHTML = '<div class="text-danger small">Promise error: ' + msg + '</div>';
});

const API_MAP = {
  'schema': window.TELA_SQL_API.schemaUrl,
  'query/validate': window.TELA_SQL_API.validateUrl,
  'query/execute': window.TELA_SQL_API.executeUrl,
  'catalog/list': window.TELA_SQL_API.catalogListUrl,
  'catalog/get': window.TELA_SQL_API.catalogGetUrl,
  'catalog/save': window.TELA_SQL_API.catalogSaveUrl,
};
const API = (route) => API_MAP[route] || '';

let SCHEMA = null;
let ANGLE_PARAMS = {}; // {NAME: value}


function el(id){ return document.getElementById(id); }

function safeOnclick(id, fn){ const e = el(id); if(e) e.onclick = fn; }

function escapeHtml(s){
  return String(s).replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));
}

async function apiGet(route){
  const res = await fetch(API(route));
  return await res.json();
}
async function apiPost(route, body){
  const payload = Object.assign({_csrf: (window.TELA_SQL_API?.csrf || '')}, body ?? {});
  const res = await fetch(API(route), {
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body: JSON.stringify(payload)
  });
  return await res.json();
}

function renderTables(){
  const list = el('tablesList');
  list.innerHTML = '';
  const filter = (el('tableFilter').value || '').toLowerCase();

  (SCHEMA.tables || [])
    .filter(t => t.toLowerCase().includes(filter))
    .forEach(t => {
      const a = document.createElement('a');
      a.className = 'list-group-item list-group-item-action py-2';
      a.textContent = t;
      a.onclick = () => selectMainTable(t);
      list.appendChild(a);
    });
}

function fillMainTableSelect(){
  const sel = el('mainTable');
  sel.innerHTML = '';
  (SCHEMA.tables || []).forEach(t=>{
    const o = document.createElement('option');
    o.value = t; o.textContent = t;
    sel.appendChild(o);
  });
  sel.onchange = () => selectMainTable(sel.value);
}

function renderFields(table){
  const box = el('fieldsBox');
  const cols = (SCHEMA.columns && SCHEMA.columns[table]) ? SCHEMA.columns[table] : null;
  if (!cols){ box.innerHTML = '<div class="text-muted small">Sem colunas.</div>'; return; }

  box.innerHTML = cols.map(c => {
    const key = c.key ? '<span class="badge text-bg-light ms-1">' + escapeHtml(c.key) + '</span>' : '';
    return '<div class="form-check">' +
      '<input class="form-check-input fieldCheck" type="checkbox" value="' + escapeHtml(c.name) + '" id="f_' + escapeHtml(c.name) + '" checked>' +
      '<label class="form-check-label" for="f_' + escapeHtml(c.name) + '">' +
        '<span class="font-monospace">' + escapeHtml(c.name) + '</span> ' +
        '<span class="text-muted">(' + escapeHtml(c.column_type) + ')</span>' + key +
      '</label>' +
    '</div>';
  }).join('');
}

function fillFieldCombos(table){
  const cols = (SCHEMA.columns && SCHEMA.columns[table]) ? SCHEMA.columns[table] : [];
  const whereField = el('whereField');
  const orderBy = el('orderBy');
  whereField.innerHTML = '<option value="">(sem filtro)</option>';
  orderBy.innerHTML = '<option value="">(sem ORDER)</option>';
  cols.forEach(c=>{
    const o1 = document.createElement('option');
    o1.value = c.name; o1.textContent = c.name;
    whereField.appendChild(o1);

    const o2 = document.createElement('option');
    o2.value = c.name; o2.textContent = c.name;
    orderBy.appendChild(o2);
  });
}

function selectMainTable(table){
  el('mainTable').value = table;
  renderFields(table);
  fillFieldCombos(table);
}

function clearFieldSelection(){
  Array.from(document.querySelectorAll('.fieldCheck')).forEach(chk => { chk.checked = false; });
}

function buildSQLFromBuilder(){
  const table = el('mainTable').value;

  const checks = Array.from(document.querySelectorAll('.fieldCheck'));
  const fields = checks.filter(x => x.checked).map(x => '`' + x.value + '`');

  const selFields = fields.length ? fields.join(', ') : '*';

  let sql = 'SELECT ' + selFields + '\nFROM `' + table + '`';

  const wf = el('whereField').value;
  const op = el('whereOp').value;
  const wv = (el('whereVal').value || '').trim();

  if (wf){
    if (op === 'IS NULL' || op === 'IS NOT NULL'){
      sql += '\nWHERE `' + wf + '` ' + op;
    } else if (op === 'BETWEEN'){
      sql += '\nWHERE `' + wf + '` BETWEEN ' + (wv || ':a AND :b');
    } else if (op === 'IN'){
      sql += '\nWHERE `' + wf + '` IN (' + (wv || ':lista') + ')';
    } else if (op === 'LIKE'){
      sql += '\nWHERE `' + wf + '` LIKE ' + (wv || ':padrao');
    } else {
      sql += '\nWHERE `' + wf + '` ' + op + ' ' + (wv || ':valor');
    }
  }

  const ob = el('orderBy').value;
  const dir = el('orderDir').value;
  if (ob){
    sql += '\nORDER BY `' + ob + '` ' + dir;
  }

  return sql;
}

function setValidateBox(ok, errors=[], warnings=[]){
  const box = el('validateBox');
  if (ok){
    box.innerHTML = '<div class="alert alert-success py-2 mb-0"><strong>OK</strong>' +
      (warnings.length ? ('<div class="mt-1">' + warnings.map(w=>'<div>⚠️ ' + escapeHtml(w) + '</div>').join('') + '</div>') : '') +
      '</div>';
  } else {
    box.innerHTML = '<div class="alert alert-danger py-2 mb-0"><strong>Bloqueado</strong><div class="mt-1">' +
      errors.map(e=>'<div>• ' + escapeHtml(e) + '</div>').join('') +
      '</div></div>';
  }
}

async function validateSQL(){
  let sql = el('sqlEditor').value;
  const ap = applyAngleParams(sql);
  sql = ap.sql;
  const fsb = el('finalSqlBox');
  if (fsb) fsb.value = sql;
  if (ap.missing && ap.missing.length){
    el('resultBox').innerHTML = '<div class="text-danger small">Preencha os placeholders: <strong>' + escapeHtml(ap.missing.join(', ')) + '</strong></div>';
    el('runInfo').textContent = '—';
    return;
  }
  const out = await apiPost('query/validate', {sql});
  if (out.ok) setValidateBox(true, [], out.warnings||[]);
  else setValidateBox(false, out.errors||[out.error||'Erro'], out.warnings||[]);
  return out;
}



function extractAngleParams(sql){
  // aceita: <<VAR>>, << VAR >>, ‹‹VAR››, «VAR» (variações de colchetes por autocorreção)
  const regex = /(?:<<|‹‹|«\s*)(\s*[A-Za-z0-9_]+\s*)(?:>>|››|\s*»)/g;
  let match;
  const vars = [];
  while ((match = regex.exec(sql)) !== null){
    const v = String(match[1] || '').trim().toUpperCase();
    if (v && !vars.includes(v)) vars.push(v);
  }
  return vars;
}



function renderAngleParams(){
  const box = el('angleParamsBox');
  const dbg = el('angleParamsDebug');
  const se = el('sqlEditor');
  if (!box) return;
  if (!se){
    box.innerHTML = '<div class="text-danger small">Textarea #sqlEditor não encontrado nesta página.</div>';
    if (dbg) dbg.textContent = 'Detectado: (nenhum)';
    return;
  }
  const sql = se.value || '';
  let vars = [];
  try { vars = extractAngleParams(sql); } catch(e){ vars = []; }

  // garante que os detectados existam no mapa
  vars.forEach(v => { if (!Object.prototype.hasOwnProperty.call(ANGLE_PARAMS, v)) ANGLE_PARAMS[v] = ''; });

  const keys = Object.keys(ANGLE_PARAMS).sort();
  if (dbg) dbg.textContent = 'SQL chars: ' + sql.length + ' • Detectado: ' + (vars.length ? vars.join(', ') : '(nenhum)');

  if (keys.length === 0){
    box.innerHTML = '<div class="text-muted small">Nenhum placeholder definido. Use o botão "Adicionar".</div>';
    return;
  }

  box.innerHTML = keys.map(k => {
    const val = ANGLE_PARAMS[k] ?? '';
    return '<div class="mb-1">' +
      '<div class="d-flex justify-content-between align-items-center">' +
        '<label class="small font-monospace mb-0">' + escapeHtml(k) + '</label>' +
        '<button type="button" class="btn btn-outline-danger btn-sm py-0" data-del="'+ escapeHtml(k) +'">x</button>' +
      '</div>' +
      '<input class="form-control form-control-sm angleParam" data-name="' + escapeHtml(k) + '" value="' + escapeHtml(val) + '" placeholder="valor para ' + escapeHtml(k) + '">' +
    '</div>';
  }).join('');

  // bind inputs -> mapa
  document.querySelectorAll('.angleParam').forEach(inp => {
    inp.addEventListener('input', ()=>{
      const name = String(inp.dataset.name||'').trim().toUpperCase();
      ANGLE_PARAMS[name] = String(inp.value ?? '');
      // atualiza SQL final preview se existir
      const fsb = el('finalSqlBox');
      if (fsb){
        const ap = applyAngleParams(se.value || '');
        fsb.value = ap.sql;
      }
    });
  });

  // bind delete
  box.querySelectorAll('button[data-del]').forEach(btn => {
    btn.addEventListener('click', ()=>{
      const k = String(btn.getAttribute('data-del')||'').trim().toUpperCase();
      delete ANGLE_PARAMS[k];
      renderAngleParams();
    });
  });
}



function sqlLiteralFromInput(raw, autoQuote=true){
  const v = String(raw ?? '').trim();
  if (v === '') return '';
  const upper = v.toUpperCase();
  if (upper === 'NULL' || upper === 'TRUE' || upper === 'FALSE') return upper;
  if (/^[-+]?[0-9]+(\.[0-9]+)?$/.test(v)) return v;
  if ((v.startsWith("'") && v.endsWith("'")) || (v.startsWith('"') && v.endsWith('"'))) return v;
  if (!autoQuote) return v;
  return "'" + v.replace(/'/g, "''") + "'";
}

function applyAngleParams(sql){
  const auto = !!document.getElementById('autoQuoteStrings')?.checked;
  const keys = Object.keys(ANGLE_PARAMS || {});
  let missing = [];
  keys.forEach(name0 => {
    const name = String(name0||'').trim().toUpperCase();
    const raw = String(ANGLE_PARAMS[name] ?? '').trim();
    if (raw === '') missing.push(name);
    const lit = sqlLiteralFromInput(raw, auto);

    const r1 = new RegExp('<<\\s*' + name + '\\s*>>', 'gi');
    const r2 = new RegExp('‹‹\\s*' + name + '\\s*››', 'gi');
    const r3 = new RegExp('«\\s*' + name + '\\s*»', 'gi');
    sql = sql.replace(r1, lit).replace(r2, lit).replace(r3, lit);
  });

  // também detecta placeholders no SQL que ainda não estão no mapa
  const varsInSql = extractAngleParams(sql);
  varsInSql.forEach(v => { if (!Object.prototype.hasOwnProperty.call(ANGLE_PARAMS, v)) missing.push(v); });

  missing = Array.from(new Set(missing)).filter(Boolean);
  return { sql, missing };
}






async function executeSQL(){
  let sql = el('sqlEditor').value;
  const ap = applyAngleParams(sql);
  sql = ap.sql;
  const fsb = el('finalSqlBox');
  if (fsb) fsb.value = sql;
  if (ap.missing && ap.missing.length){
    el('resultBox').innerHTML = '<div class="text-danger small">Preencha os placeholders: <strong>' + escapeHtml(ap.missing.join(', ')) + '</strong></div>';
    el('runInfo').textContent = '—';
    return;
  }

  let params = {};
  const ptxt = (el('paramsEditor').value || '').trim();
  if (ptxt){
    try { params = JSON.parse(ptxt); }
    catch(e){ alert('Parâmetros JSON inválidos'); return; }
  }

  const limit = parseInt(el('previewLimit').value || '200', 10);
  const offset = parseInt(el('previewOffset').value || '0', 10);

  const out = await apiPost('query/execute', {sql, params, mode:'screen', limit, offset});
  if (!out.ok){
    setValidateBox(false, out.errors||[out.error||'Erro'], out.warnings||[]);
    el('resultBox').innerHTML = '<div class="text-danger small">' + escapeHtml(out.error || 'Erro') + '</div>' + (out.sql_run ? ('<div class="small text-muted mt-2">SQL enviado:<br><span class="font-monospace">' + escapeHtml(out.sql_run) + '</span></div>') : '');
    const fsbE = el('finalSqlBox'); if (fsbE && out.sql_run) fsbE.value = out.sql_run;
    el('runInfo').textContent = '—';
    return;
  }

  el('runInfo').textContent = out.row_count + ' linhas • ' + out.elapsed_ms + ' ms';
  const fsb2 = el('finalSqlBox');
  if (fsb2 && out.sql_run) fsb2.value = out.sql_run;
  renderResultTable(out.columns, out.rows, out.truncated, out.limit, out.offset);
}

function renderResultTable(columns, rows, truncated, limit, offset){
  const box = el('resultBox');
  if (!rows || rows.length === 0){
    box.innerHTML = '<div class="text-muted small">Sem linhas retornadas.</div>';
    return;
  }

  const head = columns.map(c=>'<th class="small">' + escapeHtml(c) + '</th>').join('');
  const body = rows.map(r=>{
    const tds = columns.map(c=>'<td class="small">' + escapeHtml(r[c]) + '</td>').join('');
    return '<tr>' + tds + '</tr>';
  }).join('');

  const note = truncated ? '<div class="small text-muted mb-2">Mostrando até ' + limit + ' linhas (offset ' + offset + ').</div>' : '';

  box.innerHTML = note +
    '<div class="table-responsive">' +
      '<table class="table table-sm table-striped align-middle">' +
        '<thead><tr>' + head + '</tr></thead>' +
        '<tbody>' + body + '</tbody>' +
      '</table>' +
    '</div>';
}

async function saveCatalog(){
  let sql = el('sqlEditor').value;
  const ap = applyAngleParams(sql);
  sql = ap.sql;
  const fsb = el('finalSqlBox');
  if (fsb) fsb.value = sql;
  if (ap.missing && ap.missing.length){
    el('resultBox').innerHTML = '<div class="text-danger small">Preencha os placeholders: <strong>' + escapeHtml(ap.missing.join(', ')) + '</strong></div>';
    el('runInfo').textContent = '—';
    return;
  }
  const slug = (el('slug').value || '').trim();
  const title = (el('title').value || '').trim();
  const description = (el('description').value || '').trim();
  const prompt_text = (el('promptEditor').value || '').trim();
  const select_desc = (el('selectDescSave').value || '').trim();

  const mainTable = el('mainTable').value;
  const tables_json = mainTable ? [mainTable] : null;

  let params_json = null;
  const ptxt = (el('paramsEditor').value || '').trim();
  if (ptxt){
    try {
      const obj = JSON.parse(ptxt);
      params_json = Object.keys(obj).map(k=>({name:k,type:'string',required:true,label:k}));
    } catch(e) {}
  }

  const out = await apiPost('catalog/save', {slug, title, description, sql, prompt_text, select_desc, tables_json, params_json});
  if (!out.ok){
    alert(out.error || (out.errors ? out.errors.join('\n') : 'Falha ao salvar'));
    return;
  }
  alert('Salvo: ' + out.slug + ' v' + out.version);
}

async function loadCatalog(){
  const out = await apiGet('catalog/list');
  if (!out.ok){ alert(out.error||'Erro'); return; }

  const list = el('catalogList');
  list.innerHTML = '';
  (out.items || []).forEach(it=>{
    const a = document.createElement('a');
    a.className = 'list-group-item list-group-item-action';
    a.innerHTML = '<div class="d-flex justify-content-between">' +
      '<div>' +
        '<div class="fw-semibold font-monospace">' + escapeHtml(it.slug) + '</div>' +
        '<div class="small">' + escapeHtml(it.title||'') + '</div>' +
        '<div class="small text-muted">' + escapeHtml(it.description||'') + '</div>' +
      '</div>' +
      '<div class="small text-muted text-end">v' + it.current_version + '<br>' + escapeHtml(it.db_name||'') + '</div>' +
    '</div>';

    a.onclick = async ()=>{
      const g = await fetch(window.TELA_SQL_API.catalogGetUrl + '?slug=' + encodeURIComponent(it.slug)).then(r=>r.json());
      if (!g.ok){ alert(g.error||'Erro'); return; }
      const ver = g.version || {};
      el('slug').value = g.query.slug || it.slug;
      el('title').value = g.query.title || it.title || '';
      el('description').value = g.query.description || it.description || '';
      el('sqlEditor').value = ver.sql_text || '';
      el('promptEditor').value = ver.prompt_text || '';
      el('selectDescSave').value = ver.select_desc || '';
      document.querySelector('[data-bs-target="#tabSQL"]').click();
      const modal = bootstrap.Modal.getInstance(el('catalogModal'));
      if (modal) modal.hide();
    };

    list.appendChild(a);
  });

  new bootstrap.Modal(el('catalogModal')).show();
}

async function init(){
  const vb = document.getElementById('dsVersionBadge');
  if (vb) vb.textContent = window.DS_SQL_VER || 'v?';
  // --- Placeholders: setup sempre, mesmo se schema falhar ---
  const se = el('sqlEditor');
  if (se){
    ['input','change','keyup','paste','focus'].forEach(ev => se.addEventListener(ev, renderAngleParams));
  }
  const brp = el('btnRefreshPlaceholders');
  if (brp) brp.addEventListener('click', (e)=>{ e.preventDefault(); renderAngleParams(); });
  const bap = el('btnAddPlaceholder');
  const apn = el('addPlaceholderName');
  if (bap && apn){
    bap.addEventListener('click', ()=>{
      const n = String(apn.value||'').trim().toUpperCase().replace(/[^A-Z0-9_]/g,'');
      if (!n) return;
      if (!Object.prototype.hasOwnProperty.call(ANGLE_PARAMS, n)) ANGLE_PARAMS[n] = '';
      apn.value='';
      renderAngleParams();
    });
  }

  // quando trocar para a aba SQL, recalcula
  document.querySelectorAll('[data-bs-toggle="tab"]').forEach(t => {
    t.addEventListener('shown.bs.tab', () => setTimeout(renderAngleParams, 50));
  });
  // primeira renderização
  setTimeout(renderAngleParams, 50);



  const sch = await apiGet('schema');
  if (!sch.ok){
    el('tablesList').innerHTML = '<div class="text-danger small">' + escapeHtml(sch.error||'Erro') + '</div>';
    /* continua com placeholders e SQL manual */
  }
  SCHEMA = sch;
  el('targetDbBadge').textContent = (sch.target_db && sch.target_db.db_name) ? sch.target_db.db_name : 'DB';

  fillMainTableSelect();
  renderTables();
  selectMainTable(el('mainTable').value);

  el('tableFilter').addEventListener('input', renderTables);
  safeOnclick('btnReloadSchema', init);

  safeOnclick('btnClearFields', clearFieldSelection);

  const __b1 = el('btnEditSelectDesc'); if(__b1) __b1.onclick = ()=>{
    const c = el('selectDescCollapse');
    const bs = bootstrap.Collapse.getOrCreateInstance(c);
    if (c.classList.contains('show')) bs.hide(); else bs.show();
    setTimeout(()=> el('selectDescBuilder').focus(), 150);
  };

  const __b2 = el('btnBuildToSQL'); if(__b2) __b2.onclick = ()=>{
    const sql = buildSQLFromBuilder();
    el('sqlEditor').value = sql;
    el('selectDescSave').value = (el('selectDescBuilder').value || '').trim().slice(0,80);
    document.querySelector('[data-bs-target="#tabSQL"]').click();
  };

  safeOnclick('btnValidate', async ()=>{ await validateSQL(); });

  const __b3 = el('btnExecute'); if(__b3) __b3.onclick = async ()=>{
    const v = await validateSQL();
    if (v.ok) await executeSQL();
  };

  safeOnclick('btnSave', saveCatalog);
  safeOnclick('btnLoadCatalog', loadCatalog);

  el('catalogSearch').addEventListener('input', async (e)=>{
    const s = (e.target.value || '').trim();
    const res = await fetch(window.TELA_SQL_API.catalogListUrl + '?search=' + encodeURIComponent(s) + '&active=1');
    const out = await res.json();
    if (!out.ok) return;

    const list = el('catalogList');
    list.innerHTML = '';
    (out.items || []).forEach(it=>{
      const a = document.createElement('a');
      a.className = 'list-group-item list-group-item-action';
      a.innerHTML = '<div class="d-flex justify-content-between">' +
        '<div>' +
          '<div class="fw-semibold font-monospace">' + escapeHtml(it.slug) + '</div>' +
          '<div class="small">' + escapeHtml(it.title||'') + '</div>' +
          '<div class="small text-muted">' + escapeHtml(it.description||'') + '</div>' +
        '</div>' +
        '<div class="small text-muted text-end">v' + it.current_version + '<br>' + escapeHtml(it.db_name||'') + '</div>' +
      '</div>';

      a.onclick = async ()=>{
        const g = await fetch(window.TELA_SQL_API.catalogGetUrl + '?slug=' + encodeURIComponent(it.slug)).then(r=>r.json());
        if (!g.ok){ alert(g.error||'Erro'); return; }
        const ver = g.version || {};
        el('slug').value = g.query.slug || it.slug;
        el('title').value = g.query.title || it.title || '';
        el('description').value = g.query.description || it.description || '';
        el('sqlEditor').value = ver.sql_text || '';
        el('promptEditor').value = ver.prompt_text || '';
        el('selectDescSave').value = ver.select_desc || '';
        document.querySelector('[data-bs-target="#tabSQL"]').click();
        const modal = bootstrap.Modal.getInstance(el('catalogModal'));
        if (modal) modal.hide();
      };

      list.appendChild(a);
    });
  });
}

window.addEventListener("DOMContentLoaded", () => { init(); });
