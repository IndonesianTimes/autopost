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

    private const LOG_FILE = '/storage/logs/sae_worker.log';
    private const MAX_FILE_SIZE = 5242880; // 5MB
    private const MAX_FILES = 5;

    private static function truncate(string $json, int $max = 10000): string
    {
        return strlen($json) > $max ? substr($json, 0, $max) : $json;
    }

    public static function logSuccess(int $queueId, string $platform, ?string $postId, array $raw): void
    {
        try {
            $db = self::db();
            $json = json_encode($raw, JSON_UNESCAPED_UNICODE);
            $stmt = $db->prepare('INSERT INTO social_posts (queue_id, platform, post_id, raw_response, posted_at) VALUES (:qid,:pf,:pid,:resp,NOW())');
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
            $json = json_encode($responseRaw, JSON_UNESCAPED_UNICODE);
            $stmt = $db->prepare('INSERT INTO webhooks_log (queue_id, platform, response_code, error_message, response_body) VALUES (:qid,:pf,:code,:msg,:resp)');
            $stmt->execute([
                ':qid' => $queueId,
                ':pf' => $platform,
                ':code' => $code,
                ':msg' => $message,
                ':resp' => self::truncate($json ?: ''),
            ]);
        } catch (\Throwable $e) {
            // swallow logging errors
        }
    }

    public static function cli(string $message): void
    {
        $line = '[' . date('Y-m-d H:i:s') . '] ' . $message;
        echo $line . PHP_EOL;
        self::writeLog($line);
    }

    private static function writeLog(string $line): void
    {
        $file = self::logPath();
        $dir = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        self::rotate($file);
        file_put_contents($file, $line . PHP_EOL, FILE_APPEND);
    }

    private static function logPath(): string
    {
        return dirname(__DIR__, 2) . self::LOG_FILE;
    }

    private static function rotate(string $file): void
    {
        if (file_exists($file) && filesize($file) >= self::MAX_FILE_SIZE) {
            $max = self::MAX_FILES;
            $oldest = $file . '.' . $max;
            if (file_exists($oldest)) {
                unlink($oldest);
            }
            for ($i = $max - 1; $i >= 1; $i--) {
                $src = $file . '.' . $i;
                if (file_exists($src)) {
                    rename($src, $file . '.' . ($i + 1));
                }
            }
            rename($file, $file . '.1');
        }
    }
}
