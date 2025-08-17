<?php
require 'vendor/autoload.php';
require 'src/Helpers/Db.php';

use App\Helpers\Db;

try {
    $pdo = Db::instance();
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_ASSOC);
    echo "<pre>";
    print_r($tables);
    echo "</pre>";
} catch (Exception $e) {
    echo "DB Error: " . $e->getMessage();
}
