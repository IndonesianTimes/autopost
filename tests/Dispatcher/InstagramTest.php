<?php

declare(strict_types=1);

namespace Tests\Dispatcher;

use App\Dispatcher\Instagram;
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
            'caption' => 'Hello  ',
            'account_id' => '100',
            'access_token' => 'token',
        ];

        $result = $dispatcher->post($payload);

        $this->assertSame('instagram', $result['platform']);
        $this->assertSame('654321', $result['post_id']);
    }

    public function testCaptionTruncatedWhenTooLong(): void
    {
        $captions = [];
        $client = function (string $url, array $params) use (&$captions): array {
            if (isset($params['caption'])) {
                $captions[] = $params['caption'];
            }
            if (str_contains($url, '/media_publish')) {
                return ['status' => 200, 'body' => ['id' => '654321']];
            }
            return ['status' => 200, 'body' => ['id' => '123456']];
        };

        $dispatcher = new Instagram($client);

        $max = 2200;
        $payload = [
            'image_url' => 'http://example.com/image.jpg',
            'caption' => str_repeat('a', $max + 10),
            'account_id' => '100',
            'access_token' => 'token',
        ];

        $dispatcher->post($payload);

        $this->assertNotEmpty($captions);
        $this->assertSame($max, mb_strlen($captions[0]));
    }
}
