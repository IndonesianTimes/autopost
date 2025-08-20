<?php
require __DIR__ . '/../../vendor/autoload.php';
$pdo = App\Helpers\Db::pdo();
$pdo->exec("
INSERT INTO shortlinks (short_id, target_url, queue_id, platform)
VALUES ('abc123', 'https://example.com', 1, 'telegram')
ON DUPLICATE KEY UPDATE target_url=VALUES(target_url);
");
echo "Shortlink seeded: abc123\n";
