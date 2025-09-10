<?php
/**
 * Check Instrument Logging
 * This script verifies that instrument add/remove operations are being logged
 */

require_once('./core/config/config.php');
require_once('./core/config/db.class.php');

// Set content type to HTML for browser viewing
header('Content-Type: text/html; charset=UTF-8');

echo "<h2>Instrument Operations Logging Check</h2>";
echo "<pre>";

try {
    echo "🔍 Checking instrument-related log entries...\n\n";
    
    // Check recent instrument operations
    $logs = DB::query(
        "SELECT log_id, change_type, change_description, change_by, change_datetime, unit_id
         FROM log 
         WHERE change_type IN ('test_instrument_add', 'test_instrument_remove', 'test_instrument_remove_blocked')
         ORDER BY change_datetime DESC 
         LIMIT 20"
    );
    
    if (!empty($logs)) {
        echo "✅ Found " . count($logs) . " recent instrument operation logs:\n";
        echo str_repeat("-", 80) . "\n";
        
        foreach ($logs as $log) {
            echo "📅 " . $log['change_datetime'] . " | ";
            echo "👤 User ID: " . $log['change_by'] . " | ";
            echo "🏢 Unit: " . $log['unit_id'] . "\n";
            echo "🔧 Action: " . $log['change_type'] . "\n";
            echo "📝 Description: " . $log['change_description'] . "\n";
            echo str_repeat("-", 80) . "\n";
        }
    } else {
        echo "ℹ️  No instrument operation logs found yet.\n";
        echo "   This could mean:\n";
        echo "   1. No instruments have been added/removed recently\n";
        echo "   2. Logging is not working (needs investigation)\n\n";
    }
    
    // Check if there are any logs at all
    $totalLogs = DB::queryFirstField("SELECT COUNT(*) FROM log");
    echo "📊 Total log entries in database: " . $totalLogs . "\n\n";
    
    // Check recent logs of any type
    echo "🔍 Recent log entries (any type):\n";
    $recentLogs = DB::query(
        "SELECT change_type, COUNT(*) as count 
         FROM log 
         WHERE change_datetime >= DATE_SUB(NOW(), INTERVAL 7 DAY)
         GROUP BY change_type 
         ORDER BY count DESC 
         LIMIT 10"
    );
    
    if (!empty($recentLogs)) {
        foreach ($recentLogs as $logType) {
            echo "   - " . $logType['change_type'] . ": " . $logType['count'] . " entries\n";
        }
    } else {
        echo "   No recent logs found\n";
    }
    
    echo "\n";
    
    // Show log table structure
    echo "🗂️  Log table structure:\n";
    $structure = DB::query("DESCRIBE log");
    foreach ($structure as $column) {
        echo "   - " . $column['Field'] . " (" . $column['Type'] . ")\n";
    }
    
    echo "\n✅ Logging system verification complete!\n";
    
} catch (Exception $e) {
    echo "❌ Error checking logs: " . $e->getMessage() . "\n";
    error_log("Log check error: " . $e->getMessage());
}

echo "</pre>";

// Clean up note
echo "<p><strong>Note:</strong> This is a diagnostic script. You can delete it after checking the logs.</p>";
?>