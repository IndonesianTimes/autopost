-- Initial schema for social posting app

CREATE TABLE `platform_accounts` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `platform` ENUM('fb','ig','twitter','telegram','linkedin') NOT NULL,
  `name` VARCHAR(120) NOT NULL,
  `page_id` VARCHAR(64) NULL,
  `ig_user_id` VARCHAR(64) NULL,
  `chat_id` VARCHAR(64) NULL,
  `access_token` TEXT NOT NULL,
  `refresh_token` TEXT NULL,
  `meta_json` JSON NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Platform accounts credentials';

CREATE TABLE `social_queue` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(200) NOT NULL,
  `summary` TEXT NOT NULL,
  `link_url` TEXT NOT NULL,
  `image_url` TEXT NULL,
  `utm_json` JSON NULL,
  `payload_json` JSON NULL,
  `channels` SET('fb','ig','twitter','telegram') NOT NULL,
  `publish_at` DATETIME NOT NULL,
  `priority` INT NOT NULL DEFAULT 0,
  `status` ENUM('draft','ready','posting','posted','failed','retry') NOT NULL DEFAULT 'ready',
  `retries` TINYINT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status_publish_at` (`status`, `publish_at`),
  KEY `idx_publish_at` (`publish_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Queue of posts for social platforms';

CREATE TABLE `social_posts` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `queue_id` INT UNSIGNED NOT NULL,
  `platform` ENUM('fb','ig','twitter','telegram') NOT NULL,
  `platform_post_id` VARCHAR(128) NULL,
  `status` ENUM('posted','failed') NOT NULL,
  `response_json` JSON NULL,
  `posted_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `idx_queue_platform` (`queue_id`, `platform`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Posts created from queue per platform';

CREATE TABLE `webhooks_log` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `source` VARCHAR(64) NOT NULL,
  `payload_json` JSON NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Inbound webhooks log';

