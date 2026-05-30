# V16.5.5 — Alinhamento com a importação legada de bibliografia

## Regra do legado
A importação antiga grava arquivos locais como:
- `file_source_type = relative_path`
- `file_source_value = caminho relativo`
- `link_url = o mesmo caminho relativo`

A base usada para resolver esses caminhos é a pasta da bibliografia importada, especialmente:
- `Bibliografia/upload/CR y R`

## Ajustes
- caminhos com `file_source_type = url` mas sem `http(s)` passam a ser tratados como `relative_path`
- `PaperFileService` ganhou as raízes legadas:
  - `Bibliografia/upload/CR y R`
  - `Bibliografia/upload`
- links externos HTTP continuam sendo tratados como URL externa
