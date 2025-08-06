<?php

namespace KelvinKurniawan\LightORM\Migration;

use KelvinKurniawan\LightORM\Contracts\ConnectionInterface;
use KelvinKurniawan\LightORM\Contracts\MigrationInterface;
use Exception;

class MigrationManager {
    private ConnectionInterface $connection;
    private string              $migrationsPath;
    private string              $migrationsTable = 'migrations';

    public function __construct(ConnectionInterface $connection, string $migrationsPath = 'migrations/') {
        $this->connection     = $connection;
        $this->migrationsPath = rtrim($migrationsPath, '/') . '/';
    }

    /**
     * Initialize migrations table
     */
    public function initializeMigrationsTable(): void {
        $sql = "CREATE TABLE IF NOT EXISTS {$this->migrationsTable} (
            id INTEGER PRIMARY KEY AUTO_INCREMENT,
            migration VARCHAR(255) NOT NULL,
            batch INTEGER NOT NULL,
            executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";

        // Adjust for SQLite
        if($this->connection->getPdo()->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $sql = "CREATE TABLE IF NOT EXISTS {$this->migrationsTable} (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                migration VARCHAR(255) NOT NULL,
                batch INTEGER NOT NULL,
                executed_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )";
        }

        $this->connection->query($sql);
    }

    /**
     * Run pending migrations
     */
    public function migrate(): array {
        $this->initializeMigrationsTable();

        $executed = $this->getExecutedMigrations();
        $pending  = $this->getPendingMigrations($executed);

        if(empty($pending)) {
            return ['message' => 'No pending migrations'];
        }

        $batch    = $this->getNextBatchNumber();
        $migrated = [];

        foreach($pending as $migration) {
            try {
                $this->connection->beginTransaction();

                // Run migration
                $migration->up();

                // Record migration
                $this->recordMigration($migration->getName(), $batch);

                $this->connection->commit();
                $migrated[] = $migration->getName();

            } catch (Exception $e) {
                $this->connection->rollback();
                throw new Exception("Migration failed: {$migration->getName()}. Error: " . $e->getMessage());
            }
        }

        return [
            'message'  => 'Migrations completed successfully',
            'migrated' => $migrated,
            'batch'    => $batch
        ];
    }

    /**
     * Rollback migrations
     */
    public function rollback(int $steps = 1): array {
        $this->initializeMigrationsTable();

        $batches         = $this->getExecutedBatches();
        $rollbackBatches = array_slice($batches, -$steps);

        if(empty($rollbackBatches)) {
            return ['message' => 'No migrations to rollback'];
        }

        $rolledBack = [];

        foreach($rollbackBatches as $batch) {
            $migrations = $this->getBatchMigrations($batch);

            // Rollback in reverse order
            foreach(array_reverse($migrations) as $migrationName) {
                try {
                    $migration = $this->loadMigration($migrationName);

                    $this->connection->beginTransaction();

                    // Run rollback
                    $migration->down();

                    // Remove migration record
                    $this->removeMigrationRecord($migrationName);

                    $this->connection->commit();
                    $rolledBack[] = $migrationName;

                } catch (Exception $e) {
                    $this->connection->rollback();
                    // If migration file not found, just remove the record
                    if(strpos($e->getMessage(), 'Migration not found') !== FALSE) {
                        $this->removeMigrationRecord($migrationName);
                        $rolledBack[] = $migrationName . ' (file not found, record removed)';
                    } else {
                        throw new Exception("Rollback failed: {$migrationName}. Error: " . $e->getMessage());
                    }
                }
            }
        }

        return [
            'message'     => 'Rollback completed successfully',
            'rolled_back' => $rolledBack
        ];
    }

    /**
     * Get migrations status
     */
    public function status(): array {
        $this->initializeMigrationsTable();

        $migrations   = [];
        $migrationDir = $this->migrationsPath;

        if(!is_dir($migrationDir)) {
            return $migrations;
        }

        $files = glob($migrationDir . '/*.php');
        sort($files);

        foreach($files as $file) {
            $filename = pathinfo($file, PATHINFO_FILENAME);

            require_once $file;

            // Extract class name from file content
            $content = file_get_contents($file);
            preg_match('/class\s+(\w+)/i', $content, $matches);

            if(!isset($matches[1])) {
                continue; // Skip if no class found
            }

            $className = $matches[1];

            if(!class_exists($className)) {
                continue; // Skip if class doesn't exist
            }

            $migration = new $className();

            // Get timestamp and migration name
            $timestamp = $migration->getTimestamp();
            $name      = $migration->getName();

            // Check if migration was executed
            $sql  = "SELECT batch, executed_at FROM {$this->migrationsTable} WHERE migration = ?";
            $stmt = $this->connection->getPdo()->prepare($sql);
            $stmt->execute([$name]);
            $executed = $stmt->fetch(\PDO::FETCH_ASSOC);

            $migrations[] = [
                'name'        => $name,
                'migration'   => $name,
                'timestamp'   => $timestamp,
                'status'      => $executed ? 'executed' : 'pending',
                'batch'       => $executed ? $executed['batch'] : NULL,
                'executed_at' => $executed ? $executed['executed_at'] : NULL,
                'file'        => basename($file)
            ];
        }

        return $migrations;
    }

    /**
     * Create new migration file
     */
    public function createMigration(string $name): string {
        $timestamp = date('Y_m_d_His');
        $className = $this->studlyCase($name);
        $filename  = "{$timestamp}_{$name}.php";
        $filepath  = $this->migrationsPath . $filename;

        $template = $this->getMigrationTemplate($className, $timestamp);

        if(!is_dir($this->migrationsPath)) {
            mkdir($this->migrationsPath, 0755, TRUE);
        }

        file_put_contents($filepath, $template);

        return $filepath;
    }

    /**
     * Get executed migrations
     */
    private function getExecutedMigrations(): array {
        try {
            $result = $this->connection->query("SELECT migration FROM {$this->migrationsTable} ORDER BY id");
            return array_column($result->fetchAll(\PDO::FETCH_ASSOC), 'migration');
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Get pending migrations
     */
    private function getPendingMigrations(array $executed): array {
        $files   = $this->getAllMigrationFiles();
        $pending = [];

        foreach($files as $file) {
            $name = $this->extractMigrationName($file);
            if(!in_array($name, $executed)) {
                $pending[] = $this->loadMigration($name);
            }
        }

        return $pending;
    }

    /**
     * Get all migration files
     */
    private function getAllMigrationFiles(): array {
        if(!is_dir($this->migrationsPath)) {
            return [];
        }

        $files = glob($this->migrationsPath . '*.php');
        sort($files);

        return $files;
    }

    /**
     * Load migration instance
     */
    private function loadMigration(string $name): MigrationInterface {
        $files = $this->getAllMigrationFiles();

        foreach($files as $file) {
            if($this->extractMigrationName($file) === $name) {
                require_once $file;

                // Extract class name from file
                $content = file_get_contents($file);
                preg_match('/class\s+(\w+)/i', $content, $matches);

                if(!isset($matches[1])) {
                    throw new Exception("Could not find class in migration file: {$file}");
                }

                $className = $matches[1];

                if(!class_exists($className)) {
                    throw new Exception("Migration class not found: {$className}");
                }

                return new $className();
            }
        }

        throw new Exception("Migration not found: {$name}");
    }

    /**
     * Extract migration name from file path
     */
    private function extractMigrationName(string $file): string {
        $basename = basename($file, '.php');
        // Remove timestamp prefix (YYYY_MM_DD_HHMMSS_)
        return preg_replace('/^\d{4}_\d{2}_\d{2}_\d{6}_/', '', $basename);
    }

    /**
     * Get next batch number
     */
    private function getNextBatchNumber(): int {
        $result = $this->connection->query("SELECT MAX(batch) as max_batch FROM {$this->migrationsTable}");
        $row    = $result->fetch(\PDO::FETCH_ASSOC);

        return ($row['max_batch'] ?? 0) + 1;
    }

    /**
     * Record migration execution
     */
    private function recordMigration(string $migration, int $batch): void {
        $this->connection->query(
            "INSERT INTO {$this->migrationsTable} (migration, batch) VALUES (?, ?)",
            [$migration, $batch]
        );
    }

    /**
     * Remove migration record
     */
    private function removeMigrationRecord(string $migration): void {
        $this->connection->query(
            "DELETE FROM {$this->migrationsTable} WHERE migration = ?",
            [$migration]
        );
    }

    /**
     * Get executed batches
     */
    private function getExecutedBatches(): array {
        $result = $this->connection->query("SELECT DISTINCT batch FROM {$this->migrationsTable} ORDER BY batch");
        return array_column($result->fetchAll(\PDO::FETCH_ASSOC), 'batch');
    }

    /**
     * Get migrations for specific batch
     */
    private function getBatchMigrations(int $batch): array {
        $result = $this->connection->query(
            "SELECT migration FROM {$this->migrationsTable} WHERE batch = ? ORDER BY id",
            [$batch]
        );
        return array_column($result->fetchAll(\PDO::FETCH_ASSOC), 'migration');
    }

    /**
     * Convert string to StudlyCase
     */
    private function studlyCase(string $value): string {
        return str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $value)));
    }

    /**
     * Get migration template
     */
    private function getMigrationTemplate(string $className, string $timestamp): string {
        $migrationName = strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $className));
        return "<?php

use KelvinKurniawan\LightORM\Contracts\MigrationInterface;
use KelvinKurniawan\LightORM\Migration\Blueprint;
use KelvinKurniawan\LightORM\Migration\Schema;

class {$className} implements MigrationInterface {
    
    public function up(): void {
        // Schema::create('table_name', function(Blueprint \$table) {
        //     \$table->id();
        //     \$table->string('name');
        //     \$table->timestamps();
        // });
    }
    
    public function down(): void {
        // Schema::dropIfExists('table_name');
    }
    
    public function getName(): string {
        return '{$migrationName}';
    }
    
    public function getTimestamp(): string {
        return '{$timestamp}';
    }
}
";
    }
}
