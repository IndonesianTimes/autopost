<?php
require 'vendor/autoload.php';
require 'src/Helpers/Db.php';

use App\Helpers\Db;

$pdo = Db::instance();

// 1) Insert ke social_queue (status pending, publish_now)
$sql = "INSERT INTO social_queue
        (title, summary, link_url, image_url, channels, status, publish_at, created_at)
        VALUES
        (:title, :summary, :link_url, :image_url, :channels, 'pending', NOW(), NOW())";

$ok = $pdo->prepare($sql)->execute([
  ':title'    => 'Hello World Smoke',
  ':summary'  => 'Tes insert dari CLI',
  ':link_url' => 'https://example.com/demo',
  ':image_url'=> 'https://via.placeholder.com/600x400.png?text=demo',
  ':channels' => json_encode(['telegram','twitter']) // atau array channel yang kamu pakai
]);

echo "insert_queue: " . ($ok ? "OK" : "FAIL") . PHP_EOL;

// 2) Baca kembali 5 data terakhir
$rows = $pdo->query("SELECT id,title,channels,status,publish_at FROM social_queue ORDER BY id DESC LIMIT 5")
            ->fetchAll(PDO::FETCH_ASSOC);
print_r($rows);
