<?php

declare(strict_types=1);

namespace App\Helpers;

class ErrorClassifier
{
    public static function isRetryable(int $code): bool
    {
        return $code === 0 || $code === 429 || $code >= 500;
    }
}
