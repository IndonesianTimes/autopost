<?php

use Dotenv\Dotenv;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/helpers.php';

date_default_timezone_set('UTC');

$dotenv = Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=utf8mb4',
            $_ENV['DB_HOST'] ?? 'localhost',
            $_ENV['DB_NAME'] ?? ''
        );
        $pdo = new PDO($dsn, $_ENV['DB_USER'] ?? '', $_ENV['DB_PASS'] ?? '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
    return $pdo;
}

function json(int $status, $payload): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    if ($status >= 400) {
        echo json_encode(['status' => 'error', 'error' => $payload], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode(['status' => 'ok', 'data' => $payload], JSON_UNESCAPED_UNICODE);
    }
}

function requireBearer(string $expected): void
{
    $token = bearerAuth();
    if ($token !== $expected) {
        json(401, 'Unauthorized');
        exit;
    }
}
