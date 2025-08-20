<?php

declare(strict_types=1);

namespace Tests;

use App\Exceptions\PlatformException;
use App\Helpers\ErrorClassifier;
use PHPUnit\Framework\TestCase;

final class ErrorClassifierTest extends TestCase
{
    public function testRetryableStatusCodes(): void
    {
        $this->assertTrue(ErrorClassifier::isRetryable(new PlatformException('p', 500, 'err')));
        $this->assertTrue(ErrorClassifier::isRetryable(new PlatformException('p', 429, 'err')));
        $this->assertFalse(ErrorClassifier::isRetryable(new PlatformException('p', 400, 'err')));
    }
}
