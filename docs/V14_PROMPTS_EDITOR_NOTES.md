# V14 — Tela de edição de prompts

## O que foi trazido do legado

A tela nova foi montada a partir do comportamento real de `CRUD_prompts.php`:

- CRUD da tabela `prompts`
- relação `prompts.assistente = form_fields.prompt_code`
- filtros por Prompt Code, Função e Seção
- seleção de perguntas do questionário
- seleção de papers
- anexação de bloco `EXECUTAR SQL=`

## Ajuste importante de compatibilidade

O legado tinha um desencontro entre a tela de edição e o pré-processamento dos prompts:
- a edição inseria `{{campo}}` e `[ARTIGO] Título`
- o pré-processamento do fluxo de IA resolve marcadores `<<...>>`

Nesta versão, a inserção da tela nova já usa:
- `<<nome da pergunta>>`
- `<<título do paper>>`

Isso evita criar prompts novos com marcadores incompatíveis.

## Tabelas esperadas

- `prompts`
- `form_fields`
- `papers`
- `datasmart_query_versions` (opcional, para SQL)

Se `datasmart_query_versions` não existir, a tela continua funcionando, apenas sem o picker de SQL.
