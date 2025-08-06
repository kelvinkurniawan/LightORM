<?php

namespace KelvinKurniawan\LightORM\Migration;

use KelvinKurniawan\LightORM\Contracts\ConnectionInterface;

class Schema {
    private static ConnectionInterface $connection;

    public static function setConnection(ConnectionInterface $connection): void {
        self::$connection = $connection;
    }

    /**
     * Create a new table
     */
    public static function create(string $table, callable $callback): void {
        $blueprint = new Blueprint($table, self::$connection);
        $callback($blueprint);
        $blueprint->create();
    }

    /**
     * Modify an existing table
     */
    public static function table(string $table, callable $callback): void {
        $blueprint = new Blueprint($table, self::$connection);
        $callback($blueprint);
        $blueprint->alter();
    }

    /**
     * Drop a table if it exists
     */
    public static function dropIfExists(string $table): void {
        $driver = self::$connection->getPdo()->getAttribute(\PDO::ATTR_DRIVER_NAME);

        if($driver === 'sqlite') {
            $sql = "DROP TABLE IF EXISTS {$table}";
        } else {
            $sql = "DROP TABLE IF EXISTS `{$table}`";
        }

        self::$connection->query($sql);
    }

    /**
     * Drop a table
     */
    public static function drop(string $table): void {
        $driver = self::$connection->getPdo()->getAttribute(\PDO::ATTR_DRIVER_NAME);

        if($driver === 'sqlite') {
            $sql = "DROP TABLE {$table}";
        } else {
            $sql = "DROP TABLE `{$table}`";
        }

        self::$connection->query($sql);
    }

    /**
     * Check if table exists
     */
    public static function hasTable(string $table): bool {
        $driver = self::$connection->getPdo()->getAttribute(\PDO::ATTR_DRIVER_NAME);

        switch($driver) {
            case 'sqlite':
                $sql = "SELECT name FROM sqlite_master WHERE type='table' AND name=?";
                break;
            case 'mysql':
                $sql = "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?";
                break;
            case 'pgsql':
                $sql = "SELECT tablename FROM pg_tables WHERE tablename = ?";
                break;
            default:
                throw new \Exception("Unsupported database driver: {$driver}");
        }

        $result = self::$connection->query($sql, [$table]);
        return $result->fetch(\PDO::FETCH_ASSOC) !== FALSE;
    }

    /**
     * Check if column exists
     */
    public static function hasColumn(string $table, string $column): bool {
        $driver = self::$connection->getPdo()->getAttribute(\PDO::ATTR_DRIVER_NAME);

        switch($driver) {
            case 'sqlite':
                $sql = "PRAGMA table_info({$table})";
                $result = self::$connection->query($sql);
                $columns = $result->fetchAll(\PDO::FETCH_ASSOC);
                foreach($columns as $col) {
                    if($col['name'] === $column) {
                        return TRUE;
                    }
                }
                return FALSE;

            case 'mysql':
                $sql = "SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?";
                break;

            case 'pgsql':
                $sql = "SELECT column_name FROM information_schema.columns WHERE table_name = ? AND column_name = ?";
                break;

            default:
                throw new \Exception("Unsupported database driver: {$driver}");
        }

        $result = self::$connection->query($sql, [$table, $column]);
        return $result->fetch(\PDO::FETCH_ASSOC) !== FALSE;
    }
}
