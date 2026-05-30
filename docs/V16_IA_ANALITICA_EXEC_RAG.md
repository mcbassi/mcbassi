# V16 — IA Analítica com execução real e RAG

## O que foi migrado

- execução real do pacote da IA Analítica
- resolução de SQL antes do envio ao modelo
- materialização dos papers citados no prompt
- reuso de `openai_file_id` quando já existe em `papers_file_cache`
- upload para OpenAI quando o paper ainda não foi carregado
- gravação/atualização do cache em `papers_file_cache`
- registro de uso em `prompt_file_usage`
- persistência do retorno em `responses_detailed.prompt_response`

## Estrutura preservada

1. montar o prompt com placeholders resolvidos
2. executar o SQL, quando houver
3. preparar os documentos citados
4. reutilizar `openai_file_id` quando possível
5. subir arquivo quando necessário
6. chamar a Responses API com `input_file` + `input_text`
7. gravar a resposta na sessão
