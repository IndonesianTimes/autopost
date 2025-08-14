<?php

declare(strict_types=1);

namespace Tests;

use App\Helpers\RetryPolicy;
use PHPUnit\Framework\TestCase;

final class RetryPolicyTest extends TestCase
{
    public function testNextScheduleDelays(): void
    {
        $now = time();
        $delta0 = RetryPolicy::nextSchedule(0)->getTimestamp() - $now;
        $delta1 = RetryPolicy::nextSchedule(1)->getTimestamp() - $now;
        $delta2 = RetryPolicy::nextSchedule(2)->getTimestamp() - $now;

        $this->assertEqualsWithDelta(300, $delta0, 2);
        $this->assertEqualsWithDelta(900, $delta1, 2);
        $this->assertEqualsWithDelta(3600, $delta2, 2);
    }
}
