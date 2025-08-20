<?php
declare(strict_types=1);
require __DIR__.'/../../vendor/autoload.php';

use App\Helpers\Db;

$pdo = Db::pdo();

$argId = $argv[1] ?? null;
if ($argId) {
  $stmt = $pdo->prepare("SELECT * FROM social_queue WHERE id=? AND status='ready' AND (publish_at IS NULL OR publish_at<=NOW())");
  $stmt->execute([$argId]);
  $job = $stmt->fetch(PDO::FETCH_ASSOC);
} else {
  $job = $pdo->query("SELECT * FROM social_queue WHERE status='ready' AND (publish_at IS NULL OR publish_at<=NOW()) ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
}

if (!$job) { echo "no-due-job\n"; exit; }

// parse channels: SET("a,b") / single "telegram" / JSON
$targets = [];
$raw = trim((string)($job['channels'] ?? ''));
if ($raw !== '') {
  if ($raw[0] === '[') {
    $targets = json_decode($raw, true) ?: [];
  } else {
    $targets = array_filter(array_map('trim', explode(',', $raw)));
  }
}
if (!$targets) { echo "skip: no targets (raw='{$raw}')\n"; exit; }

$content = $job['content'] ?? ($job['summary'] ?? ($job['title'] ?? ''));
$imgUrl  = $job['img_url'] ?? ($job['image_url'] ?? null);
if ($content === '') { echo "skip: empty content\n"; exit; }

$pdo->prepare("UPDATE social_queue SET status='posting' WHERE id=?")->execute([$job['id']]);

foreach ($targets as $p) {
  $resp = ['dry_run'=>true,'content_len'=>strlen((string)$content),'img'=>(bool)$imgUrl];
  $pdo->prepare("INSERT INTO social_posts (queue_id, platform, platform_post_id, status, response_json, posted_at)
                 VALUES (?,?,?,?,?,NOW())")
     ->execute([$job['id'], $p, 'DRYRUN-'.time()."-{$p}", 'posted', json_encode($resp)]);
}

$pdo->prepare("UPDATE social_queue SET status='posted', updated_at=NOW() WHERE id=?")->execute([$job['id']]);
echo "mini-worker OK queue_id={$job['id']} targets=".implode(',', $targets)."\n";
