<?php
/**
 * Test Script for Database Error Logging
 * This script tests the error logging functionality
 */

require_once('core/config/config.php');
require_once('core/error/error_logger.php');

// Start session for testing
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set up test session data
$_SESSION['unit_id'] = 1;
$_SESSION['user_id'] = 1;

echo "<h2>Database Error Logging Test</h2>\n";

// Test 1: Basic error logging
echo "<h3>Test 1: Basic Error Logging</h3>\n";
try {
    $result = logDatabaseError("Test error message from test_error_logging.php", [
        'operation_name' => 'test_basic_error_logging',
        'unit_id' => 1,
        'val_wf_id' => 'TEST-VAL-001',
        'equip_id' => 123
    ]);
    
    if ($result) {
        echo "✅ Basic error logging test PASSED<br>\n";
    } else {
        echo "❌ Basic error logging test FAILED<br>\n";
    }
} catch (Exception $e) {
    echo "❌ Exception in basic test: " . $e->getMessage() . "<br>\n";
}

// Test 2: Error logging with minimal context
echo "<h3>Test 2: Minimal Context Error Logging</h3>\n";
try {
    $result = logDatabaseError("Test error with minimal context", [
        'operation_name' => 'test_minimal_context'
    ]);
    
    if ($result) {
        echo "✅ Minimal context error logging test PASSED<br>\n";
    } else {
        echo "❌ Minimal context error logging test FAILED<br>\n";
    }
} catch (Exception $e) {
    echo "❌ Exception in minimal context test: " . $e->getMessage() . "<br>\n";
}

// Test 3: Auto context extraction
echo "<h3>Test 3: Auto Context Extraction</h3>\n";
try {
    $_GET['val_wf_id'] = 'TEST-AUTO-001';
    $_GET['equipment_id'] = 456;
    
    $context = extractCommonContext();
    $result = logDatabaseError("Test error with auto-extracted context", $context);
    
    if ($result) {
        echo "✅ Auto context extraction test PASSED<br>\n";
        echo "Context extracted: " . print_r($context, true) . "<br>\n";
    } else {
        echo "❌ Auto context extraction test FAILED<br>\n";
    }
} catch (Exception $e) {
    echo "❌ Exception in auto context test: " . $e->getMessage() . "<br>\n";
}

// Test 4: Check database entries
echo "<h3>Test 4: Verify Database Entries</h3>\n";
try {
    $recentErrors = DB::query("SELECT * FROM error_log WHERE operation_name LIKE 'test_%' ORDER BY error_time DESC LIMIT 5");
    
    if (!empty($recentErrors)) {
        echo "✅ Found " . count($recentErrors) . " test error entries in database<br>\n";
        echo "<table border='1' style='margin: 10px 0;'>\n";
        echo "<tr><th>ID</th><th>Time</th><th>Message</th><th>Operation</th><th>Unit ID</th><th>Val WF ID</th><th>Equip ID</th></tr>\n";
        
        foreach ($recentErrors as $error) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($error['id']) . "</td>";
            echo "<td>" . htmlspecialchars($error['error_time']) . "</td>";
            echo "<td>" . htmlspecialchars($error['error_message']) . "</td>";
            echo "<td>" . htmlspecialchars($error['operation_name']) . "</td>";
            echo "<td>" . htmlspecialchars($error['current_unit_id']) . "</td>";
            echo "<td>" . htmlspecialchars($error['current_val_wf_id']) . "</td>";
            echo "<td>" . htmlspecialchars($error['equip_id']) . "</td>";
            echo "</tr>\n";
        }
        echo "</table>\n";
    } else {
        echo "❌ No test error entries found in database<br>\n";
    }
} catch (Exception $e) {
    echo "❌ Exception checking database entries: " . $e->getMessage() . "<br>\n";
}

// Test 5: Operation name mapping
echo "<h3>Test 5: Operation Name Mapping</h3>\n";
$testMappings = [
    'generateplannedvsactualrpt.php' => 'report_planned_vs_actual',
    'getschedule.php' => 'search_schedule_validation',
    'manageuserdetails.php' => 'manage_user_details',
    'unknown_file.php' => 'operation_unknown_file'
];

foreach ($testMappings as $filename => $expectedOperation) {
    $actualOperation = getOperationName($filename);
    if ($actualOperation === $expectedOperation) {
        echo "✅ Mapping for $filename: $actualOperation<br>\n";
    } else {
        echo "❌ Mapping for $filename: expected $expectedOperation, got $actualOperation<br>\n";
    }
}

echo "<h3>Summary</h3>\n";
echo "Database error logging implementation testing completed.<br>\n";
echo "Check the results above to verify functionality.<br>\n";
echo "<br><strong>Note:</strong> You can delete the test entries from error_log table if needed:<br>\n";
echo "<code>DELETE FROM error_log WHERE operation_name LIKE 'test_%';</code><br>\n";
?>