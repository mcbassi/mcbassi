<?php
// papers/api/sync_dropbox.php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function out(array $d, int $code=200): void {
  http_response_code($code);
  echo json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

function run_proc(array $cmd, ?string $cwd=null, array $env=[]): array {
  $desc = [
    1 => ['pipe', 'w'], // stdout
    2 => ['pipe', 'w'], // stderr
  ];
  $proc = proc_open($cmd, $desc, $pipes, $cwd, $env ?: null);
  if (!is_resource($proc)) return ['exit'=>-1,'stdout'=>'','stderr'=>'Falha proc_open'];

  $stdout = stream_get_contents($pipes[1]); fclose($pipes[1]);
  $stderr = stream_get_contents($pipes[2]); fclose($pipes[2]);
  $exit   = proc_close($proc);

  return ['exit'=>$exit,'stdout'=>$stdout,'stderr'=>$stderr];
}

try {
  if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    out(['ok'=>false,'error'=>'Use POST'], 405);
  }

  // Pasta do rclone
  $rcloneDir = realpath(__DIR__ . '/../../rclones'); // ...\Produtividade_emp\rclones
  if (!$rcloneDir) throw new RuntimeException('Pasta do rclone não encontrada em ../../rclones');

  $rcloneExe = $rcloneDir . DIRECTORY_SEPARATOR . 'rclone.exe';
  if (!is_file($rcloneExe)) throw new RuntimeException('rclone.exe não encontrado em: ' . $rcloneExe);

  // Config explícito (EVITA systemprofile)
  $rcloneConf = $rcloneDir . DIRECTORY_SEPARATOR . 'rclone.conf';
  if (!is_file($rcloneConf)) {
    throw new RuntimeException('rclone.conf não encontrado em: ' . $rcloneConf . ' (coloque aqui o config com [dropbox])');
  }

  // Jobs
  $jobs = [
    ['name'=>'2. Strategy and value discipline','src'=>'dropbox:Elevate IA/2. Strategy and value discipline','dst'=>'C:\\xampp\\htdocs\\Produtividade_emp\\Bibliografia\\upload\\2. Strategy and value discipline'],
    ['name'=>'1. Management practices','src'=>'dropbox:Elevate IA/1. Management practices','dst'=>'C:\\xampp\\htdocs\\Produtividade_emp\\Bibliografia\\upload\\1. Management practices'],
    ['name'=>'0. Example','src'=>'dropbox:Elevate IA/0. Example','dst'=>'C:\\xampp\\htdocs\\Produtividade_emp\\Bibliografia\\upload\\0. Example'],
    ['name'=>'CR y R','src'=>'dropbox:Elevate IA/CR y R','dst'=>'C:\\xampp\\htdocs\\Produtividade_emp\\Bibliografia\\upload\\CR y R'],
  ];

  // 1) PRÉ-TESTE: se Dropbox não listar, aborta (SEM TOCAR LOCAL)
  $pre = run_proc(
    [$rcloneExe, '--config', $rcloneConf, 'lsf', 'dropbox:Elevate IA', '--max-depth', '1'],
    $rcloneDir
  );
  if ($pre['exit'] !== 0) {
    out([
      'ok' => false,
      'error' => 'Dropbox indisponível (pré-teste falhou). Nenhuma pasta local foi alterada.',
      'detail' => trim($pre['stderr'] ?: $pre['stdout']),
    ], 503);
  }

  // 2) SYNC REAL (espelho) com proteção
  $results = [];
  foreach ($jobs as $j) {
    @mkdir($j['dst'], 0777, true);

    $cmd = [
      $rcloneExe,
      '--config', $rcloneConf,
      'sync',
      $j['src'],
      $j['dst'],
      '-v',
      '--retries', '3',
      '--retries-sleep', '2s',

      // proteção anti “apagou tudo” (ajuste o limite se quiser)
      '--max-delete', '500',

      // evita “cd” e reduz chance de travar a UI
      '--checkers', '4',
      '--transfers', '4',
    ];

    $run = run_proc($cmd, $rcloneDir);

    $results[] = [
      'name' => $j['name'],
      'src'  => $j['src'],
      'dst'  => $j['dst'],
      'exit_code' => $run['exit'],
      'ok' => ($run['exit'] === 0),
      'stdout' => $run['stdout'],
      'stderr' => $run['stderr'],
    ];

    if ($run['exit'] !== 0) {
      out([
        'ok' => false,
        'error' => 'Falha no sincronismo: ' . $j['name'],
        'detail' => trim($run['stderr'] ?: $run['stdout']),
        'results' => $results,
      ], 500);
    }
  }

  out(['ok'=>true, 'message'=>'Sincronismo concluído (espelho)', 'results'=>$results]);

} catch (Throwable $e) {
  out(['ok'=>false,'error'=>$e->getMessage()], 500);
}
