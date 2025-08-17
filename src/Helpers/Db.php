<?php
declare(strict_types=1);

namespace App\Helpers;

use PDO;
use PDOException;

class Db
{
    private static ?PDO $pdo = null;

    public static function instance(): PDO
    {
        if (self::$pdo === null) {
            $driver  = Config::get('DB_CONNECTION', 'mysql');
            $host    = Config::get('DB_HOST', '127.0.0.1');
            $port    = Config::get('DB_PORT', '3306');
            $dbname  = Config::get('DB_DATABASE', 'social_autopost_db');
            $user    = Config::get('DB_USERNAME', 'root');
            $pass    = Config::get('DB_PASSWORD', '');

            // DSN sesuai dengan .env
            $dsn = "{$driver}:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";

            try {
                self::$pdo = new PDO($dsn, $user, $pass, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);
            } catch (PDOException $e) {
                die("DB Connection failed: " . $e->getMessage());
            }
        }

        return self::$pdo;
    }
}
