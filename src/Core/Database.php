<?php

namespace KelvinKurniawan\LightORM\Core;

use KelvinKurniawan\LightORM\Database\DatabaseManager;
use KelvinKurniawan\LightORM\Contracts\ConnectionInterface;
use KelvinKurniawan\LightORM\Contracts\GrammarInterface;
use PDO;

/**
 * Legacy Database class for backward compatibility
 * 
 * @deprecated Use DatabaseManager instead
 */
class Database {
    private static ?DatabaseManager $manager = NULL;

    /**
     * Set database configuration (legacy method)
     * 
     * @param array $config Database configuration array
     * @return void
     */
    public static function setConfig(array $config): void {
        self::getManager()->addConfiguration('default', $config);
    }

    /**
     * Get database connection (legacy method)
     * 
     * @return PDO
     * @throws \Exception
     */
    public static function getConnection(): PDO {
        return self::getManager()->connection()->getPdo();
    }

    /**
     * Get current configuration (legacy method)
     * 
     * @return array|null
     */
    public static function getConfig(): ?array {
        try {
            return self::getManager()->connection()->getConfig();
        } catch (\Exception $e) {
            return NULL;
        }
    }

    /**
     * Get the database manager instance
     */
    public static function getManager(): DatabaseManager {
        if(self::$manager === NULL) {
            self::$manager = new DatabaseManager();
        }

        return self::$manager;
    }

    /**
     * Set a custom database manager
     */
    public static function setManager(DatabaseManager $manager): void {
        self::$manager = $manager;
    }

    /**
     * Get a connection interface
     */
    public static function connection(string $name = NULL): ConnectionInterface {
        return self::getManager()->connection($name);
    }

    /**
     * Get a grammar instance
     */
    public static function getGrammar(string $driver = NULL): GrammarInterface {
        return self::getManager()->getGrammar($driver);
    }

    /**
     * Execute a transaction
     */
    public static function transaction(callable $callback, string $connection = NULL): mixed {
        return self::getManager()->transaction($callback, $connection);
    }

    /**
     * Load configuration from environment
     */
    public static function loadFromEnv(string $envFile = '.env'): void {
        self::getManager()->loadFromEnv($envFile);
    }
}
