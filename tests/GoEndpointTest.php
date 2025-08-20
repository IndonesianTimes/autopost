<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

final class GoEndpointTest extends TestCase
{
    /**
     * @runInSeparateProcess
     */
    public function testRedirectAndLog(): void
    {
        define('SAE_TESTING', true);
        $_ENV['DB_DSN'] = 'sqlite::memory:';
        $_ENV['DB_USER'] = '';
        $_ENV['DB_PASS'] = '';
        $_ENV['SAE_IP_PEPPER'] = 'pep';
        $envFile = dirname(__DIR__) . '/.env';
        file_put_contents($envFile, '');
        require_once __DIR__ . '/../src/bootstrap.php';
        $pdo = db();
        $pdo->exec('CREATE TABLE shortlinks (id INTEGER PRIMARY KEY AUTOINCREMENT, short_id TEXT, target_url TEXT, queue_id INTEGER, platform TEXT)');
        $pdo->exec('CREATE TABLE metrics_clicks (id INTEGER PRIMARY KEY AUTOINCREMENT, queue_id INTEGER, platform TEXT, short_id TEXT, ip_hash TEXT, user_agent TEXT)');
        $pdo->prepare('INSERT INTO shortlinks (short_id, target_url, queue_id, platform) VALUES (?,?,?,?)')
            ->execute(['abc', 'https://example.com', 1, 'fb']);

        $_GET['id'] = 'abc';
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0';
        $_SERVER['REMOTE_ADDR'] = '1.2.3.4';

        ob_start();
        require __DIR__ . '/../public/go.php';
        ob_end_clean();

        $this->assertSame(302, http_response_code());
        $count = (int)$pdo->query('SELECT COUNT(*) FROM metrics_clicks')->fetchColumn();
        $this->assertSame(1, $count);
        unlink($envFile);
    }
}
