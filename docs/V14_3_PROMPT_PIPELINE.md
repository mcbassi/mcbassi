# V14.3 — Fluxo de prompts conectado ao pacote operacional

## O que esta versão entrega

- substituição de marcadores `<<pergunta>>` com base na última versão do questionário
- detecção de marcadores de `papers` por título
- montagem de anexos operacionais a partir de `papers` + `papers_file_cache`
- execução segura de blocos `EXECUTAR SQL=` em modo `SELECT/WITH`
- tela de `IA Analítica` e `IA Estratégica` consumindo o pacote montado

## Fluxo usado

1. carrega a versão vigente do questionário do usuário
2. lê `form_fields.prompt_code`
3. localiza o prompt na tabela `prompts`
4. resolve marcadores com as respostas
5. identifica papers referenciados
6. executa SQL do bloco, quando existir
7. monta o `compiled_prompt`

## Limites atuais

- ainda não chama a API da OpenAI
- ainda não grava `prompt_file_usage` no momento da execução
- ainda não envia `input_file` real para um endpoint de IA

## O que já fica pronto para a próxima etapa

- pacote consolidado por contexto
- papers anexáveis identificados
- SQL operacional validado e pré-executado
- telas de Analítica e Estratégica preparadas para chamar um executor real
