<?php

declare(strict_types=1);

namespace App\Worker;

use App\Contracts\DispatcherInterface;
use App\Exceptions\PlatformException;
use App\Helpers\Config;
use App\Helpers\Db;
use App\Helpers\Lock;
use App\Helpers\Logger;
use App\Helpers\Notifier;
use App\Helpers\RetryPolicy;
use App\Dispatcher\MockDispatcher;
use DateTimeImmutable;
use PDO;
use RuntimeException;

class QueueProcessor
{
    private PDO $db;
    private array $dispatchers = [];
    private bool $dryRun = false;

    public function __construct()
    {
        $this->db = Db::instance();
        $this->dryRun = Config::bool('DRY_RUN', false);
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
        Logger::cli("Job #{$queueId} start");

        foreach ($channels as $platform) {
            if ($this->isAlreadyPosted($queueId, $platform)) {
                continue;
            }
            try {
                $dispatcher = $this->getDispatcher($platform);
                $payload = $job + ['dedupe_key' => $queueId . ':' . $platform];
                $resp = $dispatcher->post($payload);
                // success already logged by dispatcher
                Logger::cli("#{$queueId} {$platform} ok");
                Notifier::notify("Posted #{$queueId} to {$platform}");
            } catch (PlatformException $e) {
                $allSuccess = false;
                $stmt = $this->db->prepare("UPDATE social_queue SET last_error_code=:code, last_error_message=:msg WHERE id=:id");
                $stmt->execute([':code' => $e->getCode(), ':msg' => $e->getMessage(), ':id' => $queueId]);
                Logger::cli("#{$queueId} {$platform} fail {$e->getMessage()}");
                Notifier::notify("Fail {$platform} #{$queueId}: {$e->getMessage()}");
            } catch (\Throwable $e) {
                $allSuccess = false;
                Logger::logError($queueId, $platform, 0, $e->getMessage(), []);
                $stmt = $this->db->prepare("UPDATE social_queue SET last_error_code=:code, last_error_message=:msg WHERE id=:id");
                $stmt->execute([':code' => 0, ':msg' => $e->getMessage(), ':id' => $queueId]);
                Logger::cli("#{$queueId} {$platform} fail {$e->getMessage()}");
                Notifier::notify("Fail {$platform} #{$queueId}: {$e->getMessage()}");
            }
        }

        if ($allSuccess) {
            $stmt = $this->db->prepare("UPDATE social_queue SET status='posted', last_error_code=NULL, last_error_message=NULL WHERE id=:id");
            $stmt->execute([':id' => $queueId]);
            Logger::cli("Job #{$queueId} done");
        } else {
            $this->scheduleRetry($job);
            Logger::cli("Job #{$queueId} retry");
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
            if ($this->dryRun) {
                $this->dispatchers[$platform] = new MockDispatcher($platform);
            } else {
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
            Notifier::notify("Job #{$queueId} failed after retries");
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

}
