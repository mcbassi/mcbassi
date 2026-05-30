<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require __DIR__ . '/config.php';

if (!function_exists('h')) {
    function h(string $s): string {
        return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!isset($pdo) || !$pdo instanceof PDO) {
    $DB_HOST = '127.0.0.1';
    $DB_NAME = 'form_app';
    $DB_USER = 'root';
    $DB_PASS = '';

    try {
        $pdo = new PDO(
            "mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4",
            $DB_USER,
            $DB_PASS,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
    } catch (PDOException $e) {
        echo '<div class="alert alert-danger m-3">Erro ao conectar ao banco: ' . h($e->getMessage()) . '</div>';
        exit;
    }
}

$BASE_PATH = 'C:\\xampp\\htdocs\\Produtividade_emp\\Bibliografia\\upload\\CR y R';
$rows = [];
$errorMsg = '';

try {
    $sql = "
        SELECT
            id,
            title,
            journal,
            key_insight,
            citation_count,
            keywords,
            link_url,
            file_source_type,
            file_source_value,
            file_enabled,
            file_preferred_name,
            file_preferred_mime,
            file_last_resolved_at,
            prompt_code,
            chapter_code,
            created_at
        FROM papers
        ORDER BY created_at DESC, id DESC
    ";
    $rows = $pdo->query($sql)->fetchAll();
} catch (Throwable $e) {
    $errorMsg = $e->getMessage();
}

$selfDir   = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
$scriptUrl = ($selfDir === '' ? '' : $selfDir) . '/papers_import_page.js?v=coherent_1';
$syncUrl   = ($selfDir === '' ? '' : $selfDir) . '/api/sync_dropbox.php';
$apiUrl    = ($selfDir === '' ? '' : $selfDir) . '/papers_import_api.php';

function badgeFileType(?string $type): string {
    $type = trim((string)$type);
    if ($type === '') return '<span class="badge bg-secondary">—</span>';

    $map = [
        'url'            => 'bg-info text-dark',
        'relative_path'  => 'bg-primary',
        'local_path'     => 'bg-success',
        'cloud_path'     => 'bg-warning text-dark',
        'openai_file_id' => 'bg-dark',
    ];
    $cls = $map[$type] ?? 'bg-secondary';
    return '<span class="badge ' . $cls . '">' . h($type) . '</span>';
}
?>

<div class="container-fluid py-3" id="papersImportPage">
  <div class="row justify-content-center">
    <div class="col-12 col-xxl-11">
      <div class="card shadow-sm border-0" style="border-radius:14px;">
        <div class="card-body p-4">
          <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
            <div>
              <h4 class="mb-1">📚 Importar Biblioteca</h4>
              <div class="text-muted small">
                Importa a pasta bibliográfica para a tabela <code>papers</code> e permite enriquecimento pontual por IA.
              </div>
            </div>
            <div class="text-end small text-muted">
              <div><strong>Pasta base:</strong></div>
              <code><?= h($BASE_PATH) ?></code>
            </div>
          </div>

          <?php if ($errorMsg !== ''): ?>
            <div class="alert alert-danger mb-3">
              Erro ao carregar dados: <?= h($errorMsg) ?>
            </div>
          <?php endif; ?>

          <div id="statusImport" class="mb-3 alert alert-secondary py-2">Pronto para importar.</div>

          <div class="progress mb-3" id="progressBox" style="display:none;">
            <div id="progressBar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width:0%" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">0%</div>
          </div>

          <div class="d-flex flex-wrap gap-2 mb-4">
            <button type="button" id="btnRunImport" class="btn btn-primary" data-sync-url="<?= h($syncUrl) ?>" data-api-url="<?= h($apiUrl) ?>">🔄 Executar importação</button>
          </div>

          <div id="papersTableWrapper">
            <?php if (!$rows): ?>
              <div class="alert alert-light border mb-0">Nenhum registro encontrado em <code>papers</code>.</div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-sm table-hover align-middle">
                  <thead class="table-light">
                    <tr>
                      <th style="width:5%;">ID</th>
                      <th style="width:20%;">Título</th>
                      <th style="width:11%;">Journal</th>
                      <th style="width:9%;">Origem</th>
                      <th style="width:16%;">Arquivo</th>
                      <th style="width:20%;">Keywords</th>
                      <th style="width:8%;">Prompt</th>
                      <th style="width:5%;">Citações</th>
                      <th style="width:6%; text-align:center;">IA</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($rows as $r): ?>
                      <?php
                        $id = (int)($r['id'] ?? 0);
                        $title = (string)($r['title'] ?? '');
                        $journal = (string)($r['journal'] ?? '');
                        $keywords = (string)($r['keywords'] ?? '');
                        $citationCount = (int)($r['citation_count'] ?? 0);
                        $promptCode = (string)($r['prompt_code'] ?? '');
                        $linkUrl = (string)($r['link_url'] ?? '');
                        $sourceType = (string)($r['file_source_type'] ?? '');
                        $sourceValue = (string)($r['file_source_value'] ?? '');
                        $preferredName = (string)($r['file_preferred_name'] ?? '');

                        $fileDisplay = $sourceValue !== '' ? $sourceValue : $linkUrl;
                        $fileNameDisplay = $preferredName !== '' ? $preferredName : basename(str_replace('\\', '/', $fileDisplay));
                      ?>
                      <tr data-id="<?= $id ?>">
                        <td><?= $id ?></td>
                        <td>
                          <div class="fw-semibold"><?= h($title) ?></div>
                          <?php if (!empty($r['chapter_code'])): ?>
                            <div class="small text-muted">Capítulo: <?= h((string)$r['chapter_code']) ?></div>
                          <?php endif; ?>
                        </td>
                        <td><?= h($journal) ?></td>
                        <td><?= badgeFileType($sourceType) ?></td>
                        <td>
                          <?php if ($fileNameDisplay !== '' && $fileNameDisplay !== '.' && $fileNameDisplay !== DIRECTORY_SEPARATOR): ?>
                            <div class="small fw-semibold"><?= h($fileNameDisplay) ?></div>
                          <?php endif; ?>
                          <?php if ($fileDisplay !== ''): ?>
                            <code class="small d-block" style="white-space:pre-wrap;word-break:break-word;"><?= h($fileDisplay) ?></code>
                          <?php else: ?>
                            <span class="text-muted">—</span>
                          <?php endif; ?>
                        </td>
                        <td class="col-keywords small" style="font-size:0.78rem; line-height:1.25;"><?= h($keywords) ?></td>
                        <td><?php if ($promptCode !== ''): ?><code><?= h($promptCode) ?></code><?php else: ?><span class="text-muted">—</span><?php endif; ?></td>
                        <td><?= $citationCount ?></td>
                        <td class="text-center">
                          <div class="d-flex flex-column gap-1 align-items-center">
                            <button type="button" class="btn btn-sm btn-outline-primary btn-run-ia" data-id="<?= $id ?>" title="Executar IA para este paper">IA</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary btn-show-cache" data-id="<?= $id ?>" title="Ver cache técnico deste paper">Cache</button>
                          </div>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>

          <div class="mt-3 small text-muted">
            1) A importação sincroniza a pasta bibliográfica e depois atualiza a tabela <code>papers</code>.<br>
            2) O botão <strong>IA</strong> executa o enriquecimento individual do registro.<br>
            3) Esta tela usa <code>papers_import_page.js</code>.
          </div>
        </div>
      </div>
    </div>
  </div>
</div>


<div id="cacheModalOverlay" style="
  display:none;
  position:fixed;
  inset:0;
  background:rgba(0,0,0,.45);
  z-index:99999;
  padding:24px;
">
  <div style="
    background:#fff;
    max-width:900px;
    width:100%;
    margin:0 auto;
    border-radius:12px;
    box-shadow:0 10px 30px rgba(0,0,0,.18);
    max-height:90vh;
    display:flex;
    flex-direction:column;
    overflow:hidden;
  ">
    <div style="
      display:flex;
      justify-content:space-between;
      align-items:center;
      padding:14px 18px;
      border-bottom:1px solid #e5e7eb;
      background:#f8fafc;
    ">
      <strong>Conferência de papers_file_cache</strong>
      <button type="button" id="btnCloseCacheModal" class="btn btn-sm btn-outline-secondary">Fechar</button>
    </div>

    <div id="cacheModalBody" style="
      padding:18px;
      overflow:auto;
      max-height:calc(90vh - 60px);
      font-size:14px;
      line-height:1.35;
      white-space:normal;
    ">
      Carregando...
    </div>
  </div>
</div>

<script src="<?= h($scriptUrl) ?>"></script>
