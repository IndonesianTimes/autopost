<?php
declare(strict_types=1);

use App\Helpers\Config;
use App\Helpers\Db;

require_once __DIR__ . '/../../../vendor/autoload.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
    return;
}

$secret = Config::require('SAE_WEBHOOK_SECRET');
$signatureHeader = $_SERVER['HTTP_X_SIGNATURE'] ?? '';
if (!preg_match('/^sha256=([0-9a-fA-F]{64})$/', $signatureHeader, $m)) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'invalid_signature']);
    return;
}

$raw = $GLOBALS['__SAE_WEBHOOK_BODY'] ?? file_get_contents('php://input') ?: '';
$expected = hash_hmac('sha256', $raw, $secret);
if (!hash_equals($expected, strtolower($m[1]))) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'invalid_signature']);
    return;
}

$data = json_decode($raw, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_json']);
    return;
}

$source = isset($data['source']) && is_string($data['source']) && $data['source'] !== '' ? $data['source'] : 'primesai';
$eventId = isset($data['event_id']) && is_string($data['event_id']) && $data['event_id'] !== '' ? $data['event_id'] : '';
if ($eventId === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'missing_event_id']);
    return;
}

$title   = isset($data['title']) && is_string($data['title']) ? trim($data['title']) : '';
$content = isset($data['content']) && is_string($data['content']) ? trim($data['content']) : '';
$imgUrl  = isset($data['img_url']) && is_string($data['img_url']) && filter_var($data['img_url'], FILTER_VALIDATE_URL)
    ? $data['img_url']
    : null;

$channels = $data['channels'] ?? null;
if (is_string($channels)) {
    $channels = array_filter(array_map('trim', explode(',', $channels)));
}
if (!is_array($channels) || !$channels) {
    $defaultChannels = Config::get('SAE_DEFAULT_CHANNELS', '');
    $channels = array_filter(array_map('trim', explode(',', $defaultChannels)));
}
$channelsStr = implode(',', $channels);

$publishAt = $data['publish_at'] ?? null;
if (is_string($publishAt) && $publishAt !== '') {
    $ts = strtotime($publishAt);
    $publishAt = $ts ? date('Y-m-d H:i:s', $ts) : null;
} else {
    $publishAt = null;
}

$meta = $data['meta'] ?? [];
if (!is_array($meta)) {
    $meta = [];
}
$metaJson = json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

$pdo = Db::pdo();
try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare('INSERT INTO webhooks_log (source, event_id, signature, payload) VALUES (:source,:event_id,:sig,:payload)');
    $stmt->execute([
        ':source'  => $source,
        ':event_id'=> $eventId,
        ':sig'     => $m[0],
        ':payload' => $raw,
    ]);

    $stmt = $pdo->prepare('INSERT INTO social_queue (title, summary, link_url, image_url, channels, status, publish_at, retry_count, meta) VALUES (:title,:summary,:link,:image,:channels,:status,:publish_at,:retry,:meta)');
    $stmt->execute([
        ':title'       => $title,
        ':summary'     => $content,
        ':link'        => '',
        ':image'       => $imgUrl,
        ':channels'    => $channelsStr,
        ':status'      => 'pending',
        ':publish_at'  => $publishAt,
        ':retry'       => 0,
        ':meta'        => $metaJson,
    ]);

    $queueId = (int)$pdo->lastInsertId();
    $pdo->commit();

    http_response_code(202);
    echo json_encode(['ok' => true, 'queue_id' => $queueId]);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    if ($e->getCode() === '23000') {
        http_response_code(202);
        echo json_encode(['ok' => true, 'dedup' => true]);
    } else {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'db_error']);
    }
}
