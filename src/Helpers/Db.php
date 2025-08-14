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
            $dsn = Config::require('DB_DSN');
            $user = Config::require('DB_USER');
            $pass = Config::get('DB_PASS', '');
            self::$pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        }
        return self::$pdo;
    }
}
