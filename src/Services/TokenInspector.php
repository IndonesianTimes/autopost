<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Db;
use DateTimeImmutable;
use DateTimeZone;
use Exception;

class TokenInspector
{
    /**
     * Inspect platform account tokens and return expiry info per platform.
     *
     * @return array<string, array{account: string, expires_at: ?string, expires_in_h: ?int}>
     */
    public static function inspect(): array
    {
        $pdo = Db::pdo();
        $stmt = $pdo->query('SELECT platform, name, meta_json FROM platform_accounts WHERE is_active = 1');

        $tokens = [];
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        while ($row = $stmt->fetch()) {
            $meta = [];
            if (!empty($row['meta_json'])) {
                $decoded = json_decode((string) $row['meta_json'], true);
                if (is_array($decoded)) {
                    $meta = $decoded;
                }
            }

            $expiresAt = $meta['expires_at'] ?? null;
            $expiresInH = null;
            if (is_string($expiresAt) && $expiresAt !== '') {
                try {
                    $expires = new DateTimeImmutable($expiresAt);
                    $expiresInH = (int) floor(($expires->getTimestamp() - $now->getTimestamp()) / 3600);
                } catch (Exception $e) {
                    $expiresAt = null;
                }
            }

            $tokens[$row['platform']] = [
                'account' => (string) $row['name'],
                'expires_at' => $expiresAt,
                'expires_in_h' => $expiresInH,
            ];
        }

        return $tokens;
    }
}
