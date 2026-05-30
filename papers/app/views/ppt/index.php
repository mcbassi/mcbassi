<?php
declare(strict_types=1);

$presentations = is_array($presentations ?? null) ? $presentations : [];
$defaults = is_array($defaults ?? null) ? $defaults : [];
$configError = (string)($configError ?? '');
$sessionsUrl = (string)($sessionsUrl ?? url('api/ppt_sessions.php'));
$generateUrl = (string)($generateUrl ?? url('api/ppt_generate.php'));
$metricYear = (int)($defaults['metric_year'] ?? date('Y'));
$userId = (int)($defaults['user_id'] ?? 1);
$industryName = (string)($defaults['industry_name'] ?? '');
$hasPresentations = $presentations !== [];
?>
<style>
.ppt-screen{display:grid;gap:18px}
.ppt-grid{display:grid;grid-template-columns:minmax(0,1.5fr) minmax(320px,1fr);gap:18px}
.ppt-card{background:#fff;border:1px solid #e2e8f0;border-radius:18px;box-shadow:0 10px 28px rgba(15,23,42,.06)}
.ppt-card__body{padding:18px}
.ppt-card__title{margin:0 0 6px;font-size:1.05rem;font-weight:700;color:#0f172a}
.ppt-card__subtitle{margin:0 0 14px;color:#64748b;font-size:.92rem}
.ppt-form-grid{display:grid;grid-template-columns:repeat(12,minmax(0,1fr));gap:12px}
.ppt-col-12{grid-column:span 12}
.ppt-col-6{grid-column:span 6}
.ppt-actions{display:flex;gap:10px;flex-wrap:wrap}
.ppt-input,.ppt-select,.ppt-button{width:100%;border-radius:12px;border:1px solid #cbd5e1;font:inherit}
.ppt-input,.ppt-select{padding:10px 12px;background:#fff;color:#0f172a}
.ppt-input:focus,.ppt-select:focus{outline:none;border-color:#6366f1;box-shadow:0 0 0 3px rgba(99,102,241,.15)}
.ppt-button{padding:10px 14px;background:#4f46e5;color:#fff;font-weight:600;cursor:pointer}
.ppt-button--secondary{background:#0f172a}
.ppt-button[disabled]{opacity:.65;cursor:not-allowed}
.ppt-label{display:block;margin-bottom:6px;font-size:.85rem;font-weight:600;color:#334155}
.ppt-table-wrap{overflow:auto;max-height:520px;border:1px solid #e2e8f0;border-radius:14px}
.ppt-table{width:100%;border-collapse:collapse;font-size:.92rem}
.ppt-table th,.ppt-table td{padding:10px 12px;border-bottom:1px solid #e2e8f0;text-align:left;vertical-align:top}
.ppt-table thead th{position:sticky;top:0;background:#f8fafc;z-index:1}
.ppt-row{cursor:pointer}
.ppt-row:hover{background:#f8fafc}
.ppt-row.is-active{background:#e0e7ff}
.ppt-status{min-height:126px;background:#0f172a;color:#e2e8f0;border-radius:14px;padding:14px;white-space:pre-wrap;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:.86rem}
.ppt-result{min-height:126px;border:1px dashed #cbd5e1;border-radius:14px;padding:14px;color:#475569;background:#f8fafc}
.ppt-alert{border-radius:14px;padding:12px 14px;font-size:.92rem}
.ppt-alert--warning{background:#fff7ed;color:#9a3412;border:1px solid #fdba74}
.ppt-muted{color:#64748b}
.ppt-links{margin:0;padding-left:18px}
.ppt-links li+li{margin-top:6px}
.ppt-links a{color:#4338ca;text-decoration:none}
.ppt-links a:hover{text-decoration:underline}
@media (max-width: 1080px){.ppt-grid{grid-template-columns:1fr}}
@media (max-width: 720px){.ppt-col-6{grid-column:span 12}}
</style>

<div id="ppt-screen" class="ppt-screen" data-sessions-url="<?= h($sessionsUrl) ?>" data-generate-url="<?= h($generateUrl) ?>">
    <div class="ppt-card">
        <div class="ppt-card__body">
            <h2 class="ppt-card__title">Integração do gerador de apresentações</h2>
            <p class="ppt-card__subtitle">Selecione uma sessão do questionário e acione o motor validado de PPT dentro do menu principal do sistema.</p>
            <?php if ($configError !== ''): ?>
                <div class="ppt-alert ppt-alert--warning">Configuração do PPT não carregada: <?= h($configError) ?></div>
            <?php endif; ?>
        </div>
    </div>

    <div class="ppt-grid">
        <section class="ppt-card">
            <div class="ppt-card__body">
                <h3 class="ppt-card__title">Buscar sessões do questionário</h3>
                <p class="ppt-card__subtitle">Clique em uma linha para preencher automaticamente o formulário de geração.</p>

                <form id="ppt-session-filter" class="ppt-form-grid" autocomplete="off">
                    <div class="ppt-col-6">
                        <label class="ppt-label" for="ppt-company-filter">Empresa</label>
                        <input id="ppt-company-filter" class="ppt-input" type="text" name="company_name" placeholder="Nome da empresa">
                    </div>
                    <div class="ppt-col-6">
                        <label class="ppt-label" for="ppt-email-filter">Email da resposta</label>
                        <input id="ppt-email-filter" class="ppt-input" type="text" name="email_resp" placeholder="email@empresa.com">
                    </div>
                    <div class="ppt-col-12 ppt-actions">
                        <button class="ppt-button" type="submit">Buscar sessões</button>
                        <button class="ppt-button ppt-button--secondary" type="button" id="ppt-reload-sessions">Recarregar</button>
                    </div>
                </form>

                <div class="ppt-table-wrap" style="margin-top:14px;">
                    <table class="ppt-table" id="ppt-sessions-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Versão</th>
                                <th>Empresa</th>
                                <th>Email</th>
                                <th>Data</th>
                                <th>%</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td colspan="6" class="ppt-muted">Carregando sessões...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <div class="ppt-screen" style="gap:18px;">
            <section class="ppt-card">
                <div class="ppt-card__body">
                    <h3 class="ppt-card__title">Gerar apresentação</h3>
                    <p class="ppt-card__subtitle">Os dados abaixo podem ser preenchidos manualmente ou a partir de uma sessão selecionada.</p>

                    <form id="ppt-generate-form" class="ppt-form-grid" autocomplete="off">
                        <div class="ppt-col-12">
                            <label class="ppt-label" for="ppt-presentation-name">Modelo</label>
                            <select id="ppt-presentation-name" class="ppt-select" name="presentation_name" <?= $hasPresentations ? '' : 'disabled' ?>>
                                <?php if ($hasPresentations): ?>
                                    <?php foreach ($presentations as $presentation): ?>
                                        <?php $name = (string)($presentation['name'] ?? ''); ?>
                                        <?php if ($name === '') { continue; } ?>
                                        <option value="<?= h($name) ?>"><?= h((string)($presentation['label'] ?? $name)) ?></option>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <option value="">Nenhum modelo configurado</option>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="ppt-col-6">
                            <label class="ppt-label" for="ppt-company-name">Empresa</label>
                            <input id="ppt-company-name" class="ppt-input" type="text" name="company_name" required>
                        </div>
                        <div class="ppt-col-6">
                            <label class="ppt-label" for="ppt-email-resp">Email</label>
                            <input id="ppt-email-resp" class="ppt-input" type="email" name="email_resp" required>
                        </div>
                        <div class="ppt-col-6">
                            <label class="ppt-label" for="ppt-version-id">Version ID</label>
                            <input id="ppt-version-id" class="ppt-input" type="number" name="version_id" min="1" required>
                        </div>
                        <div class="ppt-col-6">
                            <label class="ppt-label" for="ppt-user-id">User ID</label>
                            <input id="ppt-user-id" class="ppt-input" type="number" name="user_id" min="1" value="<?= h((string)$userId) ?>" required>
                        </div>
                        <div class="ppt-col-6">
                            <label class="ppt-label" for="ppt-metric-year">Metric Year</label>
                            <input id="ppt-metric-year" class="ppt-input" type="number" name="metric_year" value="<?= h((string)$metricYear) ?>">
                        </div>
                        <div class="ppt-col-6">
                            <label class="ppt-label" for="ppt-industry-name">Industry</label>
                            <input id="ppt-industry-name" class="ppt-input" type="text" name="industry_name" value="<?= h($industryName) ?>">
                        </div>
                        <div class="ppt-col-12">
                            <button class="ppt-button" type="submit" <?= $hasPresentations ? '' : 'disabled' ?>>Gerar PPT</button>
                        </div>
                    </form>
                </div>
            </section>

            <section class="ppt-card">
                <div class="ppt-card__body">
                    <h3 class="ppt-card__title">Resultado</h3>
                    <div id="ppt-result-box" class="ppt-result">Nenhuma execução ainda.</div>
                </div>
            </section>

            <section class="ppt-card">
                <div class="ppt-card__body">
                    <h3 class="ppt-card__title">Status / Debug</h3>
                    <div id="ppt-status-box" class="ppt-status">Pronto.</div>
                </div>
            </section>
        </div>
    </div>
</div>

<script>
(function () {
    const root = document.getElementById('ppt-screen');
    if (!root || root.dataset.bound === '1') {
        return;
    }
    root.dataset.bound = '1';

    const sessionsUrl = root.dataset.sessionsUrl || '';
    const generateUrl = root.dataset.generateUrl || '';
    const filterForm = root.querySelector('#ppt-session-filter');
    const reloadButton = root.querySelector('#ppt-reload-sessions');
    const generateForm = root.querySelector('#ppt-generate-form');
    const tableBody = root.querySelector('#ppt-sessions-table tbody');
    const statusBox = root.querySelector('#ppt-status-box');
    const resultBox = root.querySelector('#ppt-result-box');

    function esc(value) {
        return String(value ?? '').replace(/[&<>"']/g, function (char) {
            return ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'})[char] || char;
        });
    }

    function setStatus(text) {
        if (statusBox) {
            statusBox.textContent = String(text || '');
        }
    }

    function setSessionsLoading(message) {
        if (!tableBody) return;
        tableBody.innerHTML = '<tr><td colspan="6" class="ppt-muted">' + esc(message) + '</td></tr>';
    }

    function resolveDownloadUrl(url) {
        if (!url) {
            return '';
        }
        try {
            return new URL(String(url), generateUrl || window.location.href).toString();
        } catch (error) {
            return String(url);
        }
    }

    function autoStartDownload(url) {
        const finalUrl = resolveDownloadUrl(url);
        if (!finalUrl) {
            return;
        }
        const anchor = document.createElement('a');
        anchor.href = finalUrl;
        anchor.style.display = 'none';
        document.body.appendChild(anchor);
        anchor.click();
        anchor.remove();
    }

    async function loadSessions(params) {
        if (!sessionsUrl) {
            setStatus('URL de sessões não configurada.');
            setSessionsLoading('URL de sessões não configurada.');
            return;
        }

        setStatus('Carregando sessões...');
        setSessionsLoading('Carregando sessões...');

        try {
            const query = params instanceof URLSearchParams ? params.toString() : '';
            const response = await fetch(sessionsUrl + (query ? '?' + query : ''), {
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await response.json();

            if (!response.ok || !data.ok) {
                throw new Error(data.error || ('HTTP ' + response.status));
            }

            const rows = Array.isArray(data.rows) ? data.rows : [];
            if (!rows.length) {
                setSessionsLoading('Nenhuma sessão encontrada.');
                setStatus('Nenhuma sessão encontrada.');
                return;
            }

            tableBody.innerHTML = rows.map(function (row) {
                return '<tr class="ppt-row" data-company="' + esc(row.company_name) + '" data-email="' + esc(row.email_resp) + '" data-version="' + esc(row.version_id) + '">' +
                    '<td>' + esc(row.response_session_id) + '</td>' +
                    '<td>' + esc(row.version_id) + '</td>' +
                    '<td>' + esc(row.company_name) + '</td>' +
                    '<td>' + esc(row.email_resp) + '</td>' +
                    '<td>' + esc(row.response_datetime) + '</td>' +
                    '<td>' + esc(row.completion_pct) + '</td>' +
                '</tr>';
            }).join('');
            setStatus('Sessões carregadas: ' + rows.length);
        } catch (error) {
            const message = error && error.message ? error.message : 'Erro ao carregar sessões.';
            setSessionsLoading(message);
            setStatus(message);
        }
    }

    if (filterForm) {
        filterForm.addEventListener('submit', function (event) {
            event.preventDefault();
            loadSessions(new URLSearchParams(new FormData(filterForm)));
        });
    }

    if (reloadButton) {
        reloadButton.addEventListener('click', function () {
            if (filterForm) {
                filterForm.reset();
                loadSessions(new URLSearchParams(new FormData(filterForm)));
                return;
            }
            loadSessions(new URLSearchParams());
        });
    }

    if (tableBody && generateForm) {
        tableBody.addEventListener('click', function (event) {
            const row = event.target.closest('.ppt-row');
            if (!row) {
                return;
            }

            tableBody.querySelectorAll('.ppt-row').forEach(function (item) {
                item.classList.remove('is-active');
            });
            row.classList.add('is-active');

            const companyInput = generateForm.querySelector('[name="company_name"]');
            const emailInput = generateForm.querySelector('[name="email_resp"]');
            const versionInput = generateForm.querySelector('[name="version_id"]');

            if (companyInput) companyInput.value = row.dataset.company || '';
            if (emailInput) emailInput.value = row.dataset.email || '';
            if (versionInput) versionInput.value = row.dataset.version || '';

            setStatus('Sessão selecionada: ' + (row.dataset.company || '') + ' / v' + (row.dataset.version || ''));
        });
    }

    if (generateForm) {
        generateForm.addEventListener('submit', async function (event) {
            event.preventDefault();

            if (!generateUrl) {
                setStatus('URL de geração não configurada.');
                return;
            }

            const formData = new FormData(generateForm);
            const payload = Object.fromEntries(formData.entries());
            payload.user_id = Number(payload.user_id || 1);
            payload.version_id = Number(payload.version_id || 0);
            payload.metric_year = Number(payload.metric_year || 0);

            setStatus('Gerando PPT...');
            if (resultBox) {
                resultBox.innerHTML = '<span class="ppt-muted">Executando...</span>';
            }

            try {
                const response = await fetch(generateUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify(payload)
                });
                const data = await response.json();
                if (!response.ok || !data.ok) {
                    throw new Error(data.error || ('HTTP ' + response.status));
                }

                const pptxDownloadUrl = resolveDownloadUrl(data.pptx_download || '');
                const contextDownloadUrl = resolveDownloadUrl(data.context_json_download || '');
                const runtimeDownloadUrl = resolveDownloadUrl(data.runtime_json_download || '');

                const links = [];
                if (pptxDownloadUrl) {
                    links.push('<li><a href="' + esc(pptxDownloadUrl) + '">Baixar PPT</a></li>');
                }
                if (contextDownloadUrl) {
                    links.push('<li><a href="' + esc(contextDownloadUrl) + '">Baixar ppt_input_context.json</a></li>');
                }
                if (runtimeDownloadUrl) {
                    links.push('<li><a href="' + esc(runtimeDownloadUrl) + '">Baixar ppt_runtime_payload.json</a></li>');
                }

                if (resultBox) {
                    resultBox.innerHTML = '' +
                        '<div><strong>Execution ID:</strong> ' + esc(data.execution_id || '') + '</div>' +
                        '<div style="margin-top:8px;"><strong>PPT:</strong> <span class="ppt-muted">' + esc(data.pptx || '') + '</span></div>' +
                        '<div style="margin-top:8px;"><strong>Output dir:</strong> <span class="ppt-muted">' + esc(data.output_dir || '') + '</span></div>' +
                        '<ul class="ppt-links" style="margin-top:12px;">' + (links.join('') || '<li>Nenhum link disponível</li>') + '</ul>';
                }
                setStatus('PPT gerado com sucesso.');
                if (pptxDownloadUrl) {
                    autoStartDownload(pptxDownloadUrl);
                }
            } catch (error) {
                const message = error && error.message ? error.message : 'Falha ao gerar PPT.';
                if (resultBox) {
                    resultBox.innerHTML = '<span style="color:#b91c1c;">' + esc(message) + '</span>';
                }
                setStatus(message);
            }
        });
    }

    loadSessions(new URLSearchParams());
})();
</script>
