<?php declare(strict_types=1); ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.dataTables.min.css">
<style>
  .workspace__inner:has(.wms-bi-card){max-width:none}
  .wms-bi-card{margin:0 10px 18px;border:1px solid var(--card-border);border-radius:24px;background:#fff;padding:18px 18px 22px;box-shadow:var(--shadow-panel)}
  .wms-bi-card .nav-tabs .nav-link{border-radius:12px 12px 0 0}
  .wms-bi-card .tab-content{border:1px solid #d8dee9;border-top:0;border-radius:0 0 16px 16px;padding:14px;background:#fff}
  .wms-bi-card .table-wrap{overflow-x:auto}
  .wms-bi-card .dt-container,.wms-bi-card .dataTables_wrapper{width:100%}
  .wms-bi-card canvas{max-height:360px}
  .wms-bi-card .panel-box{border:1px solid #d8dee9;border-radius:16px;padding:14px;background:#fff;height:100%}
  .wms-bi-card .display{width:100%!important}
</style>

<section class="wms-bi-card" data-wms-bi="1">
  <div class="d-flex align-items-center justify-content-between mb-2 flex-wrap gap-2">
    <h4 class="m-0">WMS - BI (statistics)</h4>
    <small class="text-muted">Views v_wms_* + Dim wmsdata_paises</small>
  </div>

  <ul class="nav nav-tabs" role="tablist">
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#wms-t1" type="button">País</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#wms-t2" type="button">Ranking + Gráfico</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#wms-t3" type="button">Comparações</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#wms-t4" type="button">Região</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#wms-t5" type="button">Comparador</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#wms-t6" type="button">Colombia vs Regiones</button></li>
  </ul>

  <div class="tab-content">
    <div class="tab-pane fade show active" id="wms-t1">
      <div class="row g-2 align-items-end mb-2">
        <div class="col-12 col-md-4">
          <label class="form-label">Filtrar país</label>
          <input id="wms-filterPais" class="form-control" placeholder="Ex: Brazil, Germany, United States">
        </div>
        <div class="col-12 col-md-3">
          <label class="form-label">Ordenar</label>
          <select id="wms-orderPais" class="form-select">
            <option value="management_avg desc">Management (desc)</option>
            <option value="management_avg asc">Management (asc)</option>
            <option value="score_management_5d desc">Score 5D (desc)</option>
            <option value="score_management_5d asc">Score 5D (asc)</option>
          </select>
        </div>
        <div class="col-12 col-md-2">
          <button class="btn btn-primary w-100" id="wms-btnPais">Consultar</button>
        </div>
      </div>
      <div class="table-wrap"><table id="wms-tblPais" class="display nowrap" style="width:100%"></table></div>
    </div>

    <div class="tab-pane fade" id="wms-t2">
      <div class="row g-2 align-items-end mb-3">
        <div class="col-12 col-md-2">
          <label class="form-label">Top N</label>
          <select id="wms-topN" class="form-select">
            <option>10</option><option>15</option><option selected>20</option><option>30</option>
          </select>
        </div>
        <div class="col-12 col-md-2">
          <button class="btn btn-primary w-100" id="wms-btnScore">Carregar</button>
        </div>
      </div>
      <div class="row g-3">
        <div class="col-12 col-xl-6"><div class="panel-box"><div class="fw-semibold mb-2">Top países por Score (5D)</div><canvas id="wms-chartTop"></canvas></div></div>
        <div class="col-12 col-xl-6"><div class="panel-box"><div class="fw-semibold mb-2">Tabela Ranking</div><div class="table-wrap"><table id="wms-tblScore" class="display nowrap" style="width:100%"></table></div></div></div>
      </div>
    </div>

    <div class="tab-pane fade" id="wms-t3">
      <div class="d-flex gap-2 mb-3 flex-wrap">
        <button class="btn btn-primary" id="wms-btnG7">G7 vs BRICS</button>
        <button class="btn btn-primary" id="wms-btnPrimeiro">1º mundo vs demais</button>
      </div>
      <div class="row g-3">
        <div class="col-12 col-xl-6"><div class="panel-box"><div class="fw-semibold mb-2">Gráfico (Management avg)</div><canvas id="wms-chartComparacao"></canvas></div></div>
        <div class="col-12 col-xl-6"><div class="panel-box"><div class="fw-semibold mb-2">Tabela</div><div class="table-wrap"><table id="wms-tblComparacao" class="display nowrap" style="width:100%"></table></div></div></div>
      </div>
    </div>

    <div class="tab-pane fade" id="wms-t4">
      <button class="btn btn-primary mb-3" id="wms-btnRegiao">Carregar por região</button>
      <div class="row g-3">
        <div class="col-12 col-xl-6"><div class="panel-box"><div class="fw-semibold mb-2">Gráfico (Score 5D aproximado)</div><canvas id="wms-chartRegiao"></canvas><div class="small text-muted mt-2">Obs: score regional calculado via média das 5 dimensões.</div></div></div>
        <div class="col-12 col-xl-6"><div class="panel-box"><div class="fw-semibold mb-2">Tabela Região</div><div class="table-wrap"><table id="wms-tblRegiao" class="display nowrap" style="width:100%"></table></div></div></div>
      </div>
    </div>

    <div class="tab-pane fade" id="wms-t5">
      <div class="row g-2 align-items-end mb-3">
        <div class="col-12 col-md-4"><label class="form-label">País A</label><select id="wms-paisA" class="form-select"></select></div>
        <div class="col-12 col-md-4"><label class="form-label">País B</label><select id="wms-paisB" class="form-select"></select></div>
        <div class="col-12 col-md-2"><button class="btn btn-primary w-100" id="wms-btnComparador">Comparar</button></div>
      </div>
      <div class="panel-box"><div class="fw-semibold mb-2">Comparação (5 dimensões)</div><canvas id="wms-chartCompare2"></canvas></div>
      <div class="mt-3 table-wrap"><table id="wms-tblCompare2" class="display nowrap" style="width:100%"></table></div>
    </div>

    <div class="tab-pane fade" id="wms-t6">
      <h5>Comparación Colombia vs Regiones</h5>
      <canvas id="wms-chartCompare"></canvas>
      <div class="table-wrap mt-3">
        <table class="table table-sm table-striped" id="wms-tableCompare">
          <thead><tr><th>Country</th><th>Management</th><th>Operations</th><th>Monitor</th><th>Target</th><th>People</th><th>N</th></tr></thead>
          <tbody></tbody>
        </table>
      </div>
    </div>
  </div>
</section>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
(function(){
  document.body.dataset.page = 'wms-bi';
  const ensure = (name, wait = 40) => new Promise((resolve, reject) => {
    let tries = 0;
    const timer = setInterval(() => {
      tries++;
      if (window[name]) { clearInterval(timer); resolve(window[name]); }
      if (tries >= wait) { clearInterval(timer); reject(new Error(name + ' não carregou.')); }
    }, 250);
  });

  Promise.all([ensure('jQuery'), ensure('Chart')]).then(init).catch(err => console.error(err));

  function init(){
    const $ = window.jQuery;
    const API_URL = <?= json_encode(url('wms/dashboard_api.php')) ?>;
    const CSRF = <?= json_encode(csrf_token()) ?>;
    let chartTop = null, chartComparacao = null, chartRegiao = null, chartCompare2 = null, chartCompare = null;

    function esc(s){ return String(s ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])); }

    function renderTable(tableId, payload){
      const elDom = document.getElementById(tableId);
      if (!elDom || !$.fn || !$.fn.DataTable) return;
      const $el = $('#' + tableId);
      if ($.fn.dataTable.isDataTable($el)) {
        $el.DataTable().clear().destroy();
      }
      const thead = '<thead><tr>' + (payload.columns || []).map(c => `<th>${esc(c)}</th>`).join('') + '</tr></thead>';
      $el.empty().append(thead);
      $el.DataTable({
        data: payload.rows || [],
        columns: (payload.columns || []).map(c => ({ title: c, data: c })),
        pageLength: 25,
        lengthMenu: [10,25,50,100],
        scrollX: true,
        destroy: true,
        autoWidth: false,
        deferRender: true,
        order: [],
        ordering: false
      });
    }

    async function api(tab, extra={}){
      const resp = await fetch(API_URL, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {'Content-Type':'application/json','X-CSRF-Token':CSRF},
        body: JSON.stringify(Object.assign({tab}, extra))
      });
      const txt = await resp.text();
      try { return JSON.parse(txt); } catch(e) { return { ok:false, error:`HTTP ${resp.status} - resposta não-JSON: ${txt.slice(0,200)}` }; }
    }

    function setSelectOptions(selId, items){
      const sel = document.getElementById(selId); if (!sel) return;
      sel.innerHTML = '';
      (items || []).forEach(it => {
        const opt = document.createElement('option');
        opt.value = it; opt.textContent = it; sel.appendChild(opt);
      });
    }

    function makeBarChart(canvasId, labels, values, title){
      const ctx = document.getElementById(canvasId);
      return new Chart(ctx, { type:'bar', data:{ labels, datasets:[{ label:title, data:values }] }, options:{ responsive:true, plugins:{ legend:{display:true} } } });
    }

    function makeRadar2(canvasId, labels, nameA, valsA, nameB, valsB){
      return new Chart(document.getElementById(canvasId), {
        type:'radar',
        data:{ labels, datasets:[{ label:nameA, data:valsA }, { label:nameB, data:valsB }] },
        options:{ responsive:true }
      });
    }

    async function loadTab(tab){
      if (tab === 'pais') {
        const data = await api('pais', {pais: document.getElementById('wms-filterPais').value || '', order: document.getElementById('wms-orderPais').value || 'management_avg desc'});
        if (!data.ok) return alert(data.error || 'Erro');
        return renderTable('wms-tblPais', data);
      }
      if (tab === 'score') {
        const data = await api('score', {topN: parseInt(document.getElementById('wms-topN').value || '20', 10)});
        if (!data.ok) return alert(data.error || 'Erro');
        renderTable('wms-tblScore', data);
        const rows = (data.rows || []); const labels = rows.map(r => r.pais); const vals = rows.map(r => Number(r.score_management_5d));
        if (chartTop) chartTop.destroy(); chartTop = makeBarChart('wms-chartTop', labels, vals, 'Score 5D'); return;
      }
      if (tab === 'g7brics' || tab === 'primeiro_mundo') {
        const data = await api(tab);
        if (!data.ok) return alert(data.error || 'Erro');
        renderTable('wms-tblComparacao', data);
        const labels = (data.rows || []).map(r => r.grupo); const vals = (data.rows || []).map(r => Number(r.management_avg));
        if (chartComparacao) chartComparacao.destroy(); chartComparacao = makeBarChart('wms-chartComparacao', labels, vals, 'Management avg'); return;
      }
      if (tab === 'regiao') {
        const data = await api('regiao');
        if (!data.ok) return alert(data.error || 'Erro');
        renderTable('wms-tblRegiao', data);
        const labels = (data.rows || []).map(r => r.regiao);
        const vals = (data.rows || []).map(r => ((Number(r.management_avg)+Number(r.operations_avg)+Number(r.monitor_avg)+Number(r.target_avg)+Number(r.people_avg))/5));
        if (chartRegiao) chartRegiao.destroy(); chartRegiao = makeBarChart('wms-chartRegiao', labels, vals, 'Score 5D (aprox)'); return;
      }
      if (tab === 'comparador') {
        const paisA = document.getElementById('wms-paisA').value; const paisB = document.getElementById('wms-paisB').value;
        const data = await api('comparador', {paisA, paisB});
        if (!data.ok) return alert(data.error || 'Erro');
        renderTable('wms-tblCompare2', data);
        const rows = data.rows || []; const a = rows.find(r => r.pais === paisA); const b = rows.find(r => r.pais === paisB); if (!a || !b) return;
        const labels = ['management','operations','monitor','target','people'];
        const valsA = [a.management_avg,a.operations_avg,a.monitor_avg,a.target_avg,a.people_avg].map(Number);
        const valsB = [b.management_avg,b.operations_avg,b.monitor_avg,b.target_avg,b.people_avg].map(Number);
        if (chartCompare2) chartCompare2.destroy(); chartCompare2 = makeRadar2('wms-chartCompare2', labels, paisA, valsA, paisB, valsB); return;
      }
      if (tab === 'colombia_compare') {
        const data = await api('colombia_compare');
        if (!data.ok) return alert(data.error || 'Erro');
        const rows = data.rows || []; const labels = rows.map(x => x.country);
        if (chartCompare) chartCompare.destroy();
        chartCompare = new Chart(document.getElementById('wms-chartCompare'), { type:'bar', data:{ labels, datasets:[
          { label:'Management', data: rows.map(x => Number(x.management)) },
          { label:'Operations', data: rows.map(x => Number(x.operations)) },
          { label:'Monitor', data: rows.map(x => Number(x.monitor)) },
          { label:'Target', data: rows.map(x => Number(x.target)) },
          { label:'People', data: rows.map(x => Number(x.people)) }
        ]}, options:{ responsive:true }});
        const tb = document.querySelector('#wms-tableCompare tbody'); tb.innerHTML = '';
        rows.forEach(r => { tb.innerHTML += `<tr><td>${esc(r.country)}</td><td>${esc(r.management)}</td><td>${esc(r.operations)}</td><td>${esc(r.monitor)}</td><td>${esc(r.target)}</td><td>${esc(r.people)}</td><td>${esc(r.N)}</td></tr>`; });
      }
    }

    document.getElementById('wms-btnPais')?.addEventListener('click', () => loadTab('pais'));
    document.getElementById('wms-btnScore')?.addEventListener('click', () => loadTab('score'));
    document.getElementById('wms-btnG7')?.addEventListener('click', () => loadTab('g7brics'));
    document.getElementById('wms-btnPrimeiro')?.addEventListener('click', () => loadTab('primeiro_mundo'));
    document.getElementById('wms-btnRegiao')?.addEventListener('click', () => loadTab('regiao'));
    document.getElementById('wms-btnComparador')?.addEventListener('click', () => loadTab('comparador'));
    document.querySelector('[data-bs-target="#wms-t6"]')?.addEventListener('click', () => loadTab('colombia_compare'));

    api('paises_list').then(list => {
      if (list.ok) {
        setSelectOptions('wms-paisA', list.paises);
        setSelectOptions('wms-paisB', list.paises);
        const b = document.getElementById('wms-paisB'); if (b && b.options.length > 1) b.selectedIndex = 1;
      }
      loadTab('pais');
    });
  }
})();
</script>
