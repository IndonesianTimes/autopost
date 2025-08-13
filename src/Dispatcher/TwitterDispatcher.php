<?php

declare(strict_types=1);

namespace App\Dispatcher;

use App\Contracts\DispatcherInterface;

class TwitterDispatcher implements DispatcherInterface
{
    public function post(array $payload): array
    {
        return ['post_id' => 'tw_' . uniqid()];
    }
}
