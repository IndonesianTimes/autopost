<?php

declare(strict_types=1);

namespace App\Services\Alert;

class Telegram
{
    public static function send(string $text): void
    {
        $token = $_ENV['TG_BOT_TOKEN'] ?? '';
        $chatId = $_ENV['TG_CHAT_ID_ALERT'] ?? '';
        if ($token === '' || $chatId === '') {
            return;
        }

        $endpoint = "https://api.telegram.org/bot{$token}/sendMessage";
        $postFields = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
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
