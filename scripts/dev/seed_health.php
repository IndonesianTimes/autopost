<?php
require __DIR__ . '/../../vendor/autoload.php';
$pdo = App\Helpers\Db::pdo();

$pdo->exec("
INSERT INTO platform_accounts (platform, account_name, access_token, meta)
VALUES ('instagram','@dummy','TEST', JSON_OBJECT('expires_at', DATE_FORMAT(DATE_ADD(NOW(), INTERVAL 6 HOUR), '%Y-%m-%dT%H:%i:%sZ')))
ON DUPLICATE KEY UPDATE meta=VALUES(meta);
");

echo "Seeded instagram expires_at ~6h\n";
