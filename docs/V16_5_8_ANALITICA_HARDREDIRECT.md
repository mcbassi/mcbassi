# V16.5.8 — Blindagem de navegação da IA Analítica

## Ajustes
- `public/analitica/index.php` recusa `_partial=1` com status `409` e header `X-Full-Reload: 1`
- `shell.js` faz redirecionamento completo ao detectar `X-Full-Reload`
- links remanescentes para IA Analítica em telas de prompts foram marcados com `data-shell-nav="off"`

## Efeito
Mesmo que alguma rota ainda tente carregar `/analitica` via shell parcial, o sistema força a abertura completa da página.
