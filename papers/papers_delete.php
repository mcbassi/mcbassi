<?php
require __DIR__.'/config.php';
$id = (int)($_GET['id'] ?? 0);
if ($id) { $pdo->prepare("DELETE FROM papers WHERE id=?")->execute([$id]); }
header('Location: papers_index.php');
