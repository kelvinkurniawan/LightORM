<?php

namespace KelvinKurniawan\LightORM\Tests\Performance;

use PHPUnit\Framework\TestCase;
use KelvinKurniawan\LightORM\Performance\QueryProfiler;

class PerformanceTest extends TestCase {
    private QueryProfiler $profiler;

    protected function setUp(): void {
        $this->profiler = new QueryProfiler();
        $this->profiler->enable();
    }

    public function testQueryProfilerBasicFunctionality(): void {
        $this->assertTrue($this->profiler->isEnabled());

        // Test profiling a query
        $sql      = "SELECT * FROM users WHERE id = ?";
        $bindings = [1];

        $queryId = $this->profiler->startQuery($sql, $bindings);
        $this->assertNotEmpty($queryId);

        // Simulate some work
        usleep(1000); // 1ms

        $this->profiler->endQuery($queryId, 1);

        $queries = $this->profiler->getQueries();
        $this->assertCount(1, $queries);

        $query = $queries[0];
        $this->assertEquals($sql, $query['sql']);
        $this->assertEquals($bindings, $query['bindings']);
        $this->assertGreaterThan(0, $query['duration']);
        $this->assertEquals(1, $query['affected_rows']);
        $this->assertArrayHasKey('memory_usage', $query);
    }

    public function testQueryProfilerStats(): void {
        // Profile multiple queries
        $queries = [
            ["SELECT * FROM users", []],
            ["SELECT * FROM posts WHERE user_id = ?", [1]],
            ["UPDATE users SET name = ? WHERE id = ?", ['John', 1]],
        ];

        foreach($queries as $queryData) {
            $queryId = $this->profiler->startQuery($queryData[0], $queryData[1]);
            usleep(rand(500, 2000)); // Random delay
            $this->profiler->endQuery($queryId, rand(0, 5));
        }

        $stats = $this->profiler->getStats();

        $this->assertEquals(3, $stats['total_queries']);
        $this->assertGreaterThan(0, $stats['total_time']);
        $this->assertGreaterThan(0, $stats['average_time']);
        $this->assertGreaterThanOrEqual($stats['average_time'], $stats['slowest_query']);
        $this->assertLessThanOrEqual($stats['average_time'], $stats['fastest_query']);
        $this->assertGreaterThan(0, $stats['queries_per_second']);
    }

    public function testSlowQueryDetection(): void {
        // Set a very low threshold
        $this->profiler->setSlowQueryThreshold(0.001); // 1ms

        // Profile a slow query
        $queryId = $this->profiler->startQuery("SELECT * FROM large_table", []);
        usleep(2000); // 2ms - should be marked as slow
        $this->profiler->endQuery($queryId);

        $slowQueries = $this->profiler->getSlowQueries();
        $this->assertCount(1, $slowQueries);

        $stats = $this->profiler->getStats();
        $this->assertEquals(1, $stats['slow_queries_count']);
    }

    public function testDuplicateQueryDetection(): void {
        $sql = "SELECT * FROM users WHERE status = ?";

        // Execute the same query multiple times with different bindings
        for($i = 0; $i < 3; $i++) {
            $queryId = $this->profiler->startQuery($sql, ['active']);
            usleep(100);
            $this->profiler->endQuery($queryId);
        }

        // Execute a different query
        $queryId = $this->profiler->startQuery("SELECT COUNT(*) FROM posts", []);
        usleep(100);
        $this->profiler->endQuery($queryId);

        $duplicates = $this->profiler->getDuplicateQueries();

        // Should find the duplicated SELECT query
        $this->assertCount(1, $duplicates);

        $duplicate = array_values($duplicates)[0];
        $this->assertEquals(3, $duplicate['count']);
        $this->assertStringContainsString('users', $duplicate['sql']);
    }

    public function testProfilerEnableDisable(): void {
        $this->profiler->disable();
        $this->assertFalse($this->profiler->isEnabled());

        // When disabled, should return empty query ID
        $queryId = $this->profiler->startQuery("SELECT 1", []);
        $this->assertEmpty($queryId);

        $queries = $this->profiler->getQueries();
        $this->assertEmpty($queries);

        $this->profiler->enable();
        $this->assertTrue($this->profiler->isEnabled());
    }

    public function testProfilerClear(): void {
        // Add some queries
        $queryId = $this->profiler->startQuery("SELECT * FROM users", []);
        $this->profiler->endQuery($queryId);

        $this->assertCount(1, $this->profiler->getQueries());

        $this->profiler->clear();
        $this->assertEmpty($this->profiler->getQueries());
    }

    public function testProfilerExport(): void {
        // Add a query
        $queryId = $this->profiler->startQuery("SELECT * FROM users", []);
        usleep(1000);
        $this->profiler->endQuery($queryId, 5);

        $export = $this->profiler->export();

        $this->assertArrayHasKey('enabled', $export);
        $this->assertArrayHasKey('slow_query_threshold', $export);
        $this->assertArrayHasKey('stats', $export);
        $this->assertArrayHasKey('queries', $export);
        $this->assertArrayHasKey('slow_queries', $export);
        $this->assertArrayHasKey('duplicate_queries', $export);

        $this->assertTrue($export['enabled']);
        $this->assertCount(1, $export['queries']);
    }

    public function testMemoryUsageTracking(): void {
        $queryId = $this->profiler->startQuery("SELECT * FROM users", []);

        // Allocate some memory to simulate query processing
        $data = str_repeat('x', 1024 * 10); // 10KB

        $this->profiler->endQuery($queryId);

        $queries = $this->profiler->getQueries();
        $query   = $queries[0];

        $this->assertArrayHasKey('memory_usage', $query);
        $this->assertArrayHasKey('peak_memory_usage', $query);
        $this->assertArrayHasKey('memory_start', $query);
        $this->assertArrayHasKey('memory_end', $query);

        // Clean up
        unset($data);
    }

    public function testQueryNormalization(): void {
        // Test that SQL normalization works for duplicate detection
        $queries = [
            "SELECT * FROM users WHERE id = 1",
            "select * from users where id = 2", // Different case, different value
            "SELECT  *  FROM  users  WHERE  id = 3", // Extra spaces
        ];

        foreach($queries as $sql) {
            $queryId = $this->profiler->startQuery($sql, []);
            $this->profiler->endQuery($queryId);
        }

        $duplicates = $this->profiler->getDuplicateQueries();

        // All three should be considered duplicates due to normalization
        $this->assertCount(1, $duplicates);

        $duplicate = array_values($duplicates)[0];
        $this->assertEquals(3, $duplicate['count']);
    }
}
