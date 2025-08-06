<?php

namespace KelvinKurniawan\LightORM\Contracts;

interface EventDispatcherInterface {
    /**
     * Register an event listener
     */
    public function listen(string $event, callable $callback): void;

    /**
     * Dispatch an event
     */
    public function dispatch(string $event, array $payload = []): mixed;

    /**
     * Remove an event listener
     */
    public function forget(string $event): void;
}
