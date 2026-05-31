<?php
declare(strict_types=1);

/* C:\xampp\htdocs\Produtividade_emp\app\views\layouts\app.php */

$pageTitle = $pageTitle ?? 'Lab Produtividad';
$subtitle = $subtitle ?? 'ProdCol';
$contentTitle = $contentTitle ?? $pageTitle;
$translatedPageTitle = t((string) $pageTitle, [], (string) $pageTitle);
$translatedSubtitle = t((string) $subtitle, [], (string) $subtitle);
$i18nPayload = json_encode(\App\Support\I18n::jsMessages(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if ($i18nPayload === false) {
    $i18nPayload = '{}';
}
?>
<!DOCTYPE html>
<html lang="<?= h(html_lang()) ?>">
<head>
    <meta charset="UTF-8">
    <title><?= h($translatedPageTitle) ?> · Lab Produtividad</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= h(csrf_token()) ?>">
    <link rel="stylesheet" href="<?= h(asset('assets/css/app.css')) ?>?v=menufix_recollapse1">
</head>
<body data-base-path="<?= h(base_path()) ?>">
<div class="app-shell">
    <aside class="sidebar">
        <div class="sidebar__inner">
            <section class="menu-group" data-menu-group="admin-diagnosis">
                <button type="button" class="menu-group__pill menu-group__pill--indigo" data-menu-toggle aria-expanded="true" aria-controls="menu-admin-diagnosis">
                    <span><?= h(t('menu.group_admin_diagnosis')) ?></span>
                    <span class="menu-group__arrow" aria-hidden="true">▾</span>
                </button>
                <nav class="menu-links" id="menu-admin-diagnosis" data-menu-links>
                    <a class="menu-link <?= nav_active('/diagnostico/index') ?>" data-shell-nav="true" data-nav-prefix="/diagnostico/index" href="<?= h(url('diagnostico/index.php')) ?>"><?= h(t('menu.respond_questionnaire')) ?></a>
                    <a class="menu-link <?= nav_active('/analitica') ?>" data-shell-nav="true" data-nav-prefix="/analitica" href="<?= h(url('analitica/index.php')) ?>"><?= h(t('menu.ai_analytics')) ?></a>
                    <a class="menu-link <?= nav_active('/prioridades') ?>" data-shell-nav="true" data-nav-prefix="/prioridades" href="<?= h(url('prioridades/index.php')) ?>"><?= h(t('menu.ai_priorities')) ?></a>
                    <a class="menu-link <?= nav_active('/estrategica') ?>" data-shell-nav="true" data-nav-prefix="/estrategica" href="<?= h(url('estrategica/index.php')) ?>"><?= h(t('menu.ai_strategic')) ?></a>
                    <a class="menu-link <?= nav_active('/ppt') ?>" data-shell-nav="true" data-nav-prefix="/ppt" href="<?= h(url('ppt/index.php')) ?>"><?= h(t('menu.ppt_generator')) ?></a>
                    <a class="menu-link <?= nav_active('/admin/responses') ?>" data-shell-nav="true" data-nav-prefix="/admin/responses" href="<?= h(url('admin/responses.php')) ?>"><?= h(t('menu.dashboard')) ?></a>
                    <a class="menu-link <?= nav_active('/prompts') ?>" data-shell-nav="true" data-nav-prefix="/prompts" href="<?= h(url('prompts/index.php')) ?>"><?= h(t('menu.edit_prompts')) ?></a>
                </nav>
            </section>

            <section class="menu-group" data-menu-group="diagnosis">
                <button type="button" class="menu-group__pill menu-group__pill--green" data-menu-toggle aria-expanded="true" aria-controls="menu-diagnosis">
                    <span><?= h(t('menu.group_diagnosis')) ?></span>
                    <span class="menu-group__arrow" aria-hidden="true">▾</span>
                </button>
                <nav class="menu-links" id="menu-diagnosis" data-menu-links>
                    <a class="menu-link <?= nav_active('/diagnostico/respond') ?>" data-shell-nav="true" data-nav-prefix="/diagnostico/respond" href="<?= h(url('diagnostico/respond.php')) ?>"><?= h(t('menu.respond_questionnaire')) ?></a>
                    <a class="menu-link <?= nav_active('/diagnostico/history') ?>" data-shell-nav="true" data-nav-prefix="/diagnostico/history" href="<?= h(url('diagnostico/history.php')) ?>"><?= h(t('menu.history')) ?></a>
                    <a class="menu-link <?= nav_active('/diagnostico/results') ?>" data-shell-nav="true" data-nav-prefix="/diagnostico/results" href="<?= h(url('diagnostico/results.php')) ?>"><?= h(t('menu.results')) ?></a>
                    <a class="menu-link <?= nav_active('/atividades') ?>" data-shell-nav="true" data-nav-prefix="/atividades" href="<?= h(url('atividades/index.php')) ?>"><?= h(t('menu.activities')) ?></a>
                </nav>
            </section>

            <section class="menu-group" data-menu-group="bibliography">
                <button type="button" class="menu-group__pill menu-group__pill--purple" data-menu-toggle aria-expanded="true" aria-controls="menu-bibliography">
                    <span><?= h(t('menu.group_bibliography')) ?></span>
                    <span class="menu-group__arrow" aria-hidden="true">▾</span>
                </button>
                <nav class="menu-links" id="menu-bibliography" data-menu-links>
                    <a class="menu-link <?= nav_active('/papers/import') ?>" data-shell-nav="true" data-nav-prefix="/papers/import" href="<?= h(url('papers/import.php')) ?>"><?= h(t('menu.import_bibliography')) ?></a>
                    <a class="menu-link <?= nav_active('/papers') ?>" data-shell-nav="true" data-nav-prefix="/papers" href="<?= h(url('papers/index.php')) ?>"><?= h(t('menu.texts_relation')) ?></a>
                    <a class="menu-link <?= nav_active('/papers/report') ?>" data-shell-nav="true" data-nav-prefix="/papers/report" href="<?= h(url('papers/report.php')) ?>"><?= h(t('menu.evaluate_results')) ?></a>
                    <a class="menu-link <?= nav_active('/papers/prompts') ?>" data-shell-nav="true" data-nav-prefix="/papers/prompts" href="<?= h(url('papers/prompts.php')) ?>"><?= h(t('menu.prompt_flow')) ?></a>
                    <a class="menu-link <?= nav_active('/papers/chapters') ?>" data-shell-nav="true" data-nav-prefix="/papers/chapters" href="<?= h(url('papers/chapters.php')) ?>"><?= h(t('menu.chapters_publications')) ?></a>
                </nav>
            </section>

            <section class="menu-group" data-menu-group="billing">
                <button type="button" class="menu-group__pill menu-group__pill--indigo" data-menu-toggle aria-expanded="true" aria-controls="menu-billing">
                    <span><?= h(t('menu.group_billing')) ?></span>
                    <span class="menu-group__arrow" aria-hidden="true">▾</span>
                </button>
                <nav class="menu-links" id="menu-billing" data-menu-links>
                    <a class="menu-link <?= nav_active('/clientes/cadastro') ?>" data-shell-nav="true" data-nav-prefix="/clientes/cadastro" href="<?= h(url('clientes/cadastro.php')) ?>"><?= h(t('menu.register_client')) ?></a>
                    <a class="menu-link <?= nav_active('/clientes/faturamento') ?>" data-shell-nav="true" data-nav-prefix="/clientes/faturamento" href="<?= h(url('clientes/faturamento.php')) ?>">Relatorio de Faturamento</a>
                    <a class="menu-link <?= nav_active('/clientes') ?>" data-shell-nav="true" data-nav-prefix="/clientes" href="<?= h(url('clientes/index.php')) ?>"><?= h(t('menu.list_clients')) ?></a>
                </nav>
            </section>

            <section class="menu-group menu-group--tools" data-menu-group="tools">
                <button type="button" class="menu-group__pill menu-group__pill--purple" data-menu-toggle aria-expanded="true" aria-controls="menu-tools">
                    <span><?= h(t('menu.tools') ?: 'Ferramentas') ?></span>
                    <span class="menu-group__arrow" aria-hidden="true">▾</span>
                </button>
                <div class="sidebar__footer" id="menu-tools" data-menu-links>
                    <a class="quick-pill quick-pill--rose <?= nav_active('/prompts') ?>" data-shell-nav="true" data-nav-prefix="/prompts" href="<?= h(url('prompts/index.php')) ?>"><?= h(t('menu.edit_prompts')) ?></a>
                    <a class="quick-pill quick-pill--orange <?= nav_active('/grupos') ?>" data-shell-nav="true" data-nav-prefix="/grupos" href="<?= h(url('grupos/index.php')) ?>"><?= h(t('menu.edit_groups')) ?></a>
                    <a class="quick-pill quick-pill--amber <?= nav_active('/agentes') ?>" data-shell-nav="true" data-nav-prefix="/agentes" href="<?= h(url('agentes/index.php')) ?>"><?= h(t('menu.configure_agents')) ?></a>
                    <a class="quick-pill quick-pill--brown <?= nav_active('/fields') ?>" data-shell-nav="true" data-nav-prefix="/fields" href="<?= h(url('fields/index.php')) ?>"><?= h(t('menu.configure_questionnaire')) ?></a>
                    <a class="quick-pill quick-pill--orange <?= nav_active('/tela_sql') ?>" data-shell-nav="true" data-nav-prefix="/tela_sql" href="<?= h(url('tela_sql/index.php')) ?>"><?= h(t('menu.sql_sentences')) ?></a>
                    <a class="quick-pill quick-pill--rose <?= nav_active('/wms/dashboard') ?>" data-shell-nav="true" data-nav-prefix="/wms/dashboard" href="<?= h(url('wms/dashboard.php')) ?>"><?= h(t('menu.wms_bi')) ?></a>
                    <a class="quick-pill quick-pill--amber <?= nav_active('/DAMODARAM') ?>" data-shell-nav="true" data-nav-prefix="/DAMODARAM" href="<?= h(url('DAMODARAM/index.php')) ?>"><?= h(t('menu.damodaran_bi')) ?></a>
                    <a class="quick-pill quick-pill--rose <?= nav_active('/clientes') ?>" data-shell-nav="true" data-nav-prefix="/clientes" href="<?= h(url('clientes/index.php')) ?>"><?= h(t('menu.clients')) ?></a>
                </div>
            </section>
        </div>
    </aside>

    <div class="workspace">
        <div class="workspace__inner">
            <header class="hero-card">
                <div>
                    <h1 class="hero-card__title">Lab Produtividad</h1>
                    <div class="hero-card__subtitle"><?= h($translatedSubtitle) ?></div>
                    <div class="hero-card__badges">
                        <span class="badge badge--green"><?= h(t('layout.user')) ?>: <?= h(session_user_name()) ?></span>
                        <span class="badge badge--blue"><?= h(t('layout.email')) ?>: <?= h(session_user_email() !== '' ? session_user_email() : t('layout.email_not_informed')) ?></span>
                        <span class="badge badge--dark"><?= h(t('layout.level')) ?>: <?= h(session_user_level()) ?></span>
                    </div>
                </div>

                <div class="hero-card__language" style="display:flex;align-items:center;gap:.5rem;margin-left:auto;margin-right:1rem;">
                    <label for="app-language" style="font-size:.82rem;font-weight:700;color:#475569;"><?= h(t('layout.language')) ?></label>
                    <select id="app-language" name="lang" style="border:1px solid #cbd5e1;border-radius:999px;padding:.35rem .65rem;background:#fff;" onchange="(function(s){var href=window.location.href;var hash='';var hashPos=href.indexOf('#');if(hashPos>=0){hash=href.substring(hashPos);href=href.substring(0,hashPos);}var parts=href.split('?');var base=parts[0];var params=[];if(parts.length>1&&parts[1]){var raw=parts.slice(1).join('?').split('&');for(var i=0;i<raw.length;i++){if(raw[i]&&raw[i].split('=')[0]!=='lang'){params.push(raw[i]);}}}params.push('lang='+encodeURIComponent(s.value));window.location.href=base+'?'+params.join('&')+hash;})(this)">
                        <?php foreach (available_langs() as $langCode => $langLabel): ?>
                            <option value="<?= h($langCode) ?>" <?= current_lang() === $langCode ? 'selected' : '' ?>><?= h($langLabel) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="hero-card__logo">
                    <img src="<?= h(asset('assets/img/xcelera-logo.svg')) ?>" alt="XCELERA">
                </div>
            </header>

            <section id="shell-content" class="content-stage js-shell-content" data-current-path="<?= h(current_path()) ?>">
                <?= \App\Support\I18n::translateHtml((string) $content) ?>
            </section>
        </div>
    </div>
</div>
<?php
$chatCandidates = [
    dirname(__DIR__, 3) . '/CHAT/widget.php',
    dirname(__DIR__, 4) . '/CHAT/widget.php',
    dirname(__DIR__, 5) . '/CHAT/widget.php',
];

foreach ($chatCandidates as $chatFile) {
    if (is_file($chatFile)) {
        include_once $chatFile;
        break;
    }
}
?>
<script>window.APP_LANG = <?= json_encode(current_lang(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>; window.APP_I18N = <?= $i18nPayload ?>;</script>
<script src="<?= h(asset('assets/js/shell.js')) ?>?v=<?= h($shellAssetVersion ?? 'mp_sdk_shell_fix1') ?>"></script>
</body>
</html>
