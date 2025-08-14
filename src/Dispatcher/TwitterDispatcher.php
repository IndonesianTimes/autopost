<?php

declare(strict_types=1);

namespace App\Dispatcher;

use App\Contracts\DispatcherInterface;
use App\Helpers\Logger;
use App\Helpers\Sanitizer;

class TwitterDispatcher implements DispatcherInterface
{
    public function post(array $payload): array
    {
        $queueId = (int)($payload['id'] ?? 0);
        $caption = Sanitizer::sanitizeCaption((string)($payload['caption'] ?? ''), 280);
        $postId = 'tw_' . uniqid();
        Logger::logSuccess($queueId, 'twitter', $postId, ['caption' => $caption]);
        return ['post_id' => $postId, 'platform' => 'twitter', 'raw' => ['caption' => $caption]];
    }
}
