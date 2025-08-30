<?php
/**
 * Login Timing Diagnostic
 * Tests the exact timing behavior during login flow
 */

require_once 'core/config/config.php';
require_once 'core/email/SmartOTPEmailSender.php';

// Simulate the exact conditions during web login
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

echo "=== Login Timing Diagnostic ===\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n\n";

// Test the exact call that happens in checklogin.php
echo "1. Testing Smart Email Sender (Login Flow = TRUE):\n";

$startTime = microtime(true);

$smartEmailSender = new SmartOTPEmailSender();

// Check what the smart sender thinks about sync vs async
$healthCheck = $smartEmailSender->healthCheck();
echo "   - Health Check:\n";
foreach ($healthCheck as $key => $value) {
    if (is_array($value)) {
        echo "     * $key: " . json_encode($value) . "\n";
    } else {
        echo "     * $key: " . ($value ? 'TRUE' : 'FALSE') . "\n";
    }
}

// Simulate the exact call from checklogin.php
$testStart = microtime(true);

$emailResult = $smartEmailSender->sendOTP(
    'login-timing-test@example.com',
    'Test User',
    '123456',
    5,
    'TIMING001',
    1,
    true  // This is the key: isLoginFlow = true
);

$testEnd = microtime(true);
$testDuration = ($testEnd - $testStart) * 1000;

echo "\n2. Results:\n";
echo "   - Duration: " . round($testDuration, 2) . " ms\n";
echo "   - Success: " . ($emailResult['success'] ? 'YES' : 'NO') . "\n";
echo "   - Async Used: " . (isset($emailResult['async']) && $emailResult['async'] ? 'YES' : 'NO') . "\n";
echo "   - Full Result: " . json_encode($emailResult, JSON_PRETTY_PRINT) . "\n";

echo "\n3. Performance Analysis:\n";
if ($testDuration > 500) {
    echo "   - Status: SLOW (> 500ms) - This explains the delay!\n";
    echo "   - Likely Cause: Smart sender chose synchronous mode\n";
    echo "   - Check logs above for why sync mode was chosen\n";
} else {
    echo "   - Status: FAST (< 500ms) - Should be instant redirect\n";
    echo "   - Login flow should be working correctly\n";
}

echo "\n4. System Status:\n";
if (function_exists('sys_getloadavg')) {
    $load = sys_getloadavg();
    echo "   - Current Load: " . round($load[0], 2) . "\n";
    echo "   - High Load Threshold: 4.0\n";
    echo "   - Extreme Load Threshold: 8.0\n";
    echo "   - Load Status: " . ($load[0] > 8.0 ? 'EXTREME' : ($load[0] > 4.0 ? 'HIGH' : 'NORMAL')) . "\n";
}

$memoryUsage = memory_get_usage(true);
$memoryLimit = ini_get('memory_limit');
echo "   - Memory Usage: " . round($memoryUsage / 1024 / 1024, 2) . " MB\n";
echo "   - Memory Limit: $memoryLimit\n";

echo "\n5. Configuration Check:\n";
echo "   - EMAIL_ASYNC_ENABLED: " . (EMAIL_ASYNC_ENABLED ? 'TRUE' : 'FALSE') . "\n";
echo "   - FORCE_SYNC_EMAIL: " . (defined('FORCE_SYNC_EMAIL') && FORCE_SYNC_EMAIL ? 'TRUE' : 'FALSE') . "\n";
echo "   - FORCE_SYNC_LOGIN_EMAIL: " . (defined('FORCE_SYNC_LOGIN_EMAIL') && FORCE_SYNC_LOGIN_EMAIL ? 'TRUE' : 'FALSE') . "\n";
echo "   - PHP SAPI: " . php_sapi_name() . "\n";

$totalTime = (microtime(true) - $startTime) * 1000;

echo "\n=== DIAGNOSTIC COMPLETE ===\n";
echo "Total time: " . round($totalTime, 2) . " ms\n";

if ($testDuration < 100) {
    echo "RESULT: Login should be INSTANT - email sending is properly async\n";
} elseif ($testDuration < 500) {
    echo "RESULT: Login should be FAST - minor delay but acceptable\n";
} else {
    echo "RESULT: Login will be SLOW - this explains the delay you're experiencing\n";
    echo "ACTION NEEDED: Check why synchronous mode is being used\n";
}
?>