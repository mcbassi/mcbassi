# V15 — Editar Grupos

## O que esta versão entrega

- módulo real em `public/grupos/index.php`
- descoberta automática de tabelas candidatas no banco atual
- lista de registros
- edição por formulário dinâmico
- criação de registro
- exclusão de registro

## Como a descoberta funciona

O editor procura tabelas cujo nome contenha:
- `group`
- `grupo`
- `chapter`

Também considera tabelas com colunas típicas:
- `group_code`
- `group_name`
- `grupo_codigo`
- `grupo_nome`
- `chapter_code`
- `chapter_name`

## Observação

Como o legado de grupos não veio isolado aqui, esta abordagem evita hardcode frágil e usa as estruturas reais existentes no banco da instalação local.
