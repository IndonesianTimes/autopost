<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Exceptions\PlatformException;

class ErrorClassifier
{
    public static function isRetryable(PlatformException $e): bool
    {
        $code = $e->getCode();
        if ($code === 0) {
            return true; // network error
        }
        if ($code === 429 || $code >= 500) {
            return true; // rate limit or server errors
        }
        if (in_array($code, [400, 401, 403], true)) {
            return false; // client errors
        }
        return false;
    }
}
