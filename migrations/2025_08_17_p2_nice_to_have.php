#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Helpers\Config;
use App\Helpers\Db;

Config::load(dirname(__DIR__));

$pdo = Db::pdo();

$pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS webhooks_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    source VARCHAR(50) NOT NULL,
    event_id VARCHAR(100) NOT NULL,
    signature VARCHAR(255) NULL,
    payload JSON NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_source_event (source, event_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);

$pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS shortlinks (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    short_id VARCHAR(32) NOT NULL,
    target_url TEXT NOT NULL,
    queue_id BIGINT UNSIGNED NULL,
    platform VARCHAR(50) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_short_id (short_id),
    KEY idx_queue_id (queue_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);

$pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS metrics_clicks (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    queue_id BIGINT UNSIGNED NULL,
    platform VARCHAR(50) NULL,
    short_id VARCHAR(32) NULL,
    ip_hash CHAR(64) NULL,
    user_agent TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_queue_id (queue_id),
    KEY idx_short_id (short_id),
    KEY idx_ip_hash (ip_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);

$col = $pdo->query("SHOW COLUMNS FROM social_queue LIKE 'meta'")->fetch();
if (!$col) {
    $pdo->exec("ALTER TABLE social_queue ADD COLUMN meta JSON NULL AFTER updated_at");
}

$col = $pdo->query("SHOW COLUMNS FROM webhooks_log LIKE 'source'")->fetch();
if (!$col) {
    $pdo->exec("ALTER TABLE webhooks_log ADD COLUMN source VARCHAR(50) NOT NULL AFTER id");
}

$col = $pdo->query("SHOW COLUMNS FROM webhooks_log LIKE 'event_id'")->fetch();
if (!$col) {
    $pdo->exec("ALTER TABLE webhooks_log ADD COLUMN event_id VARCHAR(100) NOT NULL AFTER source");
}

$col = $pdo->query("SHOW COLUMNS FROM webhooks_log LIKE 'signature'")->fetch();
if (!$col) {
    $pdo->exec("ALTER TABLE webhooks_log ADD COLUMN signature VARCHAR(255) NULL AFTER event_id");
}

$col = $pdo->query("SHOW COLUMNS FROM webhooks_log LIKE 'payload'")->fetch();
if (!$col) {
    $pdo->exec("ALTER TABLE webhooks_log ADD COLUMN payload JSON NOT NULL AFTER signature");
}

$idx = $pdo->query("SHOW INDEX FROM webhooks_log WHERE Key_name = 'uq_source_event'")->fetch();
if (!$idx) {
    $pdo->exec("CREATE UNIQUE INDEX uq_source_event ON webhooks_log (source, event_id)");
}

echo "Migration completed\n";
