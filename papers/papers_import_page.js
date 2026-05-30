(function () {
  function ready(fn) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', fn, { once: true });
    } else {
      fn();
    }
  }

  ready(function initPapersImportPage() {
    const root = document.getElementById('papersImportPage') || document;
    const btnRun = document.getElementById('btnRunImport');
    const statusBox = document.getElementById('statusImport');
    const progressBox = document.getElementById('progressBox');
    const progressBar = document.getElementById('progressBar');
    const tableWrapper = document.getElementById('papersTableWrapper');
    const cacheModalOverlay = document.getElementById('cacheModalOverlay');
    const cacheModalBody = document.getElementById('cacheModalBody');
    const btnCloseCacheModal = document.getElementById('btnCloseCacheModal');

    console.log('[papers_import] init ok');
    if (!btnRun) {
      console.warn('[papers_import] btnRunImport não encontrado');
      return;
    }
    if (root.dataset.papersImportBound === '1') {
      console.log('[papers_import] já inicializado');
      return;
    }
    root.dataset.papersImportBound = '1';

    const syncUrl = btnRun.getAttribute('data-sync-url') || '';
    const apiUrl = btnRun.getAttribute('data-api-url') || '';
    console.log('[papers_import] syncUrl =', syncUrl);
    console.log('[papers_import] apiUrl =', apiUrl);

    function setStatus(type, msg) {
      if (!statusBox) return;
      statusBox.className = 'mb-3 alert py-2 ' + (
        type === 'danger' ? 'alert-danger' :
        type === 'success' ? 'alert-success' :
        'alert-info'
      );
      statusBox.textContent = msg;
    }

    function setProgress(pct) {
      if (!progressBox || !progressBar) return;
      progressBox.style.display = 'block';
      progressBar.style.width = pct + '%';
      progressBar.textContent = pct + '%';
      progressBar.setAttribute('aria-valuenow', String(pct));
    }

    function hideProgressLater() {
      window.setTimeout(function () {
        if (progressBox) progressBox.style.display = 'none';
      }, 1200);
    }

    function esc(s) {
      return String(s ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
    }

    function openCacheModal(html) {
      if (!cacheModalOverlay || !cacheModalBody) return;
      cacheModalBody.innerHTML = html;
      cacheModalOverlay.style.display = 'block';
    }

    function closeCacheModal() {
      if (!cacheModalOverlay) return;
      cacheModalOverlay.style.display = 'none';
    }

    async function postForm(url, formData) {
      console.log('[papers_import] POST =>', url);

      const resp = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
      });

      const text = await resp.text();
      console.log('[papers_import] resposta:', text.substring(0, 1000));

      let data = null;
      try {
        data = JSON.parse(text);
      } catch (e) {
        throw new Error('Resposta inválida: ' + text.substring(0, 300));
      }

      if (!resp.ok || !data || data.ok === false) {
        throw new Error(
          (data && (data.error || data.message)) || ('HTTP ' + resp.status)
        );
      }

      return data;
    }

    async function runImport() {
      btnRun.disabled = true;
      try {
        setStatus('info', 'Sincronizando Dropbox...');
        setProgress(15);
        const fdSync = new FormData();
        await postForm(syncUrl, fdSync);

        setStatus('info', 'Importando biblioteca...');
        setProgress(60);
        const fdImport = new FormData();
        fdImport.append('action', 'import');
        const data = await postForm(apiUrl, fdImport);

        setProgress(100);
        setStatus('success', 'Importação concluída. Criados: ' + (data.created || 0) + ' • Atualizados: ' + (data.updated || 0) + ' • Arquivos encontrados: ' + (data.total_files || 0));
        window.setTimeout(function () { window.location.reload(); }, 800);
      } catch (err) {
        console.error('[papers_import] erro importação', err);
        setStatus('danger', 'Erro: ' + (err && err.message ? err.message : String(err)));
      } finally {
        btnRun.disabled = false;
        hideProgressLater();
      }
    }

    async function runAI(id, button) {
      if (!id) return;
      if (button) button.disabled = true;
      const row = button ? button.closest('tr') : null;
      const keywordsCell = row ? row.querySelector('.col-keywords') : null;

      console.log('[papers_import] clique IA id=', id);
      try {
        setStatus('info', 'Executando IA para o paper ID ' + id + '...');
        setProgress(35);

        const fd = new FormData();
        fd.append('action', 'run_ai');
        fd.append('id', String(id));
        const data = await postForm(apiUrl, fd);

        setProgress(100);
        setStatus('success', 'IA concluída para o paper ID ' + id + '.');

        const keywords = (data.paper && data.paper.keywords) ?? data.keywords ?? '';
        if (keywordsCell) keywordsCell.textContent = keywords;
      } catch (err) {
        console.error('[papers_import] erro IA', err);
        setStatus('danger', 'Erro IA: ' + (err && err.message ? err.message : String(err)));
      } finally {
        if (button) button.disabled = false;
        hideProgressLater();
      }
    }

    async function showCache(id, button) {
      if (!id) return;
      if (button) button.disabled = true;

      try {
        setStatus('info', 'Buscando dados do cache do paper ID ' + id + '...');

        const fd = new FormData();
        fd.append('action', 'get_cache');
        fd.append('id', String(id));

        const data = await postForm(apiUrl, fd);

        if (!data.cache) {
          openCacheModal(
            '<div class="alert alert-warning mb-0">Nenhum registro encontrado em <code>papers_file_cache</code> para o paper ID <strong>' + esc(id) + '</strong>.</div>'
          );
          return;
        }

        const c = data.cache;
        openCacheModal(`
          <div class="table-responsive">
            <table class="table table-sm table-bordered align-middle">
              <tbody>
                <tr><th style="width:220px;">paper_id</th><td>${esc(c.paper_id)}</td></tr>
                <tr><th>cache_id</th><td>${esc(c.cache_id)}</td></tr>
                <tr><th>source_sha256</th><td><code>${esc(c.source_sha256)}</code></td></tr>
                <tr><th>original_filename</th><td>${esc(c.original_filename)}</td></tr>
                <tr><th>mime_type</th><td>${esc(c.mime_type)}</td></tr>
                <tr><th>file_ext</th><td>${esc(c.file_ext)}</td></tr>
                <tr><th>size_bytes</th><td>${esc(c.size_bytes)}</td></tr>
                <tr><th>local_cache_path</th><td><code>${esc(c.local_cache_path)}</code></td></tr>
                <tr><th>source_type</th><td>${esc(c.source_type)}</td></tr>
                <tr><th>source_value</th><td><code>${esc(c.source_value)}</code></td></tr>
                <tr><th>openai_file_id</th><td>${esc(c.openai_file_id)}</td></tr>
                <tr><th>openai_file_purpose</th><td>${esc(c.openai_file_purpose)}</td></tr>
                <tr><th>vector_store_id</th><td>${esc(c.vector_store_id)}</td></tr>
                <tr><th>cache_status</th><td>${esc(c.cache_status)}</td></tr>
                <tr><th>exists_flag</th><td>${esc(c.exists_flag)}</td></tr>
                <tr><th>last_error</th><td><pre style="margin:0;white-space:pre-wrap;">${esc(c.last_error)}</pre></td></tr>
                <tr><th>created_at</th><td>${esc(c.created_at)}</td></tr>
                <tr><th>updated_at</th><td>${esc(c.updated_at)}</td></tr>
                <tr><th>last_used_at</th><td>${esc(c.last_used_at)}</td></tr>
                <tr><th>last_checked_at</th><td>${esc(c.last_checked_at)}</td></tr>
              </tbody>
            </table>
          </div>
        `);
      } catch (err) {
        console.error('[papers_import] erro cache', err);
        setStatus('danger', 'Erro ao buscar cache: ' + (err && err.message ? err.message : String(err)));
      } finally {
        if (button) button.disabled = false;
      }
    }

    btnRun.addEventListener('click', function (e) {
      e.preventDefault();
      runImport();
    });

    const iaButtons = document.querySelectorAll('.btn-run-ia');
    console.log('[papers_import] botoes IA encontrados:', iaButtons.length);
    iaButtons.forEach(function (btn) {
      if (btn.dataset.boundIa === '1') return;
      btn.dataset.boundIa = '1';
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        const id = btn.getAttribute('data-id') || '';
        runAI(id, btn);
      });
    });

    const cacheButtons = document.querySelectorAll('.btn-show-cache');
    console.log('[papers_import] botoes Cache encontrados:', cacheButtons.length);
    cacheButtons.forEach(function (btn) {
      if (btn.dataset.boundCache === '1') return;
      btn.dataset.boundCache = '1';
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        const id = btn.getAttribute('data-id') || '';
        showCache(id, btn);
      });
    });

    if (tableWrapper && tableWrapper.dataset.boundDelegation !== '1') {
      tableWrapper.dataset.boundDelegation = '1';
      tableWrapper.addEventListener('click', function (e) {
        const btnIa = e.target.closest('.btn-run-ia');
        if (btnIa) {
          e.preventDefault();
          e.stopPropagation();
          const id = btnIa.getAttribute('data-id') || '';
          runAI(id, btnIa);
          return;
        }

        const btnCache = e.target.closest('.btn-show-cache');
        if (btnCache) {
          e.preventDefault();
          e.stopPropagation();
          const id = btnCache.getAttribute('data-id') || '';
          showCache(id, btnCache);
        }
      });
    }

    if (btnCloseCacheModal) {
      btnCloseCacheModal.addEventListener('click', closeCacheModal);
    }

    if (cacheModalOverlay) {
      cacheModalOverlay.addEventListener('click', function (e) {
        if (e.target === cacheModalOverlay) closeCacheModal();
      });
    }

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') closeCacheModal();
    });
  });
})();