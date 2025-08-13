<?php

declare(strict_types=1);

namespace App\Models;

use PDO;
use RuntimeException;

class Queue
{
    public static function insert(array $data): int
    {
        $required = ['title', 'summary', 'link_url', 'channels', 'publish_at'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new RuntimeException("Missing field $field");
            }
        }

        $pdo = db();
        $sql = 'INSERT INTO social_queue (title, summary, link_url, image_url, utm_json, payload_json, channels, publish_at, priority, status)'
            . ' VALUES (:title, :summary, :link_url, :image_url, :utm_json, :payload_json, :channels, :publish_at, :priority, :status)';
        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            ':title' => $data['title'],
            ':summary' => $data['summary'],
            ':link_url' => $data['link_url'],
            ':image_url' => $data['image_url'] ?? null,
            ':utm_json' => isset($data['utm_json']) ? json_encode($data['utm_json'], JSON_UNESCAPED_UNICODE) : null,
            ':payload_json' => isset($data['payload_json']) ? json_encode($data['payload_json'], JSON_UNESCAPED_UNICODE) : null,
            ':channels' => self::normalizeChannels($data['channels']),
            ':publish_at' => $data['publish_at'],
            ':priority' => $data['priority'] ?? 0,
            ':status' => $data['status'] ?? 'ready',
        ]);

        return (int)$pdo->lastInsertId();
    }

    public static function fetchDue(int $limit = 20): array
    {
        $pdo = db();
        $sql = "SELECT * FROM social_queue WHERE status IN ('ready','retry') AND publish_at <= NOW() ORDER BY priority DESC, publish_at ASC LIMIT :limit";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) {
            $row['utm_json'] = $row['utm_json'] !== null ? json_decode($row['utm_json'], true) : null;
            $row['payload_json'] = $row['payload_json'] !== null ? json_decode($row['payload_json'], true) : null;
        }
        return $rows;
    }

    public static function markPosting(int $id): void
    {
        $stmt = db()->prepare("UPDATE social_queue SET status='posting' WHERE id = :id");
        $stmt->execute([':id' => $id]);
    }

    public static function markPosted(int $id): void
    {
        $stmt = db()->prepare("UPDATE social_queue SET status='posted' WHERE id = :id");
        $stmt->execute([':id' => $id]);
    }

    public static function markFailed(int $id, string $reason = ''): void
    {
        $pdo = db();
        if ($reason !== '') {
            $stmt = $pdo->prepare('SELECT payload_json FROM social_queue WHERE id = :id');
            $stmt->execute([':id' => $id]);
            $payload = $stmt->fetchColumn();
            $payloadArr = $payload ? json_decode((string)$payload, true) : [];
            $payloadArr['error'] = $reason;
            $payloadJson = json_encode($payloadArr, JSON_UNESCAPED_UNICODE);
            $stmt = $pdo->prepare("UPDATE social_queue SET status='failed', payload_json=:payload_json WHERE id=:id");
            $stmt->execute([':payload_json' => $payloadJson, ':id' => $id]);
        } else {
            $stmt = $pdo->prepare("UPDATE social_queue SET status='failed' WHERE id=:id");
            $stmt->execute([':id' => $id]);
        }
    }

    public static function scheduleRetry(int $id, int $minutes): void
    {
        $stmt = db()->prepare("UPDATE social_queue SET status='retry', retries = retries + 1, publish_at = DATE_ADD(NOW(), INTERVAL :minutes MINUTE) WHERE id = :id");
        $stmt->bindValue(':minutes', $minutes, PDO::PARAM_INT);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    private static function normalizeChannels(array|string $channels): string
    {
        if (is_string($channels)) {
            $channels = array_map('trim', explode(',', $channels));
        }

        $allowed = ['fb', 'ig', 'twitter', 'telegram'];
        $channels = array_intersect($channels, $allowed);
        $channels = array_unique($channels);
        if (empty($channels)) {
            throw new RuntimeException('Invalid channels');
        }
        sort($channels);
        return implode(',', $channels);
    }
}
