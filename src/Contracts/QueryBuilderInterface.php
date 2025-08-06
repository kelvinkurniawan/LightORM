<?php

namespace KelvinKurniawan\LightORM\Contracts;

interface QueryBuilderInterface {
    /**
     * Add a select clause
     */
    public function select(array|string $columns = ['*']): self;

    /**
     * Add a where clause
     */
    public function where(string $column, mixed $operator = NULL, mixed $value = NULL): self;

    /**
     * Add an order by clause
     */
    public function orderBy(string $column, string $direction = 'asc'): self;

    /**
     * Add a limit clause
     */
    public function limit(int $value): self;

    /**
     * Add an offset clause
     */
    public function offset(int $value): self;

    /**
     * Add a join clause
     */
    public function join(string $table, string $first, string $operator = NULL, string $second = NULL): self;

    /**
     * Get the SQL query string
     */
    public function toSql(): string;

    /**
     * Get the query bindings
     */
    public function getBindings(): array;

    /**
     * Execute the query and get results
     */
    public function get(): array;

    /**
     * Execute the query and get first result
     */
    public function first(): mixed;
}
