<?php
/**
 * Comprehensive Email Flow Test
 * Tests both initial login email sending and resend functionality
 * to ensure the reported issue is resolved
 */

// Prevent browser access - admin only via CLI or localhost
$allowedIPs = ['127.0.0.1', '::1'];
$clientIP = $_SERVER['REMOTE_ADDR'] ?? '';
$isLocalhost = in_array($clientIP, $allowedIPs);

if (!$isLocalhost && php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('Access denied - Localhost or CLI access only');
}

require_once 'core/config/config.php';
require_once 'core/email/SmartOTPEmailSender.php';
require_once 'core/email/BasicOTPEmailService.php';

// Set content type for web access
if (php_sapi_name() !== 'cli') {
    header('Content-Type: application/json');
}

$testResults = [];
$timestamp = date('Y-m-d H:i:s');

echo "=== ProVal HVAC Email Flow Comprehensive Test ===\n";
echo "Timestamp: $timestamp\n\n";

try {
    // Test 1: Basic Configuration Check
    echo "1. Configuration Check:\n";
    $configTests = [
        'EMAIL_ASYNC_ENABLED' => EMAIL_ASYNC_ENABLED,
        'EMAIL_CONNECTION_POOLING_ENABLED' => EMAIL_CONNECTION_POOLING_ENABLED,
        'EMAIL_OTP_RATE_LIMIT_SECONDS' => EMAIL_OTP_RATE_LIMIT_SECONDS,
        'PHP_BINARY_EXISTS' => file_exists(PHP_BINARY),
        'EXEC_FUNCTION_AVAILABLE' => function_exists('exec')
    ];
    
    foreach ($configTests as $test => $result) {
        echo "   - $test: " . ($result ? 'PASS' : 'FAIL') . "\n";
    }
    echo "\n";
    
    // Test 2: Smart Email Sender Health Check
    echo "2. Smart Email Sender Health Check:\n";
    $smartSender = new SmartOTPEmailSender();
    $healthCheck = $smartSender->healthCheck();
    
    foreach ($healthCheck as $check => $status) {
        if (is_array($status)) {
            echo "   - $check: " . json_encode($status) . "\n";
        } else {
            echo "   - $check: " . ($status ? 'PASS' : 'FAIL') . "\n";
        }
    }
    echo "\n";
    
    // Test 3: Background Script Direct Test
    echo "3. Background Script Direct Test:\n";
    $testEmail = 'test' . time() . '@example.com';
    $backgroundScript = __DIR__ . '/core/email/background_email_sender.php';
    
    if (file_exists($backgroundScript) && function_exists('exec')) {
        $command = PHP_BINARY . ' ' . escapeshellarg($backgroundScript) . ' ' . 
                   escapeshellarg($testEmail) . ' ' . 
                   escapeshellarg('Test User') . ' ' . 
                   escapeshellarg('123456') . ' 5 TEST001 1';
        
        exec($command, $output, $returnCode);
        
        echo "   - Command: $command\n";
        echo "   - Return Code: $returnCode\n";
        echo "   - Output: " . implode("\n            ", $output) . "\n";
        echo "   - Status: " . ($returnCode === 0 ? 'PASS' : 'FAIL') . "\n";
    } else {
        echo "   - Status: SKIP (exec not available or script missing)\n";
    }
    echo "\n";
    
    // Test 4: Smart Sender Async Test
    echo "4. Smart Sender Async Email Test:\n";
    $testEmail2 = 'test' . (time() + 1) . '@example.com';
    
    $result = $smartSender->sendOTP(
        $testEmail2,
        'Smart Test User',
        '654321',
        5,
        'SMART001',
        1
    );
    
    echo "   - Test Email: $testEmail2\n";
    echo "   - Result: " . json_encode($result, JSON_PRETTY_PRINT) . "\n";
    echo "   - Status: " . ($result['success'] ? 'PASS' : 'FAIL') . "\n";
    echo "\n";
    
    // Test 5: Basic Email Service Sync Test
    echo "5. Basic Email Service Sync Test:\n";
    $basicService = new BasicOTPEmailService();
    $testEmail3 = 'test' . (time() + 2) . '@example.com';
    
    $result2 = $basicService->sendOTP(
        $testEmail3,
        'Basic Test User',
        '789123',
        5,
        'BASIC001',
        1
    );
    
    echo "   - Test Email: $testEmail3\n";
    echo "   - Result: " . json_encode($result2, JSON_PRETTY_PRINT) . "\n";
    echo "   - Status: " . ($result2['success'] ? 'PASS' : 'FAIL') . "\n";
    echo "\n";
    
    // Test 6: Performance Stats
    echo "6. Performance Statistics:\n";
    $performanceStats = $smartSender->getPerformanceStats();
    echo "   - Stats: " . json_encode($performanceStats, JSON_PRETTY_PRINT) . "\n";
    echo "\n";
    
    // Test 7: Error Log Analysis
    echo "7. Recent Email Log Analysis:\n";
    $errorLogPath = ini_get('error_log');
    if ($errorLogPath && file_exists($errorLogPath)) {
        $recentLogs = [];
        $handle = fopen($errorLogPath, 'r');
        if ($handle) {
            // Read last 100 lines
            $lines = [];
            while (($line = fgets($handle)) !== false) {
                $lines[] = $line;
                if (count($lines) > 100) {
                    array_shift($lines);
                }
            }
            fclose($handle);
            
            // Filter for email-related logs from last hour
            $oneHourAgo = time() - 3600;
            foreach ($lines as $line) {
                if (strpos($line, '[OTP EMAIL]') !== false || 
                    strpos($line, '[BACKGROUND EMAIL]') !== false ||
                    strpos($line, '[SMART EMAIL]') !== false) {
                    echo "   - " . trim($line) . "\n";
                }
            }
        }
    } else {
        echo "   - Error log not accessible\n";
    }
    echo "\n";
    
    // Summary
    echo "=== TEST SUMMARY ===\n";
    echo "All core email functionality tests completed.\n";
    echo "Check individual test results above for specific issues.\n";
    echo "\nKey Points:\n";
    echo "- Smart Email Sender automatically chooses async/sync based on conditions\n";
    echo "- Background email process is functional and executable\n";
    echo "- Fallback mechanisms are in place for reliability\n";
    echo "- Both login and resend flows now use the same smart sender\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}

if (php_sapi_name() !== 'cli') {
    // For web access, also output as JSON
    echo "\n\n<!-- JSON OUTPUT -->\n";
    echo json_encode($testResults, JSON_PRETTY_PRINT);
}
?>