<?php require __DIR__.'/config.php'; ?>
<!doctype html><html lang="pt-br"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Papers - Relatório</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head><body class="bg-light">
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">Relatório de Papers</h1>
    <a class="btn btn-outline-secondary" href="papers_index.php">Voltar</a>
  </div>

  <?php
    $top = $pdo->query("SELECT id,title,journal,citation_count FROM papers ORDER BY citation_count DESC, id DESC LIMIT 20")->fetchAll();

    $byJournal = $pdo->query("SELECT journal, COUNT(*) as n, SUM(citation_count) as total FROM papers GROUP BY journal ORDER BY total DESC")->fetchAll();

    $byChapter = $pdo->query("SELECT chapter_code, COUNT(*) as n, SUM(citation_count) as total FROM papers GROUP BY chapter_code ORDER BY total DESC")->fetchAll();

    $byPrompt = $pdo->query("SELECT prompt_code, COUNT(*) as n FROM papers GROUP BY prompt_code ORDER BY n DESC")->fetchAll();
  ?>

  <div class="row g-4">
    <div class="col-lg-6">
      <div class="card h-100">
        <div class="card-header">Top por Citações</div>
        <div class="table-responsive">
          <table class="table table-sm mb-0">
            <thead><tr><th>#</th><th>Título</th><th>Journal</th><th>Citações</th></tr></thead>
            <tbody>
              <?php foreach ($top as $r): ?>
              <tr>
                <td><?php echo (int)$r['id']; ?></td>
                <td><?php echo h($r['title']); ?></td>
                <td><?php echo h($r['journal']); ?></td>
                <td><?php echo (int)$r['citation_count']; ?></td>
              </tr>
              <?php endforeach; if (!$top): ?>
              <tr><td colspan="4" class="text-center text-muted">Sem dados</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="col-lg-6">
      <div class="card h-100">
        <div class="card-header">Citações por Journal</div>
        <div class="table-responsive">
          <table class="table table-sm mb-0">
            <thead><tr><th>Journal</th><th># Papers</th><th>Total Citações</th></tr></thead>
            <tbody>
              <?php foreach ($byJournal as $r): ?>
              <tr>
                <td><?php echo h($r['journal']); ?></td>
                <td><?php echo (int)$r['n']; ?></td>
                <td><?php echo (int)$r['total']; ?></td>
              </tr>
              <?php endforeach; if (!$byJournal): ?>
              <tr><td colspan="3" class="text-center text-muted">Sem dados</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="col-lg-6">
      <div class="card h-100">
        <div class="card-header">Citações por Capítulo</div>
        <div class="table-responsive">
          <table class="table table-sm mb-0">
            <thead><tr><th>Capítulo</th><th># Papers</th><th>Total Citações</th></tr></thead>
            <tbody>
              <?php foreach ($byChapter as $r): ?>
              <tr>
                <td><?php echo h($r['chapter_code'] ?: '-'); ?></td>
                <td><?php echo (int)$r['n']; ?></td>
                <td><?php echo (int)$r['total']; ?></td>
              </tr>
              <?php endforeach; if (!$byChapter): ?>
              <tr><td colspan="3" class="text-center text-muted">Sem dados</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="col-lg-6">
      <div class="card h-100">
        <div class="card-header">Contagem por Prompt</div>
        <div class="table-responsive">
          <table class="table table-sm mb-0">
            <thead><tr><th>Prompt</th><th># Papers</th></tr></thead>
            <tbody>
              <?php foreach ($byPrompt as $r): ?>
              <tr>
                <td><code><?php echo h($r['prompt_code'] ?: '-'); ?></code></td>
                <td><?php echo (int)$r['n']; ?></td>
              </tr>
              <?php endforeach; if (!$byPrompt): ?>
              <tr><td colspan="2" class="text-center text-muted">Sem dados</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

  </div>
</div>
</body></html>
