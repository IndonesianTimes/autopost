<?php

declare(strict_types=1);

namespace App\Controllers;

class IngestController
{
    public static function ingest(): void
    {
        requireBearer($_ENV['API_TOKEN'] ?? '');

        $input = jsonInput();
        $platform = $input['platform'] ?? null;
        $caption = $input['caption'] ?? null;
        $imageUrl = $input['image_url'] ?? null;

        if (!is_string($platform) || $platform === '') {
            json(400, 'Invalid platform');
            return;
        }

        if (!is_string($caption) || $caption === '') {
            json(400, 'Invalid caption');
            return;
        }

        if ($imageUrl !== null && $imageUrl !== '') {
            if (!is_string($imageUrl) || !filter_var($imageUrl, FILTER_VALIDATE_URL)) {
                json(400, 'Invalid image_url');
                return;
            }
        } else {
            $imageUrl = null;
        }

        json(200, [
            'platform' => $platform,
            'caption' => $caption,
            'image_url' => $imageUrl,
            'received_at' => nowUTC(),
        ]);
    }
}
