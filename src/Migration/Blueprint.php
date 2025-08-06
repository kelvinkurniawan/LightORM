<?php

namespace KelvinKurniawan\LightORM\Migration;

use KelvinKurniawan\LightORM\Contracts\ConnectionInterface;

class Blueprint {
    private string              $table;
    private ConnectionInterface $connection;
    private array               $columns     = [];
    private array               $indexes     = [];
    private array               $foreignKeys = [];
    private string              $engine      = 'InnoDB';
    private string              $charset     = 'utf8mb4';
    private string              $collation   = 'utf8mb4_unicode_ci';

    public function __construct(string $table, ConnectionInterface $connection) {
        $this->table      = $table;
        $this->connection = $connection;
    }

    /**
     * Create auto-incrementing primary key column
     */
    public function id(string $column = 'id'): self {
        $this->columns[] = [
            'name'       => $column,
            'type'       => 'id',
            'attributes' => []
        ];
        return $this;
    }

    /**
     * Create string column
     */
    public function string(string $column, int $length = 255): self {
        $this->columns[] = [
            'name'       => $column,
            'type'       => 'string',
            'attributes' => ['length' => $length]
        ];
        return $this;
    }

    /**
     * Create text column
     */
    public function text(string $column): self {
        $this->columns[] = [
            'name'       => $column,
            'type'       => 'text',
            'attributes' => []
        ];
        return $this;
    }

    /**
     * Create integer column
     */
    public function integer(string $column): self {
        $this->columns[] = [
            'name'       => $column,
            'type'       => 'integer',
            'attributes' => []
        ];
        return $this;
    }

    /**
     * Create big integer column
     */
    public function bigInteger(string $column): self {
        $this->columns[] = [
            'name'       => $column,
            'type'       => 'bigInteger',
            'attributes' => []
        ];
        return $this;
    }

    /**
     * Create decimal column
     */
    public function decimal(string $column, int $precision = 8, int $scale = 2): self {
        $this->columns[] = [
            'name'       => $column,
            'type'       => 'decimal',
            'attributes' => ['precision' => $precision, 'scale' => $scale]
        ];
        return $this;
    }

    /**
     * Create float column
     */
    public function float(string $column): self {
        $this->columns[] = [
            'name'       => $column,
            'type'       => 'float',
            'attributes' => []
        ];
        return $this;
    }

    /**
     * Create boolean column
     */
    public function boolean(string $column): self {
        $this->columns[] = [
            'name'       => $column,
            'type'       => 'boolean',
            'attributes' => []
        ];
        return $this;
    }

    /**
     * Create date column
     */
    public function date(string $column): self {
        $this->columns[] = [
            'name'       => $column,
            'type'       => 'date',
            'attributes' => []
        ];
        return $this;
    }

    /**
     * Create datetime column
     */
    public function dateTime(string $column): self {
        $this->columns[] = [
            'name'       => $column,
            'type'       => 'dateTime',
            'attributes' => []
        ];
        return $this;
    }

    /**
     * Create timestamp column
     */
    public function timestamp(string $column): self {
        $this->columns[] = [
            'name'       => $column,
            'type'       => 'timestamp',
            'attributes' => []
        ];
        return $this;
    }

    /**
     * Create timestamps (created_at, updated_at)
     */
    public function timestamps(): self {
        $this->timestamp('created_at');
        $this->timestamp('updated_at');
        return $this;
    }

    /**
     * Create JSON column
     */
    public function json(string $column): self {
        $this->columns[] = [
            'name'       => $column,
            'type'       => 'json',
            'attributes' => []
        ];
        return $this;
    }

    /**
     * Make column nullable
     */
    public function nullable(): self {
        $lastIndex = count($this->columns) - 1;
        if($lastIndex >= 0) {
            $this->columns[$lastIndex]['attributes']['nullable'] = TRUE;
        }
        return $this;
    }

    /**
     * Set default value
     */
    public function default($value): self {
        $lastIndex = count($this->columns) - 1;
        if($lastIndex >= 0) {
            $this->columns[$lastIndex]['attributes']['default'] = $value;
        }
        return $this;
    }

    /**
     * Make column unique
     */
    public function unique(): self {
        $lastIndex = count($this->columns) - 1;
        if($lastIndex >= 0) {
            $this->columns[$lastIndex]['attributes']['unique'] = TRUE;
        }
        return $this;
    }

    /**
     * Add index
     */
    public function index(array $columns, string $name = NULL): self {
        $this->indexes[] = [
            'columns' => $columns,
            'name'    => $name ?: $this->table . '_' . implode('_', $columns) . '_index',
            'type'    => 'index'
        ];
        return $this;
    }

    /**
     * Add foreign key
     */
    public function foreign(string $column): ForeignKeyDefinition {
        return new ForeignKeyDefinition($this, $column);
    }

    /**
     * Add foreign key constraint
     */
    public function addForeignKey(string $column, string $references, string $on, string $onDelete = 'RESTRICT', string $onUpdate = 'RESTRICT'): self {
        $this->foreignKeys[] = [
            'column'     => $column,
            'references' => $references,
            'on'         => $on,
            'onDelete'   => $onDelete,
            'onUpdate'   => $onUpdate
        ];
        return $this;
    }

    /**
     * Create the table
     */
    public function create(): void {
        $driver = $this->connection->getPdo()->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $sql    = $this->buildCreateTableSql($driver);

        $this->connection->query($sql);

        // Create indexes
        foreach($this->indexes as $index) {
            $this->createIndex($index, $driver);
        }

        // Create foreign keys
        foreach($this->foreignKeys as $fk) {
            $this->createForeignKey($fk, $driver);
        }
    }

    /**
     * Alter the table
     */
    public function alter(): void {
        $driver = $this->connection->getPdo()->getAttribute(\PDO::ATTR_DRIVER_NAME);

        foreach($this->columns as $column) {
            $sql = $this->buildAddColumnSql($column, $driver);
            $this->connection->query($sql);
        }

        // Create indexes
        foreach($this->indexes as $index) {
            $this->createIndex($index, $driver);
        }

        // Create foreign keys
        foreach($this->foreignKeys as $fk) {
            $this->createForeignKey($fk, $driver);
        }
    }

    /**
     * Build CREATE TABLE SQL
     */
    private function buildCreateTableSql(string $driver): string {
        $sql = "CREATE TABLE ";

        if($driver !== 'sqlite') {
            $sql .= "`{$this->table}` (";
        } else {
            $sql .= "{$this->table} (";
        }

        $columnDefinitions = [];
        foreach($this->columns as $column) {
            $columnDefinitions[] = $this->buildColumnDefinition($column, $driver);
        }

        $sql .= implode(', ', $columnDefinitions);
        $sql .= ")";

        // Add engine and charset for MySQL
        if($driver === 'mysql') {
            $sql .= " ENGINE={$this->engine} DEFAULT CHARSET={$this->charset} COLLATE={$this->collation}";
        }

        return $sql;
    }

    /**
     * Build column definition
     */
    private function buildColumnDefinition(array $column, string $driver): string {
        $name = $driver === 'sqlite' ? $column['name'] : "`{$column['name']}`";
        $type = $this->mapColumnType($column['type'], $column['attributes'], $driver);

        $definition = "{$name} {$type}";

        // Add constraints
        if($column['type'] === 'id') {
            if($driver === 'sqlite') {
                $definition .= " PRIMARY KEY AUTOINCREMENT";
            } elseif($driver === 'mysql') {
                $definition .= " PRIMARY KEY AUTO_INCREMENT";
            } elseif($driver === 'pgsql') {
                $definition .= " PRIMARY KEY";
            }
        }

        if(!($column['attributes']['nullable'] ?? FALSE) && $column['type'] !== 'id') {
            $definition .= " NOT NULL";
        }

        if(isset($column['attributes']['default'])) {
            $default = $column['attributes']['default'];
            if(is_string($default)) {
                $definition .= " DEFAULT '{$default}'";
            } elseif(is_bool($default)) {
                $definition .= " DEFAULT " . ($default ? '1' : '0');
            } elseif(is_null($default)) {
                $definition .= " DEFAULT NULL";
            } else {
                $definition .= " DEFAULT {$default}";
            }
        }

        if($column['attributes']['unique'] ?? FALSE) {
            $definition .= " UNIQUE";
        }

        return $definition;
    }

    /**
     * Map column type to database-specific type
     */
    private function mapColumnType(string $type, array $attributes, string $driver): string {
        switch($type) {
            case 'id':
                return $driver === 'sqlite' ? 'INTEGER' :
                    ($driver === 'pgsql' ? 'SERIAL' : 'INT UNSIGNED');

            case 'string':
                $length = $attributes['length'] ?? 255;
                return "VARCHAR({$length})";

            case 'text':
                return 'TEXT';

            case 'integer':
                return $driver === 'pgsql' ? 'INTEGER' : 'INT';

            case 'bigInteger':
                return $driver === 'sqlite' ? 'INTEGER' : 'BIGINT';

            case 'decimal':
                $precision = $attributes['precision'] ?? 8;
                $scale = $attributes['scale'] ?? 2;
                return "DECIMAL({$precision},{$scale})";

            case 'float':
                return 'FLOAT';

            case 'boolean':
                return $driver === 'pgsql' ? 'BOOLEAN' : ($driver === 'sqlite' ? 'INTEGER' : 'TINYINT(1)');

            case 'date':
                return 'DATE';

            case 'dateTime':
                return $driver === 'sqlite' ? 'DATETIME' : 'DATETIME';

            case 'timestamp':
                return 'TIMESTAMP';

            case 'json':
                return $driver === 'mysql' ? 'JSON' : 'TEXT';

            default:
                return 'VARCHAR(255)';
        }
    }

    /**
     * Build ADD COLUMN SQL
     */
    private function buildAddColumnSql(array $column, string $driver): string {
        $table     = $driver === 'sqlite' ? $this->table : "`{$this->table}`";
        $columnDef = $this->buildColumnDefinition($column, $driver);

        return "ALTER TABLE {$table} ADD COLUMN {$columnDef}";
    }

    /**
     * Create index
     */
    private function createIndex(array $index, string $driver): void {
        $table   = $driver === 'sqlite' ? $this->table : "`{$this->table}`";
        $columns = $driver === 'sqlite' ?
            implode(', ', $index['columns']) :
            '`' . implode('`, `', $index['columns']) . '`';

        $sql = "CREATE INDEX {$index['name']} ON {$table} ({$columns})";
        $this->connection->query($sql);
    }

    /**
     * Create foreign key
     */
    private function createForeignKey(array $fk, string $driver): void {
        if($driver === 'sqlite') {
            // SQLite foreign keys are handled in table creation
            return;
        }

        $table      = "`{$this->table}`";
        $column     = "`{$fk['column']}`";
        $references = "`{$fk['references']}`";
        $on         = "`{$fk['on']}`";

        $sql = "ALTER TABLE {$table} ADD CONSTRAINT fk_{$this->table}_{$fk['column']} 
                FOREIGN KEY ({$column}) REFERENCES {$on} ({$references}) 
                ON DELETE {$fk['onDelete']} ON UPDATE {$fk['onUpdate']}";

        $this->connection->query($sql);
    }
}

/**
 * Foreign key definition helper
 */
class ForeignKeyDefinition {
    private Blueprint $blueprint;
    private string    $column;
    private ?string   $referencesColumn = NULL;
    private ?string   $onTable          = NULL;
    private string    $onDeleteAction   = 'RESTRICT';
    private string    $onUpdateAction   = 'RESTRICT';

    public function __construct(Blueprint $blueprint, string $column) {
        $this->blueprint = $blueprint;
        $this->column    = $column;
    }

    public function references(string $column): self {
        $this->referencesColumn = $column;
        return $this;
    }

    public function on(string $table): self {
        $this->onTable = $table;
        return $this;
    }

    public function onDelete(string $action): self {
        $this->onDeleteAction = $action;
        return $this;
    }

    public function onUpdate(string $action): self {
        $this->onUpdateAction = $action;
        return $this;
    }

    public function __destruct() {
        if($this->referencesColumn && $this->onTable) {
            $this->blueprint->addForeignKey(
                $this->column,
                $this->referencesColumn,
                $this->onTable,
                $this->onDeleteAction,
                $this->onUpdateAction
            );
        }
    }
}
