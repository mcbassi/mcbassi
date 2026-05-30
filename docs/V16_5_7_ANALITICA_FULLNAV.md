# V16.5.7 — IA Analítica em navegação completa

## Motivo
A rota `/analitica/index.php` estava sendo carregada pelo shell em modo parcial (`?_partial=1`), o que gerava instabilidade após erros pesados e podia terminar em `chrome-error://chromewebdata/`.

## Ajuste
- links para `IA Analítica` passam a usar carregamento completo
- o `shell.js` ignora a rota `/analitica` na navegação parcial
- o fallback de `popstate` também respeita essa regra
