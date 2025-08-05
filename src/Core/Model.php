<?php

namespace KelvinKurniawan\LightORM\Core;

use KelvinKurniawan\LightORM\Core\Database;
use PDO;
use Exception;
use DateTime;

/**
 * Advanced Model class with modern ORM features
 * 
 * Features:
 * - Active Record pattern with advanced query builder
 * - Soft deletes with automatic scoping
 * - Automatic timestamps (created_at, updated_at)
 * - Model validation with custom rules
 * - Event system with multiple hooks
 * - Attribute casting and mutators
 * - Query scopes and macros
 * - Mass assignment protection
 * - Model serialization
 * - Cache integration
 * - Database transactions
 * 
 * @package KelvinKurniawan\LightORM\Core
 * @author Your Name
 * @version 3.0
 */
abstract class Model {
    /** @var string Database table name */
    protected static string $table;

    /** @var string Primary key column name */
    protected string $primaryKey = 'id';

    /** @var array Model attributes/data */
    protected array $attributes = [];

    /** @var array Original attributes for change detection */
    protected array $original = [];

    /** @var array Attributes that can be mass assigned */
    protected array $fillable = [];

    /** @var array Attributes that cannot be mass assigned */
    protected array $guarded = ['id'];

    /** @var array Hidden attributes for serialization */
    protected array $hidden = [];

    /** @var array Visible attributes for serialization */
    protected array $visible = [];

    /** @var array Attribute casting rules */
    protected array $casts = [];

    /** @var array Validation rules */
    protected array $rules = [];

    /** @var array Custom validation messages */
    protected array $messages = [];

    /** @var bool Enable automatic timestamps */
    protected bool $timestamps = TRUE;

    /** @var bool Enable soft deletes */
    protected bool $softDeletes = FALSE;

    /** @var string Soft delete column name */
    protected string $deletedAt = 'deleted_at';

    /** @var string Created at column name */
    protected string $createdAt = 'created_at';

    /** @var string Updated at column name */
    protected string $updatedAt = 'updated_at';

    // Query builder state
    protected array $selects     = ['*'];
    protected array $wheres      = [];
    protected array $bindings    = [];
    protected array $orderBys    = [];
    protected array $joins       = [];
    protected ?int  $limitVal    = NULL;
    protected ?int  $offsetVal   = NULL;
    protected bool  $withTrashed = FALSE;
    protected bool  $onlyTrashed = FALSE;

    /** @var array Event callbacks */
    protected static array $events = [
        'creating'  => [],
        'created'   => [],
        'updating'  => [],
        'updated'   => [],
        'saving'    => [],
        'saved'     => [],
        'deleting'  => [],
        'deleted'   => [],
        'restoring' => [],
        'restored'  => [],
    ];

    /** @var array Query scopes */
    protected static array $scopes = [];

    /** @var array Global scopes */
    protected static array $globalScopes = [];

    /**
     * Constructor
     * 
     * @param array $attributes Initial model attributes
     */
    public function __construct(array $attributes = []) {
        $this->fill($attributes);
        $this->syncOriginal();
        $this->applyGlobalScopes();
    }

    // =============================================================================
    // MASS ASSIGNMENT & FILLABLE
    // =============================================================================

    /**
     * Fill model with attributes (respects fillable/guarded)
     * 
     * @param array $attributes Attributes to fill
     * @return static For method chaining
     */
    public function fill(array $attributes): static {
        foreach($attributes as $key => $value) {
            if($this->isFillable($key)) {
                $this->setAttribute($key, $value);
            }
        }
        return $this;
    }

    /**
     * Check if attribute is fillable
     * 
     * @param string $key Attribute name
     * @return bool Is fillable
     */
    protected function isFillable(string $key): bool {
        if(!empty($this->fillable)) {
            return in_array($key, $this->fillable);
        }
        return !in_array($key, $this->guarded);
    }

    /**
     * Create new instance with mass assignment
     * 
     * @param array $attributes Model attributes
     * @return static New model instance
     */
    public static function create(array $attributes): static {
        $model = new static($attributes);
        $model->save();
        return $model;
    }

    // =============================================================================
    // TIMESTAMPS
    // =============================================================================

    /**
     * Update timestamps
     */
    protected function updateTimestamps(): void {
        if(!$this->timestamps) return;

        $now = $this->freshTimestamp();

        if(!$this->exists()) {
            $this->setAttribute($this->createdAt, $now);
        }

        $this->setAttribute($this->updatedAt, $now);
    }

    /**
     * Get fresh timestamp
     * 
     * @return string Current timestamp
     */
    protected function freshTimestamp(): string {
        return (new DateTime())->format('Y-m-d H:i:s');
    }

    /**
     * Check if model exists in database
     * 
     * @return bool Model exists
     */
    public function exists(): bool {
        return !empty($this->attributes[$this->primaryKey]);
    }

    // =============================================================================
    // SOFT DELETES
    // =============================================================================

    /**
     * Soft delete the model
     * 
     * @return bool Success status
     */
    public function delete(): bool {
        if(!$this->exists()) {
            throw new Exception("Cannot delete model without primary key");
        }

        $this->fireEvent('deleting');

        if($this->softDeletes) {
            $this->setAttribute($this->deletedAt, $this->freshTimestamp());
            $result = $this->save();
        } else {
            $sql    = "DELETE FROM " . static::tableName() . " WHERE {$this->primaryKey} = ?";
            $stmt   = Database::getConnection()->prepare($sql);
            $result = $stmt->execute([$this->getKey()]);
        }

        if($result) {
            $this->fireEvent('deleted');
        }

        return $result;
    }

    /**
     * Force delete (permanent delete even with soft deletes)
     * 
     * @return bool Success status
     */
    public function forceDelete(): bool {
        if(!$this->exists()) {
            throw new Exception("Cannot delete model without primary key");
        }

        $sql  = "DELETE FROM " . static::tableName() . " WHERE {$this->primaryKey} = ?";
        $stmt = Database::getConnection()->prepare($sql);
        return $stmt->execute([$this->getKey()]);
    }

    /**
     * Restore soft deleted model
     * 
     * @return bool Success status
     */
    public function restore(): bool {
        if(!$this->softDeletes) {
            throw new Exception("Model does not use soft deletes");
        }

        $this->fireEvent('restoring');

        $this->setAttribute($this->deletedAt, NULL);
        $result = $this->save();

        if($result) {
            $this->fireEvent('restored');
        }

        return $result;
    }

    /**
     * Include soft deleted records in query
     * 
     * @return static For method chaining
     */
    public function withTrashed(): static {
        $this->withTrashed = TRUE;
        return $this;
    }

    /**
     * Only get soft deleted records
     * 
     * @return static For method chaining
     */
    public function onlyTrashed(): static {
        $this->onlyTrashed = TRUE;
        return $this;
    }

    /**
     * Check if model is soft deleted
     * 
     * @return bool Is trashed
     */
    public function trashed(): bool {
        return $this->softDeletes && !empty($this->attributes[$this->deletedAt]);
    }

    // =============================================================================
    // VALIDATION
    // =============================================================================

    /**
     * Validate model attributes
     * 
     * @return array Validation errors (empty if valid)
     */
    public function validate(): array {
        $errors = [];

        foreach($this->rules as $field => $rules) {
            $value      = $this->getAttribute($field);
            $fieldRules = is_string($rules) ? explode('|', $rules) : $rules;

            foreach($fieldRules as $rule) {
                $error = $this->validateRule($field, $value, $rule);
                if($error) {
                    $errors[$field][] = $error;
                }
            }
        }

        return $errors;
    }

    /**
     * Validate single rule
     * 
     * @param string $field Field name
     * @param mixed $value Field value
     * @param string $rule Validation rule
     * @return string|null Error message or null if valid
     */
    protected function validateRule(string $field, $value, string $rule): ?string {
        [$ruleName, $parameters] = $this->parseRule($rule);

        switch($ruleName) {
            case 'required':
                return empty($value) ? $this->getErrorMessage($field, 'required') : NULL;

            case 'min':
                $min = (int) $parameters[0];
                return (is_string($value) && strlen($value) < $min) ||
                    (is_numeric($value) && $value < $min)
                    ? $this->getErrorMessage($field, 'min', ['min' => $min]) : NULL;

            case 'max':
                $max = (int) $parameters[0];
                return (is_string($value) && strlen($value) > $max) ||
                    (is_numeric($value) && $value > $max)
                    ? $this->getErrorMessage($field, 'max', ['max' => $max]) : NULL;

            case 'email':
                return !filter_var($value, FILTER_VALIDATE_EMAIL)
                    ? $this->getErrorMessage($field, 'email') : NULL;

            case 'unique':
                return $this->validateUnique($field, $value)
                    ? NULL : $this->getErrorMessage($field, 'unique');

            default:
                return NULL;
        }
    }

    /**
     * Parse validation rule
     * 
     * @param string $rule Rule string
     * @return array [rule_name, parameters]
     */
    protected function parseRule(string $rule): array {
        if(strpos($rule, ':') === FALSE) {
            return [$rule, []];
        }

        [$name, $params] = explode(':', $rule, 2);
        return [$name, explode(',', $params)];
    }

    /**
     * Get validation error message
     * 
     * @param string $field Field name
     * @param string $rule Rule name
     * @param array $replacements Message replacements
     * @return string Error message
     */
    protected function getErrorMessage(string $field, string $rule, array $replacements = []): string {
        $key = "{$field}.{$rule}";

        if(isset($this->messages[$key])) {
            $message = $this->messages[$key];
        } elseif(isset($this->messages[$rule])) {
            $message = $this->messages[$rule];
        } else {
            $message = $this->getDefaultErrorMessage($rule);
        }

        $replacements['field'] = $field;

        foreach($replacements as $key => $value) {
            $message = str_replace(":{$key}", $value, $message);
        }

        return $message;
    }

    /**
     * Get default error messages
     * 
     * @param string $rule Rule name
     * @return string Default message
     */
    protected function getDefaultErrorMessage(string $rule): string {
        $messages = [
            'required' => 'The :field field is required.',
            'min'      => 'The :field must be at least :min characters.',
            'max'      => 'The :field may not be greater than :max characters.',
            'email'    => 'The :field must be a valid email address.',
            'unique'   => 'The :field has already been taken.',
        ];

        return $messages[$rule] ?? 'The :field is invalid.';
    }

    /**
     * Validate uniqueness
     * 
     * @param string $field Field name
     * @param mixed $value Field value
     * @return bool Is unique
     */
    protected function validateUnique(string $field, $value): bool {
        $query = static::query()->where($field, '=', $value);

        if($this->exists()) {
            $query->where($this->primaryKey, '!=', $this->getKey());
        }

        return $query->first() === NULL;
    }

    // =============================================================================
    // ATTRIBUTE CASTING & MUTATORS
    // =============================================================================

    /**
     * Set attribute with casting and mutators
     * 
     * @param string $key Attribute name
     * @param mixed $value Attribute value
     */
    protected function setAttribute(string $key, $value): void {
        // Apply mutator if exists
        $mutator = 'set' . str_replace('_', '', ucwords($key, '_')) . 'Attribute';
        if(method_exists($this, $mutator)) {
            $value = $this->$mutator($value);
        }

        $this->attributes[$key] = $value;
    }

    /**
     * Get attribute with casting and accessors
     * 
     * @param string $key Attribute name
     * @return mixed Attribute value
     */
    protected function getAttribute(string $key) {
        $value = $this->attributes[$key] ?? NULL;

        // Apply casting
        if(isset($this->casts[$key]) && $value !== NULL) {
            $value = $this->castAttribute($key, $value);
        }

        // Apply accessor if exists
        $accessor = 'get' . str_replace('_', '', ucwords($key, '_')) . 'Attribute';
        if(method_exists($this, $accessor)) {
            $value = $this->$accessor($value);
        }

        return $value;
    }

    /**
     * Cast attribute to specified type
     * 
     * @param string $key Attribute name
     * @param mixed $value Raw value
     * @return mixed Casted value
     */
    protected function castAttribute(string $key, $value) {
        $castType = $this->casts[$key];

        switch($castType) {
            case 'int':
            case 'integer':
                return (int) $value;
            case 'float':
            case 'double':
                return (float) $value;
            case 'string':
                return (string) $value;
            case 'bool':
            case 'boolean':
                return (bool) $value;
            case 'array':
            case 'json':
                return is_string($value) ? json_decode($value, TRUE) : $value;
            case 'object':
                return is_string($value) ? json_decode($value) : $value;
            case 'datetime':
                return new DateTime($value);
            default:
                return $value;
        }
    }

    // =============================================================================
    // SCOPES
    // =============================================================================

    /**
     * Register query scope
     * 
     * @param string $name Scope name
     * @param callable $callback Scope callback
     */
    public static function scope(string $name, callable $callback): void {
        self::$scopes[static::class][$name] = $callback;
    }

    /**
     * Apply query scope
     * 
     * @param string $scope Scope name
     * @param mixed ...$parameters Scope parameters
     * @return static For method chaining
     */
    public function __call(string $method, array $parameters) {
        // Check for scope
        if(isset(self::$scopes[static::class][$method])) {
            return call_user_func_array(self::$scopes[static::class][$method], [$this, ...$parameters]);
        }

        throw new Exception("Method {$method} does not exist");
    }

    /**
     * Handle dynamic scope calls statically
     * 
     * @param string $method Method name
     * @param array $parameters Scope parameters
     * @return static For method chaining
     */
    public static function __callStatic(string $method, array $parameters) {
        $instance = static::query();

        // Check for scope
        if(isset(self::$scopes[static::class][$method])) {
            return call_user_func_array(self::$scopes[static::class][$method], [$instance, ...$parameters]);
        }

        throw new Exception("Static method {$method} does not exist");
    }

    /**
     * Apply global scopes
     */
    protected function applyGlobalScopes(): void {
        // Apply soft delete scope automatically
        if($this->softDeletes && !$this->withTrashed && !$this->onlyTrashed) {
            $this->whereNull($this->deletedAt);
        } elseif($this->onlyTrashed) {
            $this->whereNotNull($this->deletedAt);
        }
    }

    // =============================================================================
    // ENHANCED QUERY BUILDER
    // =============================================================================

    /**
     * Add WHERE NULL condition
     * 
     * @param string $column Column name
     * @return static For method chaining
     */
    public function whereNull(string $column): static {
        $this->wheres[] = "$column IS NULL";
        return $this;
    }

    /**
     * Add WHERE NOT NULL condition
     * 
     * @param string $column Column name
     * @return static For method chaining
     */
    public function whereNotNull(string $column): static {
        $this->wheres[] = "$column IS NOT NULL";
        return $this;
    }

    /**
     * Add WHERE IN condition
     * 
     * @param string $column Column name
     * @param array $values Values to search
     * @return static For method chaining
     */
    public function whereIn(string $column, array $values): static {
        $placeholders   = str_repeat('?,', count($values) - 1) . '?';
        $this->wheres[] = "$column IN ($placeholders)";
        $this->bindings = array_merge($this->bindings, $values);
        return $this;
    }

    /**
     * Add WHERE BETWEEN condition
     * 
     * @param string $column Column name
     * @param mixed $min Minimum value
     * @param mixed $max Maximum value
     * @return static For method chaining
     */
    public function whereBetween(string $column, $min, $max): static {
        $this->wheres[]   = "$column BETWEEN ? AND ?";
        $this->bindings[] = $min;
        $this->bindings[] = $max;
        return $this;
    }

    /**
     * Add WHERE LIKE condition
     * 
     * @param string $column Column name
     * @param string $value Search pattern
     * @return static For method chaining
     */
    public function whereLike(string $column, string $value): static {
        $this->wheres[]   = "$column LIKE ?";
        $this->bindings[] = $value;
        return $this;
    }

    // =============================================================================
    // ADVANCED CRUD OPERATIONS
    // =============================================================================

    /**
     * Save model with validation
     * 
     * @return bool Success status
     * @throws Exception If validation fails
     */
    public function save(): bool {
        // Validate
        $errors = $this->validate();
        if(!empty($errors)) {
            throw new Exception("Validation failed: " . json_encode($errors));
        }

        // Update timestamps
        $this->updateTimestamps();

        // Fire events
        $this->fireEvent('saving');
        $isUpdate = $this->exists();
        $this->fireEvent($isUpdate ? 'updating' : 'creating');

        // Perform save
        $result = $this->performSave();

        if($result) {
            $this->fireEvent($isUpdate ? 'updated' : 'created');
            $this->fireEvent('saved');
            $this->syncOriginal();
        }

        return $result;
    }

    /**
     * Perform the actual save operation
     * 
     * @return bool Success status
     */
    protected function performSave(): bool {
        if(empty($this->attributes)) {
            return FALSE;
        }

        $conn    = Database::getConnection();
        $columns = array_keys($this->attributes);

        if($this->exists()) {
            $sql = $this->buildUpdateQuery($columns);
        } else {
            $sql = $this->buildInsertQuery($columns);
        }

        $stmt   = $conn->prepare($sql);
        $result = $stmt->execute($this->attributes);

        // Set ID for new records
        if(!$this->exists() && $result) {
            $this->setAttribute($this->primaryKey, $conn->lastInsertId());
        }

        return $result;
    }

    /**
     * Update specific attributes
     * 
     * @param array $attributes Attributes to update
     * @return bool Success status
     */
    public function update(array $attributes): bool {
        $this->fill($attributes);
        return $this->save();
    }

    /**
     * Find or create model
     * 
     * @param array $attributes Search attributes
     * @param array $values Additional values for creation
     * @return static Found or created model
     */
    public static function firstOrCreate(array $attributes, array $values = []): static {
        $query = static::query();
        foreach($attributes as $key => $value) {
            $query->where($key, '=', $value);
        }

        $model = $query->first();

        if(!$model) {
            $model = static::create(array_merge($attributes, $values));
        }

        return $model;
    }

    // =============================================================================
    // SERIALIZATION
    // =============================================================================

    /**
     * Convert model to array
     * 
     * @return array Model as array
     */
    public function toArray(): array {
        $attributes = [];

        foreach($this->attributes as $key => $value) {
            if($this->isVisible($key)) {
                $attributes[$key] = $this->getAttribute($key);
            }
        }

        return $attributes;
    }

    /**
     * Convert model to JSON
     * 
     * @return string JSON representation
     */
    public function toJson(): string {
        return json_encode($this->toArray());
    }

    /**
     * Check if attribute should be visible
     * 
     * @param string $key Attribute name
     * @return bool Is visible
     */
    protected function isVisible(string $key): bool {
        if(!empty($this->visible)) {
            return in_array($key, $this->visible);
        }

        return !in_array($key, $this->hidden);
    }

    // =============================================================================
    // UTILITY METHODS
    // =============================================================================

    /**
     * Get primary key value
     * 
     * @return mixed Primary key value
     */
    public function getKey() {
        return $this->getAttribute($this->primaryKey);
    }

    /**
     * Sync original attributes
     */
    protected function syncOriginal(): void {
        $this->original = $this->attributes;
    }

    /**
     * Check if model has been modified
     * 
     * @return bool Is dirty
     */
    public function isDirty(): bool {
        return $this->attributes !== $this->original;
    }

    /**
     * Get changed attributes
     * 
     * @return array Changed attributes
     */
    public function getDirty(): array {
        $dirty = [];

        foreach($this->attributes as $key => $value) {
            if(!isset($this->original[$key]) || $this->original[$key] !== $value) {
                $dirty[$key] = $value;
            }
        }

        return $dirty;
    }

    // =============================================================================
    // EVENT SYSTEM
    // =============================================================================

    /**
     * Register event callback
     * 
     * @param string $event Event name
     * @param callable $callback Event callback
     */
    public static function registerEvent(string $event, callable $callback): void {
        self::$events[$event][static::class][] = $callback;
    }

    /**
     * Fire event
     * 
     * @param string $event Event name
     */
    protected function fireEvent(string $event): void {
        if(isset(self::$events[$event][static::class])) {
            foreach(self::$events[$event][static::class] as $callback) {
                call_user_func($callback, $this);
            }
        }
    }

    // =============================================================================
    // EXISTING METHODS (Updated)
    // =============================================================================

    public static function tableName(): string {
        return static::$table ?? strtolower(basename(str_replace('\\', '/', static::class))) . 's';
    }

    public static function all(): array {
        return static::query()->get();
    }

    public static function find($id): ?static {
        return static::query()->where(static::make()->primaryKey, '=', $id)->first();
    }

    public static function query(): static {
        return new static();
    }

    public function select(array $columns): static {
        $this->selects = $columns;
        return $this;
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

    public function get(): array {
        // Apply global scopes before building query
        $this->applyGlobalScopes();

        $sql  = $this->buildSelectQuery();
        $stmt = Database::getConnection()->prepare($sql);
        $stmt->execute($this->bindings);

        return self::hydrateModels($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function first(): ?static {
        $results = $this->limit(1)->get();
        return $results[0] ?? NULL;
    }

    public function paginate(int $perPage, int $page = 1): array {
        $this->limitVal  = $perPage;
        $this->offsetVal = ($page - 1) * $perPage;
        return $this->get();
    }

    public function hasMany(string $relatedClass, string $foreignKey, string $localKey = 'id'): array {
        $relatedTable = $relatedClass::tableName();
        $value        = $this->{$localKey};

        $sql  = "SELECT * FROM $relatedTable WHERE $foreignKey = ?";
        $stmt = Database::getConnection()->prepare($sql);
        $stmt->execute([$value]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map(fn($row) => new $relatedClass($row), $rows);
    }

    public function belongsTo(string $relatedClass, string $foreignKey, string $ownerKey = 'id'): ?object {
        $relatedTable = $relatedClass::tableName();
        $value        = $this->{$foreignKey};

        if(!$value) {
            return NULL;
        }

        $sql  = "SELECT * FROM $relatedTable WHERE $ownerKey = ? LIMIT 1";
        $stmt = Database::getConnection()->prepare($sql);
        $stmt->execute([$value]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? new $relatedClass($row) : NULL;
    }

    public function __get(string $key) {
        return $this->getAttribute($key);
    }

    public function __set(string $key, $value): void {
        $this->setAttribute($key, $value);
    }

    // =============================================================================
    // HELPER METHODS
    // =============================================================================

    protected static function make(): static {
        return new static();
    }

    private static function hydrateModels(array $rows): array {
        return array_map(fn($row) => new static($row), $rows);
    }

    private function buildUpdateQuery(array $columns): string {
        $set = implode(', ', array_map(fn($col) => "$col = :$col", $columns));
        return "UPDATE " . static::tableName() . " SET $set WHERE {$this->primaryKey} = :{$this->primaryKey}";
    }

    private function buildInsertQuery(array $columns): string {
        $colNames     = implode(', ', $columns);
        $placeholders = implode(', ', array_map(fn($col) => ":$col", $columns));
        return "INSERT INTO " . static::tableName() . " ($colNames) VALUES ($placeholders)";
    }

    private function buildSelectQuery(): string {
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

        return $sql;
    }
}