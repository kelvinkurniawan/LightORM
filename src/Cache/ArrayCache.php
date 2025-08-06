<?php

namespace KelvinKurniawan\LightORM\Cache;

class ArrayCache extends AbstractCache {
    private array $storage    = [];
    private array $timestamps = [];

    public function get(string $key, mixed $default = NULL): mixed {
        $key = $this->buildKey($key);

        if(!$this->isValid($key)) {
            unset($this->storage[$key], $this->timestamps[$key]);
            return $default;
        }

        return $this->storage[$key] ?? $default;
    }

    public function put(string $key, mixed $value, int $ttl = NULL): bool {
        $ttl = $ttl ?? $this->defaultTtl;
        $key = $this->buildKey($key);

        $this->storage[$key]    = $value;
        $this->timestamps[$key] = time() + $ttl;

        return TRUE;
    }

    public function add(string $key, mixed $value, int $ttl = NULL): bool {
        if($this->has($key)) {
            return FALSE;
        }

        return $this->put($key, $value, $ttl);
    }

    public function forget(string $key): bool {
        $key = $this->buildKey($key);

        if(isset($this->storage[$key])) {
            unset($this->storage[$key], $this->timestamps[$key]);
            return TRUE;
        }

        return FALSE;
    }

    public function flush(): bool {
        $this->storage    = [];
        $this->timestamps = [];
        return TRUE;
    }

    public function has(string $key): bool {
        $key = $this->buildKey($key);

        if(!isset($this->storage[$key])) {
            return FALSE;
        }

        if(!$this->isValid($key)) {
            unset($this->storage[$key], $this->timestamps[$key]);
            return FALSE;
        }

        return TRUE;
    }

    public function increment(string $key, int $value = 1): int|false {
        $current = $this->get($key, 0);

        if(!is_numeric($current)) {
            return FALSE;
        }

        $new = (int) $current + $value;
        $this->put($key, $new);

        return $new;
    }

    public function decrement(string $key, int $value = 1): int|false {
        return $this->increment($key, -$value);
    }

    public function getStats(): array {
        $this->cleanup();

        return [
            'driver'       => 'array',
            'total_keys'   => count($this->storage),
            'memory_usage' => $this->calculateMemoryUsage(),
            'hit_rate'     => 100.0, // Array cache always hits if key exists
        ];
    }

    private function isValid(string $key): bool {
        if(!isset($this->timestamps[$key])) {
            return FALSE;
        }

        return $this->timestamps[$key] > time();
    }

    private function cleanup(): void {
        $now = time();
        foreach($this->timestamps as $key => $timestamp) {
            if($timestamp <= $now) {
                unset($this->storage[$key], $this->timestamps[$key]);
            }
        }
    }

    private function calculateMemoryUsage(): string {
        $size = 0;

        // Calculate approximate memory usage
        foreach($this->storage as $key => $value) {
            $size += strlen($key) + strlen(serialize($value));
        }

        if($size < 1024) {
            return $size . ' B';
        } elseif($size < 1048576) {
            return round($size / 1024, 2) . ' KB';
        } else {
            return round($size / 1048576, 2) . ' MB';
        }
    }
}
