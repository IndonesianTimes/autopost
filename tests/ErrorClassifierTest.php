<?php

declare(strict_types=1);

namespace Tests;

use App\Helpers\ErrorClassifier;
use PHPUnit\Framework\TestCase;

final class ErrorClassifierTest extends TestCase
{
    public function testRetryableStatusCodes(): void
    {
        $this->assertTrue(ErrorClassifier::isRetryable(500));
        $this->assertTrue(ErrorClassifier::isRetryable(429));
        $this->assertFalse(ErrorClassifier::isRetryable(400));
    }
}
