<?php

namespace KelvinKurniawan\LightORM\Core;

use PDO;

class Database {
    private static $pdo;
    private static $config;

    /**
     * Set database configuration
     * 
     * @param array $config Database configuration array
     * @return void
     */
    public static function setConfig(array $config): void {
        self::$config = $config;
        self::$pdo    = NULL; // Reset connection
    }

    /**
     * Get database connection
     * 
     * @return PDO
     * @throws \Exception
     */
    public static function getConnection(): PDO {
        if(!self::$pdo) {
            if(!self::$config) {
                throw new \Exception('Database configuration not set. Use Database::setConfig() first.');
            }

            $config    = self::$config;
            $dsn       = "mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8mb4";
            self::$pdo = new PDO($dsn, $config['username'], $config['password']);
            self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }

        return self::$pdo;
    }

    /**
     * Get current configuration
     * 
     * @return array|null
     */
    public static function getConfig(): ?array {
        return self::$config;
    }
}
