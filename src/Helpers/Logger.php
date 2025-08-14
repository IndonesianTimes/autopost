<?php

declare(strict_types=1);

namespace App\Helpers;

use PDO;

class Logger
{
    private static function db(): PDO
    {
        return Db::instance();
    }

    private static function truncate(string $json, int $max = 10000): string
    {
        return strlen($json) > $max ? substr($json, 0, $max) : $json;
    }

    public static function logSuccess(int $queueId, string $platform, ?string $postId, array $raw): void
    {
        try {
            $db = self::db();
            $json = json_encode($raw, JSON_UNESCAPED_UNICODE);
            $stmt = $db->prepare('INSERT INTO social_posts (queue_id, platform, platform_post_id, status, response_json, posted_at) VALUES (:qid,:pf,:pid,\'posted\',:resp,NOW())');
            $stmt->execute([
                ':qid' => $queueId,
                ':pf'  => $platform,
                ':pid' => $postId,
                ':resp'=> self::truncate($json ?: ''),
            ]);
        } catch (\Throwable $e) {
            // swallow logging errors
        }
    }

    /**
     * @param array|string|null $responseRaw
     */
    public static function logError(int $queueId, string $platform, int $code, string $message, $responseRaw = null): void
    {
        try {
            $db = self::db();
            $payload = [
                'queue_id' => $queueId,
                'code' => $code,
                'message' => $message,
                'response' => $responseRaw,
            ];
            $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
            $stmt = $db->prepare('INSERT INTO webhooks_log (source, payload_json) VALUES (:src,:payload)');
            $stmt->execute([
                ':src' => $platform,
                ':payload' => self::truncate($json ?: ''),
            ]);
        } catch (\Throwable $e) {
            // swallow logging errors
        }
    }
}
