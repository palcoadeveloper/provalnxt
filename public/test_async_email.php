<?php
/**
 * Test Asynchronous Email Functionality
 * Diagnostic script to test if async email sending works properly
 */

require_once 'core/config/config.php';
require_once 'core/email/BasicOTPEmailService.php';

// Security: Only allow access from localhost or authenticated admin
$allowedIPs = ['127.0.0.1', '::1'];
$clientIP = $_SERVER['REMOTE_ADDR'] ?? '';
$isLocalhost = in_array($clientIP, $allowedIPs);
$isAdmin = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';

if (!$isLocalhost && !$isAdmin) {
    http_response_code(403);
    die('Access denied - Admin access required');
}

header('Content-Type: application/json');

$testResults = [];

try {
    // Test 1: Check if PHP exec function is available
    $testResults['exec_function_available'] = function_exists('exec');
    
    // Test 2: Check if background script exists
    $backgroundScript = __DIR__ . '/core/email/background_email_sender.php';
    $testResults['background_script_exists'] = file_exists($backgroundScript);
    $testResults['background_script_readable'] = is_readable($backgroundScript);
    
    // Test 3: Check configuration settings
    $testResults['config'] = [
        'EMAIL_ASYNC_ENABLED' => EMAIL_ASYNC_ENABLED,
        'EMAIL_CONNECTION_POOLING_ENABLED' => EMAIL_CONNECTION_POOLING_ENABLED,
        'EMAIL_OTP_RATE_LIMIT_SECONDS' => EMAIL_OTP_RATE_LIMIT_SECONDS,
        'EMAIL_GLOBAL_RATE_LIMIT_PER_MINUTE' => EMAIL_GLOBAL_RATE_LIMIT_PER_MINUTE
    ];
    
    // Test 4: Test PHP binary detection
    $testResults['php_binary'] = PHP_BINARY;
    $testResults['php_binary_exists'] = file_exists(PHP_BINARY);
    
    // Test 5: Test basic command execution
    $testCommand = 'echo "test"';
    $output = [];
    $returnCode = 0;
    exec($testCommand, $output, $returnCode);
    $testResults['basic_exec_test'] = [
        'command' => $testCommand,
        'output' => $output,
        'return_code' => $returnCode,
        'success' => $returnCode === 0
    ];
    
    // Test 6: Test email service instantiation
    $emailService = new BasicOTPEmailService();
    $testResults['email_service_created'] = true;
    
    // Test 7: Get health check
    $healthCheck = $emailService->healthCheck();
    $testResults['health_check'] = $healthCheck;
    
    // Test 8: Get performance stats
    $performanceStats = $emailService->getPerformanceStats();
    $testResults['performance_stats'] = $performanceStats;
    
    // Test 9: Test actual async email sending (if test email provided)
    if (isset($_GET['test_email']) && filter_var($_GET['test_email'], FILTER_VALIDATE_EMAIL)) {
        $testEmail = $_GET['test_email'];
        
        $asyncResult = $emailService->sendOTPAsync(
            $testEmail,
            'Test User',
            '123456',
            5,
            'TEST001',
            1
        );
        
        $testResults['async_email_test'] = [
            'email' => $testEmail,
            'result' => $asyncResult
        ];
    }
    
    // Test 10: Check error logs for recent background email attempts
    $errorLogPath = ini_get('error_log');
    if ($errorLogPath && file_exists($errorLogPath)) {
        $recentLogs = [];
        $handle = fopen($errorLogPath, 'r');
        if ($handle) {
            // Read last 50 lines
            $lines = [];
            while (($line = fgets($handle)) !== false) {
                $lines[] = $line;
                if (count($lines) > 50) {
                    array_shift($lines);
                }
            }
            fclose($handle);
            
            // Filter for background email related logs
            foreach ($lines as $line) {
                if (strpos($line, '[BACKGROUND EMAIL]') !== false || 
                    strpos($line, '[OTP EMAIL ASYNC]') !== false) {
                    $recentLogs[] = trim($line);
                }
            }
        }
        $testResults['recent_background_email_logs'] = $recentLogs;
    }
    
    $testResults['overall_status'] = 'completed';
    $testResults['timestamp'] = date('Y-m-d H:i:s');
    
} catch (Exception $e) {
    $testResults['error'] = $e->getMessage();
    $testResults['overall_status'] = 'error';
}

echo json_encode($testResults, JSON_PRETTY_PRINT);
?>