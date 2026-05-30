<?php require __DIR__ . '/_toolbar.php'; ?>
<?php
$currentRow = is_array($damodaramRow ?? null) ? $damodaramRow : [];
$industry = (string)($damodaramIndustry ?? '');
$year = (int)($damodaramYear ?? 2024);
$chartConfigs = [
  'spread' => [
    'type' => 'bar',
    'data' => ['labels' => ['ROE','Cost of Equity','ROE - COE','ROC','Cost of Capital','ROC - WACC'], 'datasets' => [[ 'label' => $industry, 'data' => [(float)($currentRow['roe'] ?? 0),(float)($currentRow['cost_of_equity'] ?? 0),(float)($currentRow['roe_minus_coe'] ?? 0),(float)($currentRow['roc'] ?? 0),(float)($currentRow['cost_of_capital'] ?? 0),(float)($currentRow['roc_minus_wacc'] ?? 0)] ]]],
    'options' => ['responsive'=>true,'maintainAspectRatio'=>false,'plugins'=>['legend'=>['display'=>false]]],
  ],
  'value' => [
    'type' => 'bar',
    'data' => ['labels' => ['Equity EVA','EVA','BV Equity','BV Capital'], 'datasets' => [[ 'label' => $industry, 'data' => [(float)($currentRow['equity_eva'] ?? 0),(float)($currentRow['eva'] ?? 0),(float)($currentRow['book_value_equity'] ?? 0),(float)($currentRow['book_value_capital'] ?? 0)] ]]],
    'options' => ['responsive'=>true,'maintainAspectRatio'=>false,'plugins'=>['legend'=>['display'=>false]]],
  ],
];
?>
<article class="module-card dam-card"><h2 class="dam-title">Damodaran BI · Profitability</h2>
<div class="dam-meta"><span class="dam-chip">Indústria: <?= h($industry) ?></span><span class="dam-chip">Ano: <?= h((string)$year) ?></span></div>
<div class="dam-chart-shell" data-dam-chart="profitability" data-chart-configs='<?= h((string)json_encode($chartConfigs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>'>
<div class="dam-chart-top"><strong>Gráficos</strong><div class="dam-chart-buttons"><button type="button" class="dam-chart-btn" data-chart-key="spread">Retorno vs custo</button><button type="button" class="dam-chart-btn" data-chart-key="value">Criação de valor</button></div></div>
<div class="dam-canvas-wrap"><canvas></canvas></div></div>
<div class="dam-table-wrap"><table class="dam-table"><?php if (!empty($currentRow)): ?><thead><tr><?php foreach (array_keys($currentRow) as $col): ?><th><?= h((string)$col) ?></th><?php endforeach; ?></tr></thead><tbody><tr><?php foreach ($currentRow as $value): ?><td><?= h((string)$value) ?></td><?php endforeach; ?></tr></tbody><?php else: ?><tbody><tr><td>Sem dados.</td></tr></tbody><?php endif; ?></table></div>
</article>
<script>(function(){const root=document.querySelector('[data-dam-chart="profitability"]');if(!root||!window.Chart)return;const canvas=root.querySelector('canvas');const buttons=[...root.querySelectorAll('[data-chart-key]')];const configs=JSON.parse(root.getAttribute('data-chart-configs')||'{}');let chart=null;function render(key){const cfg=configs[key];if(!cfg||!canvas)return;buttons.forEach(b=>b.classList.toggle('is-active',b.getAttribute('data-chart-key')===key));if(chart)chart.destroy();chart=new Chart(canvas.getContext('2d'),cfg);}buttons.forEach(b=>b.addEventListener('click',()=>render(b.getAttribute('data-chart-key'))));if(buttons[0])render(buttons[0].getAttribute('data-chart-key'));})();</script>
