<?php require __DIR__ . '/_toolbar.php'; ?>
<?php
$currentRow = is_array($damodaramRow ?? null) ? $damodaramRow : [];
$industry = (string)($damodaramIndustry ?? '');
$year = (int)($damodaramYear ?? 2024);

$chartConfigs = [
    'bridge' => [
        'type' => 'bar',
        'data' => [
            'labels' => ['WMS Operations', 'WMS Monitor', 'WMS Target', 'WMS People', 'ROC - WACC'],
            'datasets' => [[
                'label' => $industry,
                'data' => [
                    (float)($currentRow['wms_operations_score'] ?? 0),
                    (float)($currentRow['wms_monitor_score'] ?? 0),
                    (float)($currentRow['wms_target_score'] ?? 0),
                    (float)($currentRow['wms_people_score'] ?? 0),
                    (float)($currentRow['roc_minus_wacc'] ?? 0),
                ]
            ]]
        ],
        'options' => [
            'responsive' => true,
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => ['display' => false]
            ]
        ]
    ],
    'wc' => [
        'type' => 'radar',
        'data' => [
            'labels' => ['Acc Rec / Sales', 'Inventory / Sales', 'Acc Pay / Sales', 'Non-cash WC / Sales'],
            'datasets' => [[
                'label' => $industry,
                'data' => [
                    (float)($currentRow['accounts_receivable_sales'] ?? 0),
                    (float)($currentRow['inventory_sales'] ?? 0),
                    (float)($currentRow['accounts_payable_sales'] ?? 0),
                    (float)($currentRow['non_cash_working_capital_sales'] ?? 0),
                ]
            ]]
        ],
        'options' => [
            'responsive' => true,
            'maintainAspectRatio' => false
        ]
    ]
];
?>
<article class="module-card dam-card">
    <h2 class="dam-title">Damodaran BI · WMS Bridge</h2>

    <div class="dam-meta">
        <span class="dam-chip">Indústria: <?= h($industry) ?></span>
        <span class="dam-chip">Ano: <?= h((string)$year) ?></span>
    </div>

    <?php if (empty($currentRow)): ?>
        <div class="dam-empty">Selecione um questionário para consultar.</div>
    <?php else: ?>
        <div class="dam-cta-row" style="display:flex;gap:12px;align-items:center;justify-content:flex-end;margin:12px 0 16px 0;">
            <button type="button" id="dam-exec-3-prompts" class="action-pill">Executar prompts</button>
        </div>

        <div class="dam-chart-shell"
             data-dam-chart="bridge"
             data-chart-configs='<?= h((string)json_encode($chartConfigs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>'>
            <div class="dam-chart-top">
                <strong>Gráficos</strong>
                <div class="dam-chart-buttons">
                    <button type="button" class="dam-chart-btn" data-chart-key="bridge">Gestão x Valor</button>
                    <button type="button" class="dam-chart-btn" data-chart-key="wc">Capital de Giro</button>
                </div>
            </div>
            <div class="dam-canvas-wrap">
                <canvas></canvas>
            </div>
        </div>

        <div class="dam-table-wrap">
            <table class="dam-table">
                <thead>
                    <tr>
                        <?php foreach (array_keys($currentRow) as $col): ?>
                            <th><?= h((string)$col) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <?php foreach ($currentRow as $v): ?>
                            <td><?= h((string)$v) ?></td>
                        <?php endforeach; ?>
                    </tr>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</article>

<div id="dam-prompt-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:9999;align-items:center;justify-content:center;padding:24px;">
    <div style="background:#fff;border-radius:12px;max-width:1100px;width:100%;max-height:90vh;display:flex;flex-direction:column;box-shadow:0 10px 30px rgba(0,0,0,.25);">
        <div style="padding:16px 20px;border-bottom:1px solid #e5e7eb;display:flex;justify-content:space-between;align-items:center;">
            <h3 style="margin:0;font-size:20px;">Resultado consolidado dos prompts</h3>
            <button type="button" id="dam-close-modal-top" class="action-pill action-pill--outline">Fechar</button>
        </div>

        <div id="dam-prompt-modal-body" style="padding:20px;overflow:auto;">
            <div class="text-muted">Aguardando execução...</div>
        </div>

        <div style="padding:16px 20px;border-top:1px solid #e5e7eb;display:flex;gap:12px;justify-content:flex-end;">
            <button type="button" id="dam-save-pdf" class="action-pill">Salvar</button>
            <button type="button" id="dam-close-modal-bottom" class="action-pill action-pill--outline">Fechar</button>
        </div>
    </div>
</div>

<script>
(function () {
    const root = document.querySelector('[data-dam-chart="bridge"]');
    if (root && window.Chart) {
        const canvas = root.querySelector('canvas');
        const buttons = [...root.querySelectorAll('[data-chart-key]')];
        const configs = JSON.parse(root.getAttribute('data-chart-configs') || '{}');
        let chart = null;

        function render(key) {
            const cfg = configs[key];
            if (!cfg) return;

            buttons.forEach((b) => {
                b.classList.toggle('is-active', b.getAttribute('data-chart-key') === key);
            });

            if (chart) chart.destroy();
            chart = new Chart(canvas.getContext('2d'), cfg);
        }

        buttons.forEach((b) => b.addEventListener('click', () => render(b.getAttribute('data-chart-key'))));

        if (buttons[0]) {
            render(buttons[0].getAttribute('data-chart-key'));
        }
    }

    const execBtn = document.getElementById('dam-exec-3-prompts');
    const modal = document.getElementById('dam-prompt-modal');
    const modalBody = document.getElementById('dam-prompt-modal-body');
    const saveBtn = document.getElementById('dam-save-pdf');
    const closeButtons = [
        document.getElementById('dam-close-modal-top'),
        document.getElementById('dam-close-modal-bottom')
    ];

    let lastHtml = '';

    function openModal() {
        if (modal) modal.style.display = 'flex';
    }

    function closeModal() {
        if (modal) modal.style.display = 'none';
    }

    closeButtons.forEach((btn) => {
        if (btn) btn.addEventListener('click', closeModal);
    });

    if (modal) {
        modal.addEventListener('click', function (ev) {
            if (ev.target === modal) closeModal();
        });
    }

    if (execBtn) {
        execBtn.addEventListener('click', async function () {
            const version = document.getElementById('dam-version')?.value || '';
            const selectedYear = document.getElementById('dam-year')?.value || '<?= h((string)$year) ?>';
            const selectedIndustry = document.getElementById('dam-industry')?.value || '<?= h($industry) ?>';

            if (!version) {
                openModal();
                modalBody.innerHTML = '<div class="alert alert-warning">Selecione o questionário antes de executar os prompts.</div>';
                return;
            }

            openModal();
            modalBody.innerHTML = '<div class="text-muted">Executando prompts...</div>';

            const formData = new FormData();
            formData.append('version_id', version);
            formData.append('year', selectedYear);
            formData.append('industry', selectedIndustry);

            try {
                const resp = await fetch('<?= h(url("DAMODARAM/wms_bridge_prompt_api.php")) ?>', {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                });

                const data = await resp.json();

                if (!resp.ok || !data.ok) {
                    throw new Error(data.error || 'Falha ao executar os prompts.');
                }

                lastHtml = data.html || '<div class="text-muted">Sem conteúdo retornado.</div>';
                window.lastDamodaranPromptHtml = lastHtml;
                modalBody.innerHTML = lastHtml;
            } catch (err) {
                modalBody.innerHTML = '<div class="alert alert-danger">' + String(err.message || err) + '</div>';
            }
        });
    }

    if (saveBtn) {
        saveBtn.addEventListener('click', async function () {
            const version = document.getElementById('dam-version')?.value || '';
            const selectedYear = document.getElementById('dam-year')?.value || '<?= h((string)$year) ?>';
            const selectedIndustry = document.getElementById('dam-industry')?.value || '<?= h($industry) ?>';
            const html = (window.lastDamodaranPromptHtml || lastHtml || modalBody.innerHTML || '').trim();

            if (!version) {
                alert('Selecione o questionário antes de salvar.');
                return;
            }

            if (!html) {
                alert('Não há conteúdo para salvar.');
                return;
            }

            const formData = new FormData();
            formData.append('version_id', version);
            formData.append('year', selectedYear);
            formData.append('industry', selectedIndustry);
            formData.append('html', html);

            const oldText = saveBtn.textContent;
            saveBtn.disabled = true;
            saveBtn.textContent = 'Salvando...';

            try {
                const resp = await fetch('<?= h(url("DAMODARAM/wms_bridge_prompt_save_api.php")) ?>', {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                });

                const data = await resp.json();

                if (!resp.ok || !data.ok) {
                    throw new Error(data.error || 'Falha ao salvar.');
                }

                const binary = atob(data.pdf_base64);
                const len = binary.length;
                const bytes = new Uint8Array(len);

                for (let i = 0; i < len; i++) {
                    bytes[i] = binary.charCodeAt(i);
                }

                const blob = new Blob([bytes], { type: 'application/pdf' });
                const link = document.createElement('a');
                link.href = URL.createObjectURL(blob);
                link.download = data.filename || 'damodaran.pdf';
                link.click();
                URL.revokeObjectURL(link.href);

                alert(data.message || 'Salvo com sucesso.');
            } catch (err) {
                alert(String(err.message || err));
            } finally {
                saveBtn.disabled = false;
                saveBtn.textContent = oldText;
            }
        });
    }
})();
</script>