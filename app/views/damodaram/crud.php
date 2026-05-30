<?php
/** @var array|null $snapshot */
/** @var array $metrics */
/** @var array $options */
/** @var string $msg */
/** @var int $damodaramYear */
/** @var string $damodaramIndustry */

$snapshot = $snapshot ?? null;
$metrics = $metrics ?? [];
$options = $options ?? [];
$msg = $msg ?? '';
$year = (int)($damodaramYear ?? 2024);
$industry = (string)($damodaramIndustry ?? 'Advertising');
?>
<?php include __DIR__ . '/_styles.php'; ?>

<div class="damodaram-page">
    <?php include __DIR__ . '/_toolbar.php'; ?>

    <?php if ($msg !== ''): ?>
        <div class="alert alert-success"><?= h($msg) ?></div>
    <?php endif; ?>

    <div class="card mb-3">
        <div class="card-body">
            <h3 class="mb-1">CRUD Damodaran</h3>
            <div class="text-muted">Ajuste manual de snapshot e métricas</div>
        </div>
    </div>

    <?php if (!$snapshot): ?>
        <div class="card">
            <div class="card-body">Snapshot não encontrado.</div>
        </div>
    <?php else: ?>

        <div class="card mb-3">
            <div class="card-body">
                <h5 class="mb-3">Snapshot</h5>
                <form method="post">
                    <input type="hidden" name="action" value="update_snapshot">
                    <input type="hidden" name="year" value="<?= h((string)$year) ?>">
                    <input type="hidden" name="industry" value="<?= h($industry) ?>">
                    <input type="hidden" name="snapshot_id" value="<?= h((string)$snapshot['id']) ?>">

                    <div class="row g-3">
                        <div class="col-md-2">
                            <label class="form-label">ID</label>
                            <input class="form-control" value="<?= h((string)$snapshot['id']) ?>" disabled>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Ano</label>
                            <input class="form-control" value="<?= h((string)$snapshot['asof_year']) ?>" disabled>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Indústria</label>
                            <input class="form-control" value="<?= h((string)$snapshot['industry_name']) ?>" disabled>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Number of firms</label>
                            <input class="form-control" type="text" name="number_of_firms" value="<?= h((string)$snapshot['number_of_firms']) ?>">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button class="btn btn-primary w-100" type="submit">Salvar snapshot</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-body">
                <h5 class="mb-3">Adicionar métrica</h5>
                <form method="post">
                    <input type="hidden" name="action" value="insert_metric">
                    <input type="hidden" name="year" value="<?= h((string)$year) ?>">
                    <input type="hidden" name="industry" value="<?= h($industry) ?>">
                    <input type="hidden" name="snapshot_id" value="<?= h((string)$snapshot['id']) ?>">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Métrica</label>
                            <select class="form-select" name="metric_id">
                                <?php foreach ($options as $opt): ?>
                                    <option value="<?= h((string)$opt['id']) ?>">
                                        <?= h($opt['metric_group'] . ' | ' . $opt['metric_code'] . ' | ' . $opt['metric_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">metric_value</label>
                            <input class="form-control" type="text" name="metric_value">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">metric_value_text</label>
                            <input class="form-control" type="text" name="metric_value_text">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button class="btn btn-success w-100" type="submit">Adicionar</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <h5 class="mb-3">Métricas</h5>
                <div class="table-responsive">
                    <table class="table table-sm table-striped align-middle">
                        <thead>
                            <tr>
                                <th>Grupo</th>
                                <th>Code</th>
                                <th>Name</th>
                                <th>Value</th>
                                <th>Value Text</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($metrics as $m): ?>
                                <tr>
                                    <form method="post">
                                        <td><?= h((string)$m['metric_group']) ?></td>
                                        <td><?= h((string)$m['metric_code']) ?></td>
                                        <td><?= h((string)$m['metric_name']) ?></td>
                                        <td>
                                            <input class="form-control form-control-sm" type="text" name="metric_value" value="<?= h((string)$m['metric_value']) ?>">
                                        </td>
                                        <td>
                                            <input class="form-control form-control-sm" type="text" name="metric_value_text" value="<?= h((string)$m['metric_value_text']) ?>">
                                        </td>
                                        <td style="white-space:nowrap;">
                                            <input type="hidden" name="year" value="<?= h((string)$year) ?>">
                                            <input type="hidden" name="industry" value="<?= h($industry) ?>">
                                            <input type="hidden" name="fact_id" value="<?= h((string)$m['id']) ?>">
                                            <button class="btn btn-sm btn-primary" type="submit" name="action" value="update_metric">Salvar</button>
                                            <button class="btn btn-sm btn-outline-danger" type="submit" name="action" value="delete_metric" onclick="return confirm('Excluir esta métrica?')">Excluir</button>
                                        </td>
                                    </form>
                                </tr>
                            <?php endforeach; ?>

                            <?php if (empty($metrics)): ?>
                                <tr><td colspan="6" class="text-muted">Sem métricas.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    <?php endif; ?>
</div>