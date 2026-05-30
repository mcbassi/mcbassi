<?php
declare(strict_types=1);

$pageTitle = $pageTitle ?? 'Lab Produtividad';
$subtitle = $subtitle ?? 'ProdCol';
$translatedPageTitle = t((string) $pageTitle, [], (string) $pageTitle);
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
    <link rel="stylesheet" href="<?= h(asset('assets/css/app.css')) ?>?v=client_embed_20260519">
    <style>
        html, body { min-height: 100%; }
        body.embed-body {
            margin: 0;
            background: #f6f8fb;
        }
        .embed-stage {
            width: 100%;
            max-width: none;
            padding: 16px;
            box-sizing: border-box;
        }
        .embed-stage .floating-panel,
        .embed-stage .module-card,
        .embed-stage .questionnaire-layout__main {
            max-width: none;
        }
        .embed-stage .questionnaire-layout,
        .embed-stage .stats-grid,
        .embed-stage .questionnaire-hero-panel,
        .embed-stage .module-toolbar {
            width: 100%;
        }
        .embed-stage .module-toolbar {
            margin-top: 0;
        }
        .embed-stage .form-actions--sticky {
            bottom: 0;
        }
    </style>
</head>
<body class="embed-body" data-base-path="<?= h(base_path()) ?>" data-embed-mode="1">
    <main id="client-embed-content" class="embed-stage" data-current-path="<?= h(current_path()) ?>">
        <?= \App\Support\I18n::translateHtml((string) $content) ?>
    </main>
    <script>window.APP_LANG = <?= json_encode(current_lang(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>; window.APP_I18N = <?= $i18nPayload ?>; window.APP_EMBED_MODE = true;</script>
</body>
</html>
