<?php

declare(strict_types=1);

namespace App\Dispatcher;

use App\Contracts\DispatcherInterface;

class TelegramDispatcher implements DispatcherInterface
{
    public function post(array $payload): array
    {
        return ['post_id' => 'tg_' . uniqid()];
    }
}
