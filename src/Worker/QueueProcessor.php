<?php

declare(strict_types=1);

namespace App\Worker;

use App\Contracts\DispatcherInterface;
use App\Exceptions\PlatformException;
use App\Helpers\Config;
use App\Helpers\Db;
use App\Helpers\Lock;
use App\Helpers\Logger;
use App\Helpers\RetryPolicy;
use DateTimeImmutable;
use PDO;
use RuntimeException;

class QueueProcessor
{
    private PDO $db;
    private array $dispatchers = [];

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
                $payload = $job + ['dedupe_key' => $queueId . ':' . $platform];
                $resp = $dispatcher->post($payload);
                $postId = $resp['post_id'] ?? null;
                // success already logged by dispatcher
                $this->notify("Posted #{$queueId} to {$platform}");
            } catch (PlatformException $e) {
                $allSuccess = false;
                $this->notify("Fail {$platform} #{$queueId}: {$e->getMessage()}");
            } catch (\Throwable $e) {
                $allSuccess = false;
                Logger::logError($queueId, $platform, 0, $e->getMessage(), []);
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
            $base = 'App\\Dispatcher\\' . ucfirst($platform);
            $class = $base . 'Dispatcher';
            if (!class_exists($class)) {
                $class = $base;
            }
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


    private function scheduleRetry(array $job): void
    {
        $queueId = (int)$job['id'];
        $current = (int)($job['retry_count'] ?? 0);
        $now = new DateTimeImmutable('now');

        if ($current >= RetryPolicy::MAX_RETRY) {
            $stmt = $this->db->prepare("UPDATE social_queue SET status='failed', retry_count=:rc, last_attempt_at=:now WHERE id=:id");
            $stmt->execute([':rc' => $current, ':now' => $now->format('Y-m-d H:i:s'), ':id' => $queueId]);
            $this->notify("Job #{$queueId} failed after retries");
            return;
        }

        $next = RetryPolicy::nextSchedule($current);
        $stmt = $this->db->prepare("UPDATE social_queue SET status='retry', retry_count=:rc, last_attempt_at=:now, publish_at=:next WHERE id=:id");
        $stmt->execute([
            ':rc' => $current + 1,
            ':now' => $now->format('Y-m-d H:i:s'),
            ':next' => $next->format('Y-m-d H:i:s'),
            ':id' => $queueId,
        ]);
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
