<?php require __DIR__.'/config.php'; ?>
<!doctype html><html lang="pt-br"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Papers - Lista</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head><body class="bg-light">
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">Papers</h1>
    <a class="btn btn-primary" href="/papers_form.php">Novo Paper</a>
  </div>

  <form class="row g-2 mb-3" method="get">
    <div class="col-md-3"><input type="text" name="q" value="<?php echo h($_GET['q']??'');?>" class="form-control" placeholder="Buscar por título/journal/keywords"></div>
    <div class="col-md-2">
      <select name="chapter" class="form-select">
        <option value="">Capítulo...</option>
        <?php foreach (['CAP_01','CAP_02','CAP_03','CAP_04','CAP_05','CAP_06','CAP_07','CAP_08','CAP_09'] as $c): ?>
          <option value="<?php echo h($c);?>" <?php echo (($_GET['chapter']??'')===$c)?'selected':''; ?>><?php echo h($c);?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2"><input type="text" name="prompt" class="form-control" value="<?php echo h($_GET['prompt']??'');?>" placeholder="Prompt code"></div>
    <div class="col-md-2">
      <select name="sort" class="form-select">
        <option value="">Ordenar...</option>
        <option value="cit_desc" <?php echo (($_GET['sort']??'')==='cit_desc')?'selected':''; ?>>Citações (↓)</option>
        <option value="cit_asc"  <?php echo (($_GET['sort']??'')==='cit_asc')?'selected':''; ?>>Citações (↑)</option>
      </select>
    </div>
    <div class="col-md-3">
      <button class="btn btn-outline-secondary">Filtrar</button>
      <a class="btn btn-link" href="papers/papers_index.php">Limpar</a>
      <a class="btn btn-outline-dark" href="papers/papers_report.php">Relatório</a>
    </div>
  </form>

  <?php
    $q = trim($_GET['q'] ?? '');
    $chapter = trim($_GET['chapter'] ?? '');
    $prompt = trim($_GET['prompt'] ?? '');
    $sort = $_GET['sort'] ?? '';

    $sql = "SELECT * FROM papers WHERE 1=1";
    $params = [];

    if ($q !== '') {
      $sql .= " AND (title LIKE :q OR journal LIKE :q OR keywords LIKE :q)";
      $params[':q'] = "%{$q}%";
    }
    if ($chapter !== '') {
      $sql .= " AND chapter_code = :ch";
      $params[':ch'] = $chapter;
    }
    if ($prompt !== '') {
      $sql .= " AND prompt_code = :pr";
      $params[':pr'] = $prompt;
    }

    if ($sort === 'cit_desc') $sql .= " ORDER BY citation_count DESC, id DESC";
    elseif ($sort === 'cit_asc') $sql .= " ORDER BY citation_count ASC, id DESC";
    else $sql .= " ORDER BY id DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
  ?>

  <div class="card">
    <div class="table-responsive">
      <table class="table table-sm table-hover mb-0">
        <thead class="table-light">
          <tr>
            <th>#</th>
            <th>Título</th>
            <th>Journal</th>
            <th>Citações</th>
            <th>Prompt</th>
            <th>Capítulo</th>
            <th>Link</th>
            <th style="width:120px;"></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
          <tr>
            <td><?php echo (int)$r['id']; ?></td>
            <td><a hr ?>"><?php echo h($r['title']); ?></a></td>
            <td><?php echo h($r['journal']); ?></td>
            <td><?php echo (int)$r['citation_count']; ?></td>
            <td><code><?php echo h($r['prompt_code']); ?></code></td>
            <td><?php echo h($r['chapter_code']); ?></td>
            <td><?php if ($r['link_url']): ?><a href="<?php echo h($r['link_url']); ?>" target="_blank">abrir</a><?php endif; ?></td>
            <td class="text-end">
              <a class="btn btn-sm btn-outline-primary" href="papers/papers_form.php?id=<?php echo (int)$r['id']; ?>">Editar</a>
              <a class="btn btn-sm btn-outline-danger" href="papers/papers_delete.php?id=<?php echo (int)$r['id']; ?>" onclick="return confirm('Excluir este registro?')">Excluir</a>
            </td>
          </tr>
          <?php endforeach; if (!$rows): ?>
          <tr><td colspan="8" class="text-center text-muted py-4">Sem registros</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
</body></html>
