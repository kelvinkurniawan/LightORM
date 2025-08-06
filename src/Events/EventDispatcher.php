<?php

namespace KelvinKurniawan\LightORM\Events;

use KelvinKurniawan\LightORM\Contracts\EventDispatcherInterface;

class EventDispatcher implements EventDispatcherInterface {
    protected array $listeners = [];

    public function listen(string $event, callable $callback): void {
        if(!isset($this->listeners[$event])) {
            $this->listeners[$event] = [];
        }

        $this->listeners[$event][] = $callback;
    }

    public function dispatch(string $event, array $payload = []): mixed {
        if(!isset($this->listeners[$event])) {
            return NULL;
        }

        $results = [];

        foreach($this->listeners[$event] as $listener) {
            $result = call_user_func_array($listener, $payload);

            // If any listener returns false, stop execution
            if($result === FALSE) {
                return FALSE;
            }

            $results[] = $result;
        }

        return $results;
    }

    public function forget(string $event): void {
        unset($this->listeners[$event]);
    }

    /**
     * Remove a specific listener from an event
     */
    public function removeListener(string $event, callable $callback): void {
        if(!isset($this->listeners[$event])) {
            return;
        }

        $this->listeners[$event] = array_filter(
            $this->listeners[$event],
            fn($listener) => $listener !== $callback
        );

        // Remove the event entry if no listeners remain
        if(empty($this->listeners[$event])) {
            unset($this->listeners[$event]);
        }
    }

    /**
     * Get all listeners for an event
     */
    public function getListeners(string $event): array {
        return $this->listeners[$event] ?? [];
    }

    /**
     * Check if an event has listeners
     */
    public function hasListeners(string $event): bool {
        return isset($this->listeners[$event]) && !empty($this->listeners[$event]);
    }

    /**
     * Get all registered events
     */
    public function getEvents(): array {
        return array_keys($this->listeners);
    }

    /**
     * Clear all listeners
     */
    public function clear(): void {
        $this->listeners = [];
    }

    /**
     * Clear listeners for specific event
     */
    public function clearEvent(string $event): void {
        unset($this->listeners[$event]);
    }

    /**
     * Dispatch an event until a listener returns a non-null response
     */
    public function until(string $event, array $payload = []): mixed {
        if(!isset($this->listeners[$event])) {
            return NULL;
        }

        foreach($this->listeners[$event] as $listener) {
            $result = call_user_func_array($listener, $payload);

            if($result !== NULL) {
                return $result;
            }
        }

        return NULL;
    }

    /**
     * Add a listener that will only be called once
     */
    public function once(string $event, callable $callback): void {
        $wrapper = function (...$args) use ($event, $callback) {
            $result = call_user_func_array($callback, $args);
            $this->removeListener($event, $callback);
            return $result;
        };

        $this->listen($event, $wrapper);
    }

    /**
     * Add a global listener that listens to all events
     */
    public function listenGlobal(callable $callback): void {
        $this->listen('*', $callback);
    }

    /**
     * Dispatch to global listeners as well
     */
    protected function dispatchGlobal(string $event, array $payload = []): void {
        if(isset($this->listeners['*'])) {
            foreach($this->listeners['*'] as $listener) {
                call_user_func_array($listener, array_merge([$event], $payload));
            }
        }
    }

    /**
     * Enhanced dispatch that also calls global listeners
     */
    public function dispatchWithGlobal(string $event, array $payload = []): mixed {
        // Dispatch to global listeners first
        $this->dispatchGlobal($event, $payload);

        // Then dispatch to specific event listeners
        return $this->dispatch($event, $payload);
    }
}
