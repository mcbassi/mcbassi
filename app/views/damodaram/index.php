<?php require __DIR__ . '/_toolbar.php'; ?>
<?php
$currentRow = is_array($damodaramRow ?? null) ? $damodaramRow : [];
$historyRows = is_array($historyRows ?? null) ? $historyRows : [];
$peerRows = is_array($peerRows ?? null) ? $peerRows : [];
$industry = (string)($damodaramIndustry ?? '');
$year = (int)($damodaramYear ?? 2024);
$cards = [
  'number_of_firms' => 'Number of firms',
  'roc_minus_wacc' => 'ROC - WACC',
  'eva' => 'EVA',
  'book_value_capital' => 'BV of Capital',
  'accounts_receivable_sales' => 'Acc Rec / Sales',
  'inventory_sales' => 'Inventory / Sales',
  'accounts_payable_sales' => 'Acc Pay / Sales',
  'non_cash_working_capital_sales' => 'Non-cash WC / Sales',
];
$historyYears = array_column($historyRows, 'asof_year');
$historyRoc = array_map(static fn($r) => (float)($r['roc_minus_wacc'] ?? 0), $historyRows);
$historyEva = array_map(static fn($r) => (float)($r['eva'] ?? 0), $historyRows);
$chartConfigs = [
  'value' => [
    'type' => 'bar',
    'data' => [
      'labels' => ['ROC - WACC', 'EVA', 'BV of Capital'],
      'datasets' => [[ 'label' => $industry, 'data' => [(float)($currentRow['roc_minus_wacc'] ?? 0), (float)($currentRow['eva'] ?? 0), (float)($currentRow['book_value_capital'] ?? 0)] ]],
    ],
    'options' => ['responsive' => true, 'maintainAspectRatio' => false, 'plugins' => ['legend' => ['display' => false]]],
  ],
  'wc' => [
    'type' => 'radar',
    'data' => [
      'labels' => ['Acc Rec / Sales', 'Inventory / Sales', 'Acc Pay / Sales', 'Non-cash WC / Sales'],
      'datasets' => [[ 'label' => $industry, 'data' => [(float)($currentRow['accounts_receivable_sales'] ?? 0), (float)($currentRow['inventory_sales'] ?? 0), (float)($currentRow['accounts_payable_sales'] ?? 0), (float)($currentRow['non_cash_working_capital_sales'] ?? 0)] ]],
    ],
    'options' => ['responsive' => true, 'maintainAspectRatio' => false],
  ],
  'history' => [
    'type' => 'bar',
    'data' => [
      'labels' => $historyYears,
      'datasets' => [
        ['type' => 'line', 'label' => 'ROC - WACC', 'data' => $historyRoc, 'yAxisID' => 'y'],
        ['type' => 'bar', 'label' => 'EVA', 'data' => $historyEva, 'yAxisID' => 'y1'],
      ],
    ],
    'options' => ['responsive' => true, 'maintainAspectRatio' => false, 'scales' => ['y' => ['position' => 'left'], 'y1' => ['position' => 'right', 'grid' => ['drawOnChartArea' => false]]]],
  ],
];
?>
<article class="module-card dam-card"><h2 class="dam-title">Damodaran BI · Overview</h2>
<div class="dam-meta"><span class="dam-chip">Indústria: <?= h($industry) ?></span><span class="dam-chip">Ano: <?= h((string)$year) ?></span></div>
<div class="dam-grid" style="margin-bottom:14px">
<?php foreach ($cards as $key => $label): ?><div class="dam-metric"><div class="dam-metric__label"><?= h($label) ?></div><div class="dam-metric__value"><?= h(isset($currentRow[$key]) ? (string)$currentRow[$key] : '') ?></div></div><?php endforeach; ?>
</div>
<div class="dam-chart-shell" data-dam-chart="overview" data-chart-configs='<?= h((string)json_encode($chartConfigs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>'>
<div class="dam-chart-top"><strong>Gráficos</strong><div class="dam-chart-buttons"><button type="button" class="dam-chart-btn" data-chart-key="value">Valor</button><button type="button" class="dam-chart-btn" data-chart-key="wc">Capital de Giro</button><button type="button" class="dam-chart-btn" data-chart-key="history">Histórico</button></div></div>
<div class="dam-canvas-wrap"><canvas></canvas></div></div>
<div class="dam-table-wrap"><table class="dam-table"><?php if (!empty($currentRow)): ?><thead><tr><?php foreach (array_keys($currentRow) as $col): ?><th><?= h((string)$col) ?></th><?php endforeach; ?></tr></thead><tbody><tr><?php foreach ($currentRow as $value): ?><td><?= h((string)$value) ?></td><?php endforeach; ?></tr></tbody><?php else: ?><tbody><tr><td>Sem dados.</td></tr></tbody><?php endif; ?></table></div>
</article>
<script>(function(){const root=document.querySelector('[data-dam-chart="overview"]');if(!root||!window.Chart)return;const canvas=root.querySelector('canvas');const buttons=[...root.querySelectorAll('[data-chart-key]')];const configs=JSON.parse(root.getAttribute('data-chart-configs')||'{}');let chart=null;function render(key){const cfg=configs[key];if(!cfg||!canvas)return;buttons.forEach(b=>b.classList.toggle('is-active',b.getAttribute('data-chart-key')===key));if(chart)chart.destroy();chart=new Chart(canvas.getContext('2d'),cfg);}buttons.forEach(b=>b.addEventListener('click',()=>render(b.getAttribute('data-chart-key'))));if(buttons[0])render(buttons[0].getAttribute('data-chart-key'));})();</script>
