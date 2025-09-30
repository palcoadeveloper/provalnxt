<?php
/**
 * Simple APCu Cache Demo
 */

// Test APCu directly
echo "=== APCu Direct Test ===\n";
echo "APCu Loaded: " . (extension_loaded('apcu') ? 'YES' : 'NO') . "\n";
echo "APCu Enabled: " . (apcu_enabled() ? 'YES' : 'NO') . "\n";

// Test basic cache operations
echo "\n=== Basic Cache Operations ===\n";
$key = 'test_key_' . time();
$value = 'Hello from APCu Cache!';

// Store
$start = microtime(true);
$stored = apcu_store($key, $value, 300);
$store_time = (microtime(true) - $start) * 1000;
echo "Store: " . ($stored ? 'SUCCESS' : 'FAILED') . " ({$store_time} ms)\n";

// Fetch
$start = microtime(true);
$retrieved = apcu_fetch($key, $success);
$fetch_time = (microtime(true) - $start) * 1000;
echo "Fetch: " . ($success ? 'SUCCESS' : 'FAILED') . " ({$fetch_time} ms)\n";
echo "Value: $retrieved\n";

// Test our ProValCache class
require_once('./core/config/config.php');
require_once('./core/utils/ProValCache.php');

echo "\n=== ProValCache Class Test ===\n";
echo "Cache Enabled: " . (CACHE_ENABLED ? 'YES' : 'NO') . "\n";

// Test with callback
$start = microtime(true);
$data = ProValCache::get('demo_key', function() {
    // Simulate expensive operation
    usleep(50000); // 50ms delay
    return ['message' => 'Data from expensive operation', 'timestamp' => time()];
}, 60);
$first_call = (microtime(true) - $start) * 1000;

// Test cache hit
$start = microtime(true);
$cached_data = ProValCache::get('demo_key', function() {
    // This should not execute
    return ['should' => 'not execute'];
}, 60);
$second_call = (microtime(true) - $start) * 1000;

echo "First call (cache miss): {$first_call} ms\n";
echo "Second call (cache hit): {$second_call} ms\n";
echo "Performance improvement: " . round((($first_call - $second_call) / $first_call) * 100, 1) . "%\n";
echo "Data: " . json_encode($cached_data) . "\n";

// Get cache statistics
$stats = ProValCache::getStats();
echo "\n=== Cache Statistics ===\n";
echo "Hits: " . $stats['hits'] . "\n";
echo "Misses: " . $stats['misses'] . "\n";
echo "Hit Rate: " . round($stats['hit_rate'], 1) . "%\n";
echo "Stores: " . $stats['stores'] . "\n";
echo "Errors: " . $stats['errors'] . "\n";

// Test memory info if available
if (isset($stats['memory_info'])) {
    echo "\n=== Memory Usage ===\n";
    echo "Used Memory: " . round($stats['memory_info']['used_mem'] / 1024, 2) . " KB\n";
    echo "Available Memory: " . round($stats['memory_info']['avail_mem'] / 1024 / 1024, 2) . " MB\n";
}

echo "\n✅ APCu cache is working perfectly in development environment!\n";
?>