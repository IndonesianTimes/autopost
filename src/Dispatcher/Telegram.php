<?php

declare(strict_types=1);

namespace App\Dispatcher;

use App\Contracts\DispatcherInterface;
use App\Exceptions\PlatformException;
use App\Helpers\Logger;

class Telegram implements DispatcherInterface
{
    private const BASE_URL = 'https://api.telegram.org/';

    /** @var callable */
    private $httpClient;

    public function __construct(?callable $httpClient = null)
    {
        $this->httpClient = $httpClient ?? [$this, 'defaultHttpClient'];
    }

    /**
     * @param array{bot_token:string,chat_id:string,caption:string,image_url?:?string,parse_mode?:string} $payload
     * @return array{platform:string,post_id:string,raw:mixed}
     *
     * @throws PlatformException
     */
    public function post(array $payload): array
    {
        $queueId = (int)($payload['id'] ?? 0);
        try {
            foreach (['bot_token', 'chat_id', 'caption'] as $key) {
                if (empty($payload[$key])) {
                    throw new PlatformException('telegram', 422, "Missing field: {$key}");
                }
            }

            $token = (string) $payload['bot_token'];
            $chatId = (string) $payload['chat_id'];
            $caption = (string) $payload['caption'];
            $parseMode = $payload['parse_mode'] ?? null;
            if ($parseMode === 'HTML') {
                $caption = htmlspecialchars($caption, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            }

            $imageUrl = $payload['image_url'] ?? null;
            if (!empty($imageUrl)) {
                try {
                    $resp = $this->request(
                        $token,
                        'sendPhoto',
                        [
                            'chat_id' => $chatId,
                            'photo' => $imageUrl,
                            'caption' => $caption,
                            'parse_mode' => $parseMode,
                        ]
                    );
                    Logger::logSuccess($queueId, 'telegram', (string)($resp['result']['message_id'] ?? ''), $resp);
                    return [
                        'platform' => 'telegram',
                        'post_id' => (string)($resp['result']['message_id'] ?? ''),
                        'raw' => $resp,
                    ];
                } catch (PlatformException $e) {
                    Logger::logError($queueId, 'telegram', $e->getCode(), $e->getMessage(), $e->response);
                    // fallthrough to sendMessage
                }
            }

            $resp = $this->request(
                $token,
                'sendMessage',
                [
                    'chat_id' => $chatId,
                    'text' => $caption,
                    'parse_mode' => $parseMode,
                ]
            );

            Logger::logSuccess($queueId, 'telegram', (string)($resp['result']['message_id'] ?? ''), $resp);

            return [
                'platform' => 'telegram',
                'post_id' => (string)($resp['result']['message_id'] ?? ''),
                'raw' => $resp,
            ];
        } catch (PlatformException $e) {
            Logger::logError($queueId, 'telegram', $e->getCode(), $e->getMessage(), $e->response);
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
            CURLOPT_POSTFIELDS => http_build_query(array_filter($params, fn($v) => $v !== null)),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 15,
        ]);
        $body = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($body === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new PlatformException('telegram', 0, $error);
        }
        curl_close($ch);
        $decoded = json_decode((string) $body, true);
        if (!is_array($decoded)) {
            $decoded = [];
        }
        return ['status' => $status, 'body' => $decoded];
    }

    /**
     * @return array
     */
    private function request(string $token, string $method, array $params): array
    {
        $url = self::BASE_URL . 'bot' . $token . '/' . $method;
        $result = ($this->httpClient)($url, $params);
        $status = $result['status'] ?? 0;
        $body = $result['body'] ?? [];
        if ($status >= 400 || !($body['ok'] ?? false)) {
            $this->handleError($status, $body);
        }
        return $body;
    }

    private function handleError(int $status, array $body): never
    {
        $description = $body['description'] ?? 'Unknown error';
        throw new PlatformException('telegram', $status, $description, $body);
    }
}
