<?php

namespace KelvinKurniawan\LightORM\Contracts;

use KelvinKurniawan\LightORM\Performance\QueryProfiler;
use PDO;

interface ConnectionInterface {
    /**
     * Get the PDO connection instance
     */
    public function getPdo(): PDO;

    /**
     * Execute a SQL query
     */
    public function query(string $sql, array $bindings = []): mixed;

    /**
     * Begin a database transaction
     */
    public function beginTransaction(): bool;

    /**
     * Commit a database transaction
     */
    public function commit(): bool;

    /**
     * Rollback a database transaction
     */
    public function rollback(): bool;

    /**
     * Get the connection configuration
     */
    public function getConfig(): array;

    /**
     * Disconnect from the database
     */
    public function disconnect(): void;

    /**
     * Execute a callback within a transaction
     */
    public function transaction(callable $callback): mixed;

    /**
     * Set query profiler (optional, for performance monitoring)
     */
    public function setProfiler(?QueryProfiler $profiler): void;
}
