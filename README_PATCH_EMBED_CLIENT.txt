PATCH: modo embed para Portal Client

Objetivo:
- Permitir que telas do sistema principal sejam abertas dentro do client sem exibir menu lateral, cabeçalho e área completa do sistema principal.

Arquivos alterados/adicionados:
- app/src/Support/View.php
- app/views/layouts/embed.php
- app/views/diagnostico/respond.php
- app/src/Diagnostico/ClientQuestionarioController.php

Como funciona:
- Ao acessar uma tela com ?embed=1, o sistema usa o layout app/views/layouts/embed.php.
- Esse layout carrega o CSS principal, mas mostra apenas o conteúdo interno da tela.
- No questionário, os links Nova resposta / Recarregar última versão e o POST do formulário preservam embed=1.

Exemplo:
/produtividade_emp/public/diagnostico/respond.php?embed=1
