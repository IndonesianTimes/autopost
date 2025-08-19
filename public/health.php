<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

use App\Services\TokenInspector;
use App\Services\Notifier;

$tokens = TokenInspector::inspect();
$now = gmdate('c');

$ok = true;
$soon = [];
foreach ($tokens as $platform => $info) {
    if ($info['expires_in_h'] !== null && $info['expires_in_h'] < 24) {
        $ok = false;
        $soon[$platform] = $info;
    }
}

if (!empty($soon)) {
    $lines = [];
    foreach ($soon as $platform => $info) {
        $lines[] = sprintf('%s %s expires in %dh', $platform, $info['account'], (int) $info['expires_in_h']);
    }
    $message = '[SAE] token expiring: ' . implode(', ', $lines);
    Notifier::alert($message);
}

http_response_code($ok ? 200 : 503);
header('Content-Type: application/json');
echo json_encode([
    'ok' => $ok,
    'time' => $now,
    'tokens' => $tokens,
]);
