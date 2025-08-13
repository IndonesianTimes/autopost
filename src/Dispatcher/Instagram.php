<?php

declare(strict_types=1);

namespace App\Dispatcher;

use App\Contracts\DispatcherInterface;
use App\Exceptions\PlatformException;

/**
 * Instagram Business dispatcher using Graph API.
 */
class Instagram implements DispatcherInterface
{
    private const BASE_URL = 'https://graph.facebook.com/v19.0/';
    private const MAX_CAPTION = 2200;
    private const RETRYABLE_SUBCODES = [4, 17, 32, 613, 80007];

    /** @var callable */
    private $httpClient;

    public function __construct(?callable $httpClient = null)
    {
        $this->httpClient = $httpClient ?? [$this, 'defaultHttpClient'];
    }

    /**
     * @param array{image_url:string,caption:string,account_id:string,access_token:string} $payload
     * @return array{platform:string,post_id:string,raw:mixed}
     *
     * @throws PlatformException
     */
    public function post(array $payload): array
    {
        foreach (['image_url', 'caption', 'account_id', 'access_token'] as $key) {
            if (empty($payload[$key])) {
                throw new PlatformException('instagram', 422, "Missing field: {$key}");
            }
        }

        $caption = str_replace(["\r\n", "\r"], "\n", (string) $payload['caption']);
        if (mb_strlen($caption) > self::MAX_CAPTION) {
            throw new PlatformException('instagram', 422, 'Caption exceeds maximum length');
        }

        $igUserId = $payload['account_id'];
        $token = $payload['access_token'];

        $media = $this->request(
            self::BASE_URL . $igUserId . '/media',
            [
                'image_url' => $payload['image_url'],
                'caption' => $caption,
            ],
            $token
        );

        if (!isset($media['id'])) {
            $this->handleError(400, ['error' => ['message' => 'Invalid media response']]);
        }
        $creationId = $media['id'];

        $publish = $this->request(
            self::BASE_URL . $igUserId . '/media_publish',
            ['creation_id' => $creationId],
            $token
        );

        if (!isset($publish['id'])) {
            $this->handleError(400, ['error' => ['message' => 'Invalid publish response']]);
        }

        return [
            'platform' => 'instagram',
            'post_id' => $publish['id'],
            'raw' => $publish,
        ];
    }

    /**
     * @param array $params
     * @return array{status:int,body:array}
     */
    private function defaultHttpClient(string $url, array $params): array
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
        $body = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($body === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new PlatformException('instagram', 0, $error);
        }
        curl_close($ch);
        $decoded = json_decode((string) $body, true) ?? [];
        return ['status' => $status, 'body' => $decoded];
    }

    /**
     * @return array
     */
    private function request(string $url, array $params, string $token): array
    {
        $url .= '?access_token=' . urlencode($token);
        $result = ($this->httpClient)($url, $params);
        $status = $result['status'] ?? 0;
        $body = $result['body'] ?? [];
        if ($status >= 400 || isset($body['error'])) {
            $this->handleError($status, $body);
        }
        return $body;
    }

    private function handleError(int $status, array $body): never
    {
        $error = $body['error'] ?? [];
        $subcode = $error['error_subcode'] ?? null;
        $message = $error['message'] ?? 'Unknown error';

        $retryable = $status >= 500 || in_array($subcode, self::RETRYABLE_SUBCODES, true);
        $error['retryable'] = $retryable;

        throw new PlatformException('instagram', $status, $message, $error);
    }
}
