<?php

namespace KelvinKurniawan\LightORM\Cache;

use KelvinKurniawan\LightORM\Contracts\ConnectionInterface;

class QueryCache {
    private CacheInterface $cache;
    private bool           $enabled    = TRUE;
    private int            $defaultTtl = 3600;
    private array          $tags       = [];

    public function __construct(CacheInterface $cache, array $config = []) {
        $this->cache      = $cache;
        $this->enabled    = $config['enabled'] ?? TRUE;
        $this->defaultTtl = $config['default_ttl'] ?? 3600;
    }

    /**
     * Cache a query result
     */
    public function remember(string $sql, array $bindings, callable $callback, int $ttl = NULL): mixed {
        if(!$this->enabled) {
            return $callback();
        }

        $key = $this->generateKey($sql, $bindings);

        $result = $this->cache->get($key);
        if($result !== NULL) {
            return $result;
        }

        $result = $callback();

        if($result !== NULL) {
            $this->cache->put($key, $result, $ttl ?? $this->defaultTtl);
        }

        return $result;
    }

    /**
     * Cache a query result forever (until manually cleared)
     */
    public function rememberForever(string $sql, array $bindings, callable $callback): mixed {
        if(!$this->enabled) {
            return $callback();
        }

        $key = $this->generateKey($sql, $bindings);

        $result = $this->cache->get($key);
        if($result !== NULL) {
            return $result;
        }

        $result = $callback();

        if($result !== NULL) {
            // Use a very long TTL (1 year)
            $this->cache->put($key, $result, 31536000);
        }

        return $result;
    }

    /**
     * Invalidate cached queries for a table
     */
    public function invalidateTable(string $table): bool {
        $pattern = "query_table_{$table}_*";
        return $this->invalidatePattern($pattern);
    }

    /**
     * Invalidate all cached queries
     */
    public function invalidateAll(): bool {
        return $this->cache->flush();
    }

    /**
     * Check if caching is enabled
     */
    public function isEnabled(): bool {
        return $this->enabled;
    }

    /**
     * Enable query caching
     */
    public function enable(): void {
        $this->enabled = TRUE;
    }

    /**
     * Disable query caching
     */
    public function disable(): void {
        $this->enabled = FALSE;
    }

    /**
     * Set cache TTL
     */
    public function setTtl(int $ttl): void {
        $this->defaultTtl = $ttl;
    }

    /**
     * Generate cache key for query
     */
    private function generateKey(string $sql, array $bindings): string {
        // Extract table name from SQL (simple regex)
        $table = $this->extractTableName($sql);

        // Create unique key based on SQL and bindings
        $hash = md5($sql . serialize($bindings));

        return "query_table_{$table}_{$hash}";
    }

    /**
     * Extract table name from SQL query
     */
    private function extractTableName(string $sql): string {
        // Simple regex to extract table name
        // This handles basic SELECT, INSERT, UPDATE, DELETE queries
        $patterns = [
            '/\bFROM\s+[`"]?(\w+)[`"]?/i',
            '/\bINTO\s+[`"]?(\w+)[`"]?/i',
            '/\bUPDATE\s+[`"]?(\w+)[`"]?/i',
            '/\bDELETE\s+FROM\s+[`"]?(\w+)[`"]?/i',
        ];

        foreach($patterns as $pattern) {
            if(preg_match($pattern, $sql, $matches)) {
                return $matches[1];
            }
        }

        return 'unknown';
    }

    /**
     * Invalidate cache entries matching a pattern
     */
    private function invalidatePattern(string $pattern): bool {
        // For simple cache implementations, we need to track keys
        // This is a basic implementation - Redis would support SCAN

        // For now, we'll implement a simple approach
        // In production, you'd want to use Redis SCAN or similar

        return TRUE; // Placeholder - implementation depends on cache driver
    }

    /**
     * Get cache statistics
     */
    public function getStats(): array {
        $baseStats = $this->cache->getStats();

        return array_merge($baseStats, [
            'query_cache_enabled' => $this->enabled,
            'default_ttl'         => $this->defaultTtl,
        ]);
    }
}
