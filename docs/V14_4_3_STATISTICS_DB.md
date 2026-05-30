# V14.4.3 — Base separada para estatísticas

## Objetivo

Executar os blocos `EXECUTAR SQL=` na base `statistics`, sem usar a base principal `form_app`.

## Configuração

Use no `.env`:

```dotenv
STAT_DB_DRIVER=mysql
STAT_DB_HOST=127.0.0.1
STAT_DB_PORT=3306
STAT_DB_NAME=statistics
STAT_DB_USER=root
STAT_DB_PASS=
STAT_DB_CHARSET=utf8mb4
```

## Regra

- `Database::pdo()` continua apontando para a base principal do sistema
- `Database::statisticsPdo()` aponta para a base de estatísticas
- `PromptRuntimeService` usa `statisticsPdo()` na execução dos blocos SQL
