<?php

namespace KelvinKurniawan\LightORM\Contracts;

interface ValidatorInterface {
    /**
     * Validate data against rules
     */
    public function validate(array $data, array $rules, array $messages = []): bool;

    /**
     * Get validation errors
     */
    public function errors(): array;

    /**
     * Add a custom validation rule
     */
    public function addRule(string $name, callable $callback): void;
}
