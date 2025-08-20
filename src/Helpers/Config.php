<?php
declare(strict_types=1);

namespace App\Helpers;

use Dotenv\Dotenv;

class Config
{
    private static bool $loaded = false;

    public static function load(string $basePath): void
    {
        if (self::$loaded) {
            return;
        }
        $envFile = rtrim($basePath, '/') . '/.env';
        if (is_file($envFile)) {
            $dotenv = Dotenv::createImmutable($basePath);
            $dotenv->load();
        }
        self::$loaded = true;
    }

    private static function ensureLoaded(): void
    {
        if (!self::$loaded) {
            self::load(dirname(__DIR__, 2));
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        self::ensureLoaded();
        return $_ENV[$key] ?? $_SERVER[$key] ?? $default;
    }

    public static function require(string $key): string
    {
        $value = (string) self::get($key, '');
        if ($value === '') {
            throw new \RuntimeException("Missing required env: {$key}");
        }
        return $value;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = self::get($key, $default ? 'true' : 'false');
        if (is_bool($value)) return $value;
        return filter_var((string) $value, FILTER_VALIDATE_BOOLEAN);
    }
}
