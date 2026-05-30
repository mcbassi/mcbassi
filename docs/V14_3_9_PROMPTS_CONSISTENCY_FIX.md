# V14.3.9 — consistência da tela de prompts

## Problemas corrigidos

- marcadores exibidos em `Ações` agora são lidos do conteúdo salvo do prompt
- o bloco SQL lateral agora reflete o `EXECUTAR SQL=` salvo
- o editor abre sem o SQL duplicado dentro do campo principal
- ao salvar, o sistema recompõe `prompt base + bloco SQL`
- a tela voltou para fundo claro

## Regras atuais

- `Prompt` mostra só o texto-base
- `Bloco SQL` mostra só o conteúdo após `EXECUTAR SQL=`
- `Marcadores detectados` considera marcadores do prompt e do bloco SQL
