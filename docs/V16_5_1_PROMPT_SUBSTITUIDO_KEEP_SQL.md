# V16.5.1 — Prompt Substituído mantendo o SQL

## Ajuste
A coluna `Prompt Substituído` continua processando:
- handlers `<<...>>`
- referências de documentos
- `SQL_RESULT_JSON`

Mas agora também preserva no fim do texto:
- `EXECUTAR SQL=`
- `-- DESC: ...`
- SQL já resolvido com os valores substituídos

## Resultado
O desenvolvedor consegue conferir no mesmo bloco:
- o texto final consumido pelo modelo
- e o SQL efetivamente usado na preparação
