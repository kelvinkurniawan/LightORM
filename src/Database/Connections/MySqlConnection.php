<?php

namespace KelvinKurniawan\LightORM\Database\Connections;

use PDO;
use Exception;

class MySqlConnection extends Connection {
    protected function connect(): void {
        $dsn = $this->getDsn();

        try {
            $this->pdo = new PDO(
                $dsn,
                $this->config['username'] ?? '',
                $this->config['password'] ?? '',
                $this->getOptions()
            );

            // Set charset if specified
            if(isset($this->config['charset'])) {
                $this->pdo->exec("SET NAMES {$this->config['charset']}");
            }

            // Set timezone if specified
            if(isset($this->config['timezone'])) {
                $this->pdo->exec("SET time_zone = '{$this->config['timezone']}'");
            }

            // Set SQL mode if specified
            if(isset($this->config['strict']) && $this->config['strict']) {
                $this->pdo->exec("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO'");
            }

        } catch (\PDOException $e) {
            throw new Exception("MySQL connection failed: " . $e->getMessage(), 0, $e);
        }
    }

    protected function getDsn(): string {
        $dsn = "mysql:";

        if(isset($this->config['host'])) {
            $dsn .= "host={$this->config['host']};";
        }

        if(isset($this->config['port'])) {
            $dsn .= "port={$this->config['port']};";
        }

        if(isset($this->config['dbname'])) {
            $dsn .= "dbname={$this->config['dbname']};";
        }

        if(isset($this->config['charset'])) {
            $dsn .= "charset={$this->config['charset']};";
        } else {
            $dsn .= "charset=utf8mb4;";
        }

        return rtrim($dsn, ';');
    }

    /**
     * Get MySQL specific default options
     */
    protected function getDefaultOptions(): array {
        return array_merge(parent::getDefaultOptions(), [
            PDO::MYSQL_ATTR_INIT_COMMAND       => "SET NAMES utf8mb4",
            PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => TRUE,
        ]);
    }
}
