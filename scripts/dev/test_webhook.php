<?php
require __DIR__ . '/../../vendor/autoload.php';

$body = [
  'event_id'   => 'evt-'.time(),
  'title'      => 'Daily RTP (webhook)',
  'channels'   => ['telegram'],
  'publish_at' => null
];

$secret = getenv('SAE_WEBHOOK_SECRET') ?: 'changeme';
$raw = json_encode($body, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
$sig = 'sha256=' . hash_hmac('sha256', $raw, $secret);

$url = 'http://localhost/api/autopost/webhook.php?source=primesai';

$ch = curl_init($url);
curl_setopt_array($ch, [
  CURLOPT_POST => true,
  CURLOPT_HTTPHEADER => [
    'Content-Type: application/json',
    'X-Signature: '.$sig,
  ],
  CURLOPT_POSTFIELDS => $raw,
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_HEADER => true,
  CURLOPT_TIMEOUT => 20,
]);
$res = curl_exec($ch);
if ($res === false) {
  echo 'cURL error: '.curl_error($ch).PHP_EOL;
  exit(1);
}
echo $res.PHP_EOL;
