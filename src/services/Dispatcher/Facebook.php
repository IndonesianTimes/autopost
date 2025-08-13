<?php

declare(strict_types=1);

namespace App\Services\Dispatcher;

class Facebook
{
    public static function postPhoto(string $pageId, string $pageToken, string $imageUrl, string $caption): array
    {
        $endpoint = "https://graph.facebook.com/v18.0/{$pageId}/photos";

        $ch = curl_init($endpoint);
        $postFields = [
            'url' => $imageUrl,
            'caption' => $caption,
            'access_token' => $pageToken,
        ];

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($postFields),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 15,
        ]);

        $response = curl_exec($ch);

        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            return ['ok' => false, 'error' => $error, 'raw' => null];
        }

        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $json = json_decode($response, true);

        if ($httpCode === 200 && isset($json['id'])) {
            return ['ok' => true, 'id' => $json['id'], 'raw' => $json];
        }

        $errorMsg = $json['error']['message'] ?? "HTTP {$httpCode}";
        return ['ok' => false, 'error' => $errorMsg, 'raw' => $json];
    }
}
