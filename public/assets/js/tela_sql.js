(function () {
    const editor = document.getElementById('sql-editor');
    const message = document.getElementById('message');
    const result = document.getElementById('result');
    const schema = document.getElementById('schema');
    const titleInput = document.getElementById('catalog-title');
    const descriptionInput = document.getElementById('catalog-description');

    function setMessage(text, ok) {
        message.textContent = text || '';
        message.className = 'message ' + (ok ? 'message--success' : 'message--error');
    }

    async function postJson(url, payload) {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(payload)
        });

        return response.json();
    }

    function renderTable(columns, rows) {
        if (!Array.isArray(columns) || columns.length === 0) {
            result.innerHTML = '<p class="muted">Nenhum resultado.</p>';
            return;
        }

        const head = columns.map((column) => `<th>${escapeHtml(column)}</th>`).join('');
        const body = rows.map((row) => {
            const cells = columns.map((column) => `<td>${escapeHtml(String(row[column] ?? ''))}</td>`).join('');
            return `<tr>${cells}</tr>`;
        }).join('');

        result.innerHTML = `
            <table class="result-table">
                <thead><tr>${head}</tr></thead>
                <tbody>${body}</tbody>
            </table>
        `;
    }

    function renderSchema(items) {
        if (!Array.isArray(items) || items.length === 0) {
            schema.innerHTML = '<p class="muted">Schema vazio.</p>';
            return;
        }

        schema.innerHTML = items.map((item) => {
            const columns = item.columns.map((column) => `
                <li>
                    <strong>${escapeHtml(column.name)}</strong>
                    <span>(${escapeHtml(column.type || '')})</span>
                    ${column.pk ? '<span> PK</span>' : ''}
                    ${column.nullable ? '<span> nullable</span>' : '<span> not null</span>'}
                </li>
            `).join('');

            return `
                <div class="schema-block">
                    <h4>${escapeHtml(item.table)}</h4>
                    <ul>${columns}</ul>
                </div>
            `;
        }).join('');
    }

    function escapeHtml(value) {
        return value
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    document.getElementById('validate-btn')?.addEventListener('click', async () => {
        const data = await postJson(window.TELA_SQL.validateUrl, { sql: editor.value });
        setMessage(data.message || '', !!data.ok);
    });

    document.getElementById('execute-btn')?.addEventListener('click', async () => {
        const data = await postJson(window.TELA_SQL.executeUrl, { sql: editor.value });

        if (!data.ok) {
            setMessage(data.message || 'Falha ao executar.', false);
            result.innerHTML = '';
            return;
        }

        setMessage(`Consulta executada. ${data.count} linha(s).`, true);
        renderTable(data.columns, data.rows);
    });

    document.getElementById('schema-btn')?.addEventListener('click', async () => {
        const response = await fetch(window.TELA_SQL.schemaUrl);
        const data = await response.json();

        if (!data.ok) {
            setMessage(data.message || 'Falha ao carregar schema.', false);
            return;
        }

        setMessage('Schema carregado.', true);
        renderSchema(data.items);
    });

    document.getElementById('save-catalog-btn')?.addEventListener('click', async () => {
        const data = await postJson(window.TELA_SQL.catalogSaveUrl, {
            _csrf: window.TELA_SQL.csrf,
            title: titleInput.value,
            description: descriptionInput.value,
            sql: editor.value
        });

        if (!data.ok) {
            setMessage(data.message || 'Falha ao salvar catálogo.', false);
            return;
        }

        setMessage('Consulta salva no catálogo. Recarregue a página para vê-la na lista.', true);
    });

    document.querySelectorAll('.catalog-item').forEach((button) => {
        button.addEventListener('click', async () => {
            editor.value = button.dataset.sql || '';
            titleInput.value = button.dataset.title || '';
            descriptionInput.value = button.dataset.description || '';
            setMessage('Consulta carregada do catálogo.', true);
        });
    });
})();
