<?php
declare(strict_types=1);

$answers = is_array($answers ?? null) ? $answers : [];
$fields = is_array($fields ?? null) ? $fields : [];
$stats = is_array($stats ?? null) ? $stats : [];
$validationErrors = is_array($validationErrors ?? null) ? $validationErrors : [];
$selectedVersion = is_array($selectedVersion ?? null) ? $selectedVersion : null;
$latestVersion = is_array($latestVersion ?? null) ? $latestVersion : null;
$latestIncomplete = is_array($latestIncomplete ?? null) ? $latestIncomplete : null;

$selectedVersionId = (int) ($selectedVersion['id'] ?? 0);
$companyName = trim((string) (($selectedVersion['company_name'] ?? $latestVersion['company_name'] ?? $_SESSION['company_name'] ?? $_SESSION['empresa'] ?? 'Sem empresa')));

if (!function_exists('diagnostico_client_format_dt')) {
    function diagnostico_client_format_dt(?string $value): string
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

$currentCompany = trim((string) ($companyContext ?? $companyName));
$isEmbedMode = (string) ($_GET['embed'] ?? '') === '1';

$newSessionQuery = ['new' => '1'];
if ($currentCompany !== '') {
    $newSessionQuery['company'] = $currentCompany;
}
if ($isEmbedMode) {
    $newSessionQuery['embed'] = '1';
}
$newSessionHref = url('diagnostico/respond.php?' . http_build_query($newSessionQuery));

$resumeHref = null;
if ($latestIncomplete !== null) {
    $resumeQuery = ['continue' => '1'];
    if ($currentCompany !== '') {
        $resumeQuery['company'] = $currentCompany;
    }
    if ($isEmbedMode) {
        $resumeQuery['embed'] = '1';
    }
    $resumeHref = url('diagnostico/respond.php?' . http_build_query($resumeQuery));
}

$formQuery = [];
if ($currentCompany !== '') {
    $formQuery['company'] = $currentCompany;
}
if ($isEmbedMode) {
    $formQuery['embed'] = '1';
}
$formAction = url('diagnostico/respond.php' . ($formQuery !== [] ? '?' . http_build_query($formQuery) : ''));
?>
<?php if (($notice ?? null) !== null): ?>
    <div class="alert-banner"><?= h((string) $notice) ?></div>
<?php endif; ?>

<?php if (($error ?? null) !== null): ?>
    <div class="alert-banner alert-banner--danger"><?= h((string) $error) ?></div>
<?php endif; ?>

<div class="module-toolbar module-toolbar--questionario module-toolbar--questionario-cliente">
    <a data-shell-nav="true" class="action-pill action-pill--outline" href="<?= h($newSessionHref) ?>">Nova resposta</a>
    <?php if ($resumeHref !== null): ?>
        <a data-shell-nav="true" class="action-pill action-pill--amber" href="<?= h($resumeHref) ?>">Recarregar última versão</a>
    <?php endif; ?>
</div>

<section class="questionnaire-hero-panel questionnaire-hero-panel--client">
    <div class="questionnaire-hero-panel__main">
        <h2>Questionário do cliente</h2>
        <p class="questionnaire-intro">Você pode responder agora, salvar parcialmente e continuar depois. O processamento sempre considera a última versão salva.</p>
    </div>
    <div class="questionnaire-hero-panel__meta">
        <span class="hero-meta-chip"><strong>Empresa:</strong> <?= h($companyName !== '' ? $companyName : 'Sem empresa') ?></span>
        <span class="hero-meta-chip"><strong>Responsável:</strong> <?= h(session_user_email() !== '' ? session_user_email() : session_user_name()) ?></span>
        <?php if ($selectedVersion !== null): ?>
            <span class="hero-meta-chip"><strong>Versão aberta:</strong> V<?= h((string) ($selectedVersion['version_no'] ?? 1)) ?></span>
        <?php endif; ?>
    </div>
</section>

<section class="stats-grid stats-grid--questionario stats-grid--questionario-client" data-questionnaire-stats>
    <article class="stat-card stat-card--soft-blue">
        <span class="stat-card__label">Perguntas no total</span>
        <strong class="stat-card__value" data-stat-total><?= h((string) ($stats['total'] ?? 0)) ?></strong>
    </article>
    <article class="stat-card stat-card--soft-green">
        <span class="stat-card__label">Já respondidas</span>
        <strong class="stat-card__value" data-stat-answered><?= h((string) ($stats['answered'] ?? 0)) ?></strong>
    </article>
    <article class="stat-card stat-card--soft-amber">
        <span class="stat-card__label">Faltam resposta</span>
        <strong class="stat-card__value" data-stat-pending><?= h((string) ($stats['pending'] ?? 0)) ?></strong>
    </article>
    <article class="stat-card stat-card--soft-red">
        <span class="stat-card__label">Obrigatórias pendentes</span>
        <strong class="stat-card__value" data-stat-required-pending><?= h((string) ($stats['required_pending'] ?? 0)) ?></strong>
    </article>
</section>

<div class="questionnaire-layout questionnaire-layout--client">
    <div class="questionnaire-layout__main">
        <div class="floating-panel floating-panel--wide">
            <article class="module-card questionnaire-card questionnaire-card--versioned questionnaire-card--client">
                <header class="module-card__header module-card__header--stacked">
                    <div>
                        <h2>Responder questionário</h2>
                        <p class="muted">
                            <?php if ($latestIncomplete !== null): ?>
                                A última versão incompleta foi salva em <?= h(diagnostico_client_format_dt((string) ($latestIncomplete['response_datetime'] ?? ''))) ?>.
                            <?php elseif ($selectedVersion !== null): ?>
                                Última versão salva em <?= h(diagnostico_client_format_dt((string) ($selectedVersion['response_datetime'] ?? ''))) ?>.
                            <?php else: ?>
                                Este é o primeiro preenchimento deste questionário.
                            <?php endif; ?>
                        </p>
                    </div>
                </header>

                <form method="post" action="<?= h($formAction) ?>" class="questionnaire-form questionnaire-form--single questionnaire-form--client" data-questionnaire-form>
                    <?= csrf_input() ?>
                    <input type="hidden" name="company_name" value="<?= h($companyName) ?>">
                    <?php if ($selectedVersion !== null): ?>
                        <input type="hidden" name="source_session_id" value="<?= h((string) $selectedVersionId) ?>">
                    <?php endif; ?>

                    <div class="questionnaire-flow">
                        <?php foreach ($fields as $field): ?>
                            <?php
                                $type = (string) ($field['type'] ?? 'text');
                                $label = trim((string) ($field['label'] ?? ''));
                                $name = trim((string) ($field['name'] ?? ''));
                            ?>

                            <?php if ($type === 'title'): ?>
                                <div class="title-banner questionnaire-title-banner"><?= h($label !== '' ? $label : 'Título') ?></div>
                                <?php continue; ?>
                            <?php endif; ?>

                            <?php if ($type === 'subtitle'): ?>
                                <div class="subsection-band subsection-band--questionnaire"><?= h($label !== '' ? $label : 'Subtítulo') ?></div>
                                <?php continue; ?>
                            <?php endif; ?>

                            <?php
                                if ($name === '') {
                                    continue;
                                }

                                $value = (string) ($answers[$name] ?? '');
                                $required = !empty($field['required']);
                                $errorMessage = $validationErrors[$name] ?? null;
                                $fieldState = $value !== '' ? 'answered' : ($required ? 'required-pending' : 'optional-pending');
                            ?>
                            <label
                                class="question-field question-field--full question-field--stacked question-field--client question-field--state-<?= h($fieldState) ?><?= $errorMessage !== null ? ' question-field--invalid' : '' ?>"
                                data-question-field="true"
                                data-field-name="<?= h($name) ?>"
                                data-field-type="<?= h($type) ?>"
                                data-required="<?= $required ? '1' : '0' ?>"
                            >
                                <span class="question-field__header">
                                    <span class="question-field__label<?= $errorMessage !== null ? ' question-field__label--invalid' : '' ?>"><?= h((string) ($field['label'] ?? $name)) ?></span>
                                    <span class="question-field__meta question-field__meta--inline">
                                        <?php if ($required): ?><span class="required-dot">Obrigatória</span><?php endif; ?>
                                    </span>
                                </span>

                                <?php if ($type === 'textarea'): ?>
                                    <textarea
                                        name="<?= h($name) ?>"
                                        rows="4"
                                        placeholder="<?= h((string) ($field['placeholder'] ?? '')) ?>"
                                        class="<?= $errorMessage !== null ? 'is-invalid' : '' ?>"
                                        aria-invalid="<?= $errorMessage !== null ? 'true' : 'false' ?>"
                                        data-question-input="true"
                                    ><?= h($value) ?></textarea>

                                <?php elseif ($type === 'select'): ?>
                                    <select
                                        name="<?= h($name) ?>"
                                        class="<?= $errorMessage !== null ? 'is-invalid' : '' ?>"
                                        aria-invalid="<?= $errorMessage !== null ? 'true' : 'false' ?>"
                                        data-question-input="true"
                                    >
                                        <option value="">Selecione...</option>
                                        <?php foreach (($field['options'] ?? []) as $option): ?>
                                            <?php $optionString = (string) $option; ?>
                                            <option value="<?= h($optionString) ?>" <?= $optionString === $value ? 'selected' : '' ?>><?= h($optionString) ?></option>
                                        <?php endforeach; ?>
                                    </select>

                                <?php elseif ($type === 'radio'): ?>
                                    <div class="question-choices question-choices--vertical" aria-invalid="<?= $errorMessage !== null ? 'true' : 'false' ?>" data-question-input="true">
                                        <?php foreach (($field['options'] ?? []) as $option): ?>
                                            <?php
                                                $optionString = (string) $option;
                                                $id = $name . '_' . preg_replace('/[^A-Za-z0-9_]+/u', '_', $optionString);
                                            ?>
                                            <label class="choice-pill choice-pill--block" for="<?= h($id) ?>">
                                                <input
                                                    id="<?= h($id) ?>"
                                                    type="radio"
                                                    name="<?= h($name) ?>"
                                                    value="<?= h($optionString) ?>"
                                                    <?= $optionString === $value ? 'checked' : '' ?>
                                                    data-question-choice="true"
                                                >
                                                <span><?= h($optionString) ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>

                                <?php elseif ($type === 'checkbox'): ?>
                                    <?php $selectedValues = array_values(array_filter(array_map('trim', preg_split('/[;,]+/', $value) ?: []), static fn (string $item): bool => $item !== '')); ?>
                                    <div class="question-choices question-choices--vertical" aria-invalid="<?= $errorMessage !== null ? 'true' : 'false' ?>" data-question-input="true">
                                        <?php foreach (($field['options'] ?? []) as $option): ?>
                                            <?php
                                                $optionString = (string) $option;
                                                $id = $name . '_' . preg_replace('/[^A-Za-z0-9_]+/u', '_', $optionString);
                                            ?>
                                            <label class="choice-pill choice-pill--checkbox choice-pill--block" for="<?= h($id) ?>">
                                                <input
                                                    id="<?= h($id) ?>"
                                                    type="checkbox"
                                                    name="<?= h($name) ?>[]"
                                                    value="<?= h($optionString) ?>"
                                                    <?= in_array($optionString, $selectedValues, true) ? 'checked' : '' ?>
                                                    data-question-choice="true"
                                                >
                                                <span><?= h($optionString) ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>

                                <?php else: ?>
                                    <input
                                        type="<?= h(in_array($type, ['email', 'number', 'date'], true) ? $type : 'text') ?>"
                                        name="<?= h($name) ?>"
                                        value="<?= h($value) ?>"
                                        placeholder="<?= h((string) ($field['placeholder'] ?? '')) ?>"
                                        class="<?= $errorMessage !== null ? 'is-invalid' : '' ?>"
                                        aria-invalid="<?= $errorMessage !== null ? 'true' : 'false' ?>"
                                        data-question-input="true"
                                        <?php if (($field['min'] ?? null) !== null && $field['min'] !== ''): ?>min="<?= h((string) $field['min']) ?>"<?php endif; ?>
                                        <?php if (($field['max'] ?? null) !== null && $field['max'] !== ''): ?>max="<?= h((string) $field['max']) ?>"<?php endif; ?>
                                        <?php if (($field['step'] ?? null) !== null && $field['step'] !== ''): ?>step="<?= h((string) $field['step']) ?>"<?php endif; ?>
                                    >
                                <?php endif; ?>

                                <?php if ($errorMessage !== null): ?>
                                    <span class="field-error"><?= h((string) $errorMessage) ?></span>
                                <?php endif; ?>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <footer class="form-actions form-actions--sticky">
                        <button class="secondary-button" type="submit" name="save_mode" value="draft">Salvar parcialmente</button>
                        <button class="primary-button" type="submit" name="save_mode" value="complete">Salvar como completo</button>
                    </footer>
                </form>
            </article>
        </div>
    </div>
</div>

<script src="<?= h(asset('assets/js/diagnostico_cliente.js')) ?>"></script>
