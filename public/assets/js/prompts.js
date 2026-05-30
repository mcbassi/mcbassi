(function () {
    function ready(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn, { once: true });
            return;
        }
        fn();
    }

    ready(function () {
        const root = document.querySelector('.js-shell-content') || document;
        const form = root.querySelector('#prompt-editor-form');
        const textarea = root.querySelector('#prompt-text');

        function i18nText(value) {
            let text = String(value == null ? '' : value);
            const map = (window.APP_I18N && window.APP_I18N.auto_map) ? window.APP_I18N.auto_map : {};
            Object.keys(map).sort(function (a, b) { return b.length - a.length; }).forEach(function (source) {
                if (source && map[source] && source !== map[source]) {
                    text = text.split(source).join(map[source]);
                }
            });
            return text;
        }

        function escapeHtml(value) {
            return String(value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function highlightMarkers(fragment) {
            return fragment.replace(/&lt;&lt;([\s\S]*?)&gt;&gt;/g, '<span class="prompt-syntax prompt-syntax--marker">&lt;&lt;$1&gt;&gt;</span>');
        }

        function highlightPromptText(value, mode) {
            const text = String(value || '').replace(/\r\n?|\r/g, '\n');
            const lines = text.split('\n');
            let inSql = mode === 'sql';

            return lines.map(function (line) {
                const escaped = escapeHtml(line);

                if (mode === 'sql') {
                    return '<span class="prompt-syntax prompt-syntax--sql">' + escaped + '</span>';
                }

                if (/^\s*EXECUTAR\s+SQL\s*=/.test(line)) {
                    inSql = true;
                    return '<span class="prompt-syntax prompt-syntax--sql-trigger">' + escaped + '</span>';
                }

                if (inSql) {
                    return '<span class="prompt-syntax prompt-syntax--sql">' + escaped + '</span>';
                }

                let rendered = highlightMarkers(escaped);
                if (/^\s*\[[A-Z][A-Z0-9_\- ]*\]\s+/.test(line)) {
                    rendered = '<span class="prompt-syntax prompt-syntax--biblio">' + rendered + '</span>';
                }
                return rendered;
            }).join('<br>');
        }

        function renderPromptHighlightElement(element, forcedMode) {
            if (!element) {
                return;
            }
            const sourceId = element.getAttribute('data-source-id');
            const rawText = element.getAttribute('data-raw-text');
            const sourceElement = sourceId ? root.querySelector('#' + sourceId) : null;
            const text = sourceElement ? (sourceElement.value || '') : (rawText !== null ? rawText : (element.textContent || ''));
            element.innerHTML = highlightPromptText(text, forcedMode || element.getAttribute('data-highlight-mode') || 'prompt');
        }

        function refreshStaticHighlights() {
            root.querySelectorAll('.js-prompt-highlight').forEach(function (element) {
                renderPromptHighlightElement(element);
            });
            root.querySelectorAll('.js-prompt-highlight-sql').forEach(function (element) {
                renderPromptHighlightElement(element, 'sql');
            });
            root.querySelectorAll('.js-prompt-highlight-live').forEach(function (element) {
                renderPromptHighlightElement(element);
            });
        }

        refreshStaticHighlights();

        if (!form || !textarea) {
            return;
        }

        if (root.__promptEditorInitialized === true) {
            return;
        }
        root.__promptEditorInitialized = true;

        const fieldButton = root.querySelector('.js-insert-field');
        const fieldPicker = root.querySelector('#prompt-field-picker');
        const fieldReference = root.querySelector('#prompt-field-reference');
        const paperButton = root.querySelector('.js-insert-paper');
        const paperPicker = root.querySelector('#prompt-paper-picker');
        const attachSqlButton = root.querySelector('.js-attach-sql');
        const detachSqlButton = root.querySelector('.js-detach-sql');
        const sqlPicker = root.querySelector('#prompt-sql-picker');
        const sqlPreview = root.querySelector('#prompt-sql-preview');
        const markerList = root.querySelector('.js-marker-list');
        const markerCount = root.querySelector('.js-marker-count');
        const sqlStatusText = root.querySelector('.js-sql-status-text');
        const sqlDescHidden = form.querySelector('input[name="sql_desc"]');
        const sqlTextHidden = form.querySelector('input[name="sql_text"]');
        const sqlPreviewHidden = form.querySelector('input[name="sql_preview"]');
        const execSqlButton = root.querySelector('.js-exec-sql');
        const sqlResultBody = root.querySelector('.js-sql-result-body');
        const sqlResultTitle = root.querySelector('.js-sql-result-title');
        const promptReadyView = root.querySelector('#prompt-ready-view');
        const sqlMarker = 'EXECUTAR SQL=';

        const viewTabs = root.querySelectorAll('.js-prompt-view-tab');
        const viewPanels = root.querySelectorAll('.js-prompt-view-panel');

        function activatePromptView(target) {
            viewTabs.forEach(function (tab) {
                tab.classList.toggle('is-active', tab.getAttribute('data-target') === target);
            });

            viewPanels.forEach(function (panel) {
                panel.classList.toggle('is-active', panel.getAttribute('data-panel') === target);
            });
        }

        viewTabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                activatePromptView(tab.getAttribute('data-target') || 'original');
            });
        });

        function updateFieldReference() {
            if (!fieldReference || !fieldPicker) {
                return;
            }

            const option = fieldPicker.selectedOptions ? fieldPicker.selectedOptions[0] : null;
            let text = '';

            if (option && option.value) {
                text = (option.getAttribute('data-question-label') || '').trim();
                if (!text) {
                    text = (option.textContent || '').replace(/^\s*\[[^\]]*\]\s*/, '').trim();
                }
            }

            fieldReference.textContent = text || '\u00a0';
            fieldReference.setAttribute('title', text);
        }

        fieldPicker?.addEventListener('change', updateFieldReference);
        updateFieldReference();

        function insertMacroOnce(value) {
            const macro = '<<' + value + '>>';
            const start = textarea.selectionStart ?? textarea.value.length;
            const end = textarea.selectionEnd ?? textarea.value.length;
            const current = textarea.value || '';

            if (start === end) {
                const before = current.slice(Math.max(0, start - macro.length), start);
                const after = current.slice(end, end + macro.length);
                if (before === macro || after === macro) {
                    return;
                }
            }

            insertAtCursor(macro);
        }

        function insertAtCursor(text) {
            const start = textarea.selectionStart ?? textarea.value.length;
            const end = textarea.selectionEnd ?? textarea.value.length;
            const current = textarea.value || '';

            textarea.value = current.slice(0, start) + text + current.slice(end);
            textarea.focus();

            const cursor = start + text.length;
            textarea.selectionStart = cursor;
            textarea.selectionEnd = cursor;
            textarea.dispatchEvent(new Event('input', { bubbles: true }));
        }

        function splitPromptAndSql(text) {
            const current = text || '';
            const markerIndex = current.lastIndexOf(sqlMarker);
            if (markerIndex === -1) {
                return {
                    baseText: current.trimEnd(),
                    sqlBlock: '',
                };
            }

            return {
                baseText: current.slice(0, markerIndex).replace(/\s+$/, ''),
                sqlBlock: current.slice(markerIndex + sqlMarker.length).trim(),
            };
        }

        function basePromptText() {
            return splitPromptAndSql(textarea.value || '').baseText;
        }

        function currentSqlBlock() {
            if (!sqlPreview) {
                return '';
            }

            return (sqlPreview.value || '').trim();
        }

        function composeFullPrompt(baseText, sqlBlock) {
            const cleanBase = (baseText || '').trimEnd();
            const cleanSql = (sqlBlock || '').trim();

            if (!cleanSql) {
                return cleanBase;
            }

            return cleanBase + '\n\n' + sqlMarker + '\n' + cleanSql;
        }

        function updatePromptReadyView() {
            if (!promptReadyView) {
                return;
            }

            const baseText = promptReadyView.getAttribute('data-base-text') || '';
            const sqlBlock = currentSqlBlock();
            const fullText = sqlBlock ? (baseText + '\n\n' + sqlMarker + '\n' + sqlBlock) : baseText;
            promptReadyView.setAttribute('data-raw-text', fullText);
            renderPromptHighlightElement(promptReadyView);
        }

        function selectedSqlDesc() {
            const option = sqlPicker?.selectedOptions?.[0];
            return (option?.value || '').trim();
        }

        function syncHiddenSqlState() {
            const sqlBlock = currentSqlBlock();
            if (sqlPreviewHidden) {
                sqlPreviewHidden.value = sqlBlock;
            }

            let sqlDesc = selectedSqlDesc();
            let sqlText = sqlBlock;
            const lines = sqlBlock.split(/\r\n|\r|\n/);

            if (lines.length > 0 && /^\s*--\s*DESC\s*:/i.test(lines[0])) {
                const match = lines[0].match(/^\s*--\s*DESC\s*:\s*(.+)$/i);
                if (match) {
                    sqlDesc = match[1].trim();
                }
                lines.shift();
                sqlText = lines.join('\n').trim();
            }

            if (sqlDescHidden) {
                sqlDescHidden.value = sqlDesc;
            }
            if (sqlTextHidden) {
                sqlTextHidden.value = sqlText;
            }
        }

        function markersFromText(text) {
            const matches = text.match(/<<([^>]+)>>/g) || [];
            const values = [];
            const seen = new Set();

            matches.forEach(function (item) {
                const clean = item.replace(/^<</, '').replace(/>>$/, '').trim();
                if (!clean) {
                    return;
                }
                const key = clean.toLowerCase();
                if (seen.has(key)) {
                    return;
                }
                seen.add(key);
                values.push(clean);
            });

            return values;
        }

        function renderMarkerState() {
            if (!markerList || !markerCount) {
                return;
            }

            const markers = markersFromText((textarea.value || '') + '\n' + currentSqlBlock());
            markerCount.textContent = String(markers.length);
            markerList.innerHTML = '';

            if (markers.length === 0) {
                const chip = document.createElement('span');
                chip.className = 'prompt-edit-lite__chip';
                chip.textContent = i18nText('Sem marcadores');
                markerList.appendChild(chip);
                return;
            }

            markers.forEach(function (marker) {
                const chip = document.createElement('span');
                chip.className = 'prompt-edit-lite__chip prompt-edit-lite__chip--marker';
                chip.textContent = '<<' + marker + '>>';
                markerList.appendChild(chip);
            });
        }

        function updateSqlStatus() {
            if (!sqlStatusText) {
                return;
            }

            const sqlBlock = currentSqlBlock();
            if (!sqlBlock) {
                sqlStatusText.textContent = i18nText('Sem SQL');
                return;
            }

            const firstLine = sqlBlock.split(/\r\n|\r|\n/)[0] || '';
            const match = firstLine.match(/^\s*--\s*DESC\s*:\s*(.+)$/i);
            sqlStatusText.textContent = match ? match[1].trim() : i18nText('SQL anexado');
        }

        function updateSqlPreviewFromPicker() {
            if (!sqlPreview || !sqlPicker) {
                return;
            }

            const option = sqlPicker.selectedOptions ? sqlPicker.selectedOptions[0] : null;
            const desc = option ? (option.value || '') : '';
            const sql = option ? (option.getAttribute('data-sql') || '') : '';

            if (!desc || !sql) {
                return;
            }

            const baseText = basePromptText();
            sqlPreview.value = '-- DESC: ' + desc + '\n' + sql.trim();
            textarea.value = composeFullPrompt(baseText, sqlPreview.value);
            updateSqlStatus();
            renderMarkerState();
            syncHiddenSqlState();
            updatePromptReadyView();
            refreshStaticHighlights();
        }

        renderSqlResult(null);

        function renderSqlResult(result) {
            if (!sqlResultBody) {
                return;
            }

            sqlResultTitle.textContent = (result && result.title) ? i18nText(result.title) : i18nText('Resultado SQL');

            if (result) {
                activatePromptView('sql-result');
            }

            if (!result) {
                sqlResultBody.innerHTML = '';
                return;
            }

            if (!result.ok) {
                sqlResultBody.innerHTML = '<div class="prompt-missing-box"><strong>' + escapeHtml(i18nText('Erro:')) + '</strong> ' + escapeHtml(result.message || i18nText('Falha ao executar SQL.')) + '</div>'
                    + (result.resolved_sql ? '<pre>' + escapeHtml(result.resolved_sql) + '</pre>' : '');
                return;
            }

            if (result.render_type === 'json') {
                sqlResultBody.innerHTML = '<pre>' + escapeHtml(JSON.stringify((result.payload || {}).json || {}, null, 2)) + '</pre>';
                return;
            }

            if (result.render_type === 'table') {
                const payload = result.payload || {};
                const columns = Array.isArray(payload.columns) ? payload.columns : [];
                const rows = Array.isArray(payload.rows) ? payload.rows : [];
                let html = '<table><thead><tr>';
                columns.forEach(function (column) {
                    html += '<th>' + escapeHtml(column) + '</th>';
                });
                html += '</tr></thead><tbody>';

                rows.forEach(function (row) {
                    html += '<tr>';
                    columns.forEach(function (column) {
                        const value = row && Object.prototype.hasOwnProperty.call(row, column) ? row[column] : '';
                        html += '<td>' + escapeHtml(value == null ? '' : value) + '</td>';
                    });
                    html += '</tr>';
                });

                html += '</tbody></table>';
                sqlResultBody.innerHTML = html;
                return;
            }

            const text = (result.payload && result.payload.text) ? result.payload.text : (result.message || i18nText('Sem conteúdo.'));
            sqlResultBody.innerHTML = '<pre>' + escapeHtml(text) + '</pre>';
        }

        async function executeSqlPreview() {
            if (!execSqlButton || !sqlResultBody) {
                return;
            }

            const csrfToken = root.querySelector('input[name="_csrf"]')?.value || '';
            const companyContext = root.querySelector('input[name="company_context"]')?.value || '';
            const versionContext = root.querySelector('input[name="version_context"]')?.value || '';
            const formData = new FormData();
            formData.set('_csrf', csrfToken);
            formData.set('action', 'execute_sql');
            formData.set('prompt', textarea.value || '');
            formData.set('sql_block', currentSqlBlock());
            formData.set('company_context', companyContext);
            formData.set('version_context', versionContext);

            execSqlButton.disabled = true;
            sqlResultTitle.textContent = i18nText('Executando SQL');
            sqlResultBody.innerHTML = '<p class="muted">' + escapeHtml(i18nText('Executando...')) + '</p>';

            try {
                const response = await fetch(form.getAttribute('action') || window.location.href, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin'
                });

                const result = await response.json();
                renderSqlResult(result);
            } catch (error) {
                renderSqlResult({
                    ok: false,
                    message: error && error.message ? error.message : i18nText('Falha de comunicação ao executar SQL.')
                });
            } finally {
                execSqlButton.disabled = false;
            }
        }

        function syncFromInitialPrompt() {
            if (!sqlPreview) {
                return;
            }

            const parts = splitPromptAndSql(textarea.value || '');
            if (!sqlPreview.value.trim()) {
                sqlPreview.value = parts.sqlBlock;
            }

            textarea.value = composeFullPrompt(parts.baseText, currentSqlBlock());
            updateSqlStatus();
            renderMarkerState();
            syncHiddenSqlState();
            updatePromptReadyView();
        }

        fieldButton?.addEventListener('click', function () {
            const value = fieldPicker?.value || '';
            if (!value) {
                return;
            }

            insertMacroOnce(value);
        });

        paperButton?.addEventListener('click', function () {
            const value = paperPicker?.value || '';
            if (!value) {
                return;
            }

            insertAtCursor('\n<<' + value + '>>\n');
        });

        attachSqlButton?.addEventListener('click', function () {
            updateSqlPreviewFromPicker();
        });

        detachSqlButton?.addEventListener('click', function () {
            if (!sqlPreview) {
                return;
            }

            const baseText = basePromptText();
            sqlPreview.value = '';
            textarea.value = baseText;
            updateSqlStatus();
            renderMarkerState();
            syncHiddenSqlState();
            updatePromptReadyView();
            refreshStaticHighlights();
        });

        sqlPicker?.addEventListener('change', function () {
            updateSqlPreviewFromPicker();
        });

        textarea.addEventListener('input', function () {
            const parts = splitPromptAndSql(textarea.value || '');
            if (parts.sqlBlock !== currentSqlBlock()) {
                sqlPreview.value = parts.sqlBlock;
            }
            renderMarkerState();
            updateSqlStatus();
            syncHiddenSqlState();
            updatePromptReadyView();
            refreshStaticHighlights();
        });

        sqlPreview?.addEventListener('input', function () {
            const baseText = basePromptText();
            textarea.value = composeFullPrompt(baseText, currentSqlBlock());
            updateSqlStatus();
            renderMarkerState();
            syncHiddenSqlState();
            updatePromptReadyView();
            refreshStaticHighlights();
        });

        execSqlButton?.addEventListener('click', function () {
            executeSqlPreview();
        });

        form.addEventListener('submit', function () {
            const baseText = basePromptText();
            const sqlBlock = currentSqlBlock();

            if (!sqlBlock) {
                textarea.value = baseText;
                syncHiddenSqlState();
                return;
            }

            textarea.value = baseText + '\n\n' + sqlMarker + '\n' + sqlBlock;
            syncHiddenSqlState();
        });

        syncFromInitialPrompt();
        refreshStaticHighlights();
    });
})();
