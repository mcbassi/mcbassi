Patch único baseado no Backup4.

Arquivos incluídos:
- app/views/damodaram/_toolbar.php
- app/views/damodaram/wms_bridge.php
- public/DAMODARAM/wms_bridge_prompt_api.php

O que corrige:
- mantém o botão "Executar 3 prompts" na aba WMS Bridge
- mantém o uso do combo já existente "Selecione o questionário"
- usa version_id, year e industry vindos da própria tela
- executa a sp_damodaran_prompt_master(...)
- executa os 3 prompts pela rotina padrão do sistema
- junta os 3 resultados em um único HTML na modal
- mantém "Editar dados" na barra de abas
