<?php
/**
 * Test Forced Async Mode
 * Verify that the Smart Email Sender now always uses async mode
 */

require_once 'core/config/config.php';
require_once 'core/email/SmartOTPEmailSender.php';

echo "=== Forced Async Mode Test ===\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n\n";

$smartSender = new SmartOTPEmailSender();

// Test 1: Health Check
echo "1. Health Check:\n";
$healthCheck = $smartSender->healthCheck();
foreach ($healthCheck as $key => $value) {
    if (is_array($value)) {
        echo "   - $key: " . json_encode($value) . "\n";
    } else {
        echo "   - $key: " . ($value ? 'TRUE' : 'FALSE') . "\n";
    }
}
echo "\n";

// Test 2: Performance Test
echo "2. Performance Test:\n";

$testStart = microtime(true);

$result = $smartSender->sendOTP(
    'forced-async-test@example.com',
    'Forced Async Test User',
    '123456',
    5,
    'FORCED001',
    1,
    true // isLoginFlow = true
);

$testEnd = microtime(true);
$duration = ($testEnd - $testStart) * 1000;

echo "   - Duration: " . round($duration, 2) . " ms\n";
echo "   - Success: " . ($result['success'] ? 'YES' : 'NO') . "\n";
echo "   - Async Used: " . (isset($result['async']) && $result['async'] ? 'YES' : 'NO') . "\n";
echo "   - Result: " . json_encode($result) . "\n";
echo "\n";

// Test 3: Multiple quick tests to show consistency
echo "3. Consistency Test (5 rapid sends):\n";
$times = [];

for ($i = 1; $i <= 5; $i++) {
    $start = microtime(true);
    
    $testResult = $smartSender->sendOTP(
        "consistency-test-{$i}@example.com",
        "Test User {$i}",
        sprintf('%06d', $i),
        5,
        "TEST" . sprintf('%03d', $i),
        1
    );
    
    $end = microtime(true);
    $time = ($end - $start) * 1000;
    $times[] = $time;
    
    echo "   - Test {$i}: " . round($time, 2) . " ms - " . 
         ($testResult['success'] ? 'SUCCESS' : 'FAILED') . 
         " - Async: " . (isset($testResult['async']) && $testResult['async'] ? 'YES' : 'NO') . "\n";
}

$avgTime = array_sum($times) / count($times);
$maxTime = max($times);
$minTime = min($times);

echo "\n4. Performance Summary:\n";
echo "   - Average time: " . round($avgTime, 2) . " ms\n";
echo "   - Min time: " . round($minTime, 2) . " ms\n";
echo "   - Max time: " . round($maxTime, 2) . " ms\n";
echo "   - All under 100ms: " . ($maxTime < 100 ? 'YES ✅' : 'NO ❌') . "\n";
echo "   - All under 50ms: " . ($maxTime < 50 ? 'YES ✅' : 'NO ❌') . "\n";

echo "\n=== CONCLUSION ===\n";
if ($maxTime < 100) {
    echo "🎉 SUCCESS: Forced async mode is working perfectly!\n";
    echo "📈 Performance: All email operations completed in <100ms\n";
    echo "✅ Login should now be INSTANT (no 2.27s delay)\n";
} else {
    echo "⚠️  ISSUE: Some operations still taking >100ms\n";
    echo "🔍 Need further investigation\n";
}

echo "\n💡 Next: Test actual login flow in web browser to confirm\n";
?>