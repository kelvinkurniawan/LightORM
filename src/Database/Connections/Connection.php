<?php

namespace KelvinKurniawan\LightORM\Database\Connections;

use KelvinKurniawan\LightORM\Contracts\ConnectionInterface;
use KelvinKurniawan\LightORM\Performance\QueryProfiler;
use PDO;
use PDOException;
use Exception;

abstract class Connection implements ConnectionInterface {
    protected ?PDO           $pdo              = NULL;
    protected array          $config;
    protected int            $transactionLevel = 0;
    protected ?QueryProfiler $profiler         = NULL;

    public function __construct(array $config) {
        $this->config = $config;
        $this->connect();
    }

    /**
     * Establish database connection
     */
    abstract protected function connect(): void;

    /**
     * Get connection DSN
     */
    abstract protected function getDsn(): string;

    public function getPdo(): PDO {
        if($this->pdo === NULL) {
            throw new Exception("Database connection not established");
        }
        return $this->pdo;
    }

    public function query(string $sql, array $bindings = []): mixed {
        $queryId = NULL;

        // Start profiling if profiler is available
        if($this->profiler && $this->profiler->isEnabled()) {
            $queryId = $this->profiler->startQuery($sql, $bindings);
        }

        try {
            $statement = $this->pdo->prepare($sql);

            if($statement === FALSE) {
                throw new Exception("Failed to prepare statement: " . implode(', ', $this->pdo->errorInfo()));
            }

            $success = $statement->execute($bindings);

            if(!$success) {
                throw new Exception("Failed to execute statement: " . implode(', ', $statement->errorInfo()));
            }

            // End profiling on success
            if($queryId && $this->profiler) {
                $this->profiler->endQuery($queryId, $statement->rowCount());
            }

            return $statement;
        } catch (PDOException $e) {
            // End profiling on error
            if($queryId && $this->profiler) {
                $this->profiler->endQuery($queryId);
            }

            throw new Exception("Database query failed: " . $e->getMessage(), 0, $e);
        }
    }

    public function beginTransaction(): bool {
        if($this->transactionLevel === 0) {
            $result = $this->pdo->beginTransaction();
        } else {
            // Create savepoint for nested transactions
            $savepointName = "sp_level_{$this->transactionLevel}";
            $result        = $this->pdo->exec("SAVEPOINT {$savepointName}") !== FALSE;
        }

        if($result) {
            $this->transactionLevel++;
        }

        return $result;
    }

    public function commit(): bool {
        if($this->transactionLevel === 0) {
            return FALSE;
        }

        $this->transactionLevel--;

        if($this->transactionLevel === 0) {
            return $this->pdo->commit();
        } else {
            // Release savepoint
            $savepointName = "sp_level_{$this->transactionLevel}";
            return $this->pdo->exec("RELEASE SAVEPOINT {$savepointName}") !== FALSE;
        }
    }

    public function rollback(): bool {
        if($this->transactionLevel === 0) {
            return FALSE;
        }

        $this->transactionLevel--;

        if($this->transactionLevel === 0) {
            return $this->pdo->rollback();
        } else {
            // Rollback to savepoint
            $savepointName = "sp_level_{$this->transactionLevel}";
            return $this->pdo->exec("ROLLBACK TO SAVEPOINT {$savepointName}") !== FALSE;
        }
    }

    public function getConfig(): array {
        return $this->config;
    }

    public function disconnect(): void {
        $this->pdo = NULL;
    }

    /**
     * Get the transaction level
     */
    public function getTransactionLevel(): int {
        return $this->transactionLevel;
    }

    /**
     * Check if we're in a transaction
     */
    public function inTransaction(): bool {
        return $this->transactionLevel > 0;
    }

    /**
     * Execute a callback within a transaction
     */
    public function transaction(callable $callback): mixed {
        $this->beginTransaction();

        try {
            $result = $callback($this);
            $this->commit();
            return $result;
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
    }

    /**
     * Get default PDO options
     */
    protected function getDefaultOptions(): array {
        return [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => FALSE,
        ];
    }

    /**
     * Merge user options with defaults
     */
    protected function getOptions(): array {
        $options = $this->getDefaultOptions();

        if(isset($this->config['options']) && is_array($this->config['options'])) {
            $options = array_merge($options, $this->config['options']);
        }

        return $options;
    }

    /**
     * Set query profiler
     */
    public function setProfiler(?QueryProfiler $profiler): void {
        $this->profiler = $profiler;
    }

    /**
     * Get query profiler
     */
    public function getProfiler(): ?QueryProfiler {
        return $this->profiler;
    }
}
