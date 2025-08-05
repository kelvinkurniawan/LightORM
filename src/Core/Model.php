<?php

namespace App\Core;

use App\Core\Database;
use PDO;
use Exception;

abstract class Model {
    protected static string $table;
    protected array         $selects   = ['*'];
    protected array         $orderBys  = [];
    protected ?int          $limitVal  = NULL;
    protected ?int          $offsetVal = NULL;
    protected array         $joins     = [];
    protected static array  $events    = [
        'creating' => NULL,
        'saving'   => NULL,
    ];


    public function __construct(array $attributes = []) {
        $this->attributes = $attributes;
    }
    // Events
    public static function creating(callable $callback) {
        self::$events['creating'] = $callback;
    }

    public static function saving(callable $callback) {
        self::$events['saving'] = $callback;
    }

    public static function tableName(): string {
        return static::$table ?? strtolower(static::class) . 's';
    }
    // Basic Query
    public static function all(): array {
        $stmt    = Database::getConnection()->query("SELECT * FROM " . static::tableName());
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map(fn($row) => new static($row), $results);
    }

    public static function find($id): ?static {
        $stmt = Database::getConnection()->prepare("SELECT * FROM " . static::tableName() . " WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        return $data ? new static($data) : NULL;
    }

    public function save(): bool {
        $conn    = Database::getConnection();
        $columns = array_keys($this->attributes);

        if(!empty(self::$events['saving'])) {
            call_user_func(self::$events['saving'], $this);
        }

        if(empty($this->attributes['id']) && !empty(self::$events['creating'])) {
            call_user_func(self::$events['creating'], $this);
        }

        if(!empty($this->attributes['id'])) {
            // Update
            $set = implode(', ', array_map(fn($col) => "$col = :$col", $columns));
            $sql = "UPDATE " . static::tableName() . " SET $set WHERE id = :id";
        } else {
            // Insert
            $colNames     = implode(', ', $columns);
            $placeholders = implode(', ', array_map(fn($col) => ":$col", $columns));
            $sql          = "INSERT INTO " . static::tableName() . " ($colNames) VALUES ($placeholders)";
        }

        $stmt = $conn->prepare($sql);
        return $stmt->execute($this->attributes);
    }

    public function delete(): bool {
        if(empty($this->attributes['id'])) {
            throw new Exception("Cannot delete without ID");
        }

        $stmt = Database::getConnection()->prepare("DELETE FROM " . static::tableName() . " WHERE id = :id");
        return $stmt->execute(['id' => $this->attributes['id']]);
    }

    // Chaining query builder
    public function select(array $columns): static {
        $this->selects = $columns;
        return $this;
    }

    public static function query(): static {
        return new static();
    }

    public function where(string $column, string $operator, $value): static {
        $this->wheres[]   = "$column $operator ?";
        $this->bindings[] = $value;
        return $this;
    }

    public function orWhere(string $column, string $operator, $value): static {
        $this->wheres[]   = "OR $column $operator ?";
        $this->bindings[] = $value;
        return $this;
    }

    public function whereJson(string $column, string $jsonPath, $value): static {
        $this->wheres[]   = "JSON_EXTRACT($column, ?) = ?";
        $this->bindings[] = $jsonPath;
        $this->bindings[] = $value;
        return $this;
    }

    public function whereJsonContains(string $column, $value): static {
        $this->wheres[]   = "JSON_CONTAINS($column, ?)";
        $this->bindings[] = json_encode($value);
        return $this;
    }

    public function get(): array {
        $sql = "SELECT " . implode(', ', $this->selects) . " FROM " . static::tableName();

        if(!empty($this->joins)) {
            $sql .= ' ' . implode(' ', $this->joins);
        }

        if(!empty($this->wheres)) {
            $sql .= " WHERE " . implode(' AND ', $this->wheres);
        }

        if(!empty($this->orderBys)) {
            $sql .= " ORDER BY " . implode(', ', $this->orderBys);
        }

        if($this->limitVal !== NULL) {
            $sql .= " LIMIT " . $this->limitVal;
        }

        if($this->offsetVal !== NULL) {
            $sql .= " OFFSET " . $this->offsetVal;
        }

        $stmt = Database::getConnection()->prepare($sql);
        $stmt->execute($this->bindings);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map(fn($row) => new static($row), $rows);
    }

    public function first(): ?static {
        $this->limit(1);
        $results = $this->get();
        return $results[0] ?? NULL;
    }

    public function orderBy(string $column, string $direction = 'ASC'): static {
        $this->orderBys[] = "$column $direction";
        return $this;
    }
    public function limit(int $limit): static {
        $this->limitVal = $limit;
        return $this;
    }

    public function offset(int $offset): static {
        $this->offsetVal = $offset;
        return $this;
    }
    public function join(string $table, string $first, string $operator, string $second): static {
        $this->joins[] = "JOIN $table ON $first $operator $second";
        return $this;
    }
    public function paginate(int $perPage, int $page = 1): array {
        $this->limitVal  = $perPage;
        $this->offsetVal = ($page - 1) * $perPage;
        return $this->get();
    }
    // Relations
    public function hasMany(string $relatedClass, string $foreignKey, string $localKey = 'id'): array {
        $relatedTable = $relatedClass::tableName();
        $value        = $this->{$localKey};

        $stmt = Database::getConnection()->prepare("SELECT * FROM $relatedTable WHERE $foreignKey = ?");
        $stmt->execute([$value]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(fn($row) => new $relatedClass($row), $rows);
    }

    public function belongsTo(string $relatedClass, string $foreignKey, string $ownerKey = 'id'): ?object {
        $relatedTable = $relatedClass::tableName();
        $value        = $this->{$foreignKey};

        $stmt = Database::getConnection()->prepare("SELECT * FROM $relatedTable WHERE $ownerKey = ? LIMIT 1");
        $stmt->execute([$value]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? new $relatedClass($row) : NULL;
    }

    // Set Get

    public function __get($key) {
        return $this->attributes[$key] ?? NULL;
    }

    public function __set($key, $value) {
        $this->attributes[$key] = $value;
    }
}
