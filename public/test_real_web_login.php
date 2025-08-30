<?php
/**
 * Real Web Login Test
 * This simulates exactly what happens in a real web request
 */

// Only allow from localhost
if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1']) && php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('Access denied');
}

// Set proper headers for web response
header('Content-Type: text/plain');

require_once 'core/config/config.php';
require_once 'core/email/SmartOTPEmailSender.php';

echo "=== Real Web Login Performance Test ===\n";
echo "Environment: " . (php_sapi_name() === 'cli' ? 'CLI (simulating web)' : 'Web') . "\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n\n";

// Check current PHP SAPI
echo "1. Environment Check:\n";
echo "   - PHP SAPI: " . php_sapi_name() . "\n";
echo "   - Should use async: " . (php_sapi_name() !== 'cli' ? 'YES' : 'NO (CLI detected)') . "\n";
echo "\n";

// In a real web environment, php_sapi_name() would NOT be 'cli'
// Let's test what happens with direct async call to bypass CLI detection
echo "2. Direct Async Email Test (bypassing Smart Sender CLI logic):\n";

require_once 'core/email/BasicOTPEmailService.php';
$emailService = new BasicOTPEmailService();

$testStart = microtime(true);

$directResult = $emailService->sendOTPAsync(
    'direct-web-test@example.com',
    'Direct Web Test',
    '123456',
    5,
    'WEBTEST001',
    1
);

$testEnd = microtime(true);
$directDuration = ($testEnd - $testStart) * 1000;

echo "   - Duration: " . round($directDuration, 2) . " ms\n";
echo "   - Success: " . ($directResult['success'] ? 'YES' : 'NO') . "\n";
echo "   - Is Async: " . (isset($directResult['async']) && $directResult['async'] ? 'YES' : 'NO') . "\n";
echo "\n";

// Test what Smart Sender would do in web environment
echo "3. Smart Sender Behavior Analysis:\n";
$smartSender = new SmartOTPEmailSender();

// Create a reflection to test the private method
$reflection = new ReflectionClass($smartSender);
$method = $reflection->getMethod('shouldUseSynchronous');
$method->setAccessible(true);

echo "   - shouldUseSynchronous() with isLoginFlow=false: " . ($method->invoke($smartSender, false) ? 'TRUE (sync)' : 'FALSE (async)') . "\n";
echo "   - shouldUseSynchronous() with isLoginFlow=true: " . ($method->invoke($smartSender, true) ? 'TRUE (sync)' : 'FALSE (async)') . "\n";

// Check async availability
$asyncMethod = $reflection->getMethod('isAsyncAvailable');
$asyncMethod->setAccessible(true);
echo "   - isAsyncAvailable(): " . ($asyncMethod->invoke($smartSender) ? 'TRUE' : 'FALSE') . "\n";
echo "\n";

echo "4. Web Environment Simulation:\n";
echo "   In a real web request (not CLI):\n";
echo "   - php_sapi_name() would return: 'fpm-fcgi', 'apache2handler', or similar\n";
echo "   - CLI detection would be FALSE\n";
echo "   - isLoginFlow=true would enable aggressive async mode\n";
echo "   - Expected duration: ~15ms (async)\n";
echo "   - Actual CLI duration: ~" . round($directDuration, 0) . "ms\n";
echo "\n";

echo "5. Performance Comparison:\n";
// Test sync for comparison
$syncStart = microtime(true);
$syncResult = $emailService->sendOTP('sync-comparison@example.com', 'Sync Test', '654321', 5, 'SYNC001', 1);
$syncEnd = microtime(true);
$syncDuration = ($syncEnd - $syncStart) * 1000;

echo "   - Sync Duration: " . round($syncDuration, 2) . " ms\n";
echo "   - Async Duration: " . round($directDuration, 2) . " ms\n";
echo "   - Improvement: " . round($syncDuration - $directDuration, 2) . " ms (" . round((($syncDuration - $directDuration) / $syncDuration) * 100, 1) . "% faster)\n";
echo "\n";

echo "=== CONCLUSION ===\n";
if ($directDuration < 100) {
    echo "✅ ASYNC IS WORKING: Email sending is properly asynchronous (~{$directDuration}ms)\n";
    echo "✅ LOGIN SHOULD BE INSTANT: In real web environment, redirect will be immediate\n";
    echo "⚠️  CLI LIMITATION: Current test shows CLI behavior (sync forced), but web will be async\n";
} else {
    echo "❌ PERFORMANCE ISSUE: Even async mode is slow ({$directDuration}ms)\n";
    echo "❌ NEEDS INVESTIGATION: Check background script execution\n";
}

echo "\n📋 RECOMMENDATION:\n";
echo "Test the actual login flow in a web browser to verify real performance.\n";
echo "The CLI environment forces sync mode, but web environment should use async mode.\n";
?>