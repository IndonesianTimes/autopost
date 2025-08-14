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
        $pdo->exec('CREATE TABLE social_queue (id INTEGER PRIMARY KEY, title TEXT, channels TEXT, status TEXT, publish_at TEXT, retries INTEGER, updated_at TEXT)');
        $pdo->exec('CREATE TABLE social_posts (id INTEGER PRIMARY KEY, queue_id INTEGER, platform TEXT, status TEXT, posted_at TEXT)');

        $pdo->exec("INSERT INTO social_queue (id, title, channels, status, publish_at, retries, updated_at) VALUES (1, 'Title', 'fb', 'ready', '2024-01-01 00:00:00', 0, '2024-01-01 00:00:00')");
        $pdo->exec("INSERT INTO social_posts (id, queue_id, platform, status, posted_at) VALUES (1, 1, 'fb', 'posted', '2024-01-01 01:00:00')");
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
        $this->assertSame('Title', $row['title']);
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
        $this->assertSame('Title', $row['title']);
        $this->assertSame('posted', $row['status']);
    }
}
