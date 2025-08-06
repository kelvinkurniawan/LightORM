<?php

namespace KelvinKurniawan\LightORM\Cache;

use Redis;
use Exception;

class RedisCache extends AbstractCache {
    private Redis $redis;
    private array $config;

    public function __construct(array $config = []) {
        parent::__construct($config);

        $this->config = array_merge([
            'host'          => '127.0.0.1',
            'port'          => 6379,
            'password'      => NULL,
            'database'      => 0,
            'timeout'       => 5.0,
            'read_timeout'  => 5.0,
            'persistent'    => FALSE,
            'persistent_id' => NULL,
        ], $config);

        $this->connect();
    }

    private function connect(): void {
        $this->redis = new Redis();

        try {
            if($this->config['persistent']) {
                $connected = $this->redis->pconnect(
                    $this->config['host'],
                    $this->config['port'],
                    $this->config['timeout'],
                    $this->config['persistent_id'],
                    $this->config['read_timeout']
                );
            } else {
                $connected = $this->redis->connect(
                    $this->config['host'],
                    $this->config['port'],
                    $this->config['timeout']
                );
            }

            if(!$connected) {
                throw new Exception('Failed to connect to Redis');
            }

            if($this->config['password'] !== NULL) {
                if(!$this->redis->auth($this->config['password'])) {
                    throw new Exception('Redis authentication failed');
                }
            }

            if($this->config['database'] !== 0) {
                if(!$this->redis->select($this->config['database'])) {
                    throw new Exception('Failed to select Redis database');
                }
            }

            // Set serialization mode
            $this->redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_PHP);

        } catch (Exception $e) {
            throw new Exception("Redis connection failed: " . $e->getMessage(), 0, $e);
        }
    }

    public function get(string $key, mixed $default = NULL): mixed {
        try {
            $value = $this->redis->get($this->buildKey($key));
            return $value !== FALSE ? $value : $default;
        } catch (Exception $e) {
            return $default;
        }
    }

    public function put(string $key, mixed $value, int $ttl = NULL): bool {
        try {
            $ttl = $ttl ?? $this->defaultTtl;
            return $this->redis->setex($this->buildKey($key), $ttl, $value);
        } catch (Exception $e) {
            return FALSE;
        }
    }

    public function add(string $key, mixed $value, int $ttl = NULL): bool {
        try {
            $ttl = $ttl ?? $this->defaultTtl;
            $key = $this->buildKey($key);

            // Use SET with NX (only if not exists) and EX (expiration)
            $result = $this->redis->set($key, $value, ['nx', 'ex' => $ttl]);
            return $result !== FALSE;
        } catch (Exception $e) {
            return FALSE;
        }
    }

    public function forget(string $key): bool {
        try {
            return $this->redis->del($this->buildKey($key)) > 0;
        } catch (Exception $e) {
            return FALSE;
        }
    }

    public function flush(): bool {
        try {
            return $this->redis->flushDB();
        } catch (Exception $e) {
            return FALSE;
        }
    }

    public function has(string $key): bool {
        try {
            return $this->redis->exists($this->buildKey($key)) > 0;
        } catch (Exception $e) {
            return FALSE;
        }
    }

    public function increment(string $key, int $value = 1): int|false {
        try {
            return $this->redis->incrBy($this->buildKey($key), $value);
        } catch (Exception $e) {
            return FALSE;
        }
    }

    public function decrement(string $key, int $value = 1): int|false {
        try {
            return $this->redis->decrBy($this->buildKey($key), $value);
        } catch (Exception $e) {
            return FALSE;
        }
    }

    public function many(array $keys): array {
        try {
            $prefixedKeys = array_map([$this, 'buildKey'], $keys);
            $values       = $this->redis->mget($prefixedKeys);

            $result = [];
            foreach($keys as $index => $key) {
                $result[$key] = $values[$index] !== FALSE ? $values[$index] : NULL;
            }

            return $result;
        } catch (Exception $e) {
            return array_fill_keys($keys, NULL);
        }
    }

    public function putMany(array $items, int $ttl = NULL): bool {
        try {
            $ttl  = $ttl ?? $this->defaultTtl;
            $pipe = $this->redis->multi(Redis::PIPELINE);

            foreach($items as $key => $value) {
                $pipe->setex($this->buildKey($key), $ttl, $value);
            }

            $results = $pipe->exec();
            return !in_array(FALSE, $results, TRUE);
        } catch (Exception $e) {
            return FALSE;
        }
    }

    public function forgetMany(array $keys): bool {
        try {
            $prefixedKeys = array_map([$this, 'buildKey'], $keys);
            return $this->redis->del($prefixedKeys) > 0;
        } catch (Exception $e) {
            return FALSE;
        }
    }

    public function getStats(): array {
        try {
            $info = $this->redis->info();
            return [
                'driver'                   => 'redis',
                'memory_usage'             => $info['used_memory_human'] ?? 'unknown',
                'connected_clients'        => $info['connected_clients'] ?? 0,
                'total_commands_processed' => $info['total_commands_processed'] ?? 0,
                'keyspace_hits'            => $info['keyspace_hits'] ?? 0,
                'keyspace_misses'          => $info['keyspace_misses'] ?? 0,
                'hit_rate'                 => $this->calculateHitRate($info),
            ];
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    private function calculateHitRate(array $info): float {
        $hits   = $info['keyspace_hits'] ?? 0;
        $misses = $info['keyspace_misses'] ?? 0;
        $total  = $hits + $misses;

        return $total > 0 ? round(($hits / $total) * 100, 2) : 0.0;
    }

    public function getRedis(): Redis {
        return $this->redis;
    }

    public function __destruct() {
        if(isset($this->redis)) {
            $this->redis->close();
        }
    }
}
