<?php

require_once __DIR__ . '/../src/bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'];

// Normalize the request URI by removing the script's base path
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
if ($basePath && str_starts_with($uri, $basePath)) {
    $uri = substr($uri, strlen($basePath));
}
$uri = rtrim($uri, '/') ?: '/';

if ($method === 'GET' && $uri === '/health') {
    json(200, ['ok' => true, 'php' => PHP_VERSION]);
    return;
}

  if ($method === 'POST' && $uri === '/api/autopost/ingest') {
      \App\Controllers\IngestController::ingest();
      return;
  }

  if ($method === 'GET' && $uri === '/api/autopost/queue') {
      \App\Controllers\QueueController::queue();
      return;
  }

  if ($method === 'GET' && $uri === '/api/autopost/posts') {
      \App\Controllers\QueueController::posts();
      return;
  }

  json(404, 'Not Found');
