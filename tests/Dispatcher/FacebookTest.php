<?php

declare(strict_types=1);

namespace Tests\Dispatcher;

use App\Dispatcher\Facebook;
use App\Exceptions\PlatformException;
use PHPUnit\Framework\TestCase;

class FacebookTest extends TestCase
{
    public function testPostPhoto(): void
    {
        $self = $this;
        $client = function (string $url, array $params) use ($self): array {
            $self->assertStringContainsString('/photos', $url);
            $self->assertSame('http://example.com/a.jpg', $params['url']);
            $self->assertSame('Caption', $params['caption']);
            $self->assertSame('true', $params['published']);
            $self->assertSame('token', $params['access_token']);
            return ['status' => 200, 'body' => ['id' => '123']];
        };

        $dispatcher = new Facebook($client);
        $result = $dispatcher->post([
            'page_id' => '1',
            'page_access_token' => 'token',
            'image_url' => 'http://example.com/a.jpg',
            'caption' => " Caption  ",
        ]);

        $this->assertSame('facebook', $result['platform']);
        $this->assertSame('123', $result['post_id']);
    }

    public function testPostFeedWithoutImage(): void
    {
        $self = $this;
        $client = function (string $url, array $params) use ($self): array {
            $self->assertStringContainsString('/feed', $url);
            $self->assertSame('Hi', $params['message']);
            return ['status' => 200, 'body' => ['id' => '555']];
        };

        $dispatcher = new Facebook($client);
        $result = $dispatcher->post([
            'page_id' => '1',
            'page_access_token' => 'token',
            'caption' => "Hi  ",
            'text_only' => true,
        ]);

        $this->assertSame('facebook', $result['platform']);
        $this->assertSame('555', $result['post_id']);
    }

    public function testTokenExpiredThrowsException(): void
    {
        $client = fn(string $url, array $params): array => [
            'status' => 400,
            'body' => ['error' => ['message' => 'expired', 'code' => 190, 'error_subcode' => 463]],
        ];

        $dispatcher = new Facebook($client);

        try {
            $dispatcher->post([
                'page_id' => '1',
                'page_access_token' => 'token',
                'caption' => 'Hi',
                'text_only' => true,
            ]);
            $this->fail('Expected exception not thrown');
        } catch (PlatformException $e) {
            $this->assertSame('facebook', $e->platform);
            $this->assertFalse($e->response['retryable']);
        }
    }
}
