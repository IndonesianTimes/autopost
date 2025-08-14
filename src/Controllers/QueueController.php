<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Db;
use PDO;

class QueueController
{
    public static function queue(): void
    {
        requireBearer($_ENV['API_TOKEN'] ?? '');

        $statusParam = $_GET['status'] ?? 'pending';
        $statusMap = [
            'pending' => 'ready',
            'retry' => 'retry',
            'posted' => 'posted',
            'failed' => 'failed',
        ];
        $dbStatus = $statusMap[$statusParam] ?? null;
        if ($dbStatus === null) {
            json(400, 'Invalid status');
            return;
        }

        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
        $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
        $limit = max(1, min(100, $limit));
        $offset = max(0, $offset);

        $pdo = Db::instance();
        $sql = 'SELECT id, title, channels, status, publish_at, retries AS retry_count, updated_at AS last_attempt_at '
             . 'FROM social_queue WHERE status = :status ORDER BY publish_at ASC LIMIT :limit OFFSET :offset';
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':status', $dbStatus);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) {
            if ($row['status'] === 'ready') {
                $row['status'] = 'pending';
            }
        }

        json(200, $rows);
    }

    public static function posts(): void
    {
        requireBearer($_ENV['API_TOKEN'] ?? '');

        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
        $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
        $limit = max(1, min(100, $limit));
        $offset = max(0, $offset);

        $pdo = Db::instance();
        $sql = 'SELECT p.id, q.title, q.channels, p.status, q.publish_at, q.retries AS retry_count, '
             . 'p.posted_at AS last_attempt_at '
             . 'FROM social_posts p JOIN social_queue q ON p.queue_id = q.id '
             . 'ORDER BY p.id DESC LIMIT :limit OFFSET :offset';
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        json(200, $rows);
    }
}
