<?php

declare(strict_types=1);

namespace App\Services;

class Templater
{
    public static function render(array $data, string $platform): string
    {
        $payload = $data['payload_json'] ?? [];

        $hook = $payload['hook'] ?? ($data['title'] ?? $data['summary'] ?? '');
        $game = $payload['game'] ?? ($data['title'] ?? $data['summary'] ?? '');
        $rtp = $payload['rtp'] ?? ($data['summary'] ?? $data['title'] ?? '');
        $window = $payload['window'] ?? ($data['summary'] ?? $data['title'] ?? '');
        $pattern = $payload['pattern'] ?? ($data['summary'] ?? $data['title'] ?? '');
        $jam = $payload['jam'] ?? ($data['summary'] ?? $data['title'] ?? '');
        $hashtagGame = $payload['hashtag_game'] ?? ($data['summary'] ?? $data['title'] ?? '');

        $trackedLink = UTMBuilder::build($data['link_url'], $platform, $data['utm_json'] ?? []);

        switch ($platform) {
            case 'fb':
            case 'ig':
                $caption = sprintf(
                    "%s %s RTP %s%% %s.\nPola: %s. Jam: %s.\nCek analisa ➜ %s\n#AIRTPSlot #PrimesAI #%s",
                    $hook,
                    $game,
                    $rtp,
                    $window,
                    $pattern,
                    $jam,
                    $trackedLink,
                    $hashtagGame
                );
                $limit = 300;
                break;
            case 'twitter':
                $caption = sprintf(
                    "%s %s RTP %s%% %s.\nPola: %s. Jam: %s.\nCek analisa ➜ %s\n#AIRTPSlot #PrimesAI",
                    $hook,
                    $game,
                    $rtp,
                    $window,
                    $pattern,
                    $jam,
                    $trackedLink
                );
                $limit = 240;
                break;
            case 'telegram':
                $line1 = sprintf(
                    "%s %s RTP %s%% %s. Pola: %s. Jam: %s.",
                    $hook,
                    $game,
                    $rtp,
                    $window,
                    $pattern,
                    $jam
                );
                $caption = $line1 . "\n" . $trackedLink;
                $limit = 300;
                break;
            default:
                throw new \RuntimeException('Invalid platform');
        }

        return self::truncate(trim($caption), $limit);
    }

    private static function truncate(string $s, int $limit): string
    {
        if (mb_strlen($s) <= $limit) {
            return $s;
        }

        return mb_substr($s, 0, max(0, $limit - 1)) . '…';
    }
}
