<?php

declare(strict_types=1);

namespace Tests\Controllers;

use App\Controllers\QueueController;
use App\Helpers\Db;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class QueueControllerTest extends TestCase
{
    private string $envPath;

    protected function setUp(): void
    {
        $this->envPath = __DIR__ . '/../../.env';
        file_put_contents($this->envPath, '');
        require_once __DIR__ . '/../../src/bootstrap.php';
        $_ENV['API_TOKEN'] = 'secret';
        $_ENV['DB_DSN'] = 'sqlite::memory:';
        $_ENV['DB_USER'] = 'test';
        $_ENV['DB_PASS'] = '';

        $ref = new ReflectionClass(Db::class);
        $prop = $ref->getProperty('pdo');
        $prop->setAccessible(true);
        $prop->setValue(null, null);

        $pdo = Db::instance();
        $pdo->exec('CREATE TABLE social_queue (
            id INTEGER PRIMARY KEY,
            title TEXT,
            channels TEXT,
            status TEXT,
            publish_at TEXT,
            retry_count INTEGER DEFAULT 0,
            last_attempt_at TEXT,
            last_error_code TEXT,
            last_error_message TEXT
        )');
        $pdo->exec('CREATE TABLE social_posts (
            id INTEGER PRIMARY KEY,
            queue_id INTEGER,
            platform TEXT,
            post_id TEXT,
            raw_response TEXT,
            posted_at TEXT
        )');

        $pdo->exec("INSERT INTO social_queue (id, title, channels, status, publish_at, retry_count) VALUES (1, 'Title1', 'fb', 'pending', '2024-01-01 00:00:00', 0)");
        $pdo->exec("INSERT INTO social_queue (id, title, channels, status, publish_at, retry_count) VALUES (2, 'Title2', 'fb', 'posted', '2024-01-01 00:05:00', 0)");
        $pdo->exec("INSERT INTO social_posts (id, queue_id, platform, post_id, raw_response, posted_at) VALUES (1, 2, 'fb', 'pid', '{}', '2024-01-01 01:00:00')");
    }

    protected function tearDown(): void
    {
        if (file_exists($this->envPath)) {
            unlink($this->envPath);
        }
    }

    public function testQueueEndpointReturnsData(): void
    {
        $_GET = ['status' => 'pending', 'token' => 'secret'];
        ob_start();
        QueueController::queue();
        $output = ob_get_clean();
        $data = json_decode($output, true);
        $this->assertSame('ok', $data['status']);
        $this->assertCount(1, $data['data']);
        $row = $data['data'][0];
        $this->assertSame('Title1', $row['title']);
        $this->assertSame('pending', $row['status']);
    }

    public function testPostsEndpointReturnsData(): void
    {
        $_GET = ['token' => 'secret'];
        ob_start();
        QueueController::posts();
        $output = ob_get_clean();
        $data = json_decode($output, true);
        $this->assertSame('ok', $data['status']);
        $this->assertCount(1, $data['data']);
        $row = $data['data'][0];
        $this->assertSame('Title2', $row['title']);
        $this->assertSame('posted', $row['status']);
    }
}
