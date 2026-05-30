# V14.4.4 — Correções de SQL em statistics e salvamento por ID

## SQL operacional
- a execução continua usando a conexão `STAT_DB_*`
- além disso, qualquer prefixo explícito da base principal, como `form_app.`, é reescrito para a base de estatísticas configurada

## Salvamento do prompt
- o update do prompt editado agora preserva `assistente`, `funcao` e `descricao` a partir do próprio `id`
- o formulário envia também o estado de SQL em campos ocultos
- isso elimina a dependência de dados da lista anterior para atualizar o registro
