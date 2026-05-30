# V16.5.6 — IA Analítica com carga leve

## Ajuste principal
A tela de IA Analítica deixou de executar o SQL na montagem do grid.

## Como ficou
- no carregamento da página:
  - handlers de respostas são substituídos
  - artigos são resolvidos para nomes
  - o bloco `EXECUTAR SQL=` aparece já substituído
  - `SQL_RESULT_JSON` fica como pendente
- na execução real do prompt:
  - o SQL é executado
  - o resultado é convertido em JSON
  - o `SQL_RESULT_JSON` é preenchido
  - só então o prompt final é enviado ao modelo
