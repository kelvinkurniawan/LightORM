<?php

namespace KelvinKurniawan\LightORM\Tests\Cache;

use PHPUnit\Framework\TestCase;
use KelvinKurniawan\LightORM\Cache\ArrayCache;
use KelvinKurniawan\LightORM\Cache\CacheManager;
use KelvinKurniawan\LightORM\Cache\QueryCache;

class CacheTest extends TestCase {
    private ArrayCache   $cache;
    private CacheManager $manager;

    protected function setUp(): void {
        $this->cache   = new ArrayCache(['prefix' => 'test_']);
        $this->manager = new CacheManager([
            'default' => 'array',
            'prefix'  => 'test_',
            'stores'  => [
                'array' => ['driver' => 'array']
            ]
        ]);
    }

    public function testArrayCacheBasicOperations(): void {
        // Test put and get
        $this->assertTrue($this->cache->put('key1', 'value1', 60));
        $this->assertEquals('value1', $this->cache->get('key1'));

        // Test default value
        $this->assertEquals('default', $this->cache->get('nonexistent', 'default'));

        // Test has
        $this->assertTrue($this->cache->has('key1'));
        $this->assertFalse($this->cache->has('nonexistent'));

        // Test forget
        $this->assertTrue($this->cache->forget('key1'));
        $this->assertFalse($this->cache->has('key1'));
    }

    public function testArrayCacheExpiration(): void {
        // Test expired items
        $this->cache->put('expired_key', 'value', 0); // Already expired
        $this->assertFalse($this->cache->has('expired_key'));
        $this->assertNull($this->cache->get('expired_key'));
    }

    public function testArrayCacheIncrement(): void {
        $this->cache->put('counter', 5);
        $this->assertEquals(6, $this->cache->increment('counter'));
        $this->assertEquals(10, $this->cache->increment('counter', 4));

        $this->assertEquals(8, $this->cache->decrement('counter', 2));
    }

    public function testArrayCacheMany(): void {
        $items = ['key1' => 'value1', 'key2' => 'value2', 'key3' => 'value3'];

        $this->assertTrue($this->cache->putMany($items, 60));

        $retrieved = $this->cache->many(['key1', 'key2', 'key3', 'nonexistent']);

        $this->assertEquals('value1', $retrieved['key1']);
        $this->assertEquals('value2', $retrieved['key2']);
        $this->assertEquals('value3', $retrieved['key3']);
        $this->assertNull($retrieved['nonexistent']);
    }

    public function testArrayCacheAdd(): void {
        $this->assertTrue($this->cache->add('new_key', 'new_value', 60));
        $this->assertFalse($this->cache->add('new_key', 'another_value', 60)); // Should fail
        $this->assertEquals('new_value', $this->cache->get('new_key'));
    }

    public function testArrayCacheFlush(): void {
        $this->cache->put('key1', 'value1');
        $this->cache->put('key2', 'value2');

        $this->assertTrue($this->cache->flush());

        $this->assertFalse($this->cache->has('key1'));
        $this->assertFalse($this->cache->has('key2'));
    }

    public function testArrayCacheStats(): void {
        $this->cache->put('key1', 'value1');
        $this->cache->put('key2', 'value2');

        $stats = $this->cache->getStats();

        $this->assertEquals('array', $stats['driver']);
        $this->assertEquals(2, $stats['total_keys']);
        $this->assertEquals(100.0, $stats['hit_rate']);
        $this->assertArrayHasKey('memory_usage', $stats);
    }

    public function testCacheManager(): void {
        $store = $this->manager->store('array');
        $this->assertInstanceOf(ArrayCache::class, $store);

        // Test default store
        $defaultStore = $this->manager->getDefaultDriver();
        $this->assertInstanceOf(ArrayCache::class, $defaultStore);

        // Test proxy methods
        $this->assertTrue($this->manager->put('test_key', 'test_value'));
        $this->assertEquals('test_value', $this->manager->get('test_key'));
        $this->assertTrue($this->manager->has('test_key'));
        $this->assertTrue($this->manager->forget('test_key'));
    }

    public function testQueryCache(): void {
        $queryCache = new QueryCache($this->cache);

        $sql      = "SELECT * FROM users WHERE id = ?";
        $bindings = [1];

        $callCount = 0;
        $callback  = function () use (&$callCount) {
            $callCount++;
            return [['id' => 1, 'name' => 'John']];
        };

        // First call should execute callback
        $result1 = $queryCache->remember($sql, $bindings, $callback, 60);
        $this->assertEquals(1, $callCount);
        $this->assertEquals([['id' => 1, 'name' => 'John']], $result1);

        // Second call should use cache
        $result2 = $queryCache->remember($sql, $bindings, $callback, 60);
        $this->assertEquals(1, $callCount); // Should not increase
        $this->assertEquals([['id' => 1, 'name' => 'John']], $result2);
    }

    public function testQueryCacheDisabled(): void {
        $queryCache = new QueryCache($this->cache, ['enabled' => FALSE]);

        $sql      = "SELECT * FROM users WHERE id = ?";
        $bindings = [1];

        $callCount = 0;
        $callback  = function () use (&$callCount) {
            $callCount++;
            return [['id' => 1, 'name' => 'John']];
        };

        // Both calls should execute callback when cache is disabled
        $queryCache->remember($sql, $bindings, $callback, 60);
        $queryCache->remember($sql, $bindings, $callback, 60);

        $this->assertEquals(2, $callCount);
    }

    public function testQueryCacheTableInvalidation(): void {
        $queryCache = new QueryCache($this->cache);

        // This is a basic test - full implementation would require Redis or similar
        $this->assertTrue($queryCache->invalidateTable('users'));
        $this->assertTrue($queryCache->invalidateAll());
    }

    public function testCacheManagerStoreManagement(): void {
        // Test getting available stores
        $stores = $this->manager->getStores();
        $this->assertContains('array', $stores);

        // Test checking if store exists
        $this->assertTrue($this->manager->hasStore('array'));
        $this->assertFalse($this->manager->hasStore('nonexistent'));

        // Test adding custom store
        $this->manager->addStore('custom', ['driver' => 'array', 'prefix' => 'custom_']);
        $this->assertTrue($this->manager->hasStore('custom'));
    }
}
