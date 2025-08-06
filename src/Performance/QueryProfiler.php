<?php

namespace KelvinKurniawan\LightORM\Performance;

class QueryProfiler {
    private array $queries            = [];
    private bool  $enabled            = FALSE;
    private float $slowQueryThreshold = 0.1; // 100ms

    /**
     * Enable query profiling
     */
    public function enable(): void {
        $this->enabled = TRUE;
    }

    /**
     * Disable query profiling
     */
    public function disable(): void {
        $this->enabled = FALSE;
    }

    /**
     * Check if profiling is enabled
     */
    public function isEnabled(): bool {
        return $this->enabled;
    }

    /**
     * Set slow query threshold (in seconds)
     */
    public function setSlowQueryThreshold(float $threshold): void {
        $this->slowQueryThreshold = $threshold;
    }

    /**
     * Start profiling a query
     */
    public function startQuery(string $sql, array $bindings = []): string {
        if(!$this->enabled) {
            return '';
        }

        $queryId = uniqid('query_', TRUE);

        $this->queries[$queryId] = [
            'id'                => $queryId,
            'sql'               => $sql,
            'bindings'          => $bindings,
            'start_time'        => microtime(TRUE),
            'memory_start'      => memory_get_usage(TRUE),
            'peak_memory_start' => memory_get_peak_usage(TRUE),
        ];

        return $queryId;
    }

    /**
     * End profiling a query
     */
    public function endQuery(string $queryId, ?int $affectedRows = NULL): void {
        if(!$this->enabled || !isset($this->queries[$queryId])) {
            return;
        }

        $endTime       = microtime(TRUE);
        $memoryEnd     = memory_get_usage(TRUE);
        $peakMemoryEnd = memory_get_peak_usage(TRUE);

        $query                      = &$this->queries[$queryId];
        $query['end_time']          = $endTime;
        $query['duration']          = $endTime - $query['start_time'];
        $query['memory_end']        = $memoryEnd;
        $query['memory_usage']      = $memoryEnd - $query['memory_start'];
        $query['peak_memory_end']   = $peakMemoryEnd;
        $query['peak_memory_usage'] = $peakMemoryEnd - $query['peak_memory_start'];
        $query['affected_rows']     = $affectedRows;
        $query['is_slow']           = $query['duration'] > $this->slowQueryThreshold;
        $query['timestamp']         = date('Y-m-d H:i:s', (int) $query['start_time']);
    }

    /**
     * Get all profiled queries
     */
    public function getQueries(): array {
        return array_values($this->queries);
    }

    /**
     * Get slow queries only
     */
    public function getSlowQueries(): array {
        return array_values(array_filter($this->queries, fn($query) => $query['is_slow'] ?? FALSE));
    }

    /**
     * Get query statistics
     */
    public function getStats(): array {
        if(empty($this->queries)) {
            return [
                'total_queries'        => 0,
                'total_time'           => 0,
                'average_time'         => 0,
                'slowest_query'        => 0,
                'fastest_query'        => 0,
                'slow_queries_count'   => 0,
                'total_memory_usage'   => 0,
                'average_memory_usage' => 0,
                'peak_memory_usage'    => 0,
                'queries_per_second'   => 0,
            ];
        }

        $totalTime    = array_sum(array_column($this->queries, 'duration'));
        $durations    = array_column($this->queries, 'duration');
        $memoryUsages = array_column($this->queries, 'memory_usage');

        return [
            'total_queries'        => count($this->queries),
            'total_time'           => round($totalTime, 4),
            'average_time'         => round($totalTime / count($this->queries), 4),
            'slowest_query'        => round(max($durations), 4),
            'fastest_query'        => round(min($durations), 4),
            'slow_queries_count'   => count($this->getSlowQueries()),
            'total_memory_usage'   => array_sum($memoryUsages),
            'average_memory_usage' => round(array_sum($memoryUsages) / count($memoryUsages)),
            'peak_memory_usage'    => !empty($memoryUsages) ? max($memoryUsages) : 0,
            'queries_per_second'   => $totalTime > 0 ? round(count($this->queries) / $totalTime, 2) : 0,
        ];
    }

    /**
     * Get duplicate queries
     */
    public function getDuplicateQueries(): array {
        $sqlCounts = [];

        foreach($this->queries as $query) {
            $normalizedSql = $this->normalizeSql($query['sql']);
            if(!isset($sqlCounts[$normalizedSql])) {
                $sqlCounts[$normalizedSql] = [
                    'sql'        => $query['sql'],
                    'count'      => 0,
                    'total_time' => 0,
                    'queries'    => []
                ];
            }

            $sqlCounts[$normalizedSql]['count']++;
            $sqlCounts[$normalizedSql]['total_time'] += $query['duration'];
            $sqlCounts[$normalizedSql]['queries'][]  = $query;
        }

        // Return only queries that were executed more than once
        return array_filter($sqlCounts, fn($item) => $item['count'] > 1);
    }

    /**
     * Clear all profiled queries
     */
    public function clear(): void {
        $this->queries = [];
    }

    /**
     * Export queries to array for logging/debugging
     */
    public function export(): array {
        return [
            'enabled'              => $this->enabled,
            'slow_query_threshold' => $this->slowQueryThreshold,
            'stats'                => $this->getStats(),
            'queries'              => $this->getQueries(),
            'slow_queries'         => $this->getSlowQueries(),
            'duplicate_queries'    => $this->getDuplicateQueries(),
        ];
    }

    /**
     * Normalize SQL for duplicate detection
     */
    private function normalizeSql(string $sql): string {
        // Remove extra whitespace and normalize
        $normalized = preg_replace('/\s+/', ' ', trim($sql));

        // Replace parameter placeholders with ?
        $normalized = preg_replace('/\$\d+|\?/', '?', $normalized);

        // Replace literal values (numbers, strings) with placeholders
        $normalized = preg_replace('/=\s*\d+/', '= ?', $normalized);
        $normalized = preg_replace('/=\s*\'[^\']*\'/', '= ?', $normalized);
        $normalized = preg_replace('/=\s*"[^"]*"/', '= ?', $normalized);

        // Handle IN clauses
        $normalized = preg_replace('/IN\s*\([^)]+\)/i', 'IN (?)', $normalized);

        return strtolower($normalized);
    }

    /**
     * Get query execution plan (if supported)
     */
    public function explainQuery(string $sql, array $bindings = []): array {
        // This would need database-specific implementation
        // For now, return placeholder
        return [
            'explain'  => 'Query explanation not implemented yet',
            'sql'      => $sql,
            'bindings' => $bindings
        ];
    }
}
