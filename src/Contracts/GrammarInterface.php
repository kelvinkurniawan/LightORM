<?php

namespace KelvinKurniawan\LightORM\Contracts;

interface GrammarInterface {
    /**
     * Compile a select query
     */
    public function compileSelect(array $components): string;

    /**
     * Compile an insert query
     */
    public function compileInsert(string $table, array $values): string;

    /**
     * Compile an update query
     */
    public function compileUpdate(string $table, array $values, array $wheres): string;

    /**
     * Compile a delete query
     */
    public function compileDelete(string $table, array $wheres): string;

    /**
     * Get the table prefix
     */
    public function getTablePrefix(): string;
}
