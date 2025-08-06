<?php

namespace KelvinKurniawan\LightORM\Database\Connections;

use PDO;
use Exception;

class PostgreSqlConnection extends Connection {
    protected function connect(): void {
        $dsn = $this->getDsn();

        try {
            $this->pdo = new PDO(
                $dsn,
                $this->config['username'] ?? '',
                $this->config['password'] ?? '',
                $this->getOptions()
            );

            // Set schema if specified
            if(isset($this->config['schema'])) {
                $this->pdo->exec("SET search_path TO {$this->config['schema']}");
            }

            // Set timezone if specified
            if(isset($this->config['timezone'])) {
                $this->pdo->exec("SET timezone = '{$this->config['timezone']}'");
            }

        } catch (\PDOException $e) {
            throw new Exception("PostgreSQL connection failed: " . $e->getMessage(), 0, $e);
        }
    }

    protected function getDsn(): string {
        $dsn = "pgsql:";

        if(isset($this->config['host'])) {
            $dsn .= "host={$this->config['host']};";
        }

        if(isset($this->config['port'])) {
            $dsn .= "port={$this->config['port']};";
        }

        if(isset($this->config['dbname'])) {
            $dsn .= "dbname={$this->config['dbname']};";
        }

        return rtrim($dsn, ';');
    }

    /**
     * Override rollback for PostgreSQL savepoint syntax
     */
    public function rollback(): bool {
        if($this->transactionLevel === 0) {
            return FALSE;
        }

        $this->transactionLevel--;

        if($this->transactionLevel === 0) {
            return $this->pdo->rollback();
        } else {
            // PostgreSQL uses different savepoint syntax
            $savepointName = "sp_level_{$this->transactionLevel}";
            return $this->pdo->exec("ROLLBACK TO {$savepointName}") !== FALSE;
        }
    }
}
