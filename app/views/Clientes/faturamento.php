<?php
/**
 * Dashboard de faturamento por pais e moeda.
 *
 * Variaveis:
 *   $paises, $ganhoClientes, $mesAtualRotulo
 */

$paises = is_array($paises ?? null) ? $paises : [];
$ganhoClientes = is_array($ganhoClientes ?? null) ? $ganhoClientes : [];

$money = static function (float $value, string $symbol, string $code): string {
    $decimals = strtoupper($code) === 'COP' ? 0 : 2;
    return trim($symbol . ' ' . number_format($value, $decimals, ',', '.') . ' ' . $code);
};

$maxClientes = max(array_map(static fn ($row) => (int) $row['clientes'], $ganhoClientes ?: [['clientes' => 0]]));
?>

<style>
.fat-country { margin-bottom:22px; }
.fat-filter { border:1px solid #e5e7eb; border-radius:8px; padding:14px; background:#fff; margin-bottom:18px; display:flex; justify-content:space-between; align-items:end; gap:14px; }
.fat-filter label { display:block; font-size:11px; text-transform:uppercase; letter-spacing:.6px; color:#64748b; font-weight:700; margin-bottom:6px; }
.fat-filter select { min-width:240px; height:38px; border:1px solid #d1d5db; border-radius:6px; padding:0 10px; background:#fff; color:#111827; font-size:14px; }
.fat-filter__currency { color:#111827; font-size:22px; font-weight:850; text-align:right; }
.fat-filter__hint { color:#64748b; font-size:12px; margin-top:2px; }
.fat-country__head { display:flex; justify-content:space-between; align-items:flex-start; gap:12px; margin-bottom:12px; }
.fat-country__title { margin:0; font-size:18px; font-weight:850; color:#111827; }
.fat-country__meta { color:#64748b; font-size:12px; margin-top:3px; }
.fat-grid { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:14px; margin-bottom:18px; }
.fat-kpi { border:1px solid #e5e7eb; border-radius:8px; padding:14px; background:#fff; min-width:0; }
.fat-kpi__label { font-size:11px; text-transform:uppercase; letter-spacing:.6px; color:#64748b; font-weight:700; }
.fat-kpi__value { font-size:23px; font-weight:800; color:#111827; margin-top:6px; overflow-wrap:anywhere; }
.fat-kpi__hint { font-size:12px; color:#64748b; margin-top:4px; }
.fat-panel { border:1px solid #e5e7eb; border-radius:8px; padding:16px; background:#fff; margin-bottom:18px; }
.fat-panel__title { font-size:14px; font-weight:800; color:#111827; margin-bottom:12px; display:flex; justify-content:space-between; gap:12px; }
.fat-layout { display:grid; grid-template-columns:1fr 1fr; gap:18px; }
.fat-plan { margin-bottom:14px; }
.fat-plan__head { display:flex; justify-content:space-between; font-size:13px; margin-bottom:6px; gap:10px; }
.fat-bar { height:10px; background:#eef2ff; border-radius:999px; overflow:hidden; }
.fat-bar span { display:block; height:100%; border-radius:999px; background:#2563eb; }
.fat-plan__meta { display:flex; justify-content:space-between; gap:10px; margin-top:4px; font-size:12px; color:#64748b; }
.fat-chart { display:flex; align-items:end; gap:10px; height:220px; padding:12px 4px 0; border-bottom:1px solid #e5e7eb; overflow-x:auto; }
.fat-chart__item { flex:1; min-width:34px; display:flex; flex-direction:column; align-items:center; justify-content:flex-end; height:100%; }
.fat-chart__bar { width:100%; max-width:42px; min-height:4px; border-radius:6px 6px 0 0; background:linear-gradient(180deg,#22c55e,#16a34a); }
.fat-chart__bar--planned { background:linear-gradient(180deg,#60a5fa,#2563eb); }
.fat-chart__value { font-size:11px; color:#475569; margin-bottom:4px; white-space:nowrap; }
.fat-chart__label { font-size:10px; color:#64748b; margin-top:6px; transform:rotate(-35deg); transform-origin:center top; white-space:nowrap; height:34px; }
.fat-table { width:100%; border-collapse:collapse; font-size:13px; }
.fat-table th { text-align:left; padding:9px 10px; color:#64748b; border-bottom:2px solid #e5e7eb; font-size:11px; text-transform:uppercase; letter-spacing:.6px; }
.fat-table td { padding:10px; border-bottom:1px solid #f1f5f9; }
.fat-pill { display:inline-flex; border-radius:999px; padding:2px 8px; background:#dbeafe; color:#1d4ed8; font-weight:700; font-size:11px; white-space:nowrap; }
.fat-empty { color:#64748b; margin:0; }
@media (max-width: 1080px) {
    .fat-grid { grid-template-columns:1fr 1fr; }
    .fat-layout { grid-template-columns:1fr; }
}
@media (max-width: 640px) {
    .fat-grid { grid-template-columns:1fr; }
    .fat-filter { display:block; }
    .fat-filter select { width:100%; min-width:0; }
    .fat-filter__currency { text-align:left; margin-top:10px; }
    .fat-country__head { display:block; }
}
</style>

<?php if (empty($paises)): ?>
<section class="fat-panel">
    <p class="fat-empty">Ainda nao ha clientes ativos para compor o faturamento.</p>
</section>
<?php endif; ?>

<?php if (!empty($paises)): ?>
<section class="fat-filter">
    <div>
        <label for="fat-country-select">Pais</label>
        <select id="fat-country-select">
            <?php foreach ($paises as $index => $pais): ?>
                <option
                    value="<?= h((string) $pais['pais_codigo']) ?>"
                    data-currency-code="<?= h((string) $pais['currency_code']) ?>"
                    data-currency-symbol="<?= h((string) $pais['currency_symbol']) ?>"
                    <?= $index === 0 ? 'selected' : '' ?>
                >
                    <?= h((string) $pais['pais_nome']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <div class="fat-filter__currency" id="fat-selected-currency"></div>
        <div class="fat-filter__hint">Os indicadores e graficos usam a moeda fixa do pais selecionado.</div>
    </div>
</section>
<?php endif; ?>

<?php foreach ($paises as $pais): ?>
    <?php
    $symbol = (string) ($pais['currency_symbol'] ?? 'R$');
    $code = (string) ($pais['currency_code'] ?? 'BRL');
    $planos = is_array($pais['planos'] ?? null) ? $pais['planos'] : [];
    $planejados = is_array($pais['planejados'] ?? null) ? $pais['planejados'] : [];
    $maxPlanejado = max(array_map(static fn ($row) => (float) $row['valor'], $planejados ?: [['valor' => 0]]));
    ?>
    <section class="fat-country" data-country="<?= h((string) $pais['pais_codigo']) ?>">
        <div class="fat-country__head">
            <div>
                <h2 class="fat-country__title"><?= h((string) $pais['pais_nome']) ?></h2>
                <div class="fat-country__meta">Moeda fixa: <?= h($code) ?> | Pais: <?= h((string) $pais['pais_codigo']) ?></div>
            </div>
            <span class="fat-pill"><?= h((string) $pais['clientes']) ?> clientes</span>
        </div>

        <div class="fat-grid">
            <section class="fat-kpi">
                <div class="fat-kpi__label">Clientes ativos</div>
                <div class="fat-kpi__value"><?= h((string) $pais['clientes']) ?></div>
                <div class="fat-kpi__hint">Base atual em faturamento</div>
            </section>
            <section class="fat-kpi">
                <div class="fat-kpi__label">Receita mensal contratada</div>
                <div class="fat-kpi__value"><?= h($money((float) $pais['receita_mensal'], $symbol, $code)) ?></div>
                <div class="fat-kpi__hint">Soma dos planos ativos</div>
            </section>
            <section class="fat-kpi">
                <div class="fat-kpi__label">Real do mes <?= h((string) $mesAtualRotulo) ?></div>
                <div class="fat-kpi__value"><?= h($money((float) $pais['faturamento_real_mes'], $symbol, $code)) ?></div>
                <div class="fat-kpi__hint">Pagamentos aprovados</div>
            </section>
            <section class="fat-kpi">
                <div class="fat-kpi__label">Planejado futuro</div>
                <div class="fat-kpi__value"><?= h($money((float) $pais['planejado_total'], $symbol, $code)) ?></div>
                <div class="fat-kpi__hint">Proximos meses agendados</div>
            </section>
        </div>

        <div class="fat-layout">
            <section class="fat-panel">
                <div class="fat-panel__title">
                    <span>Distribuicao por plano</span>
                    <span class="fat-pill"><?= h($code) ?></span>
                </div>
                <?php if (empty($planos)): ?>
                    <p class="fat-empty">Ainda nao ha clientes ativos.</p>
                <?php endif; ?>
                <?php foreach ($planos as $plano): ?>
                    <div class="fat-plan">
                        <div class="fat-plan__head">
                            <strong><?= h((string) $plano['nome']) ?></strong>
                            <span><?= h(number_format((float) $plano['percentual_clientes'], 1, ',', '.')) ?>%</span>
                        </div>
                        <div class="fat-bar"><span style="width:<?= h((string) max(2, (float) $plano['percentual_clientes'])) ?>%"></span></div>
                        <div class="fat-plan__meta">
                            <span><?= h((string) $plano['clientes']) ?> clientes</span>
                            <span><?= h($money((float) $plano['receita_mensal'], $symbol, $code)) ?> / <?= h(number_format((float) $plano['percentual_receita'], 1, ',', '.')) ?>% receita</span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </section>

            <section class="fat-panel">
                <div class="fat-panel__title">
                    <span>Faturamento planejado</span>
                    <span class="fat-pill"><?= h((string) count($planejados)) ?> meses</span>
                </div>
                <div class="fat-chart">
                    <?php foreach ($planejados as $row): ?>
                        <?php $height = $maxPlanejado > 0 ? max(4, ((float) $row['valor'] / $maxPlanejado) * 170) : 4; ?>
                        <div class="fat-chart__item">
                            <div class="fat-chart__value"><?= h($money((float) $row['valor'], $symbol, $code)) ?></div>
                            <div class="fat-chart__bar fat-chart__bar--planned" style="height:<?= h((string) $height) ?>px"></div>
                            <div class="fat-chart__label"><?= h((string) $row['rotulo']) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        </div>

        <section class="fat-panel">
            <div class="fat-panel__title">Detalhe do planejado</div>
            <table class="fat-table">
                <thead>
                    <tr>
                        <th>Mes</th>
                        <th>Pagamentos</th>
                        <th>Valor planejado</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($planejados as $row): ?>
                    <tr>
                        <td><?= h((string) $row['rotulo']) ?></td>
                        <td><?= h((string) $row['quantidade']) ?></td>
                        <td><?= h($money((float) $row['valor'], $symbol, $code)) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($planejados)): ?>
                    <tr><td colspan="3">Nao ha pagamentos planejados futuros para este pais.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </section>
    </section>
<?php endforeach; ?>

<section class="fat-panel">
    <div class="fat-panel__title">
        <span>Ganho de clientes YTD</span>
        <span class="fat-pill">Desde janeiro</span>
    </div>
    <div class="fat-chart">
        <?php foreach ($ganhoClientes as $row): ?>
            <?php $height = $maxClientes > 0 ? max(4, ((int) $row['clientes'] / $maxClientes) * 170) : 4; ?>
            <div class="fat-chart__item">
                <div class="fat-chart__value"><?= h((string) $row['clientes']) ?></div>
                <div class="fat-chart__bar" style="height:<?= h((string) $height) ?>px"></div>
                <div class="fat-chart__label"><?= h((string) $row['rotulo']) ?></div>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<script>
(function () {
    const select = document.getElementById('fat-country-select');
    const currency = document.getElementById('fat-selected-currency');
    const sections = Array.from(document.querySelectorAll('.fat-country[data-country]'));
    if (!select || sections.length === 0) return;

    function applyCountry() {
        const option = select.options[select.selectedIndex];
        const selected = select.value;
        const code = option ? option.dataset.currencyCode : '';
        const symbol = option ? option.dataset.currencySymbol : '';

        sections.forEach(function (section) {
            section.style.display = section.dataset.country === selected ? '' : 'none';
        });

        if (currency) {
            currency.textContent = symbol && code ? symbol + ' ' + code : code;
        }
    }

    select.addEventListener('change', applyCountry);
    applyCountry();
})();
</script>
