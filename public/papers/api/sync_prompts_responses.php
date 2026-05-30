<?php
declare(strict_types=1);

$route = '/papers/api/sync-prompts-responses';
$query = $_SERVER['QUERY_STRING'] ?? '';

$_SERVER['REQUEST_URI'] = $query !== '' ? $route . '?' . $query : $route;
require dirname(__DIR__, 2) . '/index.php';
