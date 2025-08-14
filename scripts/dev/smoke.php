#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use App\Helpers\Config;
use App\Helpers\Db;
use App\Worker\QueueProcessor;

Config::load(dirname(__DIR__,2));
$_ENV['DRY_RUN'] = 'true';

$db = Db::instance();
$sql = file_get_contents(__DIR__ . '/seed.sql');
$db->exec($sql);

$worker = new QueueProcessor();
$worker->run();

$posts = (int)$db->query('SELECT COUNT(*) FROM social_posts')->fetchColumn();
$logs = (int)$db->query('SELECT COUNT(*) FROM webhooks_log')->fetchColumn();

if ($posts > 0 && $logs === 0) {
    echo "Smoke test passed: {$posts} posts, {$logs} errors\n";
    exit(0);
}

echo "Smoke test failed: {$posts} posts, {$logs} errors\n";
exit(1);
