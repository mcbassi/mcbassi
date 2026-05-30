# V16.5.9 — Cache bust do shell

## Motivo
Após várias trocas no `shell.js`, o navegador podia continuar usando a versão antiga em cache, mantendo a navegação parcial da IA Analítica.

## Ajuste
- `app.css` e `shell.js` passam a carregar com `?v=filemtime(...)`
- isso força o navegador a baixar a versão nova após atualizar a aplicação
