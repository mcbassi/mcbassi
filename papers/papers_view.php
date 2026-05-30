<?php require __DIR__.'/config.php';
$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM papers WHERE id=?"); $stmt->execute([$id]); $r = $stmt->fetch();
if (!$r) { die('Registro não encontrado'); }
?>
<!doctype html><html lang="pt-br"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo h($r['title']); ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head><body class="bg-light">
<div class="container py-4">
  <a class="btn btn-link mb-3" href="papers_index.php">&laquo; Voltar</a>
  <div class="card">
    <div class="card-body">
      <h2 class="h5 mb-2"><?php echo h($r['title']); ?></h2>
      <p class="mb-1"><strong>Journal:</strong> <?php echo h($r['journal']); ?></p>
      <p class="mb-1"><strong>Citações:</strong> <?php echo (int)$r['citation_count']; ?></p>
      <p class="mb-1"><strong>Palavras-chave:</strong> <?php echo h($r['keywords']); ?></p>
      <p class="mb-1"><strong>Prompt:</strong> <code><?php echo h($r['prompt_code']); ?></code></p>
      <p class="mb-3"><strong>Capítulo:</strong> <?php echo h($r['chapter_code']); ?></p>
      <p><strong>Key Insight:</strong><br><?php echo nl2br(h($r['key_insight'])); ?></p>
      <?php if ($r['link_url']): ?>
        <a class="btn btn-outline-primary" href="<?php echo h($r['link_url']); ?>" target="_blank">Abrir Artigo</a>
      <?php endif; ?>
    </div>
  </div>
</div>
</body></html>
