<?php

declare(strict_types=1);

namespace Tests\Dispatcher;

use App\Dispatcher\Instagram;
use App\Exceptions\PlatformException;
use PHPUnit\Framework\TestCase;

class InstagramTest extends TestCase
{
    public function testValidPayload(): void
    {
        $client = function (string $url, array $params): array {
            if (str_contains($url, '/media_publish')) {
                return ['status' => 200, 'body' => ['id' => '654321']];
            }
            return ['status' => 200, 'body' => ['id' => '123456']];
        };

        $dispatcher = new Instagram($client);

        $payload = [
            'image_url' => 'http://example.com/image.jpg',
            'caption' => 'Hello',
            'account_id' => '100',
            'access_token' => 'token',
        ];

        $result = $dispatcher->post($payload);

        $this->assertSame('instagram', $result['platform']);
        $this->assertSame('654321', $result['post_id']);
    }

    public function testCaptionTooLongThrowsException(): void
    {
        $client = fn(string $url, array $params): array => ['status' => 200, 'body' => []];

        $dispatcher = new Instagram($client);

        $payload = [
            'image_url' => 'http://example.com/image.jpg',
            'caption' => str_repeat('a', 2201),
            'account_id' => '100',
            'access_token' => 'token',
        ];

        $this->expectException(PlatformException::class);
        $dispatcher->post($payload);
    }
}
