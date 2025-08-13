<?php

declare(strict_types=1);

namespace Tests\Dispatcher;

use App\Dispatcher\Telegram;
use App\Exceptions\PlatformException;
use PHPUnit\Framework\TestCase;

class TelegramTest extends TestCase
{
    public function testSendPhotoSuccess(): void
    {
        $client = function (string $url, array $params): array {
            $this->assertStringContainsString('sendPhoto', $url);
            $this->assertSame('123', $params['chat_id']);
            $this->assertSame('hello', $params['caption']);
            return ['status' => 200, 'body' => ['ok' => true, 'result' => ['message_id' => 42]]];
        };

        $dispatcher = new Telegram($client);

        $payload = [
            'bot_token' => 'token',
            'chat_id' => '123',
            'caption' => 'hello',
            'image_url' => 'http://example.com/img.jpg',
        ];

        $result = $dispatcher->post($payload);

        $this->assertSame('telegram', $result['platform']);
        $this->assertSame('42', $result['post_id']);
    }

    public function testFallbackToSendMessage(): void
    {
        $client = function (string $url, array $params): array {
            if (str_contains($url, 'sendPhoto')) {
                return ['status' => 400, 'body' => ['ok' => false, 'description' => 'bad photo']];
            }
            $this->assertStringContainsString('sendMessage', $url);
            return ['status' => 200, 'body' => ['ok' => true, 'result' => ['message_id' => 55]]];
        };

        $dispatcher = new Telegram($client);

        $payload = [
            'bot_token' => 'token',
            'chat_id' => '123',
            'caption' => 'hello',
            'image_url' => 'http://example.com/img.jpg',
        ];

        $result = $dispatcher->post($payload);
        $this->assertSame('55', $result['post_id']);
    }

    public function testSendMessageFailureThrowsException(): void
    {
        $client = function (string $url, array $params): array {
            if (str_contains($url, 'sendPhoto')) {
                return ['status' => 400, 'body' => ['ok' => false, 'description' => 'bad photo']];
            }
            return ['status' => 500, 'body' => ['ok' => false, 'description' => 'fail']];
        };

        $dispatcher = new Telegram($client);

        $payload = [
            'bot_token' => 'token',
            'chat_id' => '123',
            'caption' => 'hello',
            'image_url' => 'http://example.com/img.jpg',
        ];

        $this->expectException(PlatformException::class);
        $dispatcher->post($payload);
    }
}
