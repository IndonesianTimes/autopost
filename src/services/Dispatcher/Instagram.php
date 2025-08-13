<?php

declare(strict_types=1);

namespace App\Services\Dispatcher;

class Instagram
{
    /**
     * Post a photo to Instagram Business account via Graph API.
     *
     * @param string $businessId   Instagram Business Account ID
     * @param string $accessToken  Long-lived Instagram access token
     * @param string $imageUrl     Publicly accessible image URL
     * @param string $caption      Caption text
     *
     * @return array{ok:bool, id?:string, error?:string, raw?:mixed}
     */
    public static function postPhoto(string $businessId, string $accessToken, string $imageUrl, string $caption): array
    {
        $createResp = self::createMedia($businessId, $accessToken, $imageUrl, $caption);
        if (!$createResp['ok']) {
            return ['ok' => false, 'error' => $createResp['error'] ?? 'Failed to create media', 'raw' => $createResp['raw'] ?? null];
        }

        $publishResp = self::publishMedia($businessId, $accessToken, $createResp['id']);
        if (isset($publishResp['id'])) {
            return ['ok' => true, 'id' => $publishResp['id'], 'raw' => $publishResp];
        }

        $error = $publishResp['error']['message'] ?? 'Unknown error';
        return ['ok' => false, 'error' => $error, 'raw' => $publishResp];
    }

    /**
     * Step 1: create media container on IG.
     *
     * @return array{ok:bool, id?:string, error?:string, raw?:mixed}
     */
    private static function createMedia(string $businessId, string $accessToken, string $imageUrl, string $caption): array
    {
        $endpoint = "https://graph.facebook.com/v18.0/{$businessId}/media";

        $ch = curl_init($endpoint);
        $postFields = [
            'image_url' => $imageUrl,
            'caption' => $caption,
            'access_token' => $accessToken,
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
            return ['ok' => true, 'id' => (string)$json['id'], 'raw' => $json];
        }

        $error = $json['error']['message'] ?? "HTTP {$httpCode}";
        return ['ok' => false, 'error' => $error, 'raw' => $json];
    }

    /**
     * Step 2: publish previously created media.
     *
     * @return array
     */
    private static function publishMedia(string $businessId, string $accessToken, string $creationId): array
    {
        $endpoint = "https://graph.facebook.com/v18.0/{$businessId}/media_publish";

        $ch = curl_init($endpoint);
        $postFields = [
            'creation_id' => $creationId,
            'access_token' => $accessToken,
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
            return ['error' => $error];
        }
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $json = json_decode($response, true);
        if ($httpCode === 200 && isset($json['id'])) {
            return $json;
        }
        return $json ?? ['error' => "HTTP {$httpCode}"];
    }
}
