# V16.1.1 — Exibição de arquivos e SQL na IA Analítica

Ajustes aplicados sobre a base estável V16.1:
- Prompt Original usa o texto completo do prompt (`prompt_full_text`) quando existir
- Prompt Substituído passa a manter o bloco `EXECUTAR SQL=` ao final
- coluna Arquivos detecta referências em linhas `[ARTICLE]`, `[ARTIGO]` e `[PAPER]`
- coluna SQL mostra o texto real do SQL resolvido
- prompts com SQL salvo em campo separado também passam a exibir o bloco corretamente
