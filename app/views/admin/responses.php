<?php declare(strict_types=1); ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

<style>
  .dashboard-stage{max-width:none;width:100%}
  .dashboard-stage .card{border:none;border-radius:14px;box-shadow:0 4px 16px rgba(15,23,42,.06)}
  .dashboard-stage .btn{border-radius:999px}
  .dashboard-stage .muted{color:#6b7280;font-size:.85rem}
  .dashboard-stage .chart-card{height:380px}
  .dashboard-stage .chart-wrap{height:280px;position:relative;overflow:hidden}
  .dashboard-stage .chart-wrap canvas{height:280px !important;max-height:280px !important}
  .dashboard-stage .chart-card .card-body{display:flex;flex-direction:column}
  .dashboard-stage .toolbar{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
  .dashboard-stage .toolbar .spacer{flex:1 1 auto}
</style>

<div class="container-fluid py-2 dashboard-stage">
  <div class="row justify-content-center">
    <div class="col-12 col-xxl-11">
      <div class="d-flex align-items-center gap-2 mb-3">
        <h1 class="h5 mb-0">Dashboard — Respostas</h1>
      </div>

      <div class="card mb-3">
        <div class="card-body">
          <div class="toolbar">
            <div style="min-width:320px;">
              <label class="form-label mb-1">Empresa</label>
              <select class="form-select" id="companySelect">
                <option value="">(todas)</option>
                <?php foreach (($companies ?? []) as $company): $company = (string)$company; ?>
                  <option value="<?= h($company) ?>" <?= (($companyFilter ?? '') === $company) ? 'selected' : '' ?>><?= h($company) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="spacer"></div>
          </div>

          <div class="row g-3 mt-1">
            <div class="col-12 col-md-3">
              <div class="card"><div class="card-body"><div class="muted">Sessões</div><div class="fs-4 fw-semibold"><?= (int)($kpi_total_sessions ?? 0) ?></div></div></div>
            </div>
            <div class="col-12 col-md-3">
              <div class="card"><div class="card-body"><div class="muted">Empresas</div><div class="fs-4 fw-semibold"><?= (int)($kpi_companies ?? 0) ?></div></div></div>
            </div>
            <div class="col-12 col-md-3">
              <div class="card"><div class="card-body"><div class="muted">Última sessão</div><div class="fw-semibold" style="line-height:1.1;"><?= h((string)($kpi_last_label ?? '-')) ?></div></div></div>
            </div>
            <div class="col-12 col-md-3">
              <div class="card"><div class="card-body"><div class="muted">Completude (última)</div><div class="fs-4 fw-semibold"><?= h((string)($kpi_last_completeness ?? '0')) ?>%</div></div></div>
            </div>
          </div>
        </div>
      </div>

      <div class="row g-3">
        <div class="col-12 col-xl-6">
          <div class="card chart-card"><div class="card-body"><div class="fw-semibold mb-2">Completude do questionário (%)</div><div class="chart-wrap"><canvas id="chCompleteness"></canvas></div><div class="muted mt-2">% de perguntas respondidas vs total de campos (exclui title/subtitle).</div></div></div>
        </div>
        <div class="col-12 col-xl-6">
          <div class="card chart-card"><div class="card-body"><div class="fw-semibold mb-2">Total de empregados (num_total_prev)</div><div class="chart-wrap"><canvas id="chEmployees"></canvas></div><div class="muted mt-2">Mostra o valor informado por sessão (quando numérico).</div></div></div>
        </div>
        <div class="col-12 col-xl-6">
          <div class="card chart-card"><div class="card-body"><div class="fw-semibold mb-2">Riscos por nível (contagem de campos riesgo_*)</div><div class="chart-wrap"><canvas id="chRisks"></canvas></div><div class="muted mt-2">Conta quantos riscos foram marcados como Bajo/Medio/Alto em cada sessão.</div></div></div>
        </div>
        <div class="col-12 col-xl-6">
          <div class="card chart-card"><div class="card-body"><div class="fw-semibold mb-2">Adoção tecnológica (itens marcados em *_tiene)</div><div class="chart-wrap"><canvas id="chTech"></canvas></div><div class="muted mt-2">Soma itens selecionados nos campos de múltipla escolha “tiene”.</div></div></div>
        </div>
      </div>

      <div class="card mt-3">
        <div class="card-body">
          <div class="fw-semibold mb-2">Sessões encontradas</div>
          <?php if (empty($sessRows)): ?>
            <div class="alert alert-warning mb-0">Nenhuma sessão encontrada para este filtro.</div>
          <?php else: ?>
            <div class="table-responsive" style="max-height:45vh; overflow:auto;">
              <table class="table table-sm table-striped align-middle mb-0">
                <thead><tr><th>Sessão (min)</th><th>Empresa</th><th># Registros</th></tr></thead>
                <tbody>
                <?php foreach ($sessRows as $row): ?>
                  <tr>
                    <td><?= h((new DateTime((string)$row['sess']))->format('d/m/Y H:i')) ?></td>
                    <td><?= h((string)$row['company_name']) ?></td>
                    <td><?= (int)$row['n'] ?></td>
                  </tr>
                <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
(function(){
  const labels = <?= json_encode($seriesLabels ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  const completeness = <?= json_encode($seriesCompleteness ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  const employees = <?= json_encode($seriesEmployees ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  const riskBajo = <?= json_encode(($riskBuckets['Bajo'] ?? []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  const riskMedio = <?= json_encode(($riskBuckets['Medio'] ?? []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  const riskAlto = <?= json_encode(($riskBuckets['Alto'] ?? []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  const tech = <?= json_encode($techCounts ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

  function clampSeries(max){
    if(labels.length <= max) return {labels, completeness, employees, riskBajo, riskMedio, riskAlto, tech};
    const start = labels.length - max;
    return {
      labels: labels.slice(start),
      completeness: completeness.slice(start),
      employees: employees.slice(start),
      riskBajo: riskBajo.slice(start),
      riskMedio: riskMedio.slice(start),
      riskAlto: riskAlto.slice(start),
      tech: tech.slice(start)
    };
  }

  const S = clampSeries(20);
  const commonOptions = {
    responsive:true,
    maintainAspectRatio:false,
    resizeDelay:200,
    animation:false,
    plugins:{ legend:{ position:'bottom' } },
    scales:{ x:{ ticks:{ maxRotation:0, autoSkip:true, maxTicksLimit:6 } } }
  };

  function resetChart(id){
    const el = document.getElementById(id);
    if(!el || !window.Chart) return null;

    const existing = Chart.getChart(el);
    if(existing) {
      existing.destroy();
    }

    return el;
  }

  function buildLine(id, data, label, extra={}){
    const el = resetChart(id); if(!el) return;
    new Chart(el, {type:'line', data:{labels:S.labels, datasets:[{label, data, tension:0.25}]}, options:Object.assign({}, commonOptions, extra)});
  }
  function buildBar(id, datasets, extra={}){
    const el = resetChart(id); if(!el) return;
    new Chart(el, {type:'bar', data:{labels:S.labels, datasets}, options:Object.assign({}, commonOptions, extra)});
  }

  buildLine('chCompleteness', S.completeness, 'Completude (%)', {scales:{...commonOptions.scales, y:{beginAtZero:true,max:100}}});
  buildBar('chEmployees', [{label:'Total empregados', data:S.employees}], {plugins:{...commonOptions.plugins, legend:{display:false}}, scales:{...commonOptions.scales, y:{beginAtZero:true}}});
  buildBar('chRisks', [
    {label:'Bajo', data:S.riskBajo, stack:'r'},
    {label:'Medio', data:S.riskMedio, stack:'r'},
    {label:'Alto', data:S.riskAlto, stack:'r'}
  ], {scales:{...commonOptions.scales, y:{beginAtZero:true}}});
  buildLine('chTech', S.tech, 'Itens de tecnologia (tiene)', {plugins:{...commonOptions.plugins, legend:{display:false}}, scales:{...commonOptions.scales, y:{beginAtZero:true}}});

  document.getElementById('companySelect')?.addEventListener('change', function(){
    const value = this.value || '';
    const target = <?= json_encode(url('admin/responses.php')) ?> + '?company=' + encodeURIComponent(value);
    if (window.parent && typeof window.parent.loadPageIntoContent === 'function') {
      window.parent.loadPageIntoContent(target);
      return;
    }
    location.href = target;
  });
})();
</script>
