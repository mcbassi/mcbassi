Correção da navegação de Clientes

Arquivos incluídos:
- public/clientes/index.php
- public/clientes/cadastro.php
- app/views/layouts/app.php
- app/views/Clientes/index.php
- app/views/Clientes/cadastro.php
- app/src/Clientes/ClienteController.php

O que foi ajustado:
1. Criados entrypoints públicos para /clientes/index.php e /clientes/cadastro.php
2. Corrigidos os links do menu e do quick-pill Clientes
3. Corrigidos os links internos da área de Clientes
4. Corrigido o action do formulário de cadastro
5. Ajustado View::render(...) para a pasta real app/views/Clientes

Como aplicar:
- Extraia estes arquivos sobre o projeto atual mantendo a mesma estrutura de pastas.
- Depois acesse:
  * /public/clientes/index.php
  * /public/clientes/cadastro.php
  * ou use os menus do sistema

