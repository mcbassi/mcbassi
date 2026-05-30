<?php require __DIR__ . '/config.php';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$paper = [
  'title' => '',
  'journal' => '',
  'key_insight' => '',
  'citation_count' => 0,
  'keywords' => '',
  'link_url' => '',
  'file_source_type' => '',
  'file_source_value' => '',
  'file_enabled' => 1,
  'file_preferred_name' => '',
  'file_preferred_mime' => '',
  'prompt_code' => '',
  'chapter_code' => ''
];
if ($id) {
  $stmt = $pdo->prepare("SELECT * FROM papers WHERE id=?");
  $stmt->execute([$id]);
  $paper = $stmt->fetch() ?: $paper;
}
?>
<!doctype html><html lang="pt-br"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo $id ? 'Editar' : 'Novo'; ?> Paper</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head><body class="bg-light">
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><?php echo $id ? 'Editar' : 'Novo'; ?> Paper</h1>
    <a class="btn btn-outline-secondary" href="papers_index.php">Voltar</a>
  </div>

  <form method="post" action="papers_save.php">
    <?php csrf_input(); ?>
    <input type="hidden" name="id" value="<?php echo (int)$id; ?>">

    <div class="card mb-3">
      <div class="card-body">
        <div class="mb-3">
          <label class="form-label">Título *</label>
          <input class="form-control" name="title" required value="<?php echo h($paper['title']); ?>">
        </div>
        <div class="mb-3">
          <label class="form-label">Journal / Onde foi publicado *</label>
          <input class="form-control" name="journal" required value="<?php echo h($paper['journal']); ?>">
        </div>
        <div class="mb-3">
          <label class="form-label">Palavras-chave (separe por vírgula)</label>
          <input class="form-control" name="keywords" value="<?php echo h($paper['keywords']); ?>" placeholder="ex.: productivity, SMEs, workplace">
        </div>
        <div class="mb-3">
          <label class="form-label">Key Insight</label>
          <textarea class="form-control" name="key_insight" rows="3"><?php echo h($paper['key_insight']); ?></textarea>
        </div>
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">Nº de Citações</label>
            <input type="number" class="form-control" name="citation_count" min="0" value="<?php echo (int)$paper['citation_count']; ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label">Relacionar a Questão/Grupo (prompt_code)</label>
            <input class="form-control" name="prompt_code" value="<?php echo h($paper['prompt_code']); ?>" placeholder="ex.: PRD_COMP_01">
          </div>
          <div class="col-md-4">
            <label class="form-label">Capítulo do Questionário (chapter_code)</label>
            <input class="form-control" name="chapter_code" value="<?php echo h($paper['chapter_code']); ?>" placeholder="ex.: CAP_06">
          </div>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><strong>Origem do arquivo</strong></div>
      <div class="card-body">
        <div class="mb-3">
          <label class="form-label">Link legado / referência visível</label>
          <input class="form-control" name="link_url" value="<?php echo h($paper['link_url']); ?>" placeholder="https://... ou caminho relativo legado">
          <div class="form-text">Mantido por compatibilidade com o fluxo atual. O novo processamento prefere os campos abaixo.</div>
        </div>

        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">Tipo da origem</label>
            <select class="form-select" name="file_source_type">
              <?php
              $types = [
                '' => 'Selecionar...',
                'url' => 'url',
                'relative_path' => 'relative_path',
                'local_path' => 'local_path',
                'cloud_path' => 'cloud_path',
                'openai_file_id' => 'openai_file_id',
              ];
              foreach ($types as $value => $label):
              ?>
                <option value="<?php echo h($value); ?>" <?php echo ((string)$paper['file_source_type'] === (string)$value) ? 'selected' : ''; ?>><?php echo h($label); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-8">
            <label class="form-label">Valor da origem</label>
            <input class="form-control" name="file_source_value" value="<?php echo h((string)$paper['file_source_value']); ?>" placeholder="URL, caminho relativo, caminho absoluto ou file_id">
          </div>
        </div>

        <div class="row g-3 mt-1">
          <div class="col-md-5">
            <label class="form-label">Nome preferido do arquivo</label>
            <input class="form-control" name="file_preferred_name" value="<?php echo h((string)$paper['file_preferred_name']); ?>" placeholder="ex.: artigo_produtividade.pdf">
          </div>
          <div class="col-md-5">
            <label class="form-label">MIME preferido</label>
            <input class="form-control" name="file_preferred_mime" value="<?php echo h((string)$paper['file_preferred_mime']); ?>" placeholder="application/pdf">
          </div>
          <div class="col-md-2 d-flex align-items-end">
            <div class="form-check mb-2">
              <input class="form-check-input" type="checkbox" name="file_enabled" id="file_enabled" value="1" <?php echo !empty($paper['file_enabled']) ? 'checked' : ''; ?>>
              <label class="form-check-label" for="file_enabled">Ativo</label>
            </div>
          </div>
        </div>
      </div>
      <div class="card-footer d-flex gap-2">
        <button class="btn btn-primary" type="submit">Salvar</button>
        <a class="btn btn-outline-secondary" href="papers_index.php">Cancelar</a>
      </div>
    </div>
  </form>
</div>
</body></html>
