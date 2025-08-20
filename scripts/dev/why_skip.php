<?php
declare(strict_types=1);
require __DIR__.'/../../vendor/autoload.php';

use App\Helpers\Db;

$pdo = Db::pdo();

$job = $pdo->query("
  SELECT * FROM social_queue
  WHERE status='ready' AND (publish_at IS NULL OR publish_at<=NOW())
  ORDER BY id DESC LIMIT 1
")->fetch(PDO::FETCH_ASSOC);

if (!$job) { echo "NO_DUE_JOB\n"; exit; }

echo "QUEUE_ID = {$job['id']}\n";
echo "channels raw = ".($job['channels'] ?? '(null)')."\n";

// parse SET('a','b') => "a,b"
$channelsRaw = (string)($job['channels'] ?? '');
$targets = array_filter(array_map('trim', $channelsRaw === '' ? [] : explode(',', $channelsRaw)));

echo "targets = ".json_encode($targets)."\n";

// content mapping fallback
$content = $job['content'] ?? ($job['summary'] ?? $job['title'] ?? '');
$imgUrl  = $job['img_url'] ?? ($job['image_url'] ?? null);

echo "content.len = ".strlen((string)$content).", imgUrl? ".($imgUrl ? 'yes' : 'no')."\n";

// cek akun aktif per target (jika perlu)
$missing = [];
foreach ($targets as $p) {
  $stmt = $pdo->prepare("SELECT COUNT(*) FROM platform_accounts WHERE platform=? AND (is_active IS NULL OR is_active=1)");
  $stmt->execute([$p]);
  if ((int)$stmt->fetchColumn() === 0) $missing[] = $p;
}
echo "missing_accounts = ".json_encode($missing)."\n";

if (empty($targets)) { echo "WHY: no targets parsed from channels\n"; exit; }
if ($content === '') { echo "WHY: empty content (need summary/title)\n"; exit; }
if (!empty($missing)) { echo "WHY: missing active accounts for ".implode(', ', $missing)."\n"; exit; }

echo "OK_TO_POST\n";
