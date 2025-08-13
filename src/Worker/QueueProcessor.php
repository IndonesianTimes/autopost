<?php

declare(strict_types=1);

namespace App\Worker;

use App\Contracts\DispatcherInterface;
use App\Exceptions\PlatformException;
use App\Helpers\Config;
use App\Helpers\Db;
use App\Helpers\Lock;
use PDO;
use RuntimeException;

class QueueProcessor
{
    private PDO $db;
    private array $dispatchers = [];
    private array $backoff = [1 => 5, 2 => 15, 3 => 60];

    public function __construct()
    {
        $this->db = Db::instance();
    }

    public function run(): void
    {
        $lockFile = Config::get('WORKER_LOCK', '/tmp/sae_worker.lock');
        $lock = new Lock($lockFile);
        if (!$lock->acquire()) {
            echo "Worker already running\n";
            return;
        }
        try {
            $jobs = $this->fetchJobs();
            foreach ($jobs as $job) {
                $this->processJob($job);
            }
        } finally {
            $lock->release();
        }
    }

    private function fetchJobs(): array
    {
        $sql = "SELECT * FROM social_queue WHERE status IN ('pending','retry') AND publish_at <= NOW() ORDER BY priority DESC, publish_at ASC LIMIT 10";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }

    private function processJob(array $job): void
    {
        $queueId = (int)$job['id'];
        $channels = $this->parseChannels((string)$job['channels']);
        $allSuccess = true;

        foreach ($channels as $platform) {
            if ($this->isAlreadyPosted($queueId, $platform)) {
                continue;
            }
            try {
                $dispatcher = $this->getDispatcher($platform);
                $resp = $dispatcher->post($job);
                $postId = $resp['post_id'] ?? null;
                $this->logSuccess($queueId, $platform, $postId, $resp);
                $this->notify("Posted #{$queueId} to {$platform}");
            } catch (PlatformException $e) {
                $allSuccess = false;
                $this->logError($platform, $e->response);
                $this->notify("Fail {$platform} #{$queueId}: {$e->getMessage()}");
            } catch (\Throwable $e) {
                $allSuccess = false;
                $this->logError($platform, ['error' => $e->getMessage()]);
                $this->notify("Fail {$platform} #{$queueId}: {$e->getMessage()}");
            }
        }

        if ($allSuccess) {
            $stmt = $this->db->prepare("UPDATE social_queue SET status='posted' WHERE id=:id");
            $stmt->execute([':id' => $queueId]);
        } else {
            $this->scheduleRetry($job);
        }
    }

    private function parseChannels(string $channels): array
    {
        $parts = array_filter(array_map('trim', explode(',', $channels)));
        $mapped = [];
        foreach ($parts as $p) {
            $mapped[] = match ($p) {
                'fb' => 'facebook',
                'ig' => 'instagram',
                default => $p,
            };
        }
        return $mapped;
    }

    private function getDispatcher(string $platform): DispatcherInterface
    {
        if (!isset($this->dispatchers[$platform])) {
            $class = 'App\\Dispatcher\\' . ucfirst($platform) . 'Dispatcher';
            if (!class_exists($class)) {
                throw new RuntimeException("Dispatcher for {$platform} not found");
            }
            $this->dispatchers[$platform] = new $class();
        }
        return $this->dispatchers[$platform];
    }

    private function isAlreadyPosted(int $queueId, string $platform): bool
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM social_posts WHERE queue_id=:qid AND platform=:pf');
        $stmt->execute([':qid' => $queueId, ':pf' => $platform]);
        return (int)$stmt->fetchColumn() > 0;
    }

    private function logSuccess(int $queueId, string $platform, ?string $postId, array $resp): void
    {
        $stmt = $this->db->prepare('INSERT INTO social_posts (queue_id, platform, platform_post_id, status, response_json, posted_at) VALUES (:qid,:pf,:pid,\'posted\',:resp,NOW())');
        $stmt->execute([
            ':qid' => $queueId,
            ':pf' => $platform,
            ':pid' => $postId,
            ':resp' => json_encode($resp, JSON_UNESCAPED_UNICODE),
        ]);
    }

    private function logError(string $platform, array $resp): void
    {
        $stmt = $this->db->prepare('INSERT INTO webhooks_log (source, payload_json) VALUES (:src,:payload)');
        $stmt->execute([
            ':src' => $platform,
            ':payload' => json_encode($resp, JSON_UNESCAPED_UNICODE),
        ]);
    }

    private function scheduleRetry(array $job): void
    {
        $queueId = (int)$job['id'];
        $retry = (int)($job['retry_count'] ?? 0) + 1;
        if ($retry > 3) {
            $stmt = $this->db->prepare("UPDATE social_queue SET status='failed', retry_count=:rc, last_attempt_at=NOW() WHERE id=:id");
            $stmt->execute([':rc' => $retry, ':id' => $queueId]);
            $this->notify("Job #{$queueId} failed after retries");
            return;
        }
        $minutes = $this->backoff[$retry] ?? end($this->backoff);
        $stmt = $this->db->prepare("UPDATE social_queue SET status='retry', retry_count=:rc, last_attempt_at=NOW(), publish_at=DATE_ADD(NOW(), INTERVAL :mins MINUTE) WHERE id=:id");
        $stmt->execute([':rc' => $retry, ':mins' => $minutes, ':id' => $queueId]);
    }

    private function notify(string $text): void
    {
        $token = Config::get('TG_BOT_TOKEN');
        $chatId = Config::get('TG_CHAT_ID_ALERT');
        if (!$token || !$chatId) {
            return;
        }
        $endpoint = "https://api.telegram.org/bot{$token}/sendMessage";
        $postFields = [
            'chat_id' => $chatId,
            'text' => $text,
        ];
        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($postFields),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 15,
        ]);
        curl_exec($ch);
        curl_close($ch);
    }
}
