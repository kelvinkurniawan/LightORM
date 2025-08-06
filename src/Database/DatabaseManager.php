<?php

namespace KelvinKurniawan\LightORM\Database;

use KelvinKurniawan\LightORM\Contracts\ConnectionInterface;
use KelvinKurniawan\LightORM\Database\Connections\MySqlConnection;
use KelvinKurniawan\LightORM\Database\Connections\PostgreSqlConnection;
use KelvinKurniawan\LightORM\Database\Connections\SqliteConnection;
use KelvinKurniawan\LightORM\Database\Grammar\MySqlGrammar;
use KelvinKurniawan\LightORM\Database\Grammar\PostgreSqlGrammar;
use KelvinKurniawan\LightORM\Database\Grammar\SqliteGrammar;
use KelvinKurniawan\LightORM\Contracts\GrammarInterface;
use KelvinKurniawan\LightORM\Cache\CacheManager;
use KelvinKurniawan\LightORM\Cache\QueryCache;
use KelvinKurniawan\LightORM\Query\QueryBuilder;
use Exception;

class DatabaseManager {
    protected array         $connections       = [];
    protected array         $configurations    = [];
    protected ?string       $defaultConnection = NULL;
    protected ?CacheManager $cacheManager      = NULL;
    protected ?QueryCache   $queryCache        = NULL;

    /**
     * Set database configurations
     */
    public function setConfigurations(array $configurations): void {
        $this->configurations = $configurations;

        // Set default connection if not specified
        if($this->defaultConnection === NULL && !empty($configurations)) {
            $this->defaultConnection = array_key_first($configurations);
        }
    }

    /**
     * Add a single configuration
     */
    public function addConfiguration(string $name, array $config): void {
        $this->configurations[$name] = $config;

        // Set as default if it's the first one
        if($this->defaultConnection === NULL) {
            $this->defaultConnection = $name;
        }
    }

    /**
     * Set the default connection
     */
    public function setDefaultConnection(string $name): void {
        if(!isset($this->configurations[$name])) {
            throw new Exception("Configuration '{$name}' not found");
        }

        $this->defaultConnection = $name;
    }

    /**
     * Get a database connection
     */
    public function connection(string $name = NULL): ConnectionInterface {
        $name = $name ?? $this->defaultConnection;

        if($name === NULL) {
            throw new Exception("No default connection specified");
        }

        if(!isset($this->configurations[$name])) {
            throw new Exception("Configuration '{$name}' not found");
        }

        // Return existing connection if available
        if(isset($this->connections[$name])) {
            return $this->connections[$name];
        }

        // Create new connection
        $config = $this->configurations[$name];
        $driver = $config['driver'] ?? 'mysql';

        $this->connections[$name] = $this->createConnection($driver, $config);

        return $this->connections[$name];
    }

    /**
     * Create a database connection based on driver
     */
    protected function createConnection(string $driver, array $config): ConnectionInterface {
        return match ($driver) {
            'mysql'               => new MySqlConnection($config),
            'pgsql', 'postgresql' => new PostgreSqlConnection($config),
            'sqlite'              => new SqliteConnection($config),
            default               => throw new Exception("Unsupported database driver: {$driver}")
        };
    }

    /**
     * Get a grammar instance for the specified driver
     */
    public function getGrammar(string $driver = NULL): GrammarInterface {
        if($driver === NULL) {
            $config = $this->configurations[$this->defaultConnection] ?? [];
            $driver = $config['driver'] ?? 'mysql';
        }

        return match ($driver) {
            'mysql'               => new MySqlGrammar(),
            'pgsql', 'postgresql' => new PostgreSqlGrammar(),
            'sqlite'              => new SqliteGrammar(),
            default               => throw new Exception("Unsupported database driver: {$driver}")
        };
    }

    /**
     * Disconnect a specific connection
     */
    public function disconnect(string $name = NULL): void {
        $name = $name ?? $this->defaultConnection;

        if(isset($this->connections[$name])) {
            $this->connections[$name]->disconnect();
            unset($this->connections[$name]);
        }
    }

    /**
     * Disconnect all connections
     */
    public function disconnectAll(): void {
        foreach($this->connections as $connection) {
            $connection->disconnect();
        }

        $this->connections = [];
    }

    /**
     * Get all connection names
     */
    public function getConnectionNames(): array {
        return array_keys($this->configurations);
    }

    /**
     * Get the default connection name
     */
    public function getDefaultConnection(): ?string {
        return $this->defaultConnection;
    }

    /**
     * Check if a connection exists
     */
    public function hasConnection(string $name): bool {
        return isset($this->configurations[$name]);
    }

    /**
     * Execute a callback on a specific connection
     */
    public function on(string $connection, callable $callback): mixed {
        return $callback($this->connection($connection));
    }

    /**
     * Execute a transaction on the default connection
     */
    public function transaction(callable $callback, string $connection = NULL): mixed {
        return $this->connection($connection)->transaction($callback);
    }

    /**
     * Load configuration from .env file (if available)
     */
    public function loadFromEnv(string $envFile = '.env'): void {
        if(!file_exists($envFile)) {
            return;
        }

        // Simple .env parser
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if($lines === FALSE) {
            return;
        }

        foreach($lines as $line) {
            if(strpos($line, '#') === 0) {
                continue; // Skip comments
            }

            $parts = explode('=', $line, 2);
            if(count($parts) === 2) {
                $key        = trim($parts[0]);
                $value      = trim($parts[1], '"\'');
                $_ENV[$key] = $value;
                putenv("{$key}={$value}");
            }
        }

        // Build configuration from environment variables
        $config = [
            'default' => [
                'driver'   => $_ENV['DB_DRIVER'] ?? $_ENV['DB_CONNECTION'] ?? 'mysql',
                'host'     => $_ENV['DB_HOST'] ?? 'localhost',
                'port'     => $_ENV['DB_PORT'] ?? ($this->getDefaultPort($_ENV['DB_DRIVER'] ?? 'mysql')),
                'dbname'   => $_ENV['DB_DATABASE'] ?? $_ENV['DB_NAME'] ?? '',
                'username' => $_ENV['DB_USERNAME'] ?? $_ENV['DB_USER'] ?? '',
                'password' => $_ENV['DB_PASSWORD'] ?? '',
                'charset'  => $_ENV['DB_CHARSET'] ?? 'utf8mb4',
            ]
        ];

        // Add SQLite specific configuration
        if(($config['default']['driver'] ?? '') === 'sqlite') {
            $config['default']['database'] = $_ENV['DB_DATABASE'] ?? $_ENV['DB_FILE'] ?? ':memory:';
        }

        $this->setConfigurations($config);
    }

    /**
     * Get default port for database driver
     */
    protected function getDefaultPort(string $driver): int {
        return match ($driver) {
            'mysql'               => 3306,
            'pgsql', 'postgresql' => 5432,
            'sqlite'              => 0,
            default               => 3306
        };
    }

    /**
     * Set cache manager
     */
    public function setCacheManager(CacheManager $cacheManager): void {
        $this->cacheManager = $cacheManager;
        $this->queryCache   = new QueryCache($cacheManager->getDefaultDriver());
    }

    /**
     * Get cache manager
     */
    public function getCacheManager(): ?CacheManager {
        return $this->cacheManager;
    }

    /**
     * Get query cache
     */
    public function getQueryCache(): ?QueryCache {
        return $this->queryCache;
    }

    /**
     * Create a new query builder instance
     */
    public function table(string $table, string $connection = NULL): QueryBuilder {
        $conn    = $this->connection($connection);
        $grammar = $this->getGrammar($this->getConnectionDriver($connection));

        return new QueryBuilder($conn, $grammar, $table, $this->queryCache);
    }

    /**
     * Get connection driver name
     */
    private function getConnectionDriver(string $connection = NULL): string {
        $name = $connection ?? $this->defaultConnection;

        if(!isset($this->configurations[$name])) {
            throw new Exception("Database configuration for [{$name}] not found");
        }

        return $this->configurations[$name]['driver'];
    }
}
