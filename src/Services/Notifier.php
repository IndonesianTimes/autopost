<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Config;

class Notifier
{
    public static function alert(string $text): void
    {
        $token = Config::get('SAE_TELEGRAM_ALERT_BOT_TOKEN');
        $chatId = Config::get('SAE_TELEGRAM_ALERT_CHAT_ID');

        if ($token && $chatId) {
            $endpoint = "https://api.telegram.org/bot{$token}/sendMessage";
            $payload = [
                'chat_id' => $chatId,
                'text' => $text,
            ];
            $ch = curl_init($endpoint);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query($payload),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_CONNECTTIMEOUT => 15,
            ]);
            curl_exec($ch);
            curl_close($ch);
        }

        $logFile = dirname(__DIR__, 2) . '/storage/alerts.log';
        $line = date('c') . ' ' . $text . PHP_EOL;
        file_put_contents($logFile, $line, FILE_APPEND);
    }
}
