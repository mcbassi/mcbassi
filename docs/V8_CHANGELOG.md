# V8 - Navegação interna do shell

## Entrou
- troca de conteúdo dentro da área flutuante principal
- navegação AJAX entre dashboard, diagnóstico, placeholders e Tela SQL
- `history.pushState` e `popstate`
- atualização visual do menu ativo sem recarregar a página inteira
- suporte a partial render por header `X-Shell-Partial` e query `_partial=1`

## Arquivos principais
- `app/src/Support/View.php`
- `app/views/layouts/app.php`
- `public/assets/js/shell.js`
- `public/assets/css/app.css`

## Observação
- os módulos continuam aceitando acesso direto por URL
- quando navegados pelo shell, o conteúdo é trocado dentro do canvas central
