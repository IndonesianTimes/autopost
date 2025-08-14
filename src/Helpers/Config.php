<?php

declare(strict_types=1);

namespace App\Helpers;

use Dotenv\Dotenv;

class Config
{
    public static function load(string $basePath): void
    {
        if (file_exists($basePath . '/.env')) {
            $dotenv = Dotenv::createImmutable($basePath);
            $dotenv->load();
        }
    }

    public static function get(string $key, $default = null)
    {
        return $_ENV[$key] ?? $_SERVER[$key] ?? $default;
    }

    public static function require(string $key): string
    {
        $value = trim((string)(self::get($key, '')));
        if ($value === '') {
            throw new \RuntimeException("Missing required env: {$key}");
        }
        return $value;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = self::get($key, $default ? 'true' : 'false');
        if (is_bool($value)) {
            return $value;
        }
        return filter_var((string)$value, FILTER_VALIDATE_BOOLEAN);
    }
}
