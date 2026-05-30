# V5.1 fix

- Corrigido bootstrap: `app/bootstrap/app.php` agora carrega `helpers.php` antes do primeiro uso de `app_path()`.
- Resolve o erro fatal: `Call to undefined function app_path()`.
