<?php

declare(strict_types=1);

namespace App\Models;

class Post
{
    public static function log(int $queueId, string $platform, ?string $platformPostId, string $status, array $response = []): void
    {
        $pdo = db();
        $sql = 'INSERT INTO social_posts (queue_id, platform, platform_post_id, status, response_json, posted_at) '
            . 'VALUES (:queue_id, :platform, :platform_post_id, :status, :response_json, :posted_at)';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':queue_id' => $queueId,
            ':platform' => $platform,
            ':platform_post_id' => $platformPostId,
            ':status' => $status,
            ':response_json' => json_encode($response, JSON_UNESCAPED_UNICODE),
            ':posted_at' => $status === 'posted' ? date('Y-m-d H:i:s') : null,
        ]);
    }
}
