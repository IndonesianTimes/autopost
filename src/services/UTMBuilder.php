<?php

declare(strict_types=1);

namespace App\Services;

class UTMBuilder
{
    private const ALLOWED_PLATFORMS = ['fb', 'ig', 'twitter', 'telegram'];

    /**
     * Build URL with UTM parameters.
     */
    public static function build(string $baseUrl, string $platform, array $meta = []): string
    {
        if (!in_array($platform, self::ALLOWED_PLATFORMS, true)) {
            throw new \RuntimeException('Invalid platform');
        }

        $parts = parse_url($baseUrl);
        if ($parts === false || !isset($parts['scheme'], $parts['host'])) {
            throw new \RuntimeException('Invalid base URL');
        }

        $query = [];
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $query);
        }

        $query['utm_source'] = $platform;
        $query['utm_medium'] = 'social_auto';
        $query['utm_campaign'] = $meta['campaign'] ?? 'ai_rtp_' . gmdate('Ymd');
        if (!empty($meta['content'])) {
            $query['utm_content'] = $meta['content'];
        } else {
            unset($query['utm_content']);
        }

        $parts['query'] = http_build_query($query, '', '&', PHP_QUERY_RFC3986);

        return self::unparseUrl($parts);
    }

    private static function unparseUrl(array $parts): string
    {
        $scheme   = isset($parts['scheme']) ? $parts['scheme'] . '://' : '';
        $host     = $parts['host'] ?? '';
        $port     = isset($parts['port']) ? ':' . $parts['port'] : '';
        $user     = $parts['user'] ?? '';
        $pass     = isset($parts['pass']) ? ':' . $parts['pass'] : '';
        $pass     = ($user !== '' || $pass !== '') ? "$pass@" : '';
        $path     = $parts['path'] ?? '';
        $query    = isset($parts['query']) && $parts['query'] !== '' ? '?' . $parts['query'] : '';
        $fragment = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';

        return "$scheme$user$pass$host$port$path$query$fragment";
    }
}
