# V13.1 — Histórico agrupado + Resultados em dashboard

## Entregas
- Histórico agrupado por empresa
- Destaque visual para a versão usada como base do processamento
- Dashboard de resultados mais próximo do shell legado
- Navegação rápida entre versões da mesma empresa

## Ajustes técnicos
- `HistoryController` agora agrupa versões por `company_name`
- `ResultsController` expõe versões da mesma empresa
- `history.php` passou a renderizar cartões por empresa
- `results.php` virou um dashboard com cards e seleção lateral de versões
