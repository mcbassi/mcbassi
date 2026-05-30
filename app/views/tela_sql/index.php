<?php
declare(strict_types=1);
$iframeSrc = url('tela_sql/legacy/index.php');
?>
<style>
.tsql-embed-card{background:#fff;border:1px solid #e6edf6;border-radius:20px;box-shadow:0 10px 30px rgba(15,23,42,.05);overflow:hidden}
.tsql-embed-head{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:16px 18px;border-bottom:1px solid #edf2f7}
.tsql-embed-head h2{margin:0;font-size:1.2rem}
.tsql-embed-head p{margin:4px 0 0;color:#64748b;font-size:.92rem}
.tsql-embed-frame{width:100%;border:0;display:block;min-height:960px;background:#f8fafc}
</style>

<div class="module-toolbar">
    <a data-shell-nav="true" class="action-pill action-pill--outline" href="<?= h(url('index.php')) ?>">Dashboard</a>
    <a data-shell-nav="true" class="action-pill action-pill--amber" href="<?= h(url('tela_sql/index.php')) ?>">Recarregar módulo</a>
</div>

<article class="tsql-embed-card">
    <header class="tsql-embed-head">
        <div>
            <h2>SQL Sentences / DataSmart</h2>
            <p>Versão funcional legada adaptada ao shell novo, preservando Builder, SQL, Prompt e Catálogo.</p>
        </div>
        <a class="action-pill action-pill--ghost" href="<?= h($iframeSrc) ?>" target="_blank" rel="noopener">Abrir em nova aba</a>
    </header>

    <iframe id="telaSqlFrame" class="tsql-embed-frame" src="<?= h($iframeSrc) ?>" loading="lazy"></iframe>
</article>

<script>
(function(){
  const frame = document.getElementById('telaSqlFrame');
  if (!frame) return;
  function adjust(){
    const rect = frame.getBoundingClientRect();
    const vh = window.innerHeight || 900;
    const h = Math.max(780, vh - rect.top - 24);
    frame.style.height = h + 'px';
  }
  window.addEventListener('resize', adjust);
  frame.addEventListener('load', adjust);
  setTimeout(adjust, 100);
})();
</script>
