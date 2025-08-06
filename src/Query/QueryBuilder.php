<?php

namespace KelvinKurniawan\LightORM\Query;

use KelvinKurniawan\LightORM\Contracts\QueryBuilderInterface;
use KelvinKurniawan\LightORM\Contracts\ConnectionInterface;
use KelvinKurniawan\LightORM\Contracts\GrammarInterface;
use KelvinKurniawan\LightORM\Cache\QueryCache;
use PDO;

class QueryBuilder implements QueryBuilderInterface {
    protected ConnectionInterface $connection;
    protected GrammarInterface    $grammar;
    protected string              $table;
    protected ?QueryCache         $queryCache = NULL;

    // Query components
    protected array $selects     = ['*'];
    protected array $wheres      = [];
    protected array $bindings    = [];
    protected array $orderBys    = [];
    protected array $joins       = [];
    protected ?int  $limitVal    = NULL;
    protected ?int  $offsetVal   = NULL;
    protected bool  $withTrashed = FALSE;
    protected bool  $onlyTrashed = FALSE;

    // Caching options
    protected bool $useCache = FALSE;
    protected ?int $cacheTtl = NULL;

    public function __construct(
        ConnectionInterface $connection,
        GrammarInterface $grammar,
        string $table,
        ?QueryCache $queryCache = NULL
    ) {
        $this->connection = $connection;
        $this->grammar    = $grammar;
        $this->table      = $table;
        $this->queryCache = $queryCache;
    }

    public function select(array|string $columns = ['*']): self {
        $this->selects = is_array($columns) ? $columns : func_get_args();
        return $this;
    }

    public function where(string $column, mixed $operator = NULL, mixed $value = NULL): self {
        // Handle different argument patterns
        if(func_num_args() === 2) {
            $value    = $operator;
            $operator = '=';
        }

        $this->wheres[] = [
            'type'     => 'basic',
            'column'   => $column,
            'operator' => $operator,
            'value'    => $value,
            'boolean'  => 'and'
        ];

        $this->bindings[] = $value;
        return $this;
    }

    public function whereIn(string $column, array $values): self {
        $this->wheres[] = [
            'type'    => 'in',
            'column'  => $column,
            'values'  => $values,
            'boolean' => 'and'
        ];

        $this->bindings = array_merge($this->bindings, $values);
        return $this;
    }

    public function whereNull(string $column): self {
        $this->wheres[] = [
            'type'    => 'null',
            'column'  => $column,
            'boolean' => 'and'
        ];

        return $this;
    }

    public function whereNotNull(string $column): self {
        $this->wheres[] = [
            'type'    => 'not_null',
            'column'  => $column,
            'boolean' => 'and'
        ];

        return $this;
    }

    public function orWhere(string $column, mixed $operator = NULL, mixed $value = NULL): self {
        if(func_num_args() === 2) {
            $value    = $operator;
            $operator = '=';
        }

        $this->wheres[] = [
            'type'     => 'basic',
            'column'   => $column,
            'operator' => $operator,
            'value'    => $value,
            'boolean'  => 'or'
        ];

        $this->bindings[] = $value;
        return $this;
    }

    public function orderBy(string $column, string $direction = 'asc'): self {
        $this->orderBys[] = [
            'column'    => $column,
            'direction' => strtolower($direction) === 'desc' ? 'desc' : 'asc'
        ];

        return $this;
    }

    public function limit(int $value): self {
        $this->limitVal = $value;
        return $this;
    }

    public function offset(int $value): self {
        $this->offsetVal = $value;
        return $this;
    }

    public function join(string $table, string $first, string $operator = NULL, string $second = NULL): self {
        if(func_num_args() === 3) {
            $second   = $operator;
            $operator = '=';
        }

        $this->joins[] = [
            'type'     => 'inner',
            'table'    => $table,
            'first'    => $first,
            'operator' => $operator,
            'second'   => $second
        ];

        return $this;
    }

    public function leftJoin(string $table, string $first, string $operator = NULL, string $second = NULL): self {
        if(func_num_args() === 3) {
            $second   = $operator;
            $operator = '=';
        }

        $this->joins[] = [
            'type'     => 'left',
            'table'    => $table,
            'first'    => $first,
            'operator' => $operator,
            'second'   => $second
        ];

        return $this;
    }

    public function withTrashed(): self {
        $this->withTrashed = TRUE;
        return $this;
    }

    public function onlyTrashed(): self {
        $this->onlyTrashed = TRUE;
        return $this;
    }

    public function toSql(): string {
        $components = [
            'table'    => $this->table,
            'selects'  => $this->selects,
            'wheres'   => $this->wheres,
            'joins'    => $this->joins,
            'orderBys' => $this->orderBys,
            'limit'    => $this->limitVal,
            'offset'   => $this->offsetVal
        ];

        return $this->grammar->compileSelect($components);
    }

    public function getBindings(): array {
        return $this->bindings;
    }

    public function get(): array {
        $sql = $this->toSql();

        // Use cache if enabled and cache is available
        if($this->useCache && $this->queryCache !== NULL) {
            return $this->queryCache->remember(
                $sql,
                $this->bindings,
                fn() => $this->executeQuery($sql),
                $this->cacheTtl
            );
        }

        return $this->executeQuery($sql);
    }

    private function executeQuery(string $sql): array {
        $result = $this->connection->query($sql, $this->bindings);

        if($result instanceof \PDOStatement) {
            return $result->fetchAll(PDO::FETCH_ASSOC);
        }

        return [];
    }

    public function first(): mixed {
        $this->limit(1);
        $results = $this->get();
        return !empty($results) ? $results[0] : NULL;
    }

    public function count(): int {
        $original      = $this->selects;
        $originalLimit = $this->limitVal;

        $this->selects  = ['COUNT(*) as count'];
        $this->limitVal = NULL; // Clear any existing limit

        $result = $this->get(); // Use get() directly instead of first()

        // Restore original state
        $this->selects  = $original;
        $this->limitVal = $originalLimit;

        return (int) ($result[0]['count'] ?? 0);
    }

    public function insert(array $values): bool {
        if(empty($values)) {
            return FALSE;
        }

        // Handle single row vs multiple rows
        $isMultiple = is_array(reset($values));
        if(!$isMultiple) {
            $values = [$values];
        }

        $sql      = $this->grammar->compileInsert($this->table, $values);
        $bindings = [];

        foreach($values as $row) {
            $bindings = array_merge($bindings, array_values($row));
        }

        $result = $this->connection->query($sql, $bindings);
        return $result !== FALSE;
    }

    public function update(array $values): int {
        if(empty($values)) {
            return 0;
        }

        $sql            = $this->grammar->compileUpdate($this->table, $values, $this->wheres);
        $updateBindings = array_values($values);
        $bindings       = array_merge($updateBindings, $this->bindings);

        $result = $this->connection->query($sql, $bindings);

        if($result instanceof \PDOStatement) {
            return $result->rowCount();
        }

        return 0;
    }

    public function delete(): int {
        $sql    = $this->grammar->compileDelete($this->table, $this->wheres);
        $result = $this->connection->query($sql, $this->bindings);

        if($result instanceof \PDOStatement) {
            return $result->rowCount();
        }

        return 0;
    }

    /**
     * Clone the query builder
     */
    public function clone(): self {
        return clone $this;
    }

    /**
     * Reset the query builder
     */
    public function reset(): self {
        $this->selects     = ['*'];
        $this->wheres      = [];
        $this->bindings    = [];
        $this->orderBys    = [];
        $this->joins       = [];
        $this->limitVal    = NULL;
        $this->offsetVal   = NULL;
        $this->withTrashed = FALSE;
        $this->onlyTrashed = FALSE;
        $this->useCache    = FALSE;
        $this->cacheTtl    = NULL;

        return $this;
    }

    /**
     * Get the table name
     */
    public function getTable(): string {
        return $this->table;
    }

    /**
     * Set the table name
     */
    public function setTable(string $table): self {
        $this->table = $table;
        return $this;
    }

    /**
     * Enable caching for this query
     */
    public function cache(int $ttl = NULL): self {
        $this->useCache = TRUE;
        $this->cacheTtl = $ttl;
        return $this;
    }

    /**
     * Disable caching for this query
     */
    public function noCache(): self {
        $this->useCache = FALSE;
        $this->cacheTtl = NULL;
        return $this;
    }

    /**
     * Cache this query forever
     */
    public function cacheForever(): self {
        $this->useCache = TRUE;
        $this->cacheTtl = 31536000; // 1 year
        return $this;
    }

    /**
     * Set query cache instance
     */
    public function setQueryCache(?QueryCache $queryCache): self {
        $this->queryCache = $queryCache;
        return $this;
    }

    /**
     * Get query cache instance
     */
    public function getQueryCache(): ?QueryCache {
        return $this->queryCache;
    }
}
