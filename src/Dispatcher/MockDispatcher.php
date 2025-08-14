<?php

declare(strict_types=1);

namespace App\Dispatcher;

use App\Contracts\DispatcherInterface;
use App\Helpers\Logger;

class MockDispatcher implements DispatcherInterface
{
    private string $platform;

    public function __construct(string $platform)
    {
        $this->platform = $platform;
    }

    public function post(array $payload): array
    {
        $queueId = (int)($payload['id'] ?? 0);
        $postId = 'mock_' . uniqid();
        Logger::logSuccess($queueId, $this->platform, $postId, ['mock' => true, 'payload' => $payload]);
        return [
            'platform' => $this->platform,
            'post_id' => $postId,
            'raw' => ['mock' => true],
        ];
    }
}
