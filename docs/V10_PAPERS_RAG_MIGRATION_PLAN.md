# V10 — Plano técnico de migração de `papers + papers_file_cache + prompt_file_usage`

## Fonte observada no legado

O fluxo real já existe no projeto original, mas está espalhado em vários pontos:

- `papers/papers_index.php` lê a tabela `papers`
- `papers/papers_save.php` grava em `papers`
- `papers/papers_delete.php` remove de `papers`
- `listar_prompts.php` faz `LEFT JOIN papers_file_cache ON c.paper_id = p.id`
- `executa_ia_analitica.php` reaproveita upload em `papers_file_cache`
- `executa_ia_analitica.php` registra uso em `prompt_file_usage`

Conclusão: o módulo não é um CRUD isolado. Ele é um catálogo bibliográfico com uma camada RAG/cache técnica já acoplada ao fluxo de IA.

## Modelo alvo

### 1. `papers`
Fonte principal do catálogo.

Campos observados no legado:
- `id`
- `title`
- `journal`
- `key_insight`
- `citation_count`
- `keywords`
- `link_url`
- `file_source_type`
- `file_source_value`
- `file_enabled`
- `file_preferred_name`
- `file_preferred_mime`
- `file_last_resolved_at`
- `prompt_code`
- `chapter_code`
- `created_at`

### 2. `papers_file_cache`
Camada RAG/cache por artigo ou arquivo resolvido.

Campos observados no legado:
- `cache_id`
- `paper_id`
- `source_sha256`
- `original_filename`
- `mime_type`
- `file_ext`
- `size_bytes`
- `local_cache_path`
- `source_type`
- `source_value`
- `openai_file_id`
- `openai_file_purpose`
- `vector_store_id`
- `cache_status`
- `exists_flag`
- `last_error`
- `last_used_at`

### 3. `prompt_file_usage`
Auditoria do uso do arquivo em execuções de IA.

Campos observados no legado:
- `response_detailed_id`
- `company_name`
- `email_resp`
- `sess_min`
- `prompt_row_id`
- `paper_title`
- `source_type`
- `source_value`
- `cache_id`
- `openai_file_id`
- `execution_mode`

## Refatoração proposta

## Fase 1 — leitura real sem quebrar o shell
Objetivo: fazer o shell novo refletir os dados reais.

### Entregas
- `PaperRepository` passa a usar PDO central
- join opcional com `papers_file_cache`
- agregação opcional de uso por `prompt_file_usage`
- UI mostra status RAG por publicação

### Critério de pronto
- a listagem da tela nova mostra os artigos reais
- o detalhe de cada artigo exibe:
  - status de cache
  - `openai_file_id`
  - `vector_store_id`
  - `last_used_at`
  - uso em prompts

## Fase 2 — CRUD real no catálogo
Objetivo: o shell novo deixa de ser mock e passa a escrever na tabela `papers`.

### Entregas
- salvar e editar artigo real
- excluir artigo com POST + CSRF
- manter campos do legado
- preservar look-and-feel do shell

### Critério de pronto
- o novo módulo substitui `papers/papers_form.php`, `papers/papers_save.php` e `papers/papers_delete.php`

## Fase 3 — separar responsabilidades do RAG
Objetivo: tirar o acoplamento escondido do fluxo de IA.

### Classes alvo
- `App\Papers\PaperRepository`
- `App\Papers\RagRepository`
- `App\Papers\PaperController`
- `App\Papers\PaperRagStatusService`
- `App\AI\PromptFileUsageRepository`

### Regra
- `papers` continua sendo o cadastro principal
- `papers_file_cache` vira camada técnica de estado
- `prompt_file_usage` vira auditoria/telemetria

## Fase 4 — sincronização e reindexação
Objetivo: ao mudar um artigo, o estado RAG precisa ficar previsível.

### Fluxo
1. salvar artigo
2. se `file_source_type`/`file_source_value` mudou:
   - atualizar `file_last_resolved_at`
   - marcar cache como pendente ou stale
3. job futuro pode:
   - resolver arquivo
   - subir para OpenAI
   - atualizar `openai_file_id`
   - atualizar `vector_store_id`
   - registrar `cache_status`

## Contrato da V10

A V10 materializa a Fase 1 e a maior parte da Fase 2:
- leitura real de `papers`
- leitura complementar de `papers_file_cache`
- leitura agregada de `prompt_file_usage`
- CRUD real na tabela `papers`
- exibição de status RAG no shell novo

## Riscos do legado preservados e já tratados na V10
- tabela auxiliar pode não existir
- coluna `paper_id` pode não existir em todas as bases
- nem toda instalação terá `vector_store_id`
- por isso a V10 faz introspecção de schema antes de montar joins
