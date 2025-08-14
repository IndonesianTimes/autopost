<?php

declare(strict_types=1);

namespace App\Helpers;

use DateTimeImmutable;

class RetryPolicy
{
    public const MAX_RETRY = 3;

    public static function nextSchedule(int $retryCount): DateTimeImmutable
    {
        $now = new DateTimeImmutable('now');
        return match ($retryCount) {
            0 => $now->modify('+5 minutes'),
            1 => $now->modify('+15 minutes'),
            default => $now->modify('+60 minutes'),
        };
    }
}
