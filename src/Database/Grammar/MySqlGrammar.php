<?php

namespace KelvinKurniawan\LightORM\Database\Grammar;

class MySqlGrammar extends Grammar {
    public function compileSelect(array $components): string {
        $sql = [];

        // Compile the selects with proper handling for functions and aliases
        $selectClauses = [];
        foreach($components['selects'] as $select) {
            // Check if this is a function call or has an alias
            if(preg_match('/\b(COUNT|SUM|AVG|MIN|MAX|UPPER|LOWER)\s*\(/i', $select) ||
                strpos($select, ' as ') !== FALSE) {
                // Don't wrap function calls or aliased expressions
                $selectClauses[] = $select;
            } else {
                // Wrap regular column names
                $selectClauses[] = $this->wrap($select);
            }
        }

        $sql[] = 'select ' . implode(', ', $selectClauses);

        // Add the from clause
        $sql[] = 'from ' . $this->wrap($this->tablePrefix . $components['table']);

        // Add joins
        if(!empty($components['joins'])) {
            $sql[] = $this->compileJoins($components['joins']);
        }

        // Add where clauses
        if(!empty($components['wheres'])) {
            $sql[] = $this->compileWheres($components['wheres']);
        }

        // Add order by
        if(!empty($components['orderBys'])) {
            $sql[] = $this->compileOrderBys($components['orderBys']);
        }

        // Add limit
        if($components['limit'] !== NULL) {
            $sql[] = $this->compileLimit($components['limit']);
        }

        // Add offset
        if($components['offset'] !== NULL) {
            $sql[] = $this->compileOffset($components['offset']);
        }

        return implode(' ', array_filter($sql));
    }

    public function compileInsert(string $table, array $values): string {
        $table = $this->wrap($this->tablePrefix . $table);

        if(empty($values)) {
            return "insert into {$table} () values ()";
        }

        // Get column names from the first row
        $columns        = array_keys($values[0]);
        $wrappedColumns = implode(', ', array_map([$this, 'wrap'], $columns));

        // Create placeholders for each row
        $placeholder  = '(' . implode(', ', array_fill(0, count($columns), '?')) . ')';
        $placeholders = implode(', ', array_fill(0, count($values), $placeholder));

        return "insert into {$table} ({$wrappedColumns}) values {$placeholders}";
    }

    public function compileUpdate(string $table, array $values, array $wheres): string {
        $table = $this->wrap($this->tablePrefix . $table);

        $columns = [];
        foreach(array_keys($values) as $column) {
            $columns[] = $this->wrap($column) . ' = ?';
        }

        $sql = "update {$table} set " . implode(', ', $columns);

        if(!empty($wheres)) {
            $sql .= ' ' . $this->compileWheres($wheres);
        }

        return $sql;
    }

    public function compileDelete(string $table, array $wheres): string {
        $table = $this->wrap($this->tablePrefix . $table);
        $sql   = "delete from {$table}";

        if(!empty($wheres)) {
            $sql .= ' ' . $this->compileWheres($wheres);
        }

        return $sql;
    }

    /**
     * Get the opening identifier character for MySQL
     */
    protected function getOpeningIdentifier(): string {
        return '`';
    }

    /**
     * Get the closing identifier character for MySQL
     */
    protected function getClosingIdentifier(): string {
        return '`';
    }
}
