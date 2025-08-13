<?php

declare(strict_types=1);

namespace App\Helpers;

use PDO;

class Db
{
    private static ?PDO $pdo = null;

    public static function instance(): PDO
    {
        if (self::$pdo === null) {
            $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4',
                Config::get('DB_HOST', 'localhost'),
                Config::get('DB_NAME', '')
            );
            self::$pdo = new PDO($dsn, Config::get('DB_USER', ''), Config::get('DB_PASS', ''), [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        }
        return self::$pdo;
    }
}
