<?php

declare(strict_types=1);

namespace App\Services {
    function curl_init(string $url) { $GLOBALS['telegram_url'] = $url; return fopen('php://memory', 'r'); }
    function curl_setopt_array($ch, array $opts): void { $GLOBALS['telegram_opts'] = $opts; }
    function curl_exec($ch) { return true; }
    function curl_close($ch): void {}
}

namespace Tests {

use PHPUnit\Framework\TestCase;
use DateTimeImmutable;

final class HealthEndpointTest extends TestCase
{
    public function testHealthAlertsOnExpiringToken(): void
    {
        $_ENV['DB_DSN'] = 'sqlite::memory:';
        $_ENV['DB_USER'] = '';
       $_ENV['DB_PASS'] = '';
        $_ENV['SAE_TELEGRAM_ALERT_BOT_TOKEN'] = 't';
        $_ENV['SAE_TELEGRAM_ALERT_CHAT_ID'] = 'c';
        $envFile = dirname(__DIR__) . '/.env';
        file_put_contents($envFile, '');
        require_once __DIR__ . '/../src/bootstrap.php';
        $pdo = \App\Helpers\Db::pdo();
        $pdo->exec('CREATE TABLE platform_accounts (id INTEGER PRIMARY KEY AUTOINCREMENT, platform TEXT, name TEXT, meta_json TEXT, is_active INTEGER)');
        $expires = (new DateTimeImmutable('+1 hour'))->format(DATE_ATOM);
        $meta = json_encode(['expires_at' => $expires]);
        $pdo->prepare('INSERT INTO platform_accounts (platform, name, meta_json, is_active) VALUES (?,?,?,1)')
            ->execute(['fb', 'acct', $meta]);

        ob_start();
        require __DIR__ . '/../public/health.php';
        ob_end_clean();

        $this->assertSame(503, http_response_code());
        $this->assertSame('https://api.telegram.org/bott/sendMessage', $GLOBALS['telegram_url'] ?? null);
        http_response_code(200);
        unlink($envFile);
    }
}
}
