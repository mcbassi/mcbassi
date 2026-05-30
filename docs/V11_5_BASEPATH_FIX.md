# V11.5 Base Path Fix

## Correção
A base URL deixava de apontar para `/public` quando a requisição entrava por um arquivo em subpasta, como `public/papers/index.php`.

Exemplo quebrado:
- página atual: `/produtividade_emp/public/papers/index.php`
- link gerado: `/produtividade_emp/public/papers/papers/form.php`
- Apache reescrevia para `/public/index.php`
- resultado visual: usuário voltava ao Dashboard

## Ajuste
`App\Support\Request::basePath()` agora detecta a raiz web até `/public`, independentemente do entrypoint atual.

## Impacto
Passam a funcionar:
- filtros de `papers`
- botões `Ver`, `Editar`, `Novo Paper`
- navegação para `import.php` e `prompts.php`
- qualquer link gerado a partir de wrappers em subpastas de `public/`
