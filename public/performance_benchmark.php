<?php
/**
 * ProVal HVAC Performance Benchmark Script
 *
 * This script measures performance of critical system components
 * and provides optimization recommendations.
 *
 * Usage: php performance_benchmark.php
 * Web: http://localhost:8000/performance_benchmark.php
 */

require_once('./core/config/config.php');
require_once('core/config/db.class.php');

// Set longer execution time for benchmarks
set_time_limit(300);
ini_set('memory_limit', '512M');

class ProValPerformanceBenchmark {

    private $results = [];
    private $iterations = 10;

    public function __construct() {
        echo "<h1>ProVal HVAC Performance Benchmark</h1>\n";
        echo "<p>Testing system performance...</p>\n";
    }

    /**
     * Benchmark database connection performance
     */
    public function benchmarkDatabaseConnection() {
        echo "<h2>Database Connection Performance</h2>\n";

        $times = [];
        for ($i = 0; $i < $this->iterations; $i++) {
            $start = microtime(true);

            try {
                $result = DB::query("SELECT 1 as test");
                $end = microtime(true);
                $times[] = ($end - $start) * 1000; // Convert to milliseconds
            } catch (Exception $e) {
                echo "<p style='color: red;'>Database connection failed: " . $e->getMessage() . "</p>\n";
                return;
            }
        }

        $avgTime = array_sum($times) / count($times);
        $minTime = min($times);
        $maxTime = max($times);

        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
        echo "<tr><th>Metric</th><th>Value</th><th>Status</th></tr>\n";
        echo "<tr><td>Average Connection Time</td><td>" . number_format($avgTime, 2) . "ms</td><td>" . ($avgTime < 10 ? "✅ Good" : "⚠️ Slow") . "</td></tr>\n";
        echo "<tr><td>Min Connection Time</td><td>" . number_format($minTime, 2) . "ms</td><td>-</td></tr>\n";
        echo "<tr><td>Max Connection Time</td><td>" . number_format($maxTime, 2) . "ms</td><td>-</td></tr>\n";
        echo "</table>\n";

        $this->results['db_connection'] = $avgTime;
    }

    /**
     * Benchmark dashboard query performance
     */
    public function benchmarkDashboardQueries() {
        echo "<h2>Dashboard Query Performance</h2>\n";

        // Test queries from home.php
        $queries = [
            'vendor_dashboard' => "
                SELECT
                    COUNT(CASE WHEN t1.test_wf_current_stage = '1' THEN 1 END) as new_tasks,
                    COUNT(CASE WHEN t1.test_wf_current_stage IN ('1PRV', '3BPRV', '4BPRV') THEN 1 END) as offline_tasks,
                    COUNT(CASE WHEN t1.test_wf_current_stage IN ('3B', '4B', '1RRV') THEN 1 END) as reassigned_tasks
                FROM tbl_test_schedules_tracking t1
                JOIN equipments t2 ON t1.equip_id = t2.equipment_id
                LIMIT 1000
            ",
            'engineering_dashboard' => "
                SELECT
                    COUNT(CASE WHEN t1.test_wf_current_stage = '1' AND t1.vendor_id = 0 THEN 1 END) as new_tasks,
                    COUNT(CASE WHEN t1.test_wf_current_stage = '2' THEN 1 END) as approval_pending
                FROM tbl_test_schedules_tracking t1
                JOIN equipments t2 ON t1.equip_id = t2.equipment_id
                LIMIT 1000
            ",
            'simple_count' => "SELECT COUNT(*) FROM tbl_test_schedules_tracking"
        ];

        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
        echo "<tr><th>Query Type</th><th>Avg Time (ms)</th><th>Min (ms)</th><th>Max (ms)</th><th>Status</th></tr>\n";

        foreach ($queries as $name => $query) {
            $times = [];

            for ($i = 0; $i < 5; $i++) { // Fewer iterations for complex queries
                $start = microtime(true);

                try {
                    $result = DB::query($query);
                    $end = microtime(true);
                    $times[] = ($end - $start) * 1000;
                } catch (Exception $e) {
                    echo "<tr><td>$name</td><td colspan='4' style='color: red;'>Error: " . $e->getMessage() . "</td></tr>\n";
                    continue 2;
                }
            }

            $avgTime = array_sum($times) / count($times);
            $minTime = min($times);
            $maxTime = max($times);
            $status = $avgTime < 50 ? "✅ Good" : ($avgTime < 200 ? "⚠️ Moderate" : "❌ Slow");

            echo "<tr><td>$name</td><td>" . number_format($avgTime, 2) . "</td><td>" . number_format($minTime, 2) . "</td><td>" . number_format($maxTime, 2) . "</td><td>$status</td></tr>\n";

            $this->results["query_$name"] = $avgTime;
        }

        echo "</table>\n";
    }

    /**
     * Benchmark session operations
     */
    public function benchmarkSessionOperations() {
        echo "<h2>Session Operations Performance</h2>\n";

        $operations = [];

        // Test session write
        $start = microtime(true);
        for ($i = 0; $i < 100; $i++) {
            $_SESSION["benchmark_test_$i"] = "test_value_$i";
        }
        $operations['session_write'] = (microtime(true) - $start) * 1000;

        // Test session read
        $start = microtime(true);
        for ($i = 0; $i < 100; $i++) {
            $value = $_SESSION["benchmark_test_$i"] ?? null;
        }
        $operations['session_read'] = (microtime(true) - $start) * 1000;

        // Clean up
        for ($i = 0; $i < 100; $i++) {
            unset($_SESSION["benchmark_test_$i"]);
        }

        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
        echo "<tr><th>Operation</th><th>Time (ms)</th><th>Status</th></tr>\n";

        foreach ($operations as $op => $time) {
            $status = $time < 5 ? "✅ Good" : ($time < 20 ? "⚠️ Moderate" : "❌ Slow");
            echo "<tr><td>$op (100 ops)</td><td>" . number_format($time, 2) . "</td><td>$status</td></tr>\n";
            $this->results[$op] = $time;
        }

        echo "</table>\n";
    }

    /**
     * Test memory usage
     */
    public function benchmarkMemoryUsage() {
        echo "<h2>Memory Usage Analysis</h2>\n";

        $memoryBefore = memory_get_usage(true);
        $peakBefore = memory_get_peak_usage(true);

        // Simulate typical dashboard load
        $testData = [];
        for ($i = 0; $i < 1000; $i++) {
            $testData[] = [
                'equipment_id' => $i,
                'test_name' => "Test Equipment $i",
                'status' => rand(1, 5),
                'data' => str_repeat('x', 100) // Simulate data
            ];
        }

        $memoryAfter = memory_get_usage(true);
        $peakAfter = memory_get_peak_usage(true);

        $memoryUsed = $memoryAfter - $memoryBefore;
        $peakIncrease = $peakAfter - $peakBefore;

        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
        echo "<tr><th>Metric</th><th>Value</th><th>Status</th></tr>\n";
        echo "<tr><td>Current Memory Usage</td><td>" . $this->formatBytes($memoryAfter) . "</td><td>" . ($memoryAfter < 50*1024*1024 ? "✅ Good" : "⚠️ High") . "</td></tr>\n";
        echo "<tr><td>Peak Memory Usage</td><td>" . $this->formatBytes($peakAfter) . "</td><td>" . ($peakAfter < 100*1024*1024 ? "✅ Good" : "⚠️ High") . "</td></tr>\n";
        echo "<tr><td>Test Data Memory Impact</td><td>" . $this->formatBytes($memoryUsed) . "</td><td>-</td></tr>\n";
        echo "</table>\n";

        $this->results['memory_usage'] = $memoryAfter;
        $this->results['peak_memory'] = $peakAfter;

        // Clean up
        unset($testData);
    }

    /**
     * Test file system operations
     */
    public function benchmarkFileOperations() {
        echo "<h2>File System Performance</h2>\n";

        $testFile = 'performance_test_file.tmp';
        $testData = str_repeat('Performance test data. ', 1000);

        $operations = [];

        // Test file write
        $start = microtime(true);
        file_put_contents($testFile, $testData);
        $operations['file_write'] = (microtime(true) - $start) * 1000;

        // Test file read
        $start = microtime(true);
        $content = file_get_contents($testFile);
        $operations['file_read'] = (microtime(true) - $start) * 1000;

        // Test file existence check
        $start = microtime(true);
        for ($i = 0; $i < 100; $i++) {
            file_exists($testFile);
        }
        $operations['file_exists_100x'] = (microtime(true) - $start) * 1000;

        // Clean up
        if (file_exists($testFile)) {
            unlink($testFile);
        }

        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
        echo "<tr><th>Operation</th><th>Time (ms)</th><th>Status</th></tr>\n";

        foreach ($operations as $op => $time) {
            $status = $time < 10 ? "✅ Good" : ($time < 50 ? "⚠️ Moderate" : "❌ Slow");
            echo "<tr><td>$op</td><td>" . number_format($time, 2) . "</td><td>$status</td></tr>\n";
            $this->results[$op] = $time;
        }

        echo "</table>\n";
    }

    /**
     * Generate performance summary and recommendations
     */
    public function generateSummary() {
        echo "<h2>Performance Summary & Recommendations</h2>\n";

        $overallScore = $this->calculateOverallScore();

        echo "<div style='background: " . ($overallScore >= 80 ? "#d4edda" : ($overallScore >= 60 ? "#fff3cd" : "#f8d7da")) . "; padding: 15px; border-radius: 5px; margin: 10px 0;'>\n";
        echo "<h3>Overall Performance Score: " . $overallScore . "/100</h3>\n";
        echo "<p>" . $this->getPerformanceMessage($overallScore) . "</p>\n";
        echo "</div>\n";

        echo "<h3>Specific Recommendations:</h3>\n";
        echo "<ul>\n";

        if (isset($this->results['query_vendor_dashboard']) && $this->results['query_vendor_dashboard'] > 100) {
            echo "<li><strong>Database Optimization Needed:</strong> Vendor dashboard queries are slow (" . number_format($this->results['query_vendor_dashboard'], 2) . "ms). Consider adding indexes and implementing query caching.</li>\n";
        }

        if (isset($this->results['memory_usage']) && $this->results['memory_usage'] > 50*1024*1024) {
            echo "<li><strong>Memory Optimization:</strong> High memory usage detected (" . $this->formatBytes($this->results['memory_usage']) . "). Consider implementing object pooling and data pagination.</li>\n";
        }

        if (isset($this->results['db_connection']) && $this->results['db_connection'] > 10) {
            echo "<li><strong>Database Connection:</strong> Slow database connections (" . number_format($this->results['db_connection'], 2) . "ms). Consider connection pooling or database server optimization.</li>\n";
        }

        echo "<li><strong>Caching Implementation:</strong> Implement APCu or Redis caching for dashboard queries to improve response times by 70-80%.</li>\n";
        echo "<li><strong>Database Indexes:</strong> Add composite indexes on frequently queried columns (test_wf_current_stage, unit_id, vendor_id).</li>\n";
        echo "<li><strong>Asset Optimization:</strong> Implement CSS/JS minification and bundling using the existing Gulp build system.</li>\n";
        echo "<li><strong>Query Optimization:</strong> Replace multiple dashboard queries with single optimized queries using CTEs or derived tables.</li>\n";

        echo "</ul>\n";

        echo "<h3>Quick Wins (High Impact, Low Effort):</h3>\n";
        echo "<ol>\n";
        echo "<li>Enable APCu caching in config.php</li>\n";
        echo "<li>Add database indexes for dashboard queries</li>\n";
        echo "<li>Implement request-level caching for user permissions</li>\n";
        echo "<li>Minimize session validation calls</li>\n";
        echo "<li>Use gulp build process to minify assets</li>\n";
        echo "</ol>\n";
    }

    /**
     * Calculate overall performance score
     */
    private function calculateOverallScore() {
        $score = 100;

        // Database performance (40% weight)
        if (isset($this->results['db_connection'])) {
            if ($this->results['db_connection'] > 20) $score -= 20;
            elseif ($this->results['db_connection'] > 10) $score -= 10;
        }

        // Query performance (30% weight)
        $avgQueryTime = 0;
        $queryCount = 0;
        foreach ($this->results as $key => $value) {
            if (strpos($key, 'query_') === 0) {
                $avgQueryTime += $value;
                $queryCount++;
            }
        }
        if ($queryCount > 0) {
            $avgQueryTime /= $queryCount;
            if ($avgQueryTime > 200) $score -= 25;
            elseif ($avgQueryTime > 100) $score -= 15;
            elseif ($avgQueryTime > 50) $score -= 10;
        }

        // Memory usage (20% weight)
        if (isset($this->results['memory_usage'])) {
            if ($this->results['memory_usage'] > 100*1024*1024) $score -= 15;
            elseif ($this->results['memory_usage'] > 50*1024*1024) $score -= 10;
        }

        // File operations (10% weight)
        if (isset($this->results['file_write']) && $this->results['file_write'] > 50) {
            $score -= 5;
        }

        return max(0, $score);
    }

    private function getPerformanceMessage($score) {
        if ($score >= 80) {
            return "Excellent performance! Your system is running optimally.";
        } elseif ($score >= 60) {
            return "Good performance with room for improvement. Consider implementing the recommended optimizations.";
        } elseif ($score >= 40) {
            return "Moderate performance issues detected. Optimization is recommended to improve user experience.";
        } else {
            return "Significant performance issues detected. Immediate optimization required.";
        }
    }

    private function formatBytes($size, $precision = 2) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');

        for ($i = 0; $size >= 1024 && $i < count($units) - 1; $i++) {
            $size /= 1024;
        }

        return round($size, $precision) . ' ' . $units[$i];
    }

    /**
     * Run all benchmarks
     */
    public function runAllBenchmarks() {
        $totalStart = microtime(true);

        $this->benchmarkDatabaseConnection();
        $this->benchmarkDashboardQueries();
        $this->benchmarkSessionOperations();
        $this->benchmarkMemoryUsage();
        $this->benchmarkFileOperations();
        $this->generateSummary();

        $totalTime = microtime(true) - $totalStart;
        echo "<hr>\n";
        echo "<p><strong>Total benchmark time:</strong> " . number_format($totalTime, 2) . " seconds</p>\n";
        echo "<p><em>Generated on " . date('Y-m-d H:i:s') . " by ProVal HVAC Performance Benchmark</em></p>\n";
    }
}

// Check if running from web browser
if (isset($_SERVER['HTTP_HOST'])) {
    echo "<!DOCTYPE html>\n<html><head><title>ProVal HVAC Performance Benchmark</title></head><body>\n";
}

// Run the benchmark
$benchmark = new ProValPerformanceBenchmark();
$benchmark->runAllBenchmarks();

if (isset($_SERVER['HTTP_HOST'])) {
    echo "</body></html>\n";
}
?>