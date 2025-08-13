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
        return $_ENV[$key] ?? $default;
    }
}
