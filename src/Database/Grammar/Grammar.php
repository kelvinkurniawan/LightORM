<?php

namespace KelvinKurniawan\LightORM\Database\Grammar;

use KelvinKurniawan\LightORM\Contracts\GrammarInterface;

abstract class Grammar implements GrammarInterface {
    protected string $tablePrefix = '';

    public function getTablePrefix(): string {
        return $this->tablePrefix;
    }

    public function setTablePrefix(string $prefix): void {
        $this->tablePrefix = $prefix;
    }

    /**
     * Wrap a value in keyword identifiers
     */
    protected function wrap(string $value): string {
        if(strpos($value, '.') !== FALSE) {
            return implode('.', array_map([$this, 'wrapSegment'], explode('.', $value)));
        }

        return $this->wrapSegment($value);
    }

    /**
     * Wrap a single string in keyword identifiers
     */
    protected function wrapSegment(string $segment): string {
        if($segment === '*') {
            return $segment;
        }

        return $this->getOpeningIdentifier() . $segment . $this->getClosingIdentifier();
    }

    /**
     * Get the opening identifier character
     */
    protected function getOpeningIdentifier(): string {
        return '`';
    }

    /**
     * Get the closing identifier character
     */
    protected function getClosingIdentifier(): string {
        return '`';
    }

    /**
     * Compile where clauses
     */
    protected function compileWheres(array $wheres): string {
        if(empty($wheres)) {
            return '';
        }

        $sql = [];

        foreach($wheres as $index => $where) {
            $boolean = $index === 0 ? 'where' : $where['boolean'];
            $method  = "compile" . ucfirst($where['type']) . "Where";

            if(method_exists($this, $method)) {
                $sql[] = $boolean . ' ' . $this->$method($where);
            }
        }

        return implode(' ', $sql);
    }

    /**
     * Compile a basic where clause
     */
    protected function compileBasicWhere(array $where): string {
        return $this->wrap($where['column']) . ' ' . $where['operator'] . ' ?';
    }

    /**
     * Compile an "in" where clause
     */
    protected function compileInWhere(array $where): string {
        $placeholders = implode(', ', array_fill(0, count($where['values']), '?'));
        return $this->wrap($where['column']) . ' in (' . $placeholders . ')';
    }

    /**
     * Compile a "null" where clause
     */
    protected function compileNullWhere(array $where): string {
        return $this->wrap($where['column']) . ' is null';
    }

    /**
     * Compile a "not null" where clause
     */
    protected function compileNotNullWhere(array $where): string {
        return $this->wrap($where['column']) . ' is not null';
    }

    /**
     * Compile join clauses
     */
    protected function compileJoins(array $joins): string {
        if(empty($joins)) {
            return '';
        }

        $sql = [];

        foreach($joins as $join) {
            $type     = strtoupper($join['type']);
            $table    = $this->wrap($join['table']);
            $first    = $this->wrap($join['first']);
            $operator = $join['operator'];
            $second   = $this->wrap($join['second']);

            $sql[] = "{$type} JOIN {$table} ON {$first} {$operator} {$second}";
        }

        return implode(' ', $sql);
    }

    /**
     * Compile order by clauses
     */
    protected function compileOrderBys(array $orderBys): string {
        if(empty($orderBys)) {
            return '';
        }

        $orders = [];

        foreach($orderBys as $orderBy) {
            $orders[] = $this->wrap($orderBy['column']) . ' ' . strtoupper($orderBy['direction']);
        }

        return 'order by ' . implode(', ', $orders);
    }

    /**
     * Compile limit clause
     */
    protected function compileLimit(?int $limit): string {
        if($limit === NULL) {
            return '';
        }

        return "limit {$limit}";
    }

    /**
     * Compile offset clause
     */
    protected function compileOffset(?int $offset): string {
        if($offset === NULL) {
            return '';
        }

        return "offset {$offset}";
    }
}
