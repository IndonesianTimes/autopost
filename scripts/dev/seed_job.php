<?php
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use App\Helpers\Db;

$pdo = Db::pdo();

/** ---- helper: list kolom tabel ---- */
$queueCols = [];
$q = $pdo->query("SHOW COLUMNS FROM social_queue");
while ($r = $q->fetch(PDO::FETCH_ASSOC)) $queueCols[$r['Field']] = strtolower((string)$r['Type']);

$acctCols = [];
$a = $pdo->query("SHOW COLUMNS FROM platform_accounts");
while ($r = $a->fetch(PDO::FETCH_ASSOC)) $acctCols[$r['Field']] = true;

/** ---- seed platform_accounts adaptif ---- */
$acctFields = ['platform'];
$acctValues = ['telegram'];
$updates    = [];

if (isset($acctCols['name']))          { $acctFields[]='name';          $acctValues[]='@dummy'; $updates[]='name=VALUES(name)'; }
if (isset($acctCols['chat_id']))       { $acctFields[]='chat_id';       $acctValues[]='@dummy'; $updates[]='chat_id=VALUES(chat_id)'; }
if (isset($acctCols['access_token']))  { $acctFields[]='access_token';  $acctValues[]='TEST';   $updates[]='access_token=VALUES(access_token)'; }
if (isset($acctCols['refresh_token'])) { $acctFields[]='refresh_token'; $acctValues[]=''; }
if (isset($acctCols['meta_json'])) {
  $acctFields[]='meta_json';
  $acctValues[] = json_encode(['expires_at'=>date('c', time()+72*3600)], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
  $updates[]='meta_json=VALUES(meta_json)';
}
if (isset($acctCols['is_active'])) { $acctFields[]='is_active'; $acctValues[] = 1; $updates[]='is_active=VALUES(is_active)'; }

$acctSql = "INSERT INTO platform_accounts (".implode(',', $acctFields).") VALUES (".rtrim(str_repeat('?,', count($acctFields)), ',').")";
if ($updates) $acctSql .= " ON DUPLICATE KEY UPDATE ".implode(',', $updates);
$pdo->prepare($acctSql)->execute($acctValues);

/** ---- bangun kolom job sesuai schema ---- */
$fields = [];
$values = [];

$fields[]='title';   $values[]='Smoke via worker';

if (isset($queueCols['summary']))   { $fields[]='summary';   $values[]='hello from worker smoke'; }
if (isset($queueCols['link_url']))  { $fields[]='link_url';  $values[]='https://example.com/demo'; }
if (isset($queueCols['image_url'])) { $fields[]='image_url'; $values[]='https://via.placeholder.com/600x400.png?text=demo'; }
if (isset($queueCols['content']))   { $fields[]='content';   $values[]='hello from worker smoke'; }
if (isset($queueCols['img_url']))   { $fields[]='img_url';   $values[]='https://via.placeholder.com/600x400.png?text=demo'; }

/* channels: deteksi tipe SET vs lainnya (JSON string) */
$channelsType = $queueCols['channels'] ?? '';
if (str_starts_with($channelsType, 'set(')) {
    $fields[]='channels'; $values[]='telegram';               // SET isi string tunggal
} else {
    $fields[]='channels'; $values[]=json_encode(['telegram'], JSON_UNESCAPED_SLASHES); // JSON
}

$fields[]='status';     $values[]='ready';
$fields[]='publish_at'; $values[]=date('Y-m-d H:i:s', time() - 60); // due now
$fields[]='retries';    $values[]=0;

if (isset($queueCols['priority']))     { $fields[]='priority';     $values[] = 0; }
if (isset($queueCols['payload_json'])) { $fields[]='payload_json'; $values[] = null; }
if (isset($queueCols['utm_json']))     { $fields[]='utm_json';     $values[] = null; }

$sql = "INSERT INTO social_queue (".implode(',', $fields).") VALUES (".rtrim(str_repeat('?,', count($fields)), ',').")";
$pdo->prepare($sql)->execute($values);

echo "Seed OK. queue_id=" . $pdo->lastInsertId() . PHP_EOL;
