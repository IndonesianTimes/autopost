<?php

declare(strict_types=1);

namespace App\Services;

class Logger
{
    private static function log(string $level, string $message, array $ctx = []): void
    {
        $logDir = __DIR__ . '/../../logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }

        $ctxJson = json_encode($ctx, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($ctxJson === false || $ctxJson === '[]') {
            $ctxJson = '{}';
        }

        $line = sprintf('[%s] %s %s | %s%s', date('Y-m-d H:i:s'), $level, $message, $ctxJson, PHP_EOL);
        file_put_contents($logDir . '/app.log', $line, FILE_APPEND);
    }

    public static function info(string $message, array $ctx = []): void
    {
        self::log('INFO', $message, $ctx);
    }

    public static function error(string $message, array $ctx = []): void
    {
        self::log('ERROR', $message, $ctx);
    }
}
