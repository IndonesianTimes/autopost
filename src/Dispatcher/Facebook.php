<?php

declare(strict_types=1);

namespace App\Dispatcher;

use App\Contracts\DispatcherInterface;
use App\Exceptions\PlatformException;
use App\Helpers\Logger;

/**
 * Facebook Page dispatcher using Graph API.
 */
class Facebook implements DispatcherInterface
{
    private const BASE_URL = 'https://graph.facebook.com/v19.0/';
    private const NON_RETRYABLE_CODES = [190, 200, 10];
    private const NON_RETRYABLE_SUBCODES = [463, 460, 467];

    /** @var callable */
    private $httpClient;

    public function __construct(?callable $httpClient = null)
    {
        $this->httpClient = $httpClient ?? [$this, 'defaultHttpClient'];
    }

    /**
     * @param array{page_id:string,page_access_token:string,caption:string,image_url?:?string,text_only?:bool,dedupe_key?:string} $payload
     * @return array{platform:string,post_id:string,raw:mixed}
     *
     * @throws PlatformException
     */
    public function post(array $payload): array
    {
        $queueId = (int)($payload['id'] ?? 0);
        try {
            foreach (['page_id', 'page_access_token', 'caption'] as $key) {
                if (empty($payload[$key])) {
                    throw new PlatformException('facebook', 422, "Missing field: {$key}");
                }
            }

            $pageId = (string) $payload['page_id'];
            $token = (string) $payload['page_access_token'];
            $caption = (string) $payload['caption'];
            $imageUrl = $payload['image_url'] ?? null;
            $textOnly = (bool)($payload['text_only'] ?? false);
            $dedupe = $payload['dedupe_key'] ?? null;

            if (!$textOnly && !empty($imageUrl)) {
                $endpoint = $pageId . '/photos';
                $params = [
                    'url' => $imageUrl,
                    'caption' => $caption,
                    'published' => 'true',
                ];
            } else {
                $endpoint = $pageId . '/feed';
                $params = [
                    'message' => $caption,
                ];
            }

            if ($dedupe !== null) {
                $params['idempotence_token'] = (string) $dedupe;
            }
            $params['access_token'] = $token;

            $url = self::BASE_URL . $endpoint;
            $result = ($this->httpClient)($url, $params);
            $status = $result['status'] ?? 0;
            $body = $result['body'] ?? [];

            if ($status >= 400 || isset($body['error'])) {
                $this->handleError($status, $body);
            }

            if (!isset($body['id'])) {
                $this->handleError($status ?: 400, ['error' => ['message' => 'Invalid response']]);
            }

            Logger::logSuccess($queueId, 'facebook', (string) $body['id'], $body);

            return [
                'platform' => 'facebook',
                'post_id' => (string) $body['id'],
                'raw' => $body,
            ];
        } catch (PlatformException $e) {
            Logger::logError($queueId, 'facebook', $e->getCode(), $e->getMessage(), $e->response);
            throw $e;
        }
    }

    /**
     * @return array{status:int,body:array}
     */
    private function defaultHttpClient(string $url, array $params): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($params),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 15,
        ]);
        $body = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($body === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new PlatformException('facebook', 0, $error);
        }
        curl_close($ch);
        $decoded = json_decode((string) $body, true);
        if (!is_array($decoded)) {
            $decoded = [];
        }
        return ['status' => $status, 'body' => $decoded];
    }

    private function handleError(int $status, array $body): never
    {
        $error = $body['error'] ?? [];
        $code = (int)($error['code'] ?? 0);
        $subcode = (int)($error['error_subcode'] ?? 0);
        $message = $error['message'] ?? 'Unknown error';

        $retryable = $status >= 500;
        if (in_array($code, self::NON_RETRYABLE_CODES, true) || in_array($subcode, self::NON_RETRYABLE_SUBCODES, true)) {
            $retryable = false;
        }

        $error['retryable'] = $retryable;

        throw new PlatformException('facebook', $status, $message, $error);
    }
}

