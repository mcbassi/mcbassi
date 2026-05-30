<?php
declare(strict_types=1);

$package = is_array($package ?? null) ? $package : [];
$items = is_array($package['items'] ?? null) ? $package['items'] : [];
$selectedVersion = is_array($package['selectedVersion'] ?? null) ? $package['selectedVersion'] : null;
$context = trim((string) ($context ?? 'analitica'));
$label = trim((string) ($label ?? ($context === 'estrategica' ? 'IA Estratégica' : 'IA Analítica')));
$companyName = trim((string) ($package['company_name'] ?? ''));
$execution = is_array($execution ?? null) ? $execution : null;
$executionError = trim((string) ($executionError ?? ''));
$executionResults = is_array($execution['results'] ?? null) ? $execution['results'] : [];
$executionSummary = is_array($execution['summary'] ?? null) ? $execution['summary'] : [];
$versions = is_array($versions ?? null) ? $versions : [];
$selectedVersionId = (int) ($selectedVersionId ?? 0);
$onlyWithPrompt = !empty($onlyWithPrompt);
$storedResponses = is_array($storedResponses ?? null) ? $storedResponses : [];

$getItemPromptCode = static function (array $item): string {
    $prompt = is_array($item['prompt'] ?? null) ? $item['prompt'] : [];
    $field = is_array($item['field'] ?? null) ? $item['field'] : [];
    $row = is_array($item['row'] ?? null) ? $item['row'] : [];

    foreach ([
        $prompt['assistente'] ?? null,
        $field['prompt_code'] ?? null,
        $row['prompt_code'] ?? null,
        $item['prompt_code'] ?? null,
    ] as $candidate) {
        $candidate = trim((string) $candidate);
        if ($candidate !== '') {
            return $candidate;
        }
    }

    return '';
};

$promptFilterOptions = [];
foreach ($items as $item) {
    if (!is_array($item)) {
        continue;
    }
    $promptCodeOption = $getItemPromptCode($item);
    if ($promptCodeOption !== '') {
        $promptFilterOptions[$promptCodeOption] = $promptCodeOption;
    }
}
natcasesort($promptFilterOptions);


$resultByQuestion = [];
foreach ($storedResponses as $questionName => $result) {
    if (is_array($result) && trim((string) ($result['response_text'] ?? '')) !== '') {
        $resultByQuestion[(string) $questionName] = $result;
    }
}
foreach ($executionResults as $result) {
    if (!is_array($result)) {
        continue;
    }
    $q = trim((string) ($result['question_name'] ?? ''));
    if ($q !== '') {
        $resultByQuestion[$q] = $result;
    }
}

$postTarget = url(trim($context) . '/index.php');
$getTarget = url(trim($context) . '/index.php');
$renderIaInline = static function (string $text): string {
    $text = h($text);
    $text = preg_replace('/\*\*([^*]+)\*\*/u', '<strong>$1</strong>', $text) ?? $text;
    $text = preg_replace('/`([^`]+)`/u', '<code>$1</code>', $text) ?? $text;
    return $text;
};

$parseIaTableRow = static function (string $line): array {
    $line = trim($line);
    $line = preg_replace('/^\||\|$/', '', $line) ?? $line;
    return array_map(static fn(string $cell): string => trim($cell), explode('|', $line));
};

$isIaSeparatorRow = static function (string $line): bool {
    $line = trim($line);
    if ($line === '' || strpos($line, '|') === false) {
        return false;
    }
    $line = preg_replace('/^\||\|$/', '', $line) ?? $line;
    $parts = array_map('trim', explode('|', $line));
    if ($parts === []) {
        return false;
    }
    foreach ($parts as $part) {
        if ($part === '') {
            continue;
        }
        if (!preg_match('/^:?-{3,}:?$/', $part)) {
            return false;
        }
    }
    return true;
};

$formatIaResult = static function (string $text) use ($renderIaInline, $parseIaTableRow, $isIaSeparatorRow): string {
    $text = trim(str_replace(["\r\n", "\r"], "\n", $text));
    if ($text === '') {
        return '<span class="analitica-result-placeholder">Aguardando execução</span>';
    }

    $lines = explode("\n", $text);
    $html = [];
    $paragraph = [];
    $inUl = false;
    $inOl = false;
    $inCode = false;
    $codeBuffer = [];
    $count = count($lines);

    $flushParagraph = static function () use (&$paragraph, &$html, $renderIaInline): void {
        if ($paragraph === []) {
            return;
        }
        $joined = trim(implode(' ', $paragraph));
        if ($joined !== '') {
            $html[] = '<p>' . $renderIaInline($joined) . '</p>';
        }
        $paragraph = [];
    };

    $closeLists = static function () use (&$inUl, &$inOl, &$html): void {
        if ($inUl) {
            $html[] = '</ul>';
            $inUl = false;
        }
        if ($inOl) {
            $html[] = '</ol>';
            $inOl = false;
        }
    };

    for ($i = 0; $i < $count; $i++) {
        $line = $lines[$i];
        $trim = trim($line);

        if (preg_match('/^```/', $trim)) {
            $flushParagraph();
            $closeLists();
            if ($inCode) {
                $html[] = '<pre class="analitica-result-pre"><code>' . h(implode("\n", $codeBuffer)) . '</code></pre>';
                $codeBuffer = [];
                $inCode = false;
            } else {
                $inCode = true;
            }
            continue;
        }

        if ($inCode) {
            $codeBuffer[] = $line;
            continue;
        }

        if ($trim === '') {
            $flushParagraph();
            $closeLists();
            continue;
        }

        if (strpos($trim, '|') !== false && isset($lines[$i + 1]) && $isIaSeparatorRow($lines[$i + 1])) {
            $flushParagraph();
            $closeLists();
            $header = $parseIaTableRow($trim);
            $i++;
            $rows = [];
            while (isset($lines[$i + 1])) {
                $next = trim($lines[$i + 1]);
                if ($next === '' || strpos($next, '|') === false || $isIaSeparatorRow($next)) {
                    break;
                }
                $i++;
                $rows[] = $parseIaTableRow($next);
            }
            $html[] = '<div class="analitica-result-table-wrap"><table class="analitica-result-table"><thead><tr>';
            foreach ($header as $cell) {
                $html[] = '<th>' . $renderIaInline($cell) . '</th>';
            }
            $html[] = '</tr></thead><tbody>';
            foreach ($rows as $row) {
                $html[] = '<tr>';
                foreach ($row as $cell) {
                    $html[] = '<td>' . $renderIaInline($cell) . '</td>';
                }
                $html[] = '</tr>';
            }
            $html[] = '</tbody></table></div>';
            continue;
        }

        if (preg_match('/^(#{1,6})\s+(.+)$/u', $trim, $m)) {
            $flushParagraph();
            $closeLists();
            $level = strlen($m[1]);
            $html[] = '<h' . $level . '>' . $renderIaInline($m[2]) . '</h' . $level . '>';
            continue;
        }

        if (preg_match('/^(?:-|\*)\s+(.+)$/u', $trim, $m)) {
            $flushParagraph();
            if ($inOl) {
                $html[] = '</ol>';
                $inOl = false;
            }
            if (!$inUl) {
                $html[] = '<ul>';
                $inUl = true;
            }
            $html[] = '<li>' . $renderIaInline($m[1]) . '</li>';
            continue;
        }

        if (preg_match('/^\d+[\.)]\s+(.+)$/u', $trim, $m)) {
            $flushParagraph();
            if ($inUl) {
                $html[] = '</ul>';
                $inUl = false;
            }
            if (!$inOl) {
                $html[] = '<ol>';
                $inOl = true;
            }
            $html[] = '<li>' . $renderIaInline($m[1]) . '</li>';
            continue;
        }

        $paragraph[] = $trim;
    }

    if ($inCode) {
        $html[] = '<pre class="analitica-result-pre"><code>' . h(implode("\n", $codeBuffer)) . '</code></pre>';
    }
    $flushParagraph();
    $closeLists();

    $final = trim(implode("\n", $html));
    return $final !== ''
        ? '<div class="analitica-result-html">' . $final . '</div>'
        : '<span class="analitica-result-placeholder">Aguardando execução</span>';
};
?>
<style>
.analitica-result-html{font-size:.95rem;line-height:1.55;color:#1f2937}
.analitica-result-html h1,.analitica-result-html h2,.analitica-result-html h3,.analitica-result-html h4,.analitica-result-html h5,.analitica-result-html h6{margin:.9rem 0 .45rem;line-height:1.25;color:#0f172a}
.analitica-result-html h1{font-size:1.28rem}.analitica-result-html h2{font-size:1.18rem}.analitica-result-html h3{font-size:1.08rem}
.analitica-result-html p{margin:.45rem 0}
.analitica-result-html ul,.analitica-result-html ol{margin:.45rem 0 .7rem 1.2rem;padding:0}
.analitica-result-html li{margin:.18rem 0}
.analitica-result-html code{background:#f3f4f6;border-radius:4px;padding:.08rem .28rem;font-size:.92em}
.analitica-result-pre{white-space:pre-wrap;overflow:auto;background:#0f172a;color:#e5eefb;border-radius:10px;padding:.85rem 1rem;margin:.7rem 0}
.analitica-result-table-wrap{overflow-x:auto;margin:.8rem 0;border:1px solid #e5e7eb;border-radius:12px;background:#fff;box-shadow:inset 0 1px 0 rgba(255,255,255,.6)}
.analitica-result-table{border-collapse:collapse;min-width:100%;width:max-content;font-size:.93rem}
.analitica-result-table th,.analitica-result-table td{border-bottom:1px solid #e5e7eb;padding:.58rem .7rem;text-align:left;vertical-align:top;white-space:nowrap}
.analitica-result-table thead th{position:sticky;top:0;background:#f8fafc;color:#0f172a;font-weight:700;z-index:1}
.analitica-result-table tbody tr:nth-child(even){background:#fcfcfd}
.analitica-result-table tbody tr:hover{background:#f6fbff}
</style>

<div class="analitica-progress-shell is-hidden" id="analitica-batch-progress" aria-live="polite">
    <div class="analitica-progress-shell__row">
        <div>
            <strong id="analitica-progress-title">Aguardando execução</strong>
            <div class="table-subtext" id="analitica-progress-text">Selecione um ou mais prompts para iniciar.</div>
        </div>
        <div class="analitica-progress-shell__totals" id="analitica-progress-counts">0 / 0</div>
    </div>
    <div class="analitica-progress-bar">
        <div class="analitica-progress-bar__fill" id="analitica-progress-fill" style="width:0%"></div>
    </div>
</div>

<article class="module-card analitica-legacy-card">
    <div class="analitica-legacy-title"><?= h($label === '' ? 'IA Analítica' : $label) ?></div>

    <?php if ($executionError !== ''): ?>
        <div class="analitica-status analitica-status--danger"><?= h($executionError) ?></div>
    <?php elseif ($executionSummary !== []): ?>
        <div class="analitica-status analitica-status--success">
            Execução concluída: <?= (int) ($executionSummary['executed'] ?? 0) ?> executado(s), <?= (int) ($executionSummary['failed'] ?? 0) ?> com falha.
        </div>
    <?php endif; ?>

    <form method="get" action="<?= h($getTarget) ?>" class="analitica-legacy-form">
        <div class="analitica-legacy-row">
            <div class="analitica-legacy-col">
                <label class="form-label analitica-legacy-label" for="version">Selecione o grupo</label>
                <select id="version" name="version" class="form-field__control analitica-select" required>
                    <option value="">— Selecione —</option>
                    <?php foreach ($versions as $version): ?>
                        <?php
                        $versionId = (int) ($version['id'] ?? 0);
                        $company = trim((string) ($version['company_name'] ?? 'Sem empresa'));
                        $date = trim((string) ($version['response_datetime'] ?? ''));
                        $status = trim((string) ($version['status'] ?? ''));
                        $labelText = $company . ($date !== '' ? ' · ' . $date : '') . ($status !== '' ? ' · ' . $status : '');
                        ?>
                        <option value="<?= h((string) $versionId) ?>" <?= $versionId === $selectedVersionId ? 'selected' : '' ?>>
                            <?= h($labelText) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="analitica-legacy-check">
                <label class="form-check-label">
                    <input type="checkbox" name="only_with_prompt" value="1" <?= $onlyWithPrompt ? 'checked' : '' ?>>
                    Apenas com prompt
                </label>
            </div>

            <div class="analitica-legacy-actions">
                <button class="action-pill action-pill--outline" type="submit">Selecionar</button>
            </div>
        </div>
    </form>

    <?php if ($selectedVersion === null): ?>
        <div class="empty-table">Selecione uma sessão para carregar os prompts.</div>
    <?php else: ?>
        <form method="post" action="<?= h($postTarget . '?version=' . (int) ($selectedVersion['id'] ?? 0) . ($companyName !== '' ? '&company=' . rawurlencode($companyName) : '')) ?>" id="analitica-exec-form">
            <?= csrf_input() ?>
            <input type="hidden" name="company" value="<?= h($companyName) ?>">
            <input type="hidden" name="version" value="<?= h((string) ((int) ($selectedVersion['id'] ?? 0))) ?>">
            <input type="hidden" name="only_with_prompt" value="<?= $onlyWithPrompt ? '1' : '0' ?>">
            <input type="hidden" name="action" id="analitica-action" value="execute_all">

            <div class="analitica-tools">
                <div class="analitica-tools-row">
                    <label for="prompt-filter" class="analitica-tools-label">Filtrar Prompt Code:</label>
                    <select id="prompt-filter" class="analitica-select analitica-select--small">
                        <option value=""><?= h(t('analitica.filter_all')) ?></option>
                        <?php foreach ($promptFilterOptions as $promptCodeOption): ?>
                            <option value="<?= h($promptCodeOption) ?>"><?= h($promptCodeOption) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button id="btn-clear-prompt-filter" type="button" class="action-pill action-pill--ghost"><?= h(t('analitica.list_all')) ?></button>
                    <?php if ($context === 'analitica'): ?>
                        <button type="submit" class="action-pill action-pill--green js-analitica-submit" data-batch-action="execute_all" onclick="document.getElementById('analitica-action').value='execute_all'"><?= h(t('analitica.run_all')) ?></button>
                        <button type="submit" class="action-pill action-pill--green js-analitica-submit" data-batch-action="execute_selected" onclick="document.getElementById('analitica-action').value='execute_selected'"><?= h(t('analitica.run_selected')) ?></button>
                    <?php endif; ?>
                </div>
            </div>

            <div class="analitica-table-wrap">
                <table class="analitica-table" id="analitica-table">
                    <thead>
                        <tr>
                            <th style="width:4.5rem">Sel</th>
                            <th style="width:4rem">#</th>
                            <th>Pergunta</th>
                            <th>Prompt Original</th>
                            <th>Prompt Substituído</th>
                            <th style="width:11rem">Arquivos</th>
                            <th style="width:8rem">SQL</th>
                            <th style="min-width:34rem;width:38%">Resultado da IA</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($items === []): ?>
                            <tr><td colspan="8" class="empty-table">Nenhum prompt encontrado para a sessão selecionada.</td></tr>
                        <?php else: ?>
                            <?php foreach ($items as $index => $item): ?>
                                <?php
                                $prompt = is_array($item['prompt'] ?? null) ? $item['prompt'] : [];
                                $field = is_array($item['field'] ?? null) ? $item['field'] : [];
                                $runtime = is_array($item['runtime'] ?? null) ? $item['runtime'] : [];
                                $attachments = is_array($runtime['attachments'] ?? null) ? $runtime['attachments'] : [];
                                $sql = is_array($runtime['sql'] ?? null) ? $runtime['sql'] : [];
                                $questionName = trim((string) ($item['question_name'] ?? $field['name'] ?? ''));
                                $promptCode = $getItemPromptCode($item);
                                $effectiveResult = $questionName !== '' && isset($resultByQuestion[$questionName]) ? $resultByQuestion[$questionName] : null;
                                $execResult = $questionName !== '' && isset($executionResults[array_search($questionName, array_column(array_filter($executionResults, 'is_array'), 'question_name'))]) ? null : null;
                                $currentRunResult = null;
                                foreach ($executionResults as $candidate) {
                                    if (is_array($candidate) && trim((string) ($candidate['question_name'] ?? '')) === $questionName) {
                                        $currentRunResult = $candidate;
                                        break;
                                    }
                                }
                                $hasIaResult = is_array($effectiveResult) && trim((string) ($effectiveResult['response_text'] ?? '')) !== '';
                                $resultText = $hasIaResult ? trim((string) ($effectiveResult['response_text'] ?? '')) : '';
                                $resultHtml = $formatIaResult($resultText);
                                $isStored = is_array($effectiveResult) && (string) ($effectiveResult['source'] ?? '') === 'stored';
                                $editHref = url('prompts/form.php?id=' . (int) ($prompt['id'] ?? 0) . '&context=' . rawurlencode($context));
                                if ($companyName !== '') {
                                    $editHref .= '&company=' . rawurlencode($companyName);
                                }
                                if (!empty($selectedVersion['id'])) {
                                    $editHref .= '&version=' . (int) $selectedVersion['id'];
                                }
                                ?>
                                <tr data-question-name="<?= h($questionName) ?>" data-prompt-code="<?= h($promptCode) ?>" class="<?= $hasIaResult ? 'analitica-row--done' : '' ?>">
                                    <td>
                                        <input type="checkbox" name="question_names[]" value="<?= h($questionName) ?>">
                                    </td>
                                    <td><?= $index + 1 ?></td>
                                    <td>
                                        <div class="analitica-question-cell">
                                            <strong><?= h($questionName !== '' ? $questionName : 'Sem pergunta') ?></strong>
                                            <?php if ($promptCode !== ''): ?>
                                                <div class="table-subtext"><?= h($promptCode) ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="analitica-prompt-cell">
                                            <?php if (!empty($prompt['id'])): ?>
                                                <a data-shell-nav="off" class="analitica-inline-link" href="<?= h($editHref) ?>">EDIT</a>
                                            <?php endif; ?>
                                            <pre class="analitica-pre"><?= h((string) ($prompt['prompt_full_text'] ?? $prompt['prompt'] ?? '')) ?></pre>
                                        </div>
                                    </td>
                                    <td>
                                        <pre class="analitica-pre"><?= h((string) ($runtime['resolved_prompt'] ?? '')) ?></pre>
                                    </td>
                                    <td>
                                        <?php if ($attachments === []): ?>
                                            <span class="table-subtext">Sem arquivos</span>
                                        <?php else: ?>
                                            <ul class="analitica-file-list">
                                                <?php foreach ($attachments as $attachment): ?>
                                                    <li>
                                                        <strong><?= h((string) ($attachment['title'] ?? 'Arquivo')) ?></strong>
                                                        <?php if (!empty($attachment['rag_status_label'])): ?>
                                                            <div class="table-subtext"><?= h((string) $attachment['rag_status_label']) ?></div>
                                                        <?php endif; ?>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($sql['has_sql'])): ?>
                                            <span class="analitica-sql-badge">Com SQL</span>
                                            <?php if (trim((string) ($sql['desc'] ?? '')) !== ''): ?>
                                                <div class="table-subtext"><?= h((string) ($sql['desc'] ?? '')) ?></div>
                                            <?php endif; ?>
                                            <pre class="analitica-sql-pre"><?= h((string) ($sql['sql_text'] ?? '')) ?></pre>
                                        <?php else: ?>
                                            <span class="table-subtext">Sem SQL</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="analitica-result-cell">
                                        <div class="analitica-result-card">
                                            <?php if (is_array($currentRunResult) && trim((string) ($currentRunResult['message'] ?? '')) !== '' && trim((string) ($currentRunResult['response_text'] ?? '')) === ''): ?>
                                                <div class="analitica-result-meta analitica-result-meta--error"><?= h((string) ($currentRunResult['message'] ?? '')) ?></div>
                                            <?php elseif ($hasIaResult && is_array($currentRunResult) && !empty($currentRunResult['ok'])): ?>
                                                <div class="analitica-result-meta analitica-result-meta--ok">Resultado atualizado nesta execução</div>
                                            <?php elseif ($hasIaResult && $isStored): ?>
                                                <div class="analitica-result-meta analitica-result-meta--stored">Resultado salvo anteriormente</div>
                                            <?php endif; ?>
                                            <div class="analitica-result-body"><?= $resultHtml ?></div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </form>
    <?php endif; ?>
</article>

<style>
.analitica-table-wrap { overflow-x: auto; }
.analitica-table { min-width: 1900px; }
.analitica-row--done { background: #f2fbf3; }
.analitica-row--done:hover { background: #edf8ee; }
.analitica-result-cell { vertical-align: top; }
.analitica-result-card { min-height: 10rem; background: #fff; border: 1px solid #e8ecef; border-radius: 10px; padding: .85rem 1rem; box-shadow: inset 0 1px 0 rgba(255,255,255,.8); }
.analitica-row--done .analitica-result-card { border-color: #d5ead8; background: #fcfffc; }
.analitica-result-body { color: #1f2937; line-height: 1.5; font-size: .94rem; }
.analitica-result-body p { margin: 0 0 .65rem 0; }
.analitica-result-body ul { margin: .25rem 0 .75rem 1.1rem; padding: 0; }
.analitica-result-body li { margin-bottom: .3rem; }
.analitica-result-body h1, .analitica-result-body h2, .analitica-result-body h3, .analitica-result-body h4, .analitica-result-body h5, .analitica-result-body h6 { margin: .2rem 0 .55rem 0; line-height: 1.25; }
.analitica-result-body h1 { font-size: 1.15rem; }
.analitica-result-body h2 { font-size: 1.05rem; }
.analitica-result-body h3 { font-size: 1rem; }
.analitica-result-body code { background: #f4f5f7; border-radius: 4px; padding: .05rem .28rem; font-size: .88em; }
.analitica-result-pre { white-space: pre-wrap; overflow-x: auto; background: #f8fafc; border: 1px solid #e5e7eb; border-radius: 8px; padding: .65rem .8rem; font-size: .86rem; margin: 0 0 .75rem 0; }
.analitica-result-placeholder { color: #7b8794; font-style: italic; }
.analitica-result-meta { display: inline-block; margin-bottom: .65rem; font-size: .82rem; padding: .2rem .5rem; border-radius: 999px; }
.analitica-result-meta--ok { background: #eaf8ec; color: #276749; }
.analitica-result-meta--stored { background: #eef4ff; color: #345995; }
.analitica-result-meta--error { background: #fff1f0; color: #b42318; }

.analitica-progress-shell {
    margin: 12px 0 14px;
    border: 1px solid #dbe6f3;
    border-radius: 14px;
    background: #ffffff;
    padding: 12px 14px;
    box-shadow: 0 8px 22px rgba(15, 23, 42, 0.05);
}
.analitica-progress-shell.is-hidden { display: none; }
.analitica-progress-shell__row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    margin-bottom: 10px;
}
.analitica-progress-shell__totals {
    min-width: 96px;
    text-align: right;
    font-weight: 800;
    color: #1d3557;
}
.analitica-progress-bar {
    height: 12px;
    border-radius: 999px;
    background: #eef2f7;
    overflow: hidden;
}
.analitica-progress-bar__fill {
    height: 100%;
    width: 0;
    background: linear-gradient(90deg, #60a5fa 0%, #22c55e 100%);
    transition: width .25s ease;
}
.analitica-row--running {
    background: #f7fbff;
}
.analitica-row--running td {
    box-shadow: inset 0 0 0 9999px rgba(59, 130, 246, 0.035);
}
.analitica-small-btn[disabled],
.action-pill[disabled] {
    opacity: .65;
    cursor: not-allowed;
}

</style>

<script>
(function () {
    const root = document.querySelector('.js-shell-content') || document;
    const execForm = root.querySelector('#analitica-exec-form');
    const filter = root.querySelector('#prompt-filter');
    const clearBtn = root.querySelector('#btn-clear-prompt-filter');
    const progressShell = root.querySelector('#analitica-batch-progress');
    const progressTitle = root.querySelector('#analitica-progress-title');
    const progressText = root.querySelector('#analitica-progress-text');
    const progressCounts = root.querySelector('#analitica-progress-counts');
    const progressFill = root.querySelector('#analitica-progress-fill');

    function getRows() {
        return Array.from(root.querySelectorAll('#analitica-table tbody tr[data-question-name]'));
    }

    function findRowByQuestion(questionName, scope) {
        const rows = Array.from((scope || root).querySelectorAll('#analitica-table tbody tr[data-question-name]'));
        const target = (questionName || '').trim();
        return rows.find(function (row) {
            return (row.getAttribute('data-question-name') || '').trim() === target;
        }) || null;
    }

    function rowMatchesPromptFilter(row) {
        const value = (filter?.value || '').trim().toLowerCase();
        const promptCode = (row.getAttribute('data-prompt-code') || '').trim().toLowerCase();
        return value === '' || promptCode === value;
    }

    function applyFilter() {
        getRows().forEach(function (row) {
            row.style.display = rowMatchesPromptFilter(row) ? '' : 'none';
        });
    }

    function showProgress(total) {
        if (!progressShell) return;
        progressShell.classList.remove('is-hidden');
        progressTitle.textContent = total > 0 ? 'Execução em andamento' : 'Aguardando execução';
        progressText.textContent = total > 0 ? ('Serão executados ' + total + ' prompt(s).') : 'Selecione um ou mais prompts para iniciar.';
        progressCounts.textContent = '0 / ' + total;
        progressFill.style.width = '0%';
    }

    function updateProgress(current, total, success, failed, questionName) {
        if (!progressShell) return;
        const safeTotal = total > 0 ? total : 1;
        const pct = Math.max(0, Math.min(100, Math.round((current / safeTotal) * 100)));
        progressShell.classList.remove('is-hidden');
        progressTitle.textContent = 'Executando ' + current + ' de ' + total;
        progressText.textContent = questionName
            ? ('Prompt atual: ' + questionName + ' · Sucesso: ' + success + ' · Falha: ' + failed)
            : ('Sucesso: ' + success + ' · Falha: ' + failed);
        progressCounts.textContent = current + ' / ' + total;
        progressFill.style.width = pct + '%';
    }

    function finishProgress(total, success, failed) {
        if (!progressShell) return;
        progressShell.classList.remove('is-hidden');
        progressTitle.textContent = 'Execução concluída';
        progressText.textContent = 'Total: ' + total + ' · Sucesso: ' + success + ' · Falha: ' + failed;
        progressCounts.textContent = total + ' / ' + total;
        progressFill.style.width = '100%';
    }

    function setBusyState(isBusy) {
        root.querySelectorAll('.js-analitica-submit').forEach(function (btn) {
            btn.disabled = isBusy;
        });
        root.querySelectorAll('#analitica-table input[type="checkbox"]').forEach(function (el) {
            el.disabled = isBusy;
        });
        if (filter) filter.disabled = isBusy;
        if (clearBtn) clearBtn.disabled = isBusy;
    }

    async function executeOne(questionName) {
        const payload = new FormData(execForm);
        payload.set('action', 'execute_one');
        payload.set('question_name', questionName);

        const response = await fetch(execForm.getAttribute('action') || window.location.href, {
            method: 'POST',
            body: payload,
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });

        const html = await response.text();
        if (!response.ok && !html) {
            throw new Error('Falha HTTP ' + response.status);
        }

        const doc = new DOMParser().parseFromString(html, 'text/html');
        const freshRow = findRowByQuestion(questionName, doc);
        const currentRow = findRowByQuestion(questionName, root);
        if (currentRow && freshRow) {
            currentRow.replaceWith(freshRow);
        }

        const freshStatus = doc.querySelector('.analitica-status');
        const currentStatus = root.querySelector('.analitica-status');
        if (freshStatus && currentStatus) {
            currentStatus.outerHTML = freshStatus.outerHTML;
        } else if (freshStatus && !currentStatus) {
            const title = root.querySelector('.analitica-legacy-title');
            if (title) {
                title.insertAdjacentHTML('afterend', freshStatus.outerHTML);
            }
        }
    }

    async function runQueue(queue) {
        if (!execForm || queue.length === 0) return;

        let success = 0;
        let failed = 0;

        showProgress(queue.length);
        setBusyState(true);

        for (let index = 0; index < queue.length; index += 1) {
            const questionName = queue[index];
            const row = findRowByQuestion(questionName, root);
            if (row) row.classList.add('analitica-row--running');
            updateProgress(index + 1, queue.length, success, failed, questionName);

            try {
                await executeOne(questionName);
                const updatedRow = findRowByQuestion(questionName, root);
                const hasDone = !!updatedRow && updatedRow.classList.contains('analitica-row--done');
                if (hasDone) {
                    success += 1;
                } else {
                    failed += 1;
                }
            } catch (error) {
                failed += 1;
                const targetRow = findRowByQuestion(questionName, root);
                if (targetRow) {
                    targetRow.classList.remove('analitica-row--running');
                    const resultCell = targetRow.querySelector('.analitica-result-body');
                    const meta = targetRow.querySelector('.analitica-result-meta') || targetRow.querySelector('.analitica-result-card');
                    if (meta && !targetRow.querySelector('.analitica-result-meta--error')) {
                        if (meta.classList.contains('analitica-result-card')) {
                            meta.insertAdjacentHTML('afterbegin', '<div class="analitica-result-meta analitica-result-meta--error">Falha na execução deste item</div>');
                        }
                    }
                    if (resultCell) {
                        resultCell.innerHTML = '<p>Falha ao executar este prompt. Refaça a tentativa.</p>';
                    }
                }
            } finally {
                const refreshedRow = findRowByQuestion(questionName, root);
                if (refreshedRow) refreshedRow.classList.remove('analitica-row--running');
                updateProgress(index + 1, queue.length, success, failed, questionName);
                applyFilter();
            }
        }

        finishProgress(queue.length, success, failed);
        setBusyState(false);
    }

    function buildQueue(submitter) {
        if (!execForm) return [];

        const actionInput = root.querySelector('#analitica-action');
        let action = (actionInput?.value || '').trim();
        if (submitter?.dataset?.batchAction) {
            action = submitter.dataset.batchAction.trim();
        }
        if (submitter?.name === 'question_name') {
            action = 'execute_one';
        }

        if (action === 'execute_one') {
            const q = (submitter?.value || '').trim();
            return q ? [q] : [];
        }

        if (action === 'execute_selected') {
            return Array.from(root.querySelectorAll('#analitica-table tbody input[type="checkbox"]:checked'))
                .map(function (el) { return (el.value || '').trim(); })
                .filter(Boolean);
        }

        return getRows()
            .filter(rowMatchesPromptFilter)
            .map(function (row) { return (row.getAttribute('data-question-name') || '').trim(); })
            .filter(Boolean);
    }

    filter?.addEventListener('change', applyFilter);
    clearBtn?.addEventListener('click', function () {
        if (filter) {
            filter.value = '';
        }
        applyFilter();
    });

    execForm?.addEventListener('submit', function (event) {
        const submitter = event.submitter || null;
        if (!submitter || !window.fetch || !window.DOMParser || !window.FormData) {
            return;
        }

        const queue = buildQueue(submitter);
        if (queue.length === 0) {
            event.preventDefault();
            showProgress(0);
            progressTitle.textContent = 'Nada para executar';
            progressText.textContent = 'Selecione ao menos um prompt antes de executar.';
            return;
        }

        event.preventDefault();
        runQueue(queue);
    });
})();
</script>
