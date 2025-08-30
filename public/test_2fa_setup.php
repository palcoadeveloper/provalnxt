<?php
/**
 * Two-Factor Authentication Setup Test Script
 * This script helps verify that the 2FA implementation is working correctly
 * 
 * SECURITY: Remove this file after testing in production!
 */

// Include required files
require_once 'core/config/config.php';
require_once 'core/config/db.class.php';
require_once 'core/security/two_factor_auth.php';
require_once 'core/email/BasicOTPEmailService.php';

// Only allow access in development or from localhost
if (ENVIRONMENT !== 'dev' && $_SERVER['REMOTE_ADDR'] !== '127.0.0.1') {
    http_response_code(403);
    die('Access denied. This test script is only available in development mode.');
}

$testResults = [];
$errors = [];

echo "<!DOCTYPE html>\n<html>\n<head>\n    <title>2FA Setup Test</title>\n    <style>\n        body { font-family: Arial, sans-serif; margin: 20px; }\n        .success { color: green; }\n        .error { color: red; }\n        .warning { color: orange; }\n        .test-section { border: 1px solid #ddd; padding: 15px; margin: 10px 0; }\n        pre { background: #f5f5f5; padding: 10px; overflow-x: auto; }\n    </style>\n</head>\n<body>\n";

echo "<h1>🔐 Two-Factor Authentication Setup Test</h1>\n";
echo "<p><strong>Environment:</strong> " . ENVIRONMENT . "</p>\n";
echo "<p><strong>Test Time:</strong> " . date('Y-m-d H:i:s') . "</p>\n";

// Test 1: Database Connection
echo "<div class='test-section'>\n";
echo "<h3>Test 1: Database Connection</h3>\n";
try {
    $dbTest = DB::queryFirstRow("SELECT 1 as test");
    if ($dbTest && $dbTest['test'] == 1) {
        echo "<p class='success'>✅ Database connection successful</p>\n";
        $testResults['database'] = true;
    } else {
        echo "<p class='error'>❌ Database connection failed</p>\n";
        $testResults['database'] = false;
        $errors[] = "Database connection test failed";
    }
} catch (Exception $e) {
    echo "<p class='error'>❌ Database connection error: " . htmlspecialchars($e->getMessage()) . "</p>\n";
    $testResults['database'] = false;
    $errors[] = "Database error: " . $e->getMessage();
}
echo "</div>\n";

// Test 2: Check if required tables exist
echo "<div class='test-section'>\n";
echo "<h3>Test 2: Database Schema</h3>\n";

// Check units table for 2FA columns
try {
    $unitsColumns = DB::query("DESCRIBE units");
    $has2FAColumns = false;
    $existingColumns = [];
    
    foreach ($unitsColumns as $column) {
        $existingColumns[] = $column['Field'];
        if (in_array($column['Field'], ['two_factor_enabled', 'otp_validity_minutes', 'otp_digits', 'otp_resend_delay_seconds'])) {
            $has2FAColumns = true;
        }
    }
    
    if ($has2FAColumns) {
        echo "<p class='success'>✅ Units table has 2FA configuration columns</p>\n";
        $testResults['units_table'] = true;
    } else {
        echo "<p class='error'>❌ Units table missing 2FA columns</p>\n";
        echo "<p class='warning'>💡 Please run the database_updates_2fa.sql script</p>\n";
        $testResults['units_table'] = false;
        $errors[] = "Units table missing 2FA columns";
    }
    
    echo "<details><summary>Units table columns</summary><pre>" . print_r($existingColumns, true) . "</pre></details>\n";
    
} catch (Exception $e) {
    echo "<p class='error'>❌ Error checking units table: " . htmlspecialchars($e->getMessage()) . "</p>\n";
    $testResults['units_table'] = false;
    $errors[] = "Units table error: " . $e->getMessage();
}

// Check user_otp_sessions table
try {
    $otpTable = DB::query("DESCRIBE user_otp_sessions");
    if ($otpTable && count($otpTable) > 0) {
        echo "<p class='success'>✅ user_otp_sessions table exists</p>\n";
        $testResults['otp_table'] = true;
    } else {
        echo "<p class='error'>❌ user_otp_sessions table not found</p>\n";
        $testResults['otp_table'] = false;
        $errors[] = "user_otp_sessions table missing";
    }
} catch (Exception $e) {
    echo "<p class='error'>❌ user_otp_sessions table not found</p>\n";
    echo "<p class='warning'>💡 Please run the database_updates_2fa.sql script</p>\n";
    $testResults['otp_table'] = false;
    $errors[] = "user_otp_sessions table missing";
}

echo "</div>\n";

// Test 3: TwoFactorAuth Class
echo "<div class='test-section'>\n";
echo "<h3>Test 3: TwoFactorAuth Class</h3>\n";

if (class_exists('TwoFactorAuth')) {
    echo "<p class='success'>✅ TwoFactorAuth class loaded</p>\n";
    $testResults['2fa_class'] = true;
    
    // Test OTP generation
    try {
        $otp = TwoFactorAuth::generateOTP(6);
        if (strlen($otp) == 6 && is_numeric($otp)) {
            echo "<p class='success'>✅ OTP generation working (sample: $otp)</p>\n";
            $testResults['otp_generation'] = true;
        } else {
            echo "<p class='error'>❌ OTP generation failed - invalid format</p>\n";
            $testResults['otp_generation'] = false;
            $errors[] = "OTP generation produces invalid format";
        }
    } catch (Exception $e) {
        echo "<p class='error'>❌ OTP generation error: " . htmlspecialchars($e->getMessage()) . "</p>\n";
        $testResults['otp_generation'] = false;
        $errors[] = "OTP generation error: " . $e->getMessage();
    }
    
} else {
    echo "<p class='error'>❌ TwoFactorAuth class not found</p>\n";
    $testResults['2fa_class'] = false;
    $errors[] = "TwoFactorAuth class not loaded";
}

echo "</div>\n";

// Test 4: BasicOTPEmailService Class
echo "<div class='test-section'>\n";
echo "<h3>Test 4: BasicOTPEmailService Class</h3>\n";

if (class_exists('BasicOTPEmailService')) {
    echo "<p class='success'>✅ BasicOTPEmailService class loaded</p>\n";
    $testResults['email_class'] = true;
    
    // Test email template generation
    try {
        $emailService = new BasicOTPEmailService();
        $reflection = new ReflectionClass($emailService);
        $method = $reflection->getMethod('generateOTPEmailTemplate');
        $method->setAccessible(true);
        
        $template = $method->invoke($emailService, 'Test User', '123456', 5);
        
        if (strpos($template, '123456') !== false && strpos($template, 'Test User') !== false) {
            echo "<p class='success'>✅ Email template generation working</p>\n";
            $testResults['email_template'] = true;
        } else {
            echo "<p class='error'>❌ Email template generation failed</p>\n";
            $testResults['email_template'] = false;
            $errors[] = "Email template generation failed";
        }
    } catch (Exception $e) {
        echo "<p class='error'>❌ Email template error: " . htmlspecialchars($e->getMessage()) . "</p>\n";
        $testResults['email_template'] = false;
        $errors[] = "Email template error: " . $e->getMessage();
    }
    
} else {
    echo "<p class='error'>❌ BasicOTPEmailService class not found</p>\n";
    $testResults['email_class'] = false;
    $errors[] = "BasicOTPEmailService class not loaded";
}

echo "</div>\n";

// Test 5: Configuration Check
echo "<div class='test-section'>\n";
echo "<h3>Test 5: Configuration</h3>\n";

// Check email configuration
if (defined('SMTP_HOST') && !empty(SMTP_HOST)) {
    echo "<p class='success'>✅ SMTP configuration present</p>\n";
    echo "<p>SMTP Host: " . htmlspecialchars(SMTP_HOST) . "</p>\n";
    $testResults['smtp_config'] = true;
} else {
    echo "<p class='warning'>⚠️ SMTP configuration not found</p>\n";
    echo "<p>Email sending may not work properly</p>\n";
    $testResults['smtp_config'] = false;
}

// Check rate limiting
if (class_exists('RateLimiter')) {
    echo "<p class='success'>✅ Rate limiting available</p>\n";
    $testResults['rate_limiting'] = true;
} else {
    echo "<p class='error'>❌ Rate limiting not available</p>\n";
    $testResults['rate_limiting'] = false;
    $errors[] = "Rate limiting not available";
}

echo "</div>\n";

// Test 6: File Permissions
echo "<div class='test-section'>\n";
echo "<h3>Test 6: File Permissions</h3>\n";

$criticalFiles = [
    'core/security/two_factor_auth.php',
    'core/email/BasicOTPEmailService.php',
    'verify_otp.php',
    'core/validation/checklogin.php'
];

$filePermissionsOk = true;
foreach ($criticalFiles as $file) {
    if (file_exists($file) && is_readable($file)) {
        echo "<p class='success'>✅ $file - readable</p>\n";
    } else {
        echo "<p class='error'>❌ $file - not found or not readable</p>\n";
        $filePermissionsOk = false;
        $errors[] = "$file not accessible";
    }
}

$testResults['file_permissions'] = $filePermissionsOk;
echo "</div>\n";

// Summary
echo "<div class='test-section'>\n";
echo "<h3>📊 Test Summary</h3>\n";

$totalTests = count($testResults);
$passedTests = count(array_filter($testResults));
$failedTests = $totalTests - $passedTests;

echo "<p><strong>Total Tests:</strong> $totalTests</p>\n";
echo "<p><strong>Passed:</strong> <span class='success'>$passedTests</span></p>\n";
echo "<p><strong>Failed:</strong> <span class='error'>$failedTests</span></p>\n";

if (empty($errors)) {
    echo "<p class='success'><strong>🎉 All tests passed! 2FA system is ready for use.</strong></p>\n";
    echo "<h4>Next Steps:</h4>\n";
    echo "<ol>\n";
    echo "<li>Enable 2FA for a test unit in the database</li>\n";
    echo "<li>Test the login flow with a user from that unit</li>\n";
    echo "<li>Set up the cleanup cron job</li>\n";
    echo "<li>Remove this test file from production</li>\n";
    echo "</ol>\n";
} else {
    echo "<p class='error'><strong>❌ Some tests failed. Please address the following issues:</strong></p>\n";
    echo "<ul>\n";
    foreach ($errors as $error) {
        echo "<li class='error'>" . htmlspecialchars($error) . "</li>\n";
    }
    echo "</ul>\n";
}

echo "</div>\n";

// Test Data
echo "<div class='test-section'>\n";
echo "<h3>🔧 Test Configuration</h3>\n";
echo "<p><strong>To enable 2FA for testing, run this SQL:</strong></p>\n";
echo "<pre>UPDATE units SET \n    two_factor_enabled = 'Yes',\n    otp_validity_minutes = 5,\n    otp_digits = 6,\n    otp_resend_delay_seconds = 60\nWHERE unit_id = [YOUR_TEST_UNIT_ID];</pre>\n";

echo "<p><strong>To check if 2FA is enabled for a unit:</strong></p>\n";
echo "<pre>SELECT unit_id, unit_name, two_factor_enabled, otp_validity_minutes, otp_digits \nFROM units \nWHERE two_factor_enabled = 'Yes';</pre>\n";
echo "</div>\n";

echo "<div class='test-section'>\n";
echo "<h3>⚠️ Security Notice</h3>\n";
echo "<p class='error'><strong>IMPORTANT: Delete this test file (test_2fa_setup.php) before deploying to production!</strong></p>\n";
echo "<p>This file contains sensitive system information and should not be accessible in production.</p>\n";
echo "</div>\n";

echo "</body>\n</html>\n";
?>