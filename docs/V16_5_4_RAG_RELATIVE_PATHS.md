# V16.5.4 — Bibliografia com caminhos relativos

## Problema
Alguns papers vêm do legado com:
- `file_source_type = url`
- `file_source_value = Management Practices/.../arquivo.docx`

Esse valor não é uma URL HTTP; é um caminho relativo de pasta.

## Correção
- `PaperFileService` agora trata qualquer `file_source_value` não-HTTP como candidato local
- amplia as raízes de busca:
  - `Bibliografia/upload`
  - `Bibliografia`
  - `papers`
  - `papers/Bibliografia`
  - `papers/Bibliografia/upload`
  - raiz do projeto
  - diretório pai do projeto
- `findByBasename()` também passa a considerar `link_url` e `source_value`
