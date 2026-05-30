<?php declare(strict_types=1); require __DIR__ . '/_styles.php'; ?>
<?php $year = (int) ($nav['year'] ?? 2024); $industry = (string) ($nav['industry'] ?? ''); ?>
<section class="dam-card">
  <h2 style="margin:0 0 6px 0">Damodaran BI</h2>
  <div style="color:#64748b">Benchmark setorial integrado ao workspace do sistema</div>
  <form class="dam-form-grid" method="get" action="<?= h(url('DAMODARAM/' . ($nav['activePage'] ?? 'index.php'))) ?>">
    <label>
      <span class="dam-label">Ano</span>
      <select class="dam-select" name="year">
        <?php foreach (($nav['years'] ?? []) as $y): ?>
          <option value="<?= h((string) $y) ?>" <?= ((int) $y === $year ? 'selected' : '') ?>><?= h((string) $y) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>
      <span class="dam-label">Indústria</span>
      <select class="dam-select" name="industry">
        <?php foreach (($nav['industries'] ?? []) as $ind): ?>
          <option value="<?= h((string) $ind) ?>" <?= ((string) $ind === $industry ? 'selected' : '') ?>><?= h((string) $ind) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <button type="submit" class="dam-btn">Atualizar</button>
  </form>
  <nav class="dam-tabs">
    <?php foreach (($nav['pages'] ?? []) as $file => $label):
      $href = url('DAMODARAM/' . $file . '?year=' . urlencode((string)$year) . '&industry=' . urlencode($industry));
      $active = ($file === ($nav['activePage'] ?? '')) ? ' is-active' : '';
    ?>
      <a data-shell-nav="true" class="dam-tab<?= $active ?>" href="<?= h($href) ?>"><?= h((string)$label) ?></a>
    <?php endforeach; ?>
  </nav>
</section>
