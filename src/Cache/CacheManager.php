<?php

namespace KelvinKurniawan\LightORM\Cache;

use Exception;

class CacheManager {
    private array           $drivers       = [];
    private array           $config;
    private ?CacheInterface $defaultDriver = NULL;

    public function __construct(array $config = []) {
        $this->config = array_merge([
            'default' => 'array',
            'prefix'  => 'lightorm_',
            'stores'  => [
                'array' => [
                    'driver' => 'array',
                ],
                'redis' => [
                    'driver'   => 'redis',
                    'host'     => '127.0.0.1',
                    'port'     => 6379,
                    'database' => 0,
                ],
            ],
        ], $config);
    }

    /**
     * Get a cache driver instance
     */
    public function store(string $name = NULL): CacheInterface {
        $name = $name ?? $this->config['default'];

        if(isset($this->drivers[$name])) {
            return $this->drivers[$name];
        }

        if(!isset($this->config['stores'][$name])) {
            throw new Exception("Cache store [{$name}] is not configured.");
        }

        $storeConfig           = $this->config['stores'][$name];
        $storeConfig['prefix'] = $this->config['prefix'];

        $this->drivers[$name] = $this->createDriver($storeConfig);

        return $this->drivers[$name];
    }

    /**
     * Get the default cache driver
     */
    public function getDefaultDriver(): CacheInterface {
        if($this->defaultDriver === NULL) {
            $this->defaultDriver = $this->store();
        }

        return $this->defaultDriver;
    }

    /**
     * Set the default cache driver
     */
    public function setDefaultDriver(string $name): void {
        $this->defaultDriver     = $this->store($name);
        $this->config['default'] = $name;
    }

    /**
     * Create a cache driver instance
     */
    private function createDriver(array $config): CacheInterface {
        $driver = $config['driver'] ?? 'array';

        return match ($driver) {
            'array' => new ArrayCache($config),
            'redis' => $this->createRedisDriver($config),
            default => throw new Exception("Cache driver [{$driver}] is not supported.")
        };
    }

    /**
     * Create Redis cache driver with fallback
     */
    private function createRedisDriver(array $config): CacheInterface {
        if(!extension_loaded('redis')) {
            throw new Exception("Redis extension is not loaded. Please install php-redis extension or use 'array' cache driver.");
        }

        if(!class_exists('Redis')) {
            throw new Exception("Redis class not found. Please install php-redis extension.");
        }

        return new RedisCache($config);
    }

    /**
     * Proxy methods to default driver
     */
    public function get(string $key, mixed $default = NULL): mixed {
        return $this->getDefaultDriver()->get($key, $default);
    }

    public function put(string $key, mixed $value, int $ttl = 3600): bool {
        return $this->getDefaultDriver()->put($key, $value, $ttl);
    }

    public function add(string $key, mixed $value, int $ttl = 3600): bool {
        return $this->getDefaultDriver()->add($key, $value, $ttl);
    }

    public function forget(string $key): bool {
        return $this->getDefaultDriver()->forget($key);
    }

    public function forgetMany(array $keys): bool {
        return $this->getDefaultDriver()->forgetMany($keys);
    }

    public function flush(): bool {
        return $this->getDefaultDriver()->flush();
    }

    public function has(string $key): bool {
        return $this->getDefaultDriver()->has($key);
    }

    public function many(array $keys): array {
        return $this->getDefaultDriver()->many($keys);
    }

    public function putMany(array $items, int $ttl = 3600): bool {
        return $this->getDefaultDriver()->putMany($items, $ttl);
    }

    public function increment(string $key, int $value = 1): int|false {
        return $this->getDefaultDriver()->increment($key, $value);
    }

    public function decrement(string $key, int $value = 1): int|false {
        return $this->getDefaultDriver()->decrement($key, $value);
    }

    public function getStats(): array {
        return $this->getDefaultDriver()->getStats();
    }

    /**
     * Get all configured stores
     */
    public function getStores(): array {
        return array_keys($this->config['stores']);
    }

    /**
     * Add a custom cache store configuration
     */
    public function addStore(string $name, array $config): void {
        $this->config['stores'][$name] = $config;
    }

    /**
     * Check if a store is configured
     */
    public function hasStore(string $name): bool {
        return isset($this->config['stores'][$name]);
    }
}
