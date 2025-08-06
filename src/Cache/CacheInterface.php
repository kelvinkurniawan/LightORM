<?php

namespace KelvinKurniawan\LightORM\Cache;

interface CacheInterface {
    /**
     * Get an item from the cache
     */
    public function get(string $key, mixed $default = NULL): mixed;

    /**
     * Store an item in the cache
     */
    public function put(string $key, mixed $value, int $ttl = 3600): bool;

    /**
     * Store an item in the cache if it doesn't exist
     */
    public function add(string $key, mixed $value, int $ttl = 3600): bool;

    /**
     * Remove an item from the cache
     */
    public function forget(string $key): bool;

    /**
     * Remove multiple items from the cache
     */
    public function forgetMany(array $keys): bool;

    /**
     * Clear all items from the cache
     */
    public function flush(): bool;

    /**
     * Check if an item exists in the cache
     */
    public function has(string $key): bool;

    /**
     * Get multiple items from the cache
     */
    public function many(array $keys): array;

    /**
     * Store multiple items in the cache
     */
    public function putMany(array $items, int $ttl = 3600): bool;

    /**
     * Increment a cached value
     */
    public function increment(string $key, int $value = 1): int|false;

    /**
     * Decrement a cached value
     */
    public function decrement(string $key, int $value = 1): int|false;

    /**
     * Get cache statistics
     */
    public function getStats(): array;
}
