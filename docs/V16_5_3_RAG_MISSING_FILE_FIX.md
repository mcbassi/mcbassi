# V16.5.3 — Falha ao preparar bibliografia/RAG

## Correção
- busca `papers_file_cache` por `paper_id` antes de tentar localizar arquivo local
- reaproveita `openai_file_id` do cache sempre que existir
- usa URL externa do paper/cache como fallback, quando disponível
- erro passou a informar as fontes verificadas para o paper
