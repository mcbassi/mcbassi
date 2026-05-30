# V10 Setup

1. Copie `.env.example` para `.env`
2. Ajuste:
   - `DB_DRIVER=mysql`
   - `DB_HOST`
   - `DB_PORT`
   - `DB_NAME=form_app`
   - `DB_USER`
   - `DB_PASS`
3. Acesse:
   - `/public/index.php?user=marco&email=mcbassi%40grupohdi.com&nivel=BPO`
   - `/public/papers/index.php`

Se a tabela `papers` não existir no banco apontado, a tela mostrará um erro explícito no shell.
