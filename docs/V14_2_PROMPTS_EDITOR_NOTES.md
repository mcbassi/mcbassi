# V14.2 — Prompts editor

## O que mudou

- A edição de prompts saiu do modo inline e foi para uma tela própria em `public/prompts/form.php`.
- A listagem continua em `public/prompts/index.php`.
- A nova tela preserva os elementos operacionais do legado:
  - Prompt Code
  - Função
  - Descrição
  - Texto do prompt
  - inserção de perguntas
  - inserção de papers
  - bloco `EXECUTAR SQL=`

## Conexão com IA Analítica / Estratégica

- Links das telas de Analítica e Estratégica agora abrem o editor com `?context=analitica` ou `?context=estrategica`.
- A tela de edição mostra um bloco de prévia operacional usando a última versão do questionário do usuário logado.
- Marcadores `<<campo>>` são resolvidos com respostas reais quando possível.
- Marcadores de papers são sinalizados como `[paper] ...`.

## Observação

A prévia operacional é uma camada segura de apoio à edição.
Ela não substitui ainda o pipeline completo de execução de IA do legado.
