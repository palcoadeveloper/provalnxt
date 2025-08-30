<?php
/**
 * Web Login Flow Performance Test
 * Tests the exact performance scenario that happens during web login
 */

require_once 'core/config/config.php';

// Create a custom test class that simulates web environment
class WebLoginPerformanceTest {
    private $smartSender;
    
    public function __construct() {
        require_once 'core/email/SmartOTPEmailSender.php';
        $this->smartSender = new SmartOTPEmailSender();
    }
    
    public function runTest() {
        echo "=== Web Login Flow Performance Test ===\n";
        echo "Timestamp: " . date('Y-m-d H:i:s') . "\n\n";
        
        // Test 1: System Conditions
        echo "1. System Conditions:\n";
        $healthCheck = $this->smartSender->healthCheck();
        echo "   - Async Available: " . ($healthCheck['async_available'] ? 'YES' : 'NO') . "\n";
        echo "   - Should Use Sync: " . ($healthCheck['should_use_sync'] ? 'YES' : 'NO') . "\n";
        
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            echo "   - Current Load: " . round($load[0], 2) . "\n";
            echo "   - High Load Threshold: 4.0\n";
            echo "   - Extreme Load Threshold: 8.0\n";
        }
        echo "\n";
        
        // Test 2: Direct Async Test (bypassing shouldUseSynchronous for CLI)
        echo "2. Direct Async Email Test:\n";
        require_once 'core/email/BasicOTPEmailService.php';
        $emailService = new BasicOTPEmailService();
        
        $testEmail = 'direct-async-' . time() . '@example.com';
        $startTime = microtime(true);
        
        $result = $emailService->sendOTPAsync(
            $testEmail,
            'Direct Test User',
            '123456',
            5,
            'DIRECT001',
            1
        );
        
        $endTime = microtime(true);
        $duration = ($endTime - $startTime) * 1000;
        
        echo "   - Duration: " . round($duration, 2) . " ms\n";
        echo "   - Success: " . ($result['success'] ? 'YES' : 'NO') . "\n";
        echo "   - Async: " . (isset($result['async']) && $result['async'] ? 'YES' : 'NO') . "\n";
        echo "   - Result: " . json_encode($result) . "\n";
        echo "\n";
        
        // Test 3: Check if exec is working properly
        echo "3. System Capability Check:\n";
        echo "   - exec() available: " . (function_exists('exec') ? 'YES' : 'NO') . "\n";
        echo "   - PHP Binary exists: " . (file_exists(PHP_BINARY) ? 'YES' : 'NO') . "\n";
        echo "   - Background script exists: " . (file_exists(__DIR__ . '/core/email/background_email_sender.php') ? 'YES' : 'NO') . "\n";
        
        // Test exec directly
        $testCommand = 'echo "exec test"';
        $output = [];
        $returnCode = 0;
        exec($testCommand, $output, $returnCode);
        echo "   - Exec test result: " . ($returnCode === 0 ? 'SUCCESS' : 'FAILED') . "\n";
        echo "   - Exec output: " . implode(' ', $output) . "\n";
        echo "\n";
        
        // Test 4: Performance Comparison
        echo "4. Performance Comparison:\n";
        
        // Sync test
        echo "   Testing Synchronous Email Sending:\n";
        $syncStartTime = microtime(true);
        $syncResult = $emailService->sendOTP(
            'sync-test-' . time() . '@example.com',
            'Sync Test User',
            '654321',
            5,
            'SYNC001',
            1
        );
        $syncEndTime = microtime(true);
        $syncDuration = ($syncEndTime - $syncStartTime) * 1000;
        
        echo "     - Duration: " . round($syncDuration, 2) . " ms\n";
        echo "     - Success: " . ($syncResult['success'] ? 'YES' : 'NO') . "\n";
        
        // Async test
        echo "   Testing Asynchronous Email Sending:\n";
        $asyncStartTime = microtime(true);
        $asyncResult = $emailService->sendOTPAsync(
            'async-test-' . time() . '@example.com',
            'Async Test User',
            '789123',
            5,
            'ASYNC001',
            1
        );
        $asyncEndTime = microtime(true);
        $asyncDuration = ($asyncEndTime - $asyncStartTime) * 1000;
        
        echo "     - Duration: " . round($asyncDuration, 2) . " ms\n";
        echo "     - Success: " . ($asyncResult['success'] ? 'YES' : 'NO') . "\n";
        echo "     - Is Async: " . (isset($asyncResult['async']) && $asyncResult['async'] ? 'YES' : 'NO') . "\n";
        echo "\n";
        
        // Summary
        echo "5. Performance Summary:\n";
        echo "   - Sync Email Duration: " . round($syncDuration, 2) . " ms\n";
        echo "   - Async Email Duration: " . round($asyncDuration, 2) . " ms\n";
        echo "   - Performance Improvement: " . round($syncDuration - $asyncDuration, 2) . " ms\n";
        echo "   - Target for Login Flow: < 500 ms\n";
        echo "   - Async Performance: " . ($asyncDuration < 500 ? 'EXCELLENT' : 'NEEDS IMPROVEMENT') . "\n";
        echo "\n";
        
        echo "=== TEST COMPLETED ===\n";
        return [
            'sync_duration' => $syncDuration,
            'async_duration' => $asyncDuration,
            'async_success' => $asyncResult['success'] && isset($asyncResult['async']) && $asyncResult['async'],
            'performance_target_met' => $asyncDuration < 500
        ];
    }
}

// Run the test
$test = new WebLoginPerformanceTest();
$results = $test->runTest();

if ($results['async_success'] && $results['performance_target_met']) {
    echo "OVERALL RESULT: PASS - Login flow performance optimized successfully!\n";
} else {
    echo "OVERALL RESULT: REVIEW NEEDED - Some optimizations may be required\n";
}
?>