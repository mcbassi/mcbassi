<?php
/**
 * Tela de manutencao dos planos de venda por pais.
 *
 * Variaveis:
 *   $documentos, $planos, $resultado, $erro
 */

$documentos = is_array($documentos ?? null) ? $documentos : [];
$planos = is_array($planos ?? null) ? $planos : [];
$paises = [];
foreach ($documentos as $doc) {
    $codigo = (string) ($doc['pais_codigo'] ?? '');
    if ($codigo !== '' && !isset($paises[$codigo])) {
        $paises[$codigo] = [
            'pais_codigo' => $codigo,
            'pais_nome' => (string) ($doc['pais_nome'] ?? $codigo),
            'currency_code' => (string) ($doc['currency_code'] ?? 'BRL'),
            'currency_symbol' => (string) ($doc['currency_symbol'] ?? 'R$'),
        ];
    }
}
$money = static function (float $value, string $symbol, string $code): string {
    $decimals = strtoupper($code) === 'COP' ? 0 : 2;
    return $symbol . ' ' . number_format($value, $decimals, ',', '.') . ' ' . $code;
};
?>

<style>
.plans-grid { display:grid; grid-template-columns:1fr 1.4fr; gap:18px; align-items:start; }
.plans-panel { border:1px solid #e5e7eb; border-radius:8px; background:#fff; padding:16px; }
.plans-title { font-size:14px; font-weight:800; color:#111827; margin-bottom:12px; }
.plans-field { margin-bottom:12px; }
.plans-field label { display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:5px; }
.plans-field input,
.plans-field select,
.plans-field textarea { width:100%; border:1px solid #d1d5db; border-radius:7px; padding:9px 10px; font-size:14px; background:#fff; }
.plans-field textarea { min-height:76px; resize:vertical; }
.plans-row { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
.plans-checks { display:flex; gap:16px; margin:10px 0 14px; font-size:13px; color:#334155; }
.plans-checks input { width:auto; }
.plans-btn { border:0; border-radius:8px; background:#4f46e5; color:#fff; padding:11px 14px; font-weight:800; cursor:pointer; }
.plans-table { width:100%; border-collapse:collapse; font-size:13px; }
.plans-table th { text-align:left; padding:9px 10px; border-bottom:2px solid #e5e7eb; color:#64748b; font-size:11px; text-transform:uppercase; letter-spacing:.5px; }
.plans-table td { padding:10px; border-bottom:1px solid #f1f5f9; vertical-align:top; }
.plans-pill { display:inline-flex; border-radius:999px; padding:2px 8px; background:#dbeafe; color:#1d4ed8; font-weight:800; font-size:11px; }
.plans-muted { color:#64748b; font-size:12px; }
@media (max-width: 980px) {
    .plans-grid { grid-template-columns:1fr; }
    .plans-row { grid-template-columns:1fr; }
}
</style>

<?php if ($resultado): ?>
<div class="alert alert--success" style="margin-bottom:16px"><?= h($resultado) ?></div>
<?php endif; ?>
<?php if ($erro): ?>
<div class="alert alert--danger" style="margin-bottom:16px"><?= h($erro) ?></div>
<?php endif; ?>

<div class="plans-grid">
    <section class="plans-panel">
        <div class="plans-title">Cadastrar ou atualizar plano</div>
        <form method="POST" action="<?= h(url('clientes/planos.php')) ?>">
            <?= csrf_input() ?>
            <div class="plans-row">
                <div class="plans-field">
                    <label>Pais</label>
                    <select name="pais_codigo" id="plan-country" required>
                        <?php foreach ($paises as $pais): ?>
                            <option value="<?= h($pais['pais_codigo']) ?>" data-currency-code="<?= h($pais['currency_code']) ?>" data-currency-symbol="<?= h($pais['currency_symbol']) ?>">
                                <?= h($pais['pais_nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="plans-field">
                    <label>Codigo</label>
                    <input type="text" name="plano_codigo" placeholder="profissional" required>
                </div>
            </div>
            <div class="plans-field">
                <label>Nome comercial</label>
                <input type="text" name="nome" placeholder="Profissional" required>
            </div>
            <div class="plans-field">
                <label>Descricao</label>
                <textarea name="descricao" placeholder="Descricao que sera usada no site de vendas"></textarea>
            </div>
            <div class="plans-row">
                <div class="plans-field">
                    <label>Valor mensal</label>
                    <input type="number" name="valor" min="0.01" step="0.01" required>
                    <div class="plans-muted" id="plan-currency-hint"></div>
                </div>
                <div class="plans-field">
                    <label>Ordem</label>
                    <input type="number" name="ordem" min="1" step="1" value="10">
                </div>
            </div>
            <div class="plans-checks">
                <label><input type="checkbox" name="ativo" value="1" checked> Ativo</label>
                <label><input type="checkbox" name="popular" value="1"> Popular</label>
            </div>
            <button class="plans-btn" type="submit">Salvar plano</button>
        </form>
    </section>

    <section class="plans-panel">
        <div class="plans-title">Planos cadastrados</div>
        <table class="plans-table">
            <thead>
                <tr>
                    <th>Pais</th>
                    <th>Plano</th>
                    <th>Valor</th>
                    <th>Status</th>
                    <th>Descricao</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($planos as $plan): ?>
                <tr>
                    <td><?= h((string) $plan['pais_codigo']) ?></td>
                    <td>
                        <strong><?= h((string) $plan['nome']) ?></strong><br>
                        <span class="plans-muted"><?= h((string) $plan['plano_codigo']) ?></span>
                    </td>
                    <td><?= h($money((float) $plan['valor'], (string) $plan['currency_symbol'], (string) $plan['currency_code'])) ?></td>
                    <td>
                        <span class="plans-pill"><?= (int) $plan['ativo'] === 1 ? 'Ativo' : 'Inativo' ?></span>
                        <?php if ((int) $plan['popular'] === 1): ?><br><span class="plans-muted">Popular</span><?php endif; ?>
                    </td>
                    <td><?= h((string) $plan['descricao']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($planos)): ?>
                <tr><td colspan="5">Nenhum plano cadastrado.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </section>
</div>

<script>
(function () {
    const country = document.getElementById('plan-country');
    const hint = document.getElementById('plan-currency-hint');
    if (!country || !hint) return;

    function syncCurrency() {
        const option = country.options[country.selectedIndex];
        hint.textContent = option ? 'Moeda: ' + option.dataset.currencySymbol + ' ' + option.dataset.currencyCode : '';
    }

    country.addEventListener('change', syncCurrency);
    syncCurrency();
})();
</script>
