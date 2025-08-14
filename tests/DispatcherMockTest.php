<?php

declare(strict_types=1);

namespace Tests;

use App\Contracts\DispatcherInterface;
use App\Exceptions\PlatformException;
use PHPUnit\Framework\TestCase;

final class DispatcherMockTest extends TestCase
{
    public function testMockDispatcherReturnsPayload(): void
    {
        $dispatcher = new class implements DispatcherInterface {
            public function post(array $payload): array
            {
                return ['post_id' => 'abc', 'response' => $payload];
            }
        };

        $result = $dispatcher->post(['a' => 1]);
        $this->assertSame('abc', $result['post_id']);
        $this->assertSame(['a' => 1], $result['response']);
    }

    public function testMockDispatcherThrowsException(): void
    {
        $dispatcher = new class implements DispatcherInterface {
            public function post(array $payload): array
            {
                throw new PlatformException('mock', 400, 'error');
            }
        };

        $this->expectException(PlatformException::class);
        $dispatcher->post([]);
    }
}
