<?php

declare(strict_types=1);

namespace App\Helpers;

class Notifier
{
    public static function notify(string $text): void
    {
        if (!Config::bool('NOTIFY_ENABLED')) {
            return;
        }
        $token = Config::get('BOT_TOKEN_ADMIN');
        $chatId = Config::get('CHAT_ID_ADMIN');
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
