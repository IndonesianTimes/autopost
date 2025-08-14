<?php

declare(strict_types=1);

namespace App\Dispatcher;

use App\Contracts\DispatcherInterface;
use App\Helpers\Logger;

class TwitterDispatcher implements DispatcherInterface
{
    public function post(array $payload): array
    {
        $queueId = (int)($payload['id'] ?? 0);
        $postId = 'tw_' . uniqid();
        Logger::logSuccess($queueId, 'twitter', $postId, []);
        return ['post_id' => $postId, 'platform' => 'twitter', 'raw' => []];
    }
}
