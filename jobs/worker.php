<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

use App\Models\Post;
use App\Models\Queue;
use App\Services\Dispatcher\Facebook;
use App\Services\Templater;
use App\Services\UTMBuilder;
use App\Services\Logger;
use App\Services\Alert\Telegram;

ini_set('max_execution_time', '120');

/**
 * Append a line to worker log.
 */
function logMessage(string $message): void
{
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0777, true);
    }

    $line = sprintf('[%s] %s%s', date('c'), $message, PHP_EOL);
    file_put_contents($logDir . '/worker.log', $line, FILE_APPEND);
}

$jobs = Queue::fetchDue(20);

$total = count($jobs);
$success = 0;
$failed = 0;

foreach ($jobs as $job) {
    $id = (int)$job['id'];
    Queue::markPosting($id);

    $channels = array_filter(array_map('trim', explode(',', (string)$job['channels'])));
    $jobSuccess = true;
    $errorReason = '';

    foreach ($channels as $platform) {
        $caption = Templater::render($job, $platform);
        $trackedLink = UTMBuilder::build($job['link_url'], $platform, $job['utm_json'] ?? []);

        $imageUrl = $job['image_url'] ?? ($_ENV['FALLBACK_IMAGE_URL'] ?? '');

        if ($platform === 'fb') {
            $pageId = $_ENV['FB_PAGE_ID'] ?? '';
            $pageToken = $_ENV['FB_PAGE_TOKEN'] ?? '';
            $resp = Facebook::postPhoto($pageId, $pageToken, $imageUrl, $caption);

            $logData = [
                'caption' => $caption,
                'tracked_link' => $trackedLink,
                'response' => $resp['raw'] ?? null,
            ];

            if ($resp['ok']) {
                Post::log($id, 'fb', $resp['id'] ?? null, 'posted', $logData);
                Logger::info("Job {$id} posted to FB", $logData);
            } else {
                $logData['error'] = $resp['error'] ?? null;
                Post::log($id, 'fb', null, 'failed', $logData);
                $jobSuccess = false;
                $errorReason = $resp['error'] ?? 'Unknown error';
                Logger::error("Job {$id} FB error: {$errorReason}", $logData);

                if (str_contains($errorReason, 'OAuthException')) {
                    Telegram::send("[Autopost] Job #{$id} FB auth error: {$errorReason}");
                }
            }
        }
        // other platforms ignored in P0
    }

    if ($jobSuccess) {
        Queue::markPosted($id);
        $success++;
        logMessage("Job {$id} posted");
        Logger::info("Job {$id} posted");
    } else {
        $retries = (int)($job['retries'] ?? 0);
        $scheme = [1, 5, 15];
        if ($retries < 3) {
            $backoff = $scheme[$retries] ?? end($scheme);
            Queue::scheduleRetry($id, $backoff);
            logMessage("Job {$id} retry #" . ($retries + 1) . " in {$backoff}m: {$errorReason}");
            Logger::error("Job {$id} retry #" . ($retries + 1) . ": {$errorReason}", ['backoff' => $backoff]);
        } else {
            Queue::markFailed($id, $errorReason);
            $failed++;
            logMessage("Job {$id} failed: {$errorReason}");
            Logger::error("Job {$id} failed after retries: {$errorReason}");
            Telegram::send("[Autopost] Job #{$id} FAILED after retries: {$errorReason}");
        }
    }
}

echo sprintf("Processed %d jobs: %d success, %d failed\n", $total, $success, $failed);
