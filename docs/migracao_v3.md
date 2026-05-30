# V3

## Entregue nesta versão

- detecção automática de subpasta via `SCRIPT_NAME`
- roteamento correto para `/public/index.php` em projetos locais
- geração de links com `url()` e `asset()`
- compatibilidade parcial com o legado:
  - `OPENAI_API_KEY`
  - `$pdo`
  - `$DB_HOST`, `$DB_NAME`, `$DB_USER`, `$DB_PASS`, `$DB_PORT`
  - `csrf_token()`, `csrf_input()`, `check_csrf()`
  - sessão legada `$_SESSION['auth']`
  - admin legado `$_SESSION['is_admin']`

## O que ainda não foi migrado

- regras de negócio dos módulos
- queries reais
- telas reais
- integrações OpenAI e Dropbox/rclone
- shell SPA do `index.php` legado
