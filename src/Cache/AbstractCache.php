<?php

namespace KelvinKurniawan\LightORM\Cache;

abstract class AbstractCache implements CacheInterface {
    protected string $prefix     = '';
    protected int    $defaultTtl = 3600;

    public function __construct(array $config = []) {
        $this->prefix     = $config['prefix'] ?? 'lightorm_';
        $this->defaultTtl = $config['default_ttl'] ?? 3600;
    }

    /**
     * Generate cache key with prefix
     */
    protected function buildKey(string $key): string {
        return $this->prefix . $key;
    }

    /**
     * Serialize value for storage
     */
    protected function serialize(mixed $value): string {
        return serialize($value);
    }

    /**
     * Unserialize value from storage
     */
    protected function unserialize(string $value): mixed {
        return unserialize($value);
    }

    /**
     * Get multiple items from the cache
     */
    public function many(array $keys): array {
        $result = [];
        foreach($keys as $key) {
            $result[$key] = $this->get($key);
        }
        return $result;
    }

    /**
     * Store multiple items in the cache
     */
    public function putMany(array $items, int $ttl = NULL): bool {
        $ttl     = $ttl ?? $this->defaultTtl;
        $success = TRUE;

        foreach($items as $key => $value) {
            if(!$this->put($key, $value, $ttl)) {
                $success = FALSE;
            }
        }

        return $success;
    }

    /**
     * Remove multiple items from the cache
     */
    public function forgetMany(array $keys): bool {
        $success = TRUE;
        foreach($keys as $key) {
            if(!$this->forget($key)) {
                $success = FALSE;
            }
        }
        return $success;
    }
}
