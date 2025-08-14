<?php

declare(strict_types=1);

namespace App\Helpers;

use InvalidArgumentException;

class Sanitizer
{
    public static function sanitizeCaption(string $caption, int $limit, array $preserve = ['link_url']): string
    {
        $caption = str_replace(["\r\n", "\r"], "\n", $caption);
        $caption = preg_replace('/[ \t]+/', ' ', $caption);
        $caption = preg_replace('/[ \t]*\n[ \t]*/', "\n", $caption);
        $caption = preg_replace('/\n{2,}/', "\n", $caption);
        $caption = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $caption);
        $caption = trim($caption);

        $pattern = '/\{\{(' . implode('|', array_map(fn($t) => preg_quote($t, '/'), $preserve)) . ')\}\}/';
        $placeholders = [];
        if ($pattern !== '/\{\{()\}\}/' && preg_match_all($pattern, $caption, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as $match) {
                $placeholders[] = ['text' => $match[0], 'offset' => $match[1]];
                $limit -= mb_strlen($match[0], 'UTF-8');
            }
            usort($placeholders, fn($a, $b) => $b['offset'] <=> $a['offset']);
            foreach ($placeholders as $ph) {
                $caption = mb_substr($caption, 0, $ph['offset'], 'UTF-8')
                    . mb_substr($caption, $ph['offset'] + mb_strlen($ph['text'], 'UTF-8'), null, 'UTF-8');
            }
            $placeholders = array_reverse($placeholders);
            if ($limit < 0) {
                $limit = 0;
            }
        }

        if (mb_strlen($caption, 'UTF-8') > $limit) {
            $caption = mb_substr($caption, 0, $limit, 'UTF-8');
        }

        $result = '';
        $cursor = 0;
        foreach ($placeholders as $ph) {
            $result .= mb_substr($caption, $cursor, $ph['offset'] - $cursor, 'UTF-8');
            $result .= $ph['text'];
            $cursor = $ph['offset'];
        }
        $result .= mb_substr($caption, $cursor, null, 'UTF-8');

        return $result;
    }

    public static function validateImageUrl(string $url, bool $checkRemote = false): string
    {
        $url = trim($url);
        if ($url === '') {
            throw new InvalidArgumentException('Empty image URL');
        }
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException('Invalid image URL');
        }
        $scheme = parse_url($url, PHP_URL_SCHEME);
        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new InvalidArgumentException('Invalid URL scheme');
        }
        $path = (string) parse_url($url, PHP_URL_PATH);
        if (!preg_match('/\.(jpe?g|png|gif|webp)$/i', $path)) {
            throw new InvalidArgumentException('Invalid image extension');
        }
        if ($checkRemote && function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_NOBODY => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_FOLLOWLOCATION => true,
            ]);
            curl_exec($ch);
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($status >= 400 || $status === 0) {
                throw new InvalidArgumentException('Image URL not accessible');
            }
        }
        return $url;
    }
}
