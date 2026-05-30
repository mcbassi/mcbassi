<?php
declare(strict_types=1);

/**
 * View: app/views/diagnostico/formulario.php
 * Correção: arquivo limpo, sem blocos PHP duplicados/corrompidos.
 */

$answers = is_array($answers ?? null) ? $answers : [];
$fields = is_array($fields ?? null) ? $fields : [];
$versions = is_array($versions ?? null) ? $versions : [];
$stats = is_array($stats ?? null) ? $stats : [];
$validationErrors = is_array($validationErrors ?? null) ? $validationErrors : [];

$selectedVersion = is_array($selectedVersion ?? null) ? $selectedVersion : null;
$latestVersion = is_array($latestVersion ?? null) ? $latestVersion : null;

$selectedVersionId = (int) ($selectedVersion['id'] ?? 0);
$latestVersionId = (int) ($latestVersion['id'] ?? 0);
$isLatest = $latestVersion !== null && $selectedVersionId > 0 && $selectedVersionId === $latestVersionId;

$isDraftFromLatest = (bool) ($isDraftFromLatest ?? false);
$sourceVersion = is_array($sourceVersion ?? null) ? $sourceVersion : $selectedVersion;

$companyName = trim((string) (
    $selectedVersion['company_name']
    ?? $latestVersion['company_name']
    ?? $_SESSION['company_name']
    ?? $_SESSION['empresa']
    ?? 'Sem empresa'
));

if (!function_exists('diagnostico_format_dt')) {
    function diagnostico_format_dt(?string $value): string
    {
        if ($value === null || trim($value) === '') {
            return '—';
        }

        try {
            return (new DateTimeImmutable($value))->format('d/m/Y H:i');
        } catch (Throwable) {
            return (string) $value;
        }
    }
}

if (!function_exists('diagnostico_input_name')) {
    function diagnostico_input_name(array $field): string
    {
        return trim((string) ($field['name'] ?? ''));
    }
}

if (!function_exists('diagnostico_field_options')) {
    function diagnostico_field_options(array $field): array
    {
        $options = $field['options'] ?? [];

        if (is_string($options)) {
            $decoded = json_decode($options, true);
            if (is_array($decoded)) {
                $options = $decoded;
            } else {
                $options = preg_split('/\r\n|\r|\n|;|\|/', $options) ?: [];
            }
        }

        if (!is_array($options)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn ($item): string => trim((string) $item),
            $options
        ), static fn (string $item): bool => $item !== ''));
    }
}

if (!function_exists('diagnostico_field_value')) {
    function diagnostico_field_value(array $answers, string $name): string
    {
        $value = $answers[$name] ?? '';

        if (is_array($value)) {
            return implode('; ', array_map(static fn ($item): string => trim((string) $item), $value));
        }

        return (string) $value;
    }
}

$currentCompany = trim((string) ($companyContext ?? $companyName));

$editLatestHref = url('diagnostico/index.php' . ($currentCompany !== '' ? '?company=' . rawurlencode($currentCompany) : ''));
$newSessionHref = url('diagnostico/index.php?new=1' . ($currentCompany !== '' ? '&company=' . rawurlencode($currentCompany) : ''));
$historyHref = url('diagnostico/history.php' . ($currentCompany !== '' ? '?company=' . rawurlencode($currentCompany) : ''));

$resultsHref = $selectedVersion !== null
    ? url('diagnostico/results.php?version=' . rawurlencode((string) $selectedVersionId) . ($currentCompany !== '' ? '&company=' . rawurlencode($currentCompany) : ''))
    : url('diagnostico/results.php' . ($currentCompany !== '' ? '?company=' . rawurlencode($currentCompany) : ''));

$formAction = url('diagnostico/index.php' . ($currentCompany !== '' ? '?company=' . rawurlencode($currentCompany) : ''));
?>

<?php if (($notice ?? null) !== null): ?>
    <div class="alert-banner"><?= h((string) $notice) ?></div>
<?php endif; ?>

<?php if (($error ?? null) !== null): ?>
    <div class="alert-banner alert-banner--danger"><?= h((string) $error) ?></div>
<?php endif; ?>

<div class="module-toolbar module-toolbar--questionario">
    <a data-shell-nav="true" class="action-pill action-pill--outline" href="<?= h($editLatestHref) ?>"><?= h(t('diagnostico.edit_latest')) ?></a>
    <a data-shell-nav="true" class="action-pill action-pill--amber" href="<?= h($newSessionHref) ?>"><?= h(t('diagnostico.new_session')) ?></a>
    <a data-shell-nav="true" class="action-pill action-pill--outline" href="<?= h($historyHref) ?>"><?= h(t('menu.history')) ?></a>
    <a data-shell-nav="true" class="action-pill action-pill--danger" href="<?= h($resultsHref) ?>"><?= h(t('menu.results')) ?></a>
</div>

<section class="questionnaire-hero-panel">
    <div class="questionnaire-hero-panel__main">
        <h2><?= h(t('diagnostico.questionnaire_title')) ?></h2>
        <p class="questionnaire-intro">
            <?= h(t('diagnostico.questionnaire_intro')) ?>
        </p>
    </div>
    <div class="questionnaire-hero-panel__meta">
        <span class="hero-meta-chip"><strong><?= h(t('diagnostico.company_label')) ?>:</strong> <?= h($companyName !== '' ? $companyName : t('diagnostico.no_company')) ?></span>
        <span class="hero-meta-chip"><strong><?= h(t('diagnostico.responsible_label')) ?>:</strong> <?= h(session_user_email() !== '' ? session_user_email() : session_user_name()) ?></span>

        <?php if ($selectedVersion !== null): ?>
            <span class="hero-meta-chip">
                <strong><?= h(t('diagnostico.editing_version')) ?>:</strong> V<?= h((string) ($selectedVersion['version_no'] ?? 1)) ?>
            </span>
        <?php else: ?>
            <span class="hero-meta-chip"><strong><?= h(t('diagnostico.editing_version')) ?>:</strong> <?= h(t('diagnostico.new_version')) ?></span>
        <?php endif; ?>
    </div>
</section>

<section class="stats-grid stats-grid--questionario">
    <article class="stat-card">
        <span class="stat-card__label"><?= h(t('diagnostico.answered')) ?></span>
        <strong class="stat-card__value">
            <?= h((string) ($stats['answered'] ?? 0)) ?> / <?= h((string) ($stats['total'] ?? count($fields))) ?>
        </strong>
    </article>

    <article class="stat-card">
        <span class="stat-card__label"><?= h(t('diagnostico.completion_required')) ?></span>
        <strong class="stat-card__value">
            <?= h(number_format((float) ($stats['completion_pct'] ?? 0), 1, ',', '.')) ?>%
        </strong>
    </article>

    <article class="stat-card">
        <span class="stat-card__label"><?= h(t('diagnostico.required_pending')) ?></span>
        <strong class="stat-card__value"><?= h((string) ($stats['required_pending'] ?? 0)) ?></strong>
    </article>

    <article class="stat-card">
        <span class="stat-card__label"><?= h(t('diagnostico.display_version')) ?></span>
        <strong class="stat-card__value stat-card__value--small">
            <?php if ($selectedVersion !== null): ?>
                V<?= h((string) ($selectedVersion['version_no'] ?? 1)) ?> · <?= h(strtoupper((string) ($selectedVersion['status'] ?? 'draft'))) ?>
            <?php else: ?>
                <?= h(t('diagnostico.new_version')) ?>
            <?php endif; ?>
        </strong>
    </article>
</section>

<div class="questionnaire-layout">
    <div class="questionnaire-layout__main">
        <div class="floating-panel floating-panel--wide">
            <article class="module-card questionnaire-card questionnaire-card--versioned">
                <header class="module-card__header module-card__header--stacked">
                    <div>
                        <h2><?= h(t('diagnostico.questionnaire')) ?></h2>
                        <p class="muted">
                            <?php if ($selectedVersion !== null): ?>
                                <?php if ($isDraftFromLatest): ?>
                                    <?= h(t('diagnostico.new_from_latest', ['date' => diagnostico_format_dt((string) ($selectedVersion['response_datetime'] ?? ''))])) ?>
                                <?php elseif ($isLatest): ?>
                                    <?= h(t('diagnostico.editing_latest', ['date' => diagnostico_format_dt((string) ($selectedVersion['response_datetime'] ?? ''))])) ?>
                                <?php else: ?>
                                    <?= h(t('diagnostico.consulting_previous')) ?>
                                <?php endif; ?>
                            <?php else: ?>
                                <?= h(t('diagnostico.no_versions_for_user')) ?>
                            <?php endif; ?>
                        </p>
                    </div>
                </header>

                <form method="post" action="<?= h($formAction) ?>" class="questionnaire-form questionnaire-form--single">
                    <?= csrf_input() ?>

                    <?php if ($selectedVersion !== null): ?>
                        <input type="hidden" name="source_session_id" value="<?= h((string) $selectedVersionId) ?>">
                    <?php endif; ?>

                    <?php if ($currentCompany !== ''): ?>
                        <input type="hidden" name="company" value="<?= h($currentCompany) ?>">
                    <?php endif; ?>

                    <div class="questionnaire-flow">
                        <?php foreach ($fields as $field): ?>
                            <?php
                                $type = strtolower(trim((string) ($field['type'] ?? 'text')));
                                $label = trim((string) ($field['label'] ?? ''));
                                $name = diagnostico_input_name($field);
                            ?>

                            <?php if ($type === 'title'): ?>
                                <div class="title-banner questionnaire-title-banner">
                                    <?= h($label !== '' ? $label : t('diagnostico.title_fallback')) ?>
                                </div>
                                <?php continue; ?>
                            <?php endif; ?>

                            <?php if ($type === 'subtitle'): ?>
                                <div class="subsection-band subsection-band--questionnaire">
                                    <?= h($label !== '' ? $label : t('diagnostico.subtitle_fallback')) ?>
                                </div>
                                <?php continue; ?>
                            <?php endif; ?>

                            <?php
                                if ($name === '') {
                                    continue;
                                }

                                $value = diagnostico_field_value($answers, $name);
                                $required = !empty($field['required']);
                                $errorMessage = $validationErrors[$name] ?? null;
                                $options = diagnostico_field_options($field);
                                $inputId = 'field_' . preg_replace('/[^A-Za-z0-9_]+/u', '_', $name);
                            ?>

                            <label class="question-field question-field--full question-field--stacked<?= $errorMessage !== null ? ' question-field--invalid' : '' ?>" for="<?= h($inputId) ?>">
                                <span class="question-field__header">
                                    <span class="question-field__label<?= $errorMessage !== null ? ' question-field__label--invalid' : '' ?>">
                                        <?= h($label !== '' ? $label : $name) ?>
                                    </span>

                                    <span class="question-field__meta question-field__meta--inline">
                                        <?php if ($required): ?>
                                            <span class="required-dot"><?= h(t('common.required')) ?></span>
                                        <?php endif; ?>
                                    </span>
                                </span>

                                <?php if ($type === 'textarea'): ?>
                                    <textarea
                                        id="<?= h($inputId) ?>"
                                        name="<?= h($name) ?>"
                                        rows="4"
                                        placeholder="<?= h((string) ($field['placeholder'] ?? '')) ?>"
                                        class="<?= $errorMessage !== null ? 'is-invalid' : '' ?>"
                                        aria-invalid="<?= $errorMessage !== null ? 'true' : 'false' ?>"
                                    ><?= h($value) ?></textarea>

                                <?php elseif ($type === 'select'): ?>
                                    <select
                                        id="<?= h($inputId) ?>"
                                        name="<?= h($name) ?>"
                                        class="<?= $errorMessage !== null ? 'is-invalid' : '' ?>"
                                        aria-invalid="<?= $errorMessage !== null ? 'true' : 'false' ?>"
                                    >
                                        <option value=""><?= h(t('common.select_placeholder')) ?></option>
                                        <?php foreach ($options as $optionString): ?>
                                            <option value="<?= h($optionString) ?>" <?= $optionString === $value ? 'selected' : '' ?>>
                                                <?= h($optionString) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>

                                <?php elseif ($type === 'radio'): ?>
                                    <div class="question-choices question-choices--vertical <?= $errorMessage !== null ? 'is-invalid' : '' ?>" aria-invalid="<?= $errorMessage !== null ? 'true' : 'false' ?>">
                                        <?php foreach ($options as $optionString): ?>
                                            <?php $choiceId = $inputId . '_' . preg_replace('/[^A-Za-z0-9_]+/u', '_', $optionString); ?>
                                            <label class="choice-pill choice-pill--block" for="<?= h($choiceId) ?>">
                                                <input
                                                    id="<?= h($choiceId) ?>"
                                                    type="radio"
                                                    name="<?= h($name) ?>"
                                                    value="<?= h($optionString) ?>"
                                                    <?= $optionString === $value ? 'checked' : '' ?>
                                                >
                                                <span><?= h($optionString) ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>

                                <?php elseif ($type === 'checkbox'): ?>
                                    <?php
                                        $selectedValues = array_values(array_filter(
                                            array_map('trim', preg_split('/[;,]+/', $value) ?: []),
                                            static fn (string $item): bool => $item !== ''
                                        ));
                                    ?>
                                    <div class="question-choices question-choices--vertical <?= $errorMessage !== null ? 'is-invalid' : '' ?>" aria-invalid="<?= $errorMessage !== null ? 'true' : 'false' ?>">
                                        <?php foreach ($options as $optionString): ?>
                                            <?php $choiceId = $inputId . '_' . preg_replace('/[^A-Za-z0-9_]+/u', '_', $optionString); ?>
                                            <label class="choice-pill choice-pill--checkbox choice-pill--block" for="<?= h($choiceId) ?>">
                                                <input
                                                    id="<?= h($choiceId) ?>"
                                                    type="checkbox"
                                                    name="<?= h($name) ?>[]"
                                                    value="<?= h($optionString) ?>"
                                                    <?= in_array($optionString, $selectedValues, true) ? 'checked' : '' ?>
                                                >
                                                <span><?= h($optionString) ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>

                                <?php else: ?>
                                    <input
                                        id="<?= h($inputId) ?>"
                                        type="<?= h(in_array($type, ['email', 'number', 'date'], true) ? $type : 'text') ?>"
                                        name="<?= h($name) ?>"
                                        value="<?= h($value) ?>"
                                        placeholder="<?= h((string) ($field['placeholder'] ?? '')) ?>"
                                        class="<?= $errorMessage !== null ? 'is-invalid' : '' ?>"
                                        aria-invalid="<?= $errorMessage !== null ? 'true' : 'false' ?>"
                                        <?php if (($field['min'] ?? null) !== null && $field['min'] !== ''): ?> min="<?= h((string) $field['min']) ?>"<?php endif; ?>
                                        <?php if (($field['max'] ?? null) !== null && $field['max'] !== ''): ?> max="<?= h((string) $field['max']) ?>"<?php endif; ?>
                                        <?php if (($field['step'] ?? null) !== null && $field['step'] !== ''): ?> step="<?= h((string) $field['step']) ?>"<?php endif; ?>
                                    >
                                <?php endif; ?>

                                <?php if ($errorMessage !== null): ?>
                                    <span class="field-error"><?= h((string) $errorMessage) ?></span>
                                <?php endif; ?>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <footer class="form-actions form-actions--sticky">
                        <button class="primary-button" type="submit" name="save_mode" value="draft"><?= h(t('diagnostico.save_partial_version')) ?></button>
                        <button class="secondary-button" type="submit" name="save_mode" value="complete"><?= h(t('diagnostico.save_complete_version')) ?></button>
                    </footer>
                </form>
            </article>
        </div>
    </div>

    <aside class="questionnaire-layout__side">
        <article class="module-card version-sidebar">
            <header class="module-card__header module-card__header--stacked">
                <div>
                    <h2><?= h(t('diagnostico.saved_versions')) ?></h2>
                    <p class="muted"><?= h(t('diagnostico.latest_used_for_processing')) ?></p>
                </div>
            </header>

            <div class="version-sidebar__list">
                <?php if ($versions === []): ?>
                    <div class="version-empty"><?= h(t('diagnostico.no_saved_versions')) ?></div>
                <?php endif; ?>

                <?php foreach ($versions as $version): ?>
                    <?php
                        if (!is_array($version)) {
                            continue;
                        }

                        $versionId = (int) ($version['id'] ?? 0);
                        $isActive = $selectedVersionId > 0 && $selectedVersionId === $versionId;
                        $status = strtoupper((string) ($version['status'] ?? 'draft'));

                        $versionHref = url(
                            'diagnostico/index.php?version=' . rawurlencode((string) $versionId)
                            . ($currentCompany !== '' ? '&company=' . rawurlencode($currentCompany) : '')
                        );
                    ?>

                    <a
                        data-shell-nav="true"
                        class="version-card <?= $isActive ? 'is-active' : '' ?>"
                        href="<?= h($versionHref) ?>"
                    >
                        <div class="version-card__top">
                            <strong>V<?= h((string) ($version['version_no'] ?? 1)) ?></strong>
                            <span class="version-badge version-badge--<?= h(strtolower((string) ($version['status'] ?? 'draft'))) ?>">
                                <?= h(t('diagnostico.status_' . strtolower($status), [], $status)) ?>
                            </span>
                        </div>

                        <div class="version-card__meta"><?= h((string) ($version['company_name'] ?? t('diagnostico.no_company'))) ?></div>
                        <div class="version-card__meta"><?= h(diagnostico_format_dt((string) ($version['response_datetime'] ?? ''))) ?></div>

                        <div class="version-card__progress">
                            <span><?= h((string) ((int) ($version['answered_count'] ?? 0))) ?> <?= h(t('common.answers')) ?></span>

                            <?php if ((int) ($version['total_questions'] ?? 0) > 0): ?>
                                <span><?= h(number_format((float) ($version['completion_pct'] ?? 0), 1, ',', '.')) ?>%</span>
                            <?php endif; ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </article>
    </aside>
</div>
