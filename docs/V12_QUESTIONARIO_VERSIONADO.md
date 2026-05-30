# V12 — Questionário versionado

## Leitura do legado

O fluxo legado salvava o questionário em `responses_detailed`, uma linha por pergunta, usando o mesmo `response_datetime` para agrupar uma sessão. Os pontos principais encontrados:

- `submit.php`
  - grava snapshot completo em `responses_detailed`
  - apagava respostas do mesmo dia para `email_resp + company_name`
  - não preservava histórico de versões por sessão
- `historico_respostas.php`
  - lê `responses_detailed`
  - monta histórico por sessão agrupando por `response_datetime`
- `editar_resposta_sessao.php`
  - edita respostas de uma sessão existente
- `dashboard_respostas.php`
  - usa a mesma tabela para calcular completude e indicadores

## Ajuste nesta versão

- nova tabela `response_sessions`
- cada salvamento cria uma nova versão
- a versão mais recente passa a ser a referência para o processamento
- `responses_detailed` continua sendo preenchida para manter compatibilidade com o legado
- se o salvamento acontecer no mesmo minuto da sessão anterior, o `response_datetime` é deslocado para o próximo minuto livre, preservando a separação das versões nas telas legadas

## Arquivos novos

- `app/src/Diagnostico/FormFieldRepository.php`
- `app/src/Diagnostico/VersionedResponseRepository.php`

## Arquivos alterados

- `app/src/Diagnostico/QuestionarioController.php`
- `app/views/diagnostico/formulario.php`
- `public/diagnostico/index.php`
- `public/assets/css/app.css`
