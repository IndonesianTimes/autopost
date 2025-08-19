<?php

require_once __DIR__ . '/../src/bootstrap.php';

$shortId = $_GET['id'] ?? null;
if ($shortId === null && isset($_SERVER['PATH_INFO'])) {
    $shortId = ltrim((string)$_SERVER['PATH_INFO'], '/');
}

if (!$shortId) {
    http_response_code(404);
    echo 'Not Found';
    exit;
}

$pdo = db();
$stmt = $pdo->prepare('SELECT target_url, queue_id, platform FROM shortlinks WHERE short_id = :id LIMIT 1');
$stmt->execute([':id' => $shortId]);
$link = $stmt->fetch();

if (!$link) {
    http_response_code(404);
    echo 'Not Found';
    exit;
}

$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
$isBot = preg_match('/bot|crawl|spider|slurp|facebookexternalhit|WhatsApp/i', $ua);
if (!$isBot) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $pepper = $_ENV['SAE_IP_PEPPER'] ?? '';
    $ipHash = hash('sha256', $ip . '|' . $pepper);
    $uaTrim = substr($ua, 0, 255);
    try {
        $logStmt = $pdo->prepare('INSERT INTO metrics_clicks (queue_id, platform, short_id, ip_hash, user_agent) VALUES (:queue_id, :platform, :short_id, :ip_hash, :ua)');
        $logStmt->execute([
            ':queue_id' => $link['queue_id'],
            ':platform' => $link['platform'],
            ':short_id' => $shortId,
            ':ip_hash' => $ipHash,
            ':ua' => $uaTrim,
        ]);
    } catch (Throwable $e) {
        // ignore logging errors
    }
}

header('Cache-Control: no-store');
header('Location: ' . $link['target_url'], true, 302);
exit;
