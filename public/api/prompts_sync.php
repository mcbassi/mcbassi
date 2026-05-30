<?php
declare(strict_types=1);

$route = '/api/prompts-sync';
$query = $_SERVER['QUERY_STRING'] ?? '';

$_SERVER['REQUEST_URI'] = $query !== '' ? $route . '?' . $query : $route;
require dirname(__DIR__) . '/index.php';
