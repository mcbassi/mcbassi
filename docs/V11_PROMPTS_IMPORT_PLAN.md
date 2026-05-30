# V11 — Plano técnico de migração: papers + prompt flow + papers_import

## Escopo executado

Esta versão fecha os dois pontos que faltavam no shell novo:

1. ligar o módulo `papers` ao fluxo de prompts já existente no legado
2. trazer o `papers_import` real para dentro do shell visual novo

## Leitura do legado

No projeto original, a bibliografia não é isolada. Ela já conversa com a IA por três estruturas principais:

- `papers`
- `papers_file_cache`
- `prompt_file_usage`

E o fluxo de prompts usa pelo menos estas peças:

- `prompts`
- `CRUD_prompts.php`
- `listar_prompts.php`
- `executa_ia_analitica.php`

## Como a V11 modela isso

### Fonte principal
- `papers`

### Camada RAG/cache
- `papers_file_cache`

### Auditoria de uso em IA
- `prompt_file_usage`

### Catálogo de prompts
- `prompts`

## Componentes novos da V11

- `App\Papers\PromptFlowRepository`
- `App\Papers\PaperImportService`
- `App\Papers\PromptFlowController`
- `App\Papers\PaperImportController`

## O que a V11 passa a fazer

### papers/index.php
- continua lendo a tabela real `papers`
- continua mostrando status RAG
- agora mostra também o contexto de prompt do paper selecionado:
  - prompts do catálogo ligados por `prompt_code`
  - uso recente real em `prompt_file_usage`

### papers/prompts.php
- mostra o mapa `papers.prompt_code -> prompts.assistente`
- mostra contagem de papers ligados por prompt
- mostra uso recente em `prompt_file_usage`

### papers/import.php
- replica o conceito do legado `papers_import.php`
- lê `PAPER_IMPORT_BASE_PATH` do `.env`
- varre a pasta real
- faz preview dos arquivos encontrados
- importa ou atualiza registros na tabela `papers`

## Regras de vínculo usadas

### Vínculo com prompts
- `papers.prompt_code = prompts.assistente`

### Vínculo com uso real em IA
- `papers_file_cache.paper_id = papers.id`
- `prompt_file_usage.cache_id = papers_file_cache.cache_id`

## Limites atuais da V11

- não reescreve ainda o JavaScript legado do `papers_import_page.js`
- não dispara upload para OpenAI
- não cria nem atualiza `papers_file_cache`
- não recria o pipeline completo de sync Dropbox/rclone
- não substitui ainda `executa_ia_analitica.php`

## Próximo passo natural

Depois da V11, o próximo avanço certo é migrar o pipeline operacional de anexação de arquivos ao prompt, preservando:

- reaproveitamento por hash
- `openai_file_id`
- `vector_store_id`
- log em `prompt_file_usage`
