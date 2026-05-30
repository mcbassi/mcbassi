<?php
$active = (string)($damodaramActivePage ?? 'index.php');
$year = (int)($damodaramYear ?? 2024);
$industry = (string)($damodaramIndustry ?? '');
$years = is_array($damodaramYears ?? null) ? $damodaramYears : [];
$industries = is_array($damodaramIndustries ?? null) ? $damodaramIndustries : [];
$versions = is_array($damodaramVersions ?? null) ? $damodaramVersions : [];
$selectedVersionId = (int)($damodaramSelectedVersionId ?? 0);

$basePages = [
    'index.php' => 'Overview',
    'profitability.php' => 'Profitability',
    'multiples.php' => 'Multiples',
    'risk_capital.php' => 'Risk / Capital',
    'reinvestment.php' => 'Reinvestment / WC',
    'history.php' => 'History',
    'wms_bridge.php' => 'WMS Bridge',
    'crud.php' => 'Editar dados',
];
?>
<?php require __DIR__ . '/_styles.php'; ?>

<div class="dam-toolbar">
    <form class="dam-toolbar" method="get" action="<?= h(url('DAMODARAM/' . $active)) ?>">
        <div class="dam-field">
            <label for="dam-year">Ano</label>
            <select id="dam-year" name="year" class="form-field__control analitica-select">
                <?php foreach ($years as $y): ?>
                    <option value="<?= h((string)$y) ?>" <?= (int)$y === $year ? 'selected' : '' ?>>
                        <?= h((string)$y) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="dam-field">
            <label for="dam-industry">Indústria</label>
            <select id="dam-industry" name="industry" class="form-field__control analitica-select">
                <?php foreach ($industries as $ind): ?>
                    <option value="<?= h((string)$ind) ?>" <?= (string)$ind === $industry ? 'selected' : '' ?>>
                        <?= h((string)$ind) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <?php if ($active === 'wms_bridge.php'): ?>
            <div class="dam-field">
                <label for="dam-version">Selecione o questionário</label>
                <select id="dam-version" name="version" class="form-field__control analitica-select">
                    <option value="">— Selecione —</option>
                    <?php foreach ($versions as $version):
                        $vid = (int)($version['id'] ?? 0);
                        $lbl = trim((string)($version['company_name'] ?? 'Sem empresa'))
                             . ' · ' . trim((string)($version['response_datetime'] ?? ''))
                             . (trim((string)($version['status'] ?? '')) !== '' ? ' · ' . trim((string)$version['status']) : '');
                    ?>
                        <option value="<?= h((string)$vid) ?>" <?= $vid === $selectedVersionId ? 'selected' : '' ?>>
                            <?= h($lbl) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>

        <div class="dam-actions">
            <button class="action-pill action-pill--outline" type="submit">Atualizar</button>
        </div>
    </form>
</div>

<div class="dam-tabs">
    <?php foreach ($basePages as $file => $label):
        $qs = '?year=' . rawurlencode((string)$year) . '&industry=' . rawurlencode($industry);
        if ($active === 'wms_bridge.php' || $file === 'wms_bridge.php') {
            $qs .= '&version=' . rawurlencode((string)$selectedVersionId);
        }
    ?>
        <a
            data-shell-nav="true"
            class="dam-tab <?= $file === $active ? 'is-active' : '' ?>"
            href="<?= h(url('DAMODARAM/' . $file . $qs)) ?>">
            <?= h($label) ?>
        </a>
    <?php endforeach; ?>
</div>