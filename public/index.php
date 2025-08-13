<?php

require_once __DIR__ . '/../src/bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if ($method === 'GET' && $uri === '/health') {
    json(200, ['ok' => true, 'php' => PHP_VERSION]);
    return;
}

if ($method === 'POST' && $uri === '/api/autopost/ingest') {
    \App\Controllers\IngestController::ingest();
    return;
}

json(404, 'Not Found');
