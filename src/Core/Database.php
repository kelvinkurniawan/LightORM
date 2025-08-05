<?php

namespace App\Core;

use PDO;

class Database {
    private static $pdo;

    public static function getConnection(): PDO {
        if(!self::$pdo) {
            $config    = require __DIR__ . '/../../config/config.php';
            $dsn       = "mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8mb4";
            self::$pdo = new PDO($dsn, $config['username'], $config['password']);
            self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }

        return self::$pdo;
    }
}
