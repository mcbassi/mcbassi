<?php
declare(strict_types=1);

$versions = is_array($versions ?? null) ? $versions : [];
$selectedVersion = is_array($selectedVersion ?? null) ? $selectedVersion : null;
$groups = is_array($groups ?? null) ? $groups : [];
$storedResults = is_array($storedResults ?? null) ? $storedResults : [];
$apiUrl = trim((string) ($apiUrl ?? url('prioridades/api.php')));
$selectedVersionId = (int) ($selectedVersion['id'] ?? 0);

$renderMd = static function (string $text): string {
    $text = trim(str_replace(["\r\n", "\r"], "\n", $text));
    if ($text === '') {
        return '<span class="prioridades-placeholder">' . h(t('prioridades.nothing_to_display')) . '</span>';
    }
    $esc = static fn(string $v): string => h($v);
    $inline = static function (string $v) use ($esc): string {
        $v = $esc($v);
        $v = preg_replace('/\*\*([^*]+)\*\*/u', '<strong>$1</strong>', $v) ?? $v;
        $v = preg_replace('/`([^`]+)`/u', '<code>$1</code>', $v) ?? $v;
        return $v;
    };
    $row = static function (string $line): array {
        $line = trim($line);
        $line = preg_replace('/^\||\|$/', '', $line) ?? $line;
        return array_map(static fn(string $cell): string => trim($cell), explode('|', $line));
    };
    $isSep = static function (string $line): bool {
        $line = trim($line);
        if ($line === '' || strpos($line, '|') === false) return false;
        $line = preg_replace('/^\||\|$/', '', $line) ?? $line;
        $parts = array_map('trim', explode('|', $line));
        foreach ($parts as $part) {
            if ($part === '') continue;
            if (!preg_match('/^:?-{3,}:?$/', $part)) return false;
        }
        return $parts !== [];
    };

    $lines = explode("\n", $text);
    $html = [];
    $p = [];
    $inUl = false;
    $inOl = false;
    $flushP = static function () use (&$p, &$html, $inline): void {
        if ($p === []) return;
        $joined = trim(implode(' ', $p));
        if ($joined !== '') $html[] = '<p>' . $inline($joined) . '</p>';
        $p = [];
    };
    $closeLists = static function () use (&$inUl, &$inOl, &$html): void {
        if ($inUl) { $html[] = '</ul>'; $inUl = false; }
        if ($inOl) { $html[] = '</ol>'; $inOl = false; }
    };

    for ($i = 0, $count = count($lines); $i < $count; $i++) {
        $line = $lines[$i];
        $trim = trim($line);
        if ($trim === '') { $flushP(); $closeLists(); continue; }

        if (strpos($trim, '|') !== false && isset($lines[$i + 1]) && $isSep($lines[$i + 1])) {
            $flushP(); $closeLists();
            $header = $row($trim);
            $i++;
            $rows = [];
            while (isset($lines[$i + 1])) {
                $next = trim($lines[$i + 1]);
                if ($next === '' || strpos($next, '|') === false || $isSep($next)) break;
                $i++;
                $rows[] = $row($next);
            }
            $html[] = '<div class="prioridades-md-table-wrap"><table class="prioridades-md-table"><thead><tr>';
            foreach ($header as $cell) $html[] = '<th>' . $inline($cell) . '</th>';
            $html[] = '</tr></thead><tbody>';
            foreach ($rows as $r) {
                $html[] = '<tr>';
                foreach ($r as $cell) $html[] = '<td>' . $inline($cell) . '</td>';
                $html[] = '</tr>';
            }
            $html[] = '</tbody></table></div>';
            continue;
        }

        if (preg_match('/^(#{1,6})\s+(.+)$/u', $trim, $m)) {
            $flushP(); $closeLists();
            $level = strlen($m[1]);
            $html[] = '<h' . $level . '>' . $inline($m[2]) . '</h' . $level . '>';
            continue;
        }
        if (preg_match('/^(?:-|\*)\s+(.+)$/u', $trim, $m)) {
            $flushP(); if ($inOl) { $html[] = '</ol>'; $inOl = false; }
            if (!$inUl) { $html[] = '<ul>'; $inUl = true; }
            $html[] = '<li>' . $inline($m[1]) . '</li>';
            continue;
        }
        if (preg_match('/^\d+[\.)]\s+(.+)$/u', $trim, $m)) {
            $flushP(); if ($inUl) { $html[] = '</ul>'; $inUl = false; }
            if (!$inOl) { $html[] = '<ol>'; $inOl = true; }
            $html[] = '<li>' . $inline($m[1]) . '</li>';
            continue;
        }
        $p[] = $trim;
    }

    $flushP();
    $closeLists();
    return '<div class="prioridades-md">' . implode("\n", $html) . '</div>';
};
?>
<style>
.prioridades-card{padding:22px 22px 18px}
.prioridades-title{margin:0 0 16px;font-size:1.5rem;font-weight:800;color:#10223f}
.prioridades-toolbar{display:flex;gap:14px;align-items:end;flex-wrap:wrap;margin-bottom:16px}
.prioridades-field{display:flex;flex-direction:column;gap:6px;min-width:260px;flex:1 1 280px}
.prioridades-field label{font-weight:700;color:#334155;font-size:.92rem}
.prioridades-check{display:flex;align-items:center;gap:8px;min-width:220px;padding-bottom:12px}
.prioridades-actions{display:flex;gap:10px;flex-wrap:wrap}
.prioridades-status{margin:0 0 14px;border-radius:999px;padding:10px 14px;font-size:.93rem;font-weight:700}
.prioridades-status--info{background:#eaf3ff;color:#184b8c}.prioridades-status--success{background:#eaf8ec;color:#276749}.prioridades-status--danger{background:#fff1f0;color:#b42318}.prioridades-status--warning{background:#fff7e6;color:#9a6700}
.prioridades-grid{display:grid;grid-template-columns:minmax(280px,.72fr) minmax(720px,1.55fr);gap:18px;align-items:start}
.prioridades-panel{background:#fff;border:1px solid #e5e7eb;border-radius:18px;padding:16px;box-shadow:0 8px 24px rgba(15,23,42,.04)}
.prioridades-panel h3{margin:0 0 12px;font-size:1rem;color:#0f172a}
.prioridades-table-wrap{overflow:auto;border:1px solid #e5e7eb;border-radius:14px;background:#fff}
.prioridades-table{width:100%;min-width:980px;border-collapse:collapse}
.prioridades-table th,.prioridades-table td{padding:.72rem .78rem;border-bottom:1px solid #e5e7eb;vertical-align:top;text-align:left}
.prioridades-table thead th{background:#f8fafc;font-weight:800;color:#0f172a;position:sticky;top:0;z-index:1}
.prioridades-table tbody tr:hover{background:#fbfdff}
.prioridades-table tbody tr.is-running{background:#f7fbff}
.prioridades-md{font-size:.94rem;line-height:1.55;color:#1f2937}.prioridades-md h1,.prioridades-md h2,.prioridades-md h3,.prioridades-md h4{margin:.8rem 0 .4rem;color:#0f172a}.prioridades-md p{margin:.45rem 0}.prioridades-md ul,.prioridades-md ol{margin:.4rem 0 .65rem 1.15rem;padding:0}.prioridades-md code{background:#f3f4f6;border-radius:4px;padding:.08rem .28rem;font-size:.92em}
.prioridades-md-table-wrap{overflow:auto;margin:.75rem 0;border:1px solid #e5e7eb;border-radius:12px;background:#fff}.prioridades-md-table{border-collapse:collapse;min-width:100%;width:max-content}.prioridades-md-table th,.prioridades-md-table td{padding:.55rem .65rem;border-bottom:1px solid #e5e7eb;text-align:left;vertical-align:top;white-space:nowrap}.prioridades-md-table thead th{background:#f8fafc;font-weight:800}
.prioridades-answer{white-space:pre-wrap;color:#1f2937}.prioridades-muted{color:#64748b;font-size:.88rem}.prioridades-placeholder{color:#94a3b8;font-style:italic}
.prioridades-json-table{width:100%;border-collapse:collapse}.prioridades-json-table th,.prioridades-json-table td{padding:.62rem .6rem;border-bottom:1px solid #e5e7eb;vertical-align:top}.prioridades-json-table textarea,.prioridades-json-table input{width:100%;min-height:40px;border:1px solid #dbe3ee;border-radius:10px;padding:.45rem .55rem;font:inherit;background:#fff}.prioridades-json-table textarea{min-height:82px;resize:vertical}
.prioridades-report{max-height:640px;overflow:auto;padding-right:6px}
.prioridades-badge{display:inline-flex;align-items:center;border-radius:999px;padding:.22rem .55rem;font-size:.78rem;font-weight:800}.prioridades-badge--ok{background:#eaf8ec;color:#276749}.prioridades-badge--empty{background:#eef2f7;color:#475569}
#prioridades-results{max-height:520px;overflow:auto}\n#prioridades-results .prioridades-table{min-width:680px;font-size:.82rem}\n#prioridades-results .prioridades-table th,#prioridades-results .prioridades-table td{padding:.42rem .5rem;line-height:1.24}\n#prioridades-results .prioridades-answer{font-size:.82rem;max-height:6.2em;overflow:auto}\n#prioridades-results .prioridades-muted{font-size:.76rem}\n#prioridades-results .prioridades-badge{font-size:.72rem;padding:.18rem .46rem}\n#prioridades-json-area .prioridades-table-wrap{max-height:none;overflow:auto}\n#prioridades-json-area .prioridades-json-table{font-size:.93rem}\n#prioridades-json-area .prioridades-json-table th,#prioridades-json-area .prioridades-json-table td{padding:.68rem .64rem}\n#prioridades-json-area .prioridades-json-table textarea,#prioridades-json-area .prioridades-json-table input{font-size:.93rem}\n@media (max-width: 1200px){.prioridades-grid{grid-template-columns:1fr}}
</style>

<article class="module-card prioridades-card">
    <h2 class="prioridades-title"><?= h(t('prioridades.title')) ?></h2>

    <div id="prioridades-status"></div>

    <div class="prioridades-toolbar">
        <div class="prioridades-field">
            <label for="prioridades-version"><?= h(t('prioridades.select_session')) ?></label>
            <select id="prioridades-version" class="form-field__control analitica-select">
                <option value=""><?= h(t('prioridades.option_select')) ?></option>
                <?php foreach ($versions as $version): ?>
                    <?php $versionId = (int) ($version['id'] ?? 0); ?>
                    <option value="<?= h((string) $versionId) ?>" <?= $versionId === $selectedVersionId ? 'selected' : '' ?>>
                        <?= h(trim((string) ($version['company_name'] ?? t('diagnostico.no_company'))) . ' · ' . trim((string) ($version['response_datetime'] ?? ''))) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="prioridades-field">
            <label for="prioridades-group"><?= h(t('prioridades.priority_group')) ?></label>
            <select id="prioridades-group" class="form-field__control analitica-select">
                <option value=""><?= h(t('prioridades.option_select')) ?></option>
                <?php foreach ($groups as $group): ?>
                    <option value="<?= h((string) ((int) ($group['id'] ?? 0))) ?>"><?= h((string) ($group['name'] ?? '')) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <label class="prioridades-check"><input type="checkbox" id="prioridades-only-ai" checked> <?= h(t('prioridades.only_ai_answers')) ?></label>
        <div class="prioridades-actions">
            <button type="button" class="action-pill action-pill--outline" id="prioridades-btn-load"><?= h(t('common.select')) ?></button>
            <button type="button" class="action-pill action-pill--green" id="prioridades-btn-exec"><?= h(t('prioridades.execute_group')) ?></button>
            <button type="button" class="action-pill action-pill--ghost" id="prioridades-btn-save" disabled><?= h(t('prioridades.save_result')) ?></button>
            <button type="button" class="action-pill action-pill--ghost" id="prioridades-btn-final"><?= h(t('prioridades.ai_final_report')) ?></button>
        </div>
    </div>

    <div class="prioridades-grid">
        <section class="prioridades-panel">
            <h3><?= h(t('prioridades.selected_group_answers')) ?> <span class="prioridades-muted" style="font-weight:600"><?= h(t('prioridades.informational')) ?></span></h3>
            <div id="prioridades-results" class="prioridades-table-wrap">
                <div class="empty-table" style="padding:18px"><?= h(t('prioridades.select_session_group_to_load')) ?></div>
            </div>
        </section>

        <section class="prioridades-panel">
            <h3><?= h(t('prioridades.proposed_priorities')) ?></h3>
            <div id="prioridades-json-area">
                <div class="empty-table"><?= h(t('prioridades.execute_group_to_build_table')) ?></div>
            </div>
        </section>
    </div>

    <section class="prioridades-panel" style="margin-top:18px">
        <h3><?= h(t('prioridades.group_analytic_report')) ?></h3>
        <div id="prioridades-final-report" class="prioridades-report">
            <div class="empty-table"><?= h(t('prioridades.generate_report_after_consolidation')) ?></div>
        </div>
    </section>
</article>

<script>
(function(){
    const root = document.querySelector('.js-shell-content') || document;
    const apiUrl = <?= json_encode($apiUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const elVersion = root.querySelector('#prioridades-version');
    const elGroup = root.querySelector('#prioridades-group');
    const elOnlyAi = root.querySelector('#prioridades-only-ai');
    const elResults = root.querySelector('#prioridades-results');
    const elJsonArea = root.querySelector('#prioridades-json-area');
    const elReport = root.querySelector('#prioridades-final-report');
    const elStatus = root.querySelector('#prioridades-status');
    const btnLoad = root.querySelector('#prioridades-btn-load');
    const btnExec = root.querySelector('#prioridades-btn-exec');
    const btnSave = root.querySelector('#prioridades-btn-save');
    const btnFinal = root.querySelector('#prioridades-btn-final');
    const L = <?= json_encode([
        'nothing_to_display' => t('prioridades.nothing_to_display'),
        'processing' => t('common.processing'),
        'api_not_json' => t('prioridades.api_not_json'),
        'no_answers_group' => t('prioridades.no_answers_group'),
        'with_ai' => t('prioridades.with_ai'),
        'without_ai' => t('prioridades.without_ai'),
        'question' => t('common.question'),
        'answer' => t('common.answer'),
        'status' => t('common.status'),
        'prompt2_invalid_json' => t('prioridades.prompt2_invalid_json'),
        'priority' => t('prioridades.priority'),
        'improvement_proposed' => t('prioridades.improvement_proposed'),
        'expected_result' => t('prioridades.expected_result'),
        'deadline' => t('prioridades.deadline'),
        'predecessor' => t('prioridades.predecessor'),
        'select_session' => t('prioridades.select_session_warning'),
        'select_group' => t('prioridades.select_group_warning'),
        'loading_group_answers' => t('prioridades.loading_group_answers'),
        'click_execute_group' => t('prioridades.click_execute_group'),
        'loaded_lines' => t('prioridades.loaded_lines'),
        'click_select_first' => t('prioridades.click_select_first'),
        'executing_priority_group' => t('prioridades.executing_priority_group'),
        'raw_prompt2_output' => t('prioridades.raw_prompt2_output'),
        'group_success' => t('prioridades.group_success'),
        'execute_group_first' => t('prioridades.execute_group_first'),
        'no_valid_json_to_save' => t('prioridades.no_valid_json_to_save'),
        'saving_diag_priority' => t('prioridades.saving_diag_priority'),
        'result_saved_success' => t('prioridades.result_saved_success'),
        'select_session_before_report' => t('prioridades.select_session_before_report'),
        'select_group_before_report' => t('prioridades.select_group_before_report'),
        'generating_group_report' => t('prioridades.generating_group_report'),
        'group_report_success' => t('prioridades.group_report_success'),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

    let currentVisibleItems = [];
    let lastExecMeta = null;
    let lastQuestionnaireIdx = null;
    let lastResultJson = null;

    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';
    const esc = (s) => String(s ?? '').replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;').replaceAll('"','&quot;').replaceAll("'", '&#039;');
    const renderMd = (md) => {
        const raw = String(md ?? '').trim();
        if (raw === '') return '<span class="prioridades-placeholder">' + esc(L.nothing_to_display) + '</span>';
        const lines = raw.replace(/\r\n?/g,'\n').split('\n');
        const html = [];
        let p = [];
        let inUl = false;
        let inOl = false;
        const flushP = () => {
            if (!p.length) return;
            const joined = p.join(' ').trim();
            if (joined) html.push('<p>' + esc(joined).replace(/\*\*([^*]+)\*\*/g,'<strong>$1</strong>').replace(/`([^`]+)`/g,'<code>$1</code>') + '</p>');
            p = [];
        };
        const closeLists = () => {
            if (inUl) { html.push('</ul>'); inUl = false; }
            if (inOl) { html.push('</ol>'); inOl = false; }
        };
        const parseRow = (line) => line.trim().replace(/^\||\|$/g,'').split('|').map(v => v.trim());
        const isSep = (line) => {
            if (!line || !line.includes('|')) return false;
            return parseRow(line).every(part => part === '' || /^:?-{3,}:?$/.test(part));
        };
        for (let i = 0; i < lines.length; i++) {
            const trim = lines[i].trim();
            if (!trim) { flushP(); closeLists(); continue; }
            if (trim.includes('|') && i + 1 < lines.length && isSep(lines[i + 1].trim())) {
                flushP(); closeLists();
                const header = parseRow(trim); i++;
                const rows = [];
                while (i + 1 < lines.length) {
                    const next = lines[i + 1].trim();
                    if (!next || !next.includes('|') || isSep(next)) break;
                    i++; rows.push(parseRow(next));
                }
                html.push('<div class="prioridades-md-table-wrap"><table class="prioridades-md-table"><thead><tr>' + header.map(c => '<th>' + esc(c) + '</th>').join('') + '</tr></thead><tbody>' + rows.map(r => '<tr>' + r.map(c => '<td>' + esc(c) + '</td>').join('') + '</tr>').join('') + '</tbody></table></div>');
                continue;
            }
            const h = trim.match(/^(#{1,6})\s+(.+)$/);
            if (h) { flushP(); closeLists(); html.push(`<h${h[1].length}>${esc(h[2]).replace(/\*\*([^*]+)\*\*/g,'<strong>$1</strong>').replace(/`([^`]+)`/g,'<code>$1</code>')}</h${h[1].length}>`); continue; }
            const ul = trim.match(/^(?:-|\*)\s+(.+)$/); if (ul) { flushP(); if (inOl){html.push('</ol>'); inOl=false;} if(!inUl){html.push('<ul>'); inUl=true;} html.push('<li>' + esc(ul[1]).replace(/\*\*([^*]+)\*\*/g,'<strong>$1</strong>').replace(/`([^`]+)`/g,'<code>$1</code>') + '</li>'); continue; }
            const ol = trim.match(/^\d+[\.)]\s+(.+)$/); if (ol) { flushP(); if (inUl){html.push('</ul>'); inUl=false;} if(!inOl){html.push('<ol>'); inOl=true;} html.push('<li>' + esc(ol[1]).replace(/\*\*([^*]+)\*\*/g,'<strong>$1</strong>').replace(/`([^`]+)`/g,'<code>$1</code>') + '</li>'); continue; }
            p.push(trim);
        }
        flushP(); closeLists();
        return '<div class="prioridades-md">' + html.join('') + '</div>';
    };
    const setStatus = (kind, msg) => {
        const cls = kind === 'danger' ? 'prioridades-status prioridades-status--danger' :
            kind === 'warning' ? 'prioridades-status prioridades-status--warning' :
            kind === 'success' ? 'prioridades-status prioridades-status--success' :
            'prioridades-status prioridades-status--info';
        elStatus.innerHTML = msg ? `<div class="${cls}">${esc(msg)}</div>` : '';
    };
    const setLoading = (loading, msg='') => {
        [btnLoad, btnExec, btnSave, btnFinal, elVersion, elGroup, elOnlyAi].forEach(el => { if (el) el.disabled = !!loading; });
        if (loading) setStatus('info', msg || L.processing);
    };
    const postJson = async (payload) => {
        const res = await fetch(apiUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'Content-Type': 'application/json', 'X-CSRF-Token': csrf(), 'X-Requested-With': 'XMLHttpRequest'},
            body: JSON.stringify(payload || {})
        });
        const text = await res.text();
        let data = null;
        try { data = JSON.parse(text); } catch (e) { throw new Error(L.api_not_json); }
        if (!res.ok || !data?.ok) throw new Error(data?.error || `HTTP ${res.status}`);
        return data;
    };
    const renderResultsTable = (items) => {
        if (!Array.isArray(items) || !items.length) {
            elResults.innerHTML = '<div class="empty-table" style="padding:18px">' + esc(L.no_answers_group) + '</div>';
            return;
        }
        const rows = items.map((r, idx) => `
            <tr data-id="${esc(r.id ?? '')}">
                <td>${idx + 1}</td>
                <td><strong>${esc(r.question_label || r.question_name || '')}</strong><div class="prioridades-muted">${esc(r.question_name || '')}</div></td>
                <td><div class="prioridades-answer">${esc(r.answer || '')}</div></td>
                <td>${r.prompt_response && String(r.prompt_response).trim() !== '' ? '<span class="prioridades-badge prioridades-badge--ok">' + esc(L.with_ai) + '</span>' : '<span class="prioridades-badge prioridades-badge--empty">' + esc(L.without_ai) + '</span>'}</td>
            </tr>
        `).join('');
        elResults.innerHTML = `<table class="prioridades-table"><thead><tr><th style="width:4rem">#</th><th>${esc(L.question)}</th><th>${esc(L.answer)}</th><th style="width:7rem">${esc(L.status)}</th></tr></thead><tbody>${rows}</tbody></table>`;
    };
    const renderJsonTable = (arr) => {
        if (!Array.isArray(arr) || !arr.length) {
            elJsonArea.innerHTML = '<div class="empty-table">' + esc(L.prompt2_invalid_json) + '</div>';
            btnSave.disabled = true;
            return;
        }
        elJsonArea.innerHTML = `<div class="prioridades-table-wrap"><table class="prioridades-json-table"><thead><tr><th style="width:110px">${esc(L.priority)}</th><th>${esc(L.improvement_proposed)}</th><th>${esc(L.expected_result)}</th><th style="width:140px">${esc(L.deadline)}</th><th style="width:180px">${esc(L.predecessor)}</th></tr></thead><tbody>${arr.map((r,i)=>`<tr data-i="${i}"><td><input class="prio-field" data-k="prioridade" value="${esc(r.prioridade ?? '')}"></td><td><textarea class="prio-field" data-k="melhoria">${esc(r.melhoria ?? '')}</textarea></td><td><textarea class="prio-field" data-k="resultado_esperado">${esc(r.resultado_esperado ?? '')}</textarea></td><td><textarea class="prio-field" data-k="prazo">${esc(r.prazo ?? '')}</textarea></td><td><textarea class="prio-field" data-k="predecessora">${esc(r.predecessora ?? '')}</textarea></td></tr>`).join('')}</tbody></table></div>`;
        btnSave.disabled = false;
    };
    const collectEditedPriorities = () => {
        if (!Array.isArray(lastResultJson)) return null;
        const copy = JSON.parse(JSON.stringify(lastResultJson));
        elJsonArea.querySelectorAll('tbody tr').forEach(tr => {
            const i = parseInt(tr.getAttribute('data-i') || '0', 10);
            tr.querySelectorAll('.prio-field').forEach(inp => {
                const key = inp.getAttribute('data-k');
                if (key) copy[i][key] = inp.value;
            });
        });
        return copy;
    };

    btnLoad?.addEventListener('click', async () => {
        const versionId = parseInt(elVersion?.value || '0', 10);
        const groupId = parseInt(elGroup?.value || '0', 10);
        if (!versionId) return setStatus('warning', L.select_session);
        if (!groupId) return setStatus('warning', L.select_group);
        setLoading(true, L.loading_group_answers);
        try {
            const data = await postJson({action: 'list_responses', version_id: versionId, priority_group_id: groupId, only_with_ai_response: elOnlyAi.checked ? 1 : 0, csrf_token: csrf()});
            currentVisibleItems = Array.isArray(data.items) ? data.items : [];
            renderResultsTable(currentVisibleItems);
            elJsonArea.innerHTML = '<div class="empty-table">' + esc(L.click_execute_group) + '</div>';
            lastExecMeta = null; lastQuestionnaireIdx = null; lastResultJson = null; btnSave.disabled = true;
            setStatus('success', L.loaded_lines.replace(':count', String(currentVisibleItems.length)));
        } catch (e) {
            setStatus('danger', e.message || String(e));
        } finally {
            setLoading(false);
        }
    });

    btnExec?.addEventListener('click', async () => {
        const versionId = parseInt(elVersion?.value || '0', 10);
        const groupId = parseInt(elGroup?.value || '0', 10);
        if (!versionId) return setStatus('warning', L.select_session);
        if (!groupId) return setStatus('warning', L.select_group);
        if (!Array.isArray(currentVisibleItems) || !currentVisibleItems.length) return setStatus('warning', L.click_select_first);
        setLoading(true, L.executing_priority_group);
        try {
            const data = await postJson({action: 'exec_priority_group', version_id: versionId, priority_group_id: groupId, answers_override: currentVisibleItems, csrf_token: csrf()});
            lastExecMeta = data.meta || null;
            lastQuestionnaireIdx = data.questionnaire_idx || null;
            lastResultJson = Array.isArray(data.result_json) ? data.result_json : null;
            renderResultsTable(Array.isArray(data.answers) ? data.answers : currentVisibleItems);
            renderJsonTable(lastResultJson);
            if (!Array.isArray(lastResultJson)) {
                elJsonArea.innerHTML += `<div style="margin-top:12px"><div class="prioridades-muted">${esc(L.raw_prompt2_output)}</div><pre class="analitica-result-pre">${esc(data.result_raw || '')}</pre></div>`;
            }
            setStatus('success', L.group_success);
        } catch (e) {
            setStatus('danger', e.message || String(e));
        } finally {
            setLoading(false);
        }
    });

    btnSave?.addEventListener('click', async () => {
        if (!lastExecMeta || !lastQuestionnaireIdx) return setStatus('warning', L.execute_group_first);
        const edited = collectEditedPriorities();
        if (!edited) return setStatus('warning', L.no_valid_json_to_save);
        setLoading(true, L.saving_diag_priority);
        try {
            const data = await postJson({action: 'save_diag_priority', priority_group_id: String(lastExecMeta.priority_group_id || ''), questionnaire_idx: String(lastQuestionnaireIdx || ''), result_json: edited, csrf_token: csrf()});
            setStatus('success', L.result_saved_success);
        } catch (e) {
            setStatus('danger', e.message || String(e));
        } finally { setLoading(false); }
    });

    btnFinal?.addEventListener('click', async () => {
        const versionId = parseInt(elVersion?.value || '0', 10);
        const groupId = parseInt(elGroup?.value || '0', 10);
        if (!versionId) return setStatus('warning', L.select_session_before_report);
        if (!groupId) return setStatus('warning', L.select_group_before_report);
        const edited = collectEditedPriorities() || [];
        setLoading(true, L.generating_group_report);
        try {
            const data = await postJson({action: 'final_report', version_id: versionId, priority_group_id: groupId, current_priorities: edited, csrf_token: csrf()});
            elReport.innerHTML = renderMd(data.report || '');
            setStatus('success', L.group_report_success);
        } catch (e) {
            setStatus('danger', e.message || String(e));
        } finally { setLoading(false); }
    });

    <?php if ($selectedVersionId > 0): ?>
    if (elVersion && !elVersion.value) elVersion.value = <?= json_encode((string) $selectedVersionId) ?>;
    <?php endif; ?>
})();
</script>
