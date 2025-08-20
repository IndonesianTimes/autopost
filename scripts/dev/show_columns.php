<?php
declare(strict_types=1);
require __DIR__ . '/../../vendor/autoload.php';
$pdo = App\Helpers\Db::pdo();
foreach (['platform_accounts','social_queue','social_posts'] as $t) {
  echo "== $t ==\n";
  foreach ($pdo->query("SHOW COLUMNS FROM $t") as $r) {
    echo "- {$r['Field']} {$r['Type']}\n";
  }
  echo "\n";
}
