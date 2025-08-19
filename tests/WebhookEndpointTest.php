<?php

declare(strict_types=1);

namespace Tests;

use App\Helpers\Db;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class WebhookEndpointTest extends TestCase
{
    protected function setUp(): void
    {
        $_ENV['SAE_WEBHOOK_SECRET'] = 'secret';
        $_ENV['DB_DSN'] = 'sqlite::memory:';
        $_ENV['DB_USERNAME'] = '';
        $_ENV['DB_PASSWORD'] = '';

        $ref = new ReflectionClass(Db::class);
        $prop = $ref->getProperty('pdo');
        $prop->setAccessible(true);
        $prop->setValue(null, null);

        require_once __DIR__ . '/../vendor/autoload.php';
    }

    public function testWebhookEnqueues(): void
    {
        $pdo = Db::pdo();
        $pdo->exec('CREATE TABLE webhooks_log (id INTEGER PRIMARY KEY AUTOINCREMENT, source TEXT, event_id TEXT, signature TEXT, payload TEXT)');
        $pdo->exec('CREATE TABLE social_queue (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT, summary TEXT, link_url TEXT, image_url TEXT, channels TEXT, status TEXT, publish_at TEXT, retry_count INTEGER, meta TEXT)');

        $body = json_encode([
            'event_id' => 'evt1',
            'title' => 'Hello',
            'content' => 'Body',
        ], JSON_UNESCAPED_UNICODE);
        $sig = hash_hmac('sha256', $body, $_ENV['SAE_WEBHOOK_SECRET']);
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['HTTP_X_SIGNATURE'] = 'sha256=' . $sig;
        $GLOBALS['__SAE_WEBHOOK_BODY'] = $body;

        ob_start();
        require __DIR__ . '/../public/api/autopost/webhook.php';
        $out = ob_get_clean();
        unset($GLOBALS['__SAE_WEBHOOK_BODY']);
        $data = json_decode($out, true);

        $this->assertSame(202, http_response_code());
        $this->assertTrue($data['ok']);
        $count = (int)$pdo->query('SELECT COUNT(*) FROM social_queue')->fetchColumn();
        $this->assertSame(1, $count);
        http_response_code(200);
    }
}
