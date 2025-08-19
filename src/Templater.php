<?php

declare(strict_types=1);

namespace App;

class Templater
{
    /**
     * In-memory templates with variants: default, A, B.
     * Example structure:
     * [
     *     'welcome' => [
     *         'default' => 'Hi {{name}}',
     *         'A' => 'Hi {{name}}!',
     *         'B' => 'Hello {{name}}',
     *     ],
     * ]
     */
    private static array $templates = [
        // templates defined here
    ];

    public static function render(string $templateKey, array $data, ?string $variant = null): string
    {
        $variant = self::chooseVariant($data, $variant);
        $templates = self::$templates[$templateKey] ?? [];
        $template = $templates[$variant] ?? $templates['default'] ?? '';
        return self::applyData($template, $data);
    }

    private static function chooseVariant(array $data, ?string $variant): string
    {
        if ($variant !== null) {
            return $variant;
        }
        $jobVariant = $data['job']['meta']['ab_variant'] ?? null;
        if (is_string($jobVariant) && $jobVariant !== '') {
            return $jobVariant;
        }
        $platform = $data['job']['platform'] ?? 'default';
        return self::roundRobinVariant((string)$platform);
    }

    private static function roundRobinVariant(string $platform): string
    {
        $storageDir = dirname(__DIR__) . '/storage';
        if (!is_dir($storageDir)) {
            return 'A';
        }
        $file = $storageDir . '/ab_variant_' . preg_replace('/[^a-z0-9_-]/i', '_', $platform) . '.txt';
        $fp = @fopen($file, 'c+');
        if ($fp === false) {
            return 'A';
        }
        $variant = 'A';
        if (flock($fp, LOCK_EX)) {
            $count = (int)stream_get_contents($fp);
            $variant = ($count % 2 === 0) ? 'A' : 'B';
            $count++;
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, (string)$count);
            fflush($fp);
            flock($fp, LOCK_UN);
        }
        fclose($fp);
        return $variant;
    }

    private static function applyData(string $template, array $data): string
    {
        $replacements = [];
        foreach ($data as $key => $value) {
            if (is_scalar($value) || (is_object($value) && method_exists($value, '__toString'))) {
                $replacements['{{' . $key . '}}'] = (string) $value;
            }
        }
        return strtr($template, $replacements);
    }
}
