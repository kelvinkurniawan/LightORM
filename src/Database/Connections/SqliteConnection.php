<?php

namespace KelvinKurniawan\LightORM\Database\Connections;

use PDO;
use Exception;

class SqliteConnection extends Connection {
    protected function connect(): void {
        $dsn = $this->getDsn();

        try {
            $this->pdo = new PDO(
                $dsn,
                NULL, // SQLite doesn't use username
                NULL, // SQLite doesn't use password
                $this->getOptions()
            );

            // Enable foreign key constraints
            $this->pdo->exec('PRAGMA foreign_keys = ON');

            // Set journal mode if specified
            if(isset($this->config['journal_mode'])) {
                $journalMode = strtoupper($this->config['journal_mode']);
                $this->pdo->exec("PRAGMA journal_mode = {$journalMode}");
            }

            // Set synchronous mode if specified
            if(isset($this->config['synchronous'])) {
                $synchronous = strtoupper($this->config['synchronous']);
                $this->pdo->exec("PRAGMA synchronous = {$synchronous}");
            }

        } catch (\PDOException $e) {
            throw new Exception("SQLite connection failed: " . $e->getMessage(), 0, $e);
        }
    }

    protected function getDsn(): string {
        if(isset($this->config['database'])) {
            // File-based database
            $database = $this->config['database'];

            // Handle special case for in-memory database
            if($database === ':memory:') {
                return 'sqlite::memory:';
            }

            // Ensure directory exists for file-based database
            $directory = dirname($database);
            if(!is_dir($directory)) {
                mkdir($directory, 0755, TRUE);
            }

            return "sqlite:{$database}";
        }

        // Default to in-memory database
        return 'sqlite::memory:';
    }

    /**
     * SQLite doesn't support savepoints in the same way, so we override transaction methods
     */
    public function beginTransaction(): bool {
        if($this->transactionLevel === 0) {
            $result = $this->pdo->beginTransaction();
        } else {
            // SQLite savepoints
            $savepointName = "sp{$this->transactionLevel}";
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
            $savepointName = "sp{$this->transactionLevel}";
            return $this->pdo->exec("RELEASE {$savepointName}") !== FALSE;
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
            $savepointName = "sp{$this->transactionLevel}";
            return $this->pdo->exec("ROLLBACK TO {$savepointName}") !== FALSE;
        }
    }
}
