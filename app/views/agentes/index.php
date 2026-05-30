<?php
declare(strict_types=1);

$config = is_array($config ?? null) ? $config : [];
$sections = is_array($sections ?? null) ? $sections : [];
$configPath = (string) ($configPath ?? 'config.cfg');
$error = (string) ($error ?? '');

$usableConfig = [];
foreach ($config as $section => $values) {
    if (!is_array($values)) {
        continue;
    }
    $usableConfig[(string) $section] = [];
    foreach ($values as $key => $value) {
        $usableConfig[(string) $section][(string) $key] = (string) $value;
    }
}
?>
<style>
.agent-config-page{display:grid;gap:18px}
.agent-config-hero{display:flex;justify-content:space-between;align-items:flex-end;gap:16px;flex-wrap:wrap}
.agent-config-hero h2{margin:0;font-size:1.35rem;color:#10223d}
.agent-config-hero p{margin:6px 0 0;color:#5b6b80}
.agent-config-path{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:.84rem;background:#f4f7fb;border:1px solid #d8e1ec;border-radius:999px;padding:8px 12px;color:#20354f}
.agent-config-sections{display:flex;gap:10px;flex-wrap:wrap}
.agent-config-section-btn{border:none;background:#e9f0fb;color:#16324f;border-radius:999px;padding:10px 16px;font-weight:700;cursor:pointer;box-shadow:0 1px 0 rgba(15,23,42,.04)}
.agent-config-section-btn.is-active{background:linear-gradient(135deg,#1d4ed8,#2563eb);color:#fff}
.agent-config-card{background:#fff;border:1px solid #dde6f1;border-radius:18px;padding:18px;box-shadow:0 10px 30px rgba(15,23,42,.05)}
.agent-config-editor{display:grid;gap:14px}
.agent-config-editor__header{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap}
.agent-config-editor__header h3{margin:0;font-size:1.08rem;color:#10223d}
.agent-config-field-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:14px}
.agent-config-field label{display:block;font-weight:700;font-size:.92rem;color:#22364f;margin-bottom:6px}
.agent-config-field input{width:100%;border:1px solid #cfd9e6;border-radius:12px;padding:10px 12px;font:inherit;color:#10223d;background:#fff}
.agent-config-field input:focus{outline:none;border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.15)}
.agent-config-actions{display:flex;gap:10px;flex-wrap:wrap}
.agent-config-toast{position:fixed;right:24px;top:24px;z-index:50;padding:12px 18px;border-radius:12px;color:#fff;font-weight:700;box-shadow:0 12px 30px rgba(15,23,42,.18);display:none}
.agent-config-toast.is-show{display:block}
.agent-config-toast--success{background:#15803d}
.agent-config-toast--error{background:#b42318}
.agent-config-empty{padding:20px;border:1px dashed #d2dce8;border-radius:16px;background:#fbfdff;color:#5b6b80}
.agent-config-btn{border:none;border-radius:999px;padding:10px 16px;font-weight:700;cursor:pointer}
.agent-config-btn--save{background:#16a34a;color:#fff}
.agent-config-btn--start{background:#f59e0b;color:#fff}
.agent-config-btn--start[disabled]{opacity:.65;cursor:not-allowed}
</style>

<div class="module-toolbar">
    <a data-shell-nav="true" class="action-pill action-pill--outline" href="<?= h(url('agentes/index.php')) ?>">Configurar Agentes</a>
</div>

<div class="agent-config-page">
    <article class="module-card agent-config-card">
        <div class="agent-config-hero">
            <div>
                <h2>Editor de Configuração por Seção</h2>
                <p>Gerencie o arquivo <code>config.cfg</code> do projeto atual, sem depender da versão BKP.</p>
            </div>
            <div class="agent-config-path">Arquivo: <?= h((string) realpath($configPath) ?: $configPath) ?></div>
        </div>
    </article>

    <?php if ($error !== ''): ?>
        <article class="module-card notice-card notice-card--error">
            <strong>Erro:</strong> <?= h($error) ?>
        </article>
    <?php endif; ?>

    <article class="module-card agent-config-card">
        <div class="agent-config-sections" id="agent-config-sections">
            <?php foreach ($sections as $index => $section): ?>
                <button type="button" class="agent-config-section-btn<?= $index === 0 ? ' is-active' : '' ?>" data-section="<?= h((string) $section) ?>"><?= h((string) $section) ?></button>
            <?php endforeach; ?>
        </div>
    </article>

    <article class="module-card agent-config-card">
        <div id="agent-config-editor" class="agent-config-editor">
            <?php if ($sections === []): ?>
                <div class="agent-config-empty">Nenhuma seção configurável encontrada.</div>
            <?php endif; ?>
        </div>
    </article>
</div>

<div id="agent-config-toast" class="agent-config-toast"></div>

<script>
(() => {
  const configData = <?= json_encode($usableConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  const saveUrl = <?= json_encode(url('agentes/save.php')) ?>;
  const startUrl = <?= json_encode(url('agentes/start.php')) ?>;
  const csrf = <?= json_encode(csrf_token()) ?>;
  const editor = document.getElementById('agent-config-editor');
  const buttons = Array.from(document.querySelectorAll('.agent-config-section-btn'));
  const toast = document.getElementById('agent-config-toast');

  function showToast(message, ok = true) {
    if (!toast) return;
    toast.textContent = message;
    toast.className = 'agent-config-toast is-show ' + (ok ? 'agent-config-toast--success' : 'agent-config-toast--error');
    setTimeout(() => toast.className = 'agent-config-toast', 2800);
  }

  function inputType(key, value) {
    if (key === 'email_password' || key.toLowerCase().includes('password')) return 'password';
    if (/^-?\d+(\.\d+)?$/.test(String(value ?? '')) && !String(key).toLowerCase().includes('user')) return 'number';
    return 'text';
  }

  function renderSection(section) {
    if (!editor) return;
    const values = configData[section] || {};
    const fields = Object.entries(values).map(([key, value]) => `
      <div class="agent-config-field">
        <label for="cfg-${section}-${key}">${key}</label>
        <input id="cfg-${section}-${key}" type="${inputType(key, value)}" name="${key}" value="${String(value ?? '').replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;')}">
      </div>
    `).join('');

    editor.innerHTML = `
      <div class="agent-config-editor__header">
        <h3>Seção: ${section}</h3>
        <div class="agent-config-actions">
          <button type="button" class="agent-config-btn agent-config-btn--start" data-start-section="${section}">START AGENT</button>
          <button type="button" class="agent-config-btn agent-config-btn--save" data-save-section="${section}">Salvar</button>
        </div>
      </div>
      <div class="agent-config-field-grid">${fields}</div>
    `;
  }

  async function saveSection(section) {
    const inputs = Array.from(editor.querySelectorAll('input[name]'));
    const fields = {};
    for (const input of inputs) fields[input.name] = input.value;

    const response = await fetch(saveUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': csrf,
      },
      body: JSON.stringify({ _csrf: csrf, section, fields }),
    });
    const data = await response.json().catch(() => ({ success: false, error: 'Resposta inválida.' }));
    if (!response.ok || !data.success) throw new Error(data.error || 'Falha ao salvar configuração.');
    configData[section] = fields;
    showToast(data.message || 'Configuração salva com sucesso.', true);
  }

  async function startAgent(section) {
    try {
      const response = await fetch(startUrl, {
        method: 'POST',
        headers: { 'X-CSRF-Token': csrf },
      });
      const data = await response.json().catch(() => ({ success: false, error: 'Resposta inválida.' }));
      if (!response.ok || !data.success) throw new Error(data.error || 'START AGENT indisponível.');
      showToast(data.message || 'Agente iniciado.', true);
    } catch (error) {
      showToast(error.message || `START AGENT indisponível para ${section}.`, false);
    }
  }

  buttons.forEach(btn => {
    btn.addEventListener('click', () => {
      buttons.forEach(item => item.classList.remove('is-active'));
      btn.classList.add('is-active');
      renderSection(btn.dataset.section || '');
    });
  });

  editor?.addEventListener('click', async (event) => {
    const saveBtn = event.target.closest('[data-save-section]');
    if (saveBtn) {
      await saveSection(saveBtn.getAttribute('data-save-section') || '');
      return;
    }
    const startBtn = event.target.closest('[data-start-section]');
    if (startBtn) {
      await startAgent(startBtn.getAttribute('data-start-section') || '');
    }
  });

  if (buttons.length) {
    renderSection(buttons[0].dataset.section || '');
  }
})();
</script>
