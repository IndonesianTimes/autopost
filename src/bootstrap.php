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
        $dsn  = $_ENV['DB_DSN'] ?? null;
        $user = $_ENV['DB_USER'] ?? '';
        $pass = $_ENV['DB_PASS'] ?? '';
        if (!$dsn) {
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=utf8mb4',
                $_ENV['DB_HOST'] ?? 'localhost',
                $_ENV['DB_NAME'] ?? ''
            );
            $user = $_ENV['DB_USER'] ?? '';
            $pass = $_ENV['DB_PASS'] ?? '';
        }
        $pdo = new PDO($dsn, $user, $pass, [
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
    $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
    if ($status >= 400) {
        echo json_encode(['status' => 'error', 'error' => $payload], $flags);
    } else {
        echo json_encode(['status' => 'ok', 'data' => $payload], $flags);
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
