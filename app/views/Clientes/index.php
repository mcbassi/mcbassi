<?php
/**
 * app/views/clientes/index.php
 * Listagem de clientes cadastrados
 * Variável disponível: $clientes (array de rows do banco)
 */

$statusLabel = [
    'ativo'        => ['txt' => 'Ativo',        'badge' => 'badge--green'],
    'inadimplente' => ['txt' => 'Inadimplente', 'badge' => 'badge--red'],
    'cancelado'    => ['txt' => 'Cancelado',    'badge' => 'badge--dark'],
    'suspenso'     => ['txt' => 'Suspenso',     'badge' => 'badge--orange'],
];
$planoLabel = [
    'basico'       => 'Básico',
    'profissional' => 'Profissional',
    'enterprise'   => 'Enterprise',
];
?>
<style>
.cli-toolbar { display:flex; justify-content:space-between; align-items:center; margin-bottom:18px; }
.cli-toolbar h2 { font-size:16px; font-weight:600; }
.cli-btn-new { display:inline-flex; align-items:center; gap:6px; background:#6366f1; color:#fff; padding:8px 16px; border-radius:8px; font-size:13px; font-weight:600; text-decoration:none; transition:.15s; }
.cli-btn-new:hover { background:#4f46e5; }
.cli-table-wrap { overflow-x:auto; }
table.cli-table { width:100%; border-collapse:collapse; font-size:13px; }
.cli-table th { text-align:left; padding:9px 12px; font-size:11px; text-transform:uppercase; letter-spacing:.8px; color:#888; border-bottom:2px solid #e5e7eb; white-space:nowrap; }
.cli-table td { padding:10px 12px; border-bottom:1px solid #f3f4f6; vertical-align:middle; }
.cli-table tr:hover td { background:#f9fafb; }
.cli-table .badge { display:inline-block; padding:2px 9px; border-radius:20px; font-size:11px; font-weight:600; }
.badge--green  { background:#d1fae5; color:#065f46; }
.badge--red    { background:#fee2e2; color:#991b1b; }
.badge--dark   { background:#e5e7eb; color:#374151; }
.badge--orange { background:#ffedd5; color:#9a3412; }
.badge--indigo { background:#e0e7ff; color:#3730a3; }
.cli-empty { text-align:center; padding:40px; color:#aaa; }
</style>

<div class="cli-toolbar">
    <h2>👥 Clientes (<?= count($clientes) ?>)</h2>
    <a class="cli-btn-new" href="<?= h(url('clientes/cadastro.php')) ?>" data-shell-nav="true" data-nav-prefix="/clientes/cadastro">
        + Novo Cliente
    </a>
</div>

<?php if (empty($clientes)): ?>
    <div class="cli-empty">Nenhum cliente cadastrado ainda.<br><br>
        <a href="<?= h(url('clientes/cadastro.php')) ?>" data-shell-nav="true">Cadastre o primeiro →</a>
    </div>
<?php else: ?>
<div class="cli-table-wrap">
    <table class="cli-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Nome</th>
                <th>E-mail</th>
                <th>Plano</th>
                <th>Valor</th>
                <th>Próx. Vencimento</th>
                <th>Status</th>
                <th>Cadastrado em</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($clientes as $c): ?>
            <?php
                $st = $statusLabel[$c['status']] ?? ['txt' => $c['status'], 'badge' => 'badge--dark'];
                $pl = $planoLabel[$c['plano']]   ?? $c['plano'];
            ?>
            <tr>
                <td style="color:#aaa"><?= h((string)$c['id']) ?></td>
                <td><strong><?= h($c['nome']) ?></strong><br>
                    <small style="color:#aaa"><?= h($c['cpf']) ?></small></td>
                <td><?= h($c['email']) ?></td>
                <td><span class="badge badge--indigo"><?= h($pl) ?></span></td>
                <td>R$ <?= number_format((float)$c['valor_plano'], 2, ',', '.') ?></td>
                <td><?= h($c['vencimento']) ?></td>
                <td><span class="badge <?= h($st['badge']) ?>"><?= h($st['txt']) ?></span></td>
                <td style="color:#aaa; font-size:12px"><?= h($c['cadastrado_em']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
