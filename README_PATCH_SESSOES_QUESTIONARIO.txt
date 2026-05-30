Patch do sistema principal - carregamento de sessões no questionário embed

Objetivo:
- Corrigir o carregamento de respostas quando o client seleciona uma sessão anterior no combo.

Correção:
- Atualiza app/src/Diagnostico/VersionedResponseRepository.php.
- Se responses_detailed não tiver registros ligados por response_session_id, o sistema faz fallback pelo minuto de response_datetime.
- Corrige comparação de data com segundos, normalizando para YYYY-MM-DD HH:ii.
- Permite localizar sessões também por email_resp quando aplicável.

Impacto esperado:
- Não remove o fluxo existente.
- Mantém o carregamento por response_session_id quando existir.
- Só usa fallback quando a busca principal retorna vazia.
