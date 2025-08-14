TRUNCATE TABLE social_posts;
TRUNCATE TABLE webhooks_log;
TRUNCATE TABLE social_queue;
TRUNCATE TABLE platform_accounts;

INSERT INTO platform_accounts (platform, name, page_id, ig_user_id, chat_id, access_token)
VALUES
('fb','Sample FB','123',NULL,NULL,'token'),
('ig','Sample IG',NULL,'123',NULL,'token'),
('twitter','Sample TW',NULL,NULL,NULL,'token'),
('telegram','Sample TG',NULL,NULL,'123','token');

INSERT INTO social_queue (title, summary, link_url, image_url, channels, status, publish_at, retry_count, created_at)
VALUES ('Hello World','Example summary','https://example.com','https://example.com/image.jpg','instagram,telegram','pending',NOW(),0,NOW());
