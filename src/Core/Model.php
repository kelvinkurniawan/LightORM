<?php

namespace App\Core;

use App\Core\Database;
use PDO;
use Exception;

abstract class Model {
    protected static string $table;
    protected array         $attributes = [];

    public function __construct(array $attributes = []) {
        $this->attributes = $attributes;
    }

    public static function tableName(): string {
        return static::$table ?? strtolower(static::class) . 's';
    }

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

    public function __get($key) {
        return $this->attributes[$key] ?? NULL;
    }

    public function __set($key, $value) {
        $this->attributes[$key] = $value;
    }
}
