<?php

declare(strict_types=1);

namespace Tests\Worker;

use App\Helpers\Db;
use App\Worker\QueueProcessor;
use PDO;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use DateTimeImmutable;

final class QueueProcessorTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('CREATE TABLE social_queue (id INTEGER PRIMARY KEY, channels TEXT, status TEXT, publish_at TEXT, retry_count INTEGER DEFAULT 0, last_attempt_at TEXT)');
    }

    private function seedDb(): void
    {
        $ref = new ReflectionClass(Db::class);
        $prop = $ref->getProperty('pdo');
        $prop->setAccessible(true);
        $prop->setValue(null, $this->pdo);
    }

    public function testScheduleRetryUpdatesPublishAt(): void
    {
        $this->seedDb();
        $now = new DateTimeImmutable('now');
        $stmt = $this->pdo->prepare('INSERT INTO social_queue (id, channels, status, publish_at, retry_count) VALUES (1, "", "pending", ?, 0)');
        $stmt->execute([$now->format('Y-m-d H:i:s')]);

        $processor = new QueueProcessor();
        $method = new ReflectionMethod($processor, 'scheduleRetry');
        $method->setAccessible(true);
        $method->invoke($processor, ['id' => 1, 'retry_count' => 0], $now);

        $row = $this->pdo->query('SELECT status, retry_count, publish_at FROM social_queue WHERE id=1')->fetch();
        $this->assertSame('retry', $row['status']);
        $this->assertSame(1, (int)$row['retry_count']);

        $expected = $now->modify('+5 minutes')->getTimestamp();
        $actual = strtotime($row['publish_at']);
        $this->assertEqualsWithDelta($expected, $actual, 2);
    }

    public function testScheduleRetryStopsAfterMax(): void
    {
        $this->seedDb();
        $now = new DateTimeImmutable('now');
        $stmt = $this->pdo->prepare('INSERT INTO social_queue (id, channels, status, publish_at, retry_count) VALUES (2, "", "retry", ?, 3)');
        $stmt->execute([$now->format('Y-m-d H:i:s')]);

        $processor = new QueueProcessor();
        $method = new ReflectionMethod($processor, 'scheduleRetry');
        $method->setAccessible(true);
        $method->invoke($processor, ['id' => 2, 'retry_count' => 3], $now);

        $row = $this->pdo->query('SELECT status, retry_count FROM social_queue WHERE id=2')->fetch();
        $this->assertSame('failed', $row['status']);
        $this->assertSame(3, (int)$row['retry_count']);
    }
}
