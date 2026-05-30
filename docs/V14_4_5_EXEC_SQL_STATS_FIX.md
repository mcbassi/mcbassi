# V14.4.5 — Correção do botão Exec. SQL

## Causa
Havia dois caminhos diferentes para SQL:
- prévia montada do prompt
- botão `Exec. SQL`

A prévia já usava `statisticsPdo()`, mas o botão ainda executava em `pdo()`.

## Correção
- `executeSqlPreview()` agora usa `statisticsPdo()`
- também aplica `normalizeSqlForStatistics()` antes da execução
- `legacy_config.php` ganhou fallback automático para `STAT_DB_*`
- `Database` agora usa `statistics` como default da conexão de estatísticas
