<?php
declare(strict_types=1);

$embedUrl = (string) ($embedUrl ?? '/ppt_dynamic_builder/public/admin_presentations.php');
?>
<style>
.ppt-embed-shell{
    display:grid;
    gap:16px;
}

.ppt-embed-card{
    background:#fff;
    border:1px solid #e2e8f0;
    border-radius:18px;
    box-shadow:0 10px 28px rgba(15,23,42,.06);
    overflow:hidden;
}

.ppt-embed-card__head{
    padding:16px 18px;
    border-bottom:1px solid #e2e8f0;
    background:#f8fafc;
}

.ppt-embed-card__title{
    margin:0;
    font-size:1.05rem;
    font-weight:700;
    color:#0f172a;
}

.ppt-embed-card__subtitle{
    margin:6px 0 0;
    color:#64748b;
    font-size:.92rem;
}

.ppt-embed-frame-wrap{
    position:relative;
    min-height:calc(100vh - 240px);
    background:#fff;
}

.ppt-embed-frame{
    display:block;
    width:100%;
    min-height:calc(100vh - 240px);
    border:0;
    background:#fff;
}

.ppt-embed-loading{
    position:absolute;
    inset:0;
    display:flex;
    align-items:center;
    justify-content:center;
    color:#475569;
    font-size:.95rem;
    background:
        linear-gradient(180deg, rgba(248,250,252,.96), rgba(255,255,255,.96));
}

.ppt-embed-loading.is-hidden{
    display:none;
}
</style>

<div class="ppt-embed-shell">
    <section class="ppt-embed-card">
        <header class="ppt-embed-card__head">
            <h2 class="ppt-embed-card__title">Gerador de PPT</h2>
            <p class="ppt-embed-card__subtitle">
                O módulo abaixo é carregado diretamente de <code>ppt_dynamic_builder</code> dentro desta aplicação.
            </p>
        </header>

        <div class="ppt-embed-frame-wrap">
            <div id="ppt-embed-loading" class="ppt-embed-loading">Carregando módulo de apresentações...</div>

            <iframe
                id="ppt-embed-frame"
                class="ppt-embed-frame"
                src="<?= h($embedUrl) ?>"
                title="PPT Dynamic Builder"
                loading="eager"
                referrerpolicy="same-origin"
            ></iframe>
        </div>
    </section>
</div>

<script>
(function () {
    const frame = document.getElementById('ppt-embed-frame');
    const loading = document.getElementById('ppt-embed-loading');

    if (!frame || !loading) {
        return;
    }

    frame.addEventListener('load', function () {
        loading.classList.add('is-hidden');
    });
})();
</script>