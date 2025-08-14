-- Adjust schema to match P1 requirements

ALTER TABLE social_queue
    CHANGE COLUMN retries retry_count TINYINT NOT NULL DEFAULT 0,
    ADD COLUMN last_attempt_at DATETIME NULL AFTER retry_count,
    ADD COLUMN last_error_code VARCHAR(64) NULL AFTER last_attempt_at,
    ADD COLUMN last_error_message VARCHAR(255) NULL AFTER last_error_code,
    MODIFY COLUMN status ENUM('pending','retry','posted','failed') NOT NULL DEFAULT 'pending';

UPDATE social_queue SET status='pending' WHERE status='ready';

ALTER TABLE social_posts
    CHANGE COLUMN platform_post_id post_id VARCHAR(128) NULL,
    DROP COLUMN status,
    CHANGE COLUMN response_json raw_response JSON NULL;

ALTER TABLE webhooks_log
    ADD COLUMN queue_id INT UNSIGNED NULL AFTER id,
    CHANGE COLUMN source platform ENUM('facebook','instagram','twitter','telegram') NOT NULL,
    ADD COLUMN response_code INT NULL AFTER platform,
    ADD COLUMN error_message VARCHAR(255) NULL AFTER response_code,
    CHANGE COLUMN payload_json response_body JSON NOT NULL;
