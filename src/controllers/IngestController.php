<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Queue;
use RuntimeException;

class IngestController
{
    public static function ingest(): void
    {
        requireBearer($_ENV['AUTOMATION_TOKEN'] ?? '');

        $input = jsonInput();

        $title = $input['title'] ?? null;
        if (!is_string($title) || $title === '') {
            json(400, 'Invalid title');
            return;
        }

        $summary = $input['summary'] ?? null;
        if (!is_string($summary) || $summary === '') {
            json(400, 'Invalid summary');
            return;
        }

        $link = $input['link'] ?? '';
        if (!is_string($link) || !filter_var($link, FILTER_VALIDATE_URL)) {
            json(400, 'Invalid link');
            return;
        }

        $channels = $input['channels'] ?? null;
        if (!is_array($channels) || $channels === []) {
            json(400, 'Invalid channels');
            return;
        }
        $allowedChannels = ['fb', 'ig', 'twitter', 'telegram'];
        foreach ($channels as $ch) {
            if (!in_array($ch, $allowedChannels, true)) {
                json(400, 'Invalid channels');
                return;
            }
        }

        $publishRaw = $input['publish_at'] ?? null;
        if ($publishRaw === null) {
            json(400, 'Invalid publish_at');
            return;
        }
        if (is_numeric($publishRaw)) {
            $publishAt = gmdate('Y-m-d H:i:s', (int)$publishRaw);
        } else {
            $ts = strtotime((string)$publishRaw);
            if ($ts === false) {
                json(400, 'Invalid publish_at');
                return;
            }
            $publishAt = gmdate('Y-m-d H:i:s', $ts);
        }

        $imageUrl = null;
        if (isset($input['image_url']) && $input['image_url'] !== '') {
            if (!is_string($input['image_url']) || !filter_var($input['image_url'], FILTER_VALIDATE_URL)) {
                json(400, 'Invalid image_url');
                return;
            }
            $imageUrl = $input['image_url'];
        }

        $payload = null;
        if (isset($input['data'])) {
            if (!is_array($input['data'])) {
                json(400, 'Invalid data');
                return;
            }
            $payload = $input['data'];
        }

        $priority = 0;
        if (isset($input['priority'])) {
            if (!is_int($input['priority']) && !ctype_digit((string)$input['priority'])) {
                json(400, 'Invalid priority');
                return;
            }
            $priority = (int)$input['priority'];
        }

        $utm = [
            'campaign' => 'ai_rtp_' . gmdate('Ymd'),
            'content' => ($payload['game'] ?? '') . '_' . ($payload['jam'] ?? ''),
        ];

        try {
            $id = Queue::insert([
                'title' => $title,
                'summary' => $summary,
                'link_url' => $link,
                'image_url' => $imageUrl,
                'utm_json' => $utm,
                'payload_json' => $payload,
                'channels' => $channels,
                'publish_at' => $publishAt,
                'priority' => $priority,
            ]);
        } catch (RuntimeException $e) {
            json(400, $e->getMessage());
            return;
        }

        json(200, ['id' => $id]);
    }
}
