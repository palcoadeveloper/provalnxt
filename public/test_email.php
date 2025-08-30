<?php
/**
 * Email Test Utility
 * This script tests email functionality for the ProVal HVAC system
 * 
 * SECURITY: Remove this file after testing in production!
 */

// Include required files
require_once 'core/config/config.php';
require_once 'core/email/BasicOTPEmailService.php';

// PHPMailer includes and namespace declarations
require_once 'core/phpmailer/src/PHPMailer.php';
require_once 'core/phpmailer/src/Exception.php';
require_once 'core/phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// Define testing mode to prevent redirects
define('TESTING_MODE', true);

// Only allow access in development or from localhost
if (ENVIRONMENT !== 'dev' && $_SERVER['REMOTE_ADDR'] !== '127.0.0.1') {
    http_response_code(403);
    die('Access denied. This test script is only available in development mode.');
}

echo "<!DOCTYPE html>\n<html>\n<head>\n    <title>Email Test Utility</title>\n    <style>\n        body { font-family: Arial, sans-serif; margin: 20px; }\n        .success { color: green; }\n        .error { color: red; }\n        .info { color: blue; }\n        .test-section { border: 1px solid #ddd; padding: 15px; margin: 10px 0; }\n        form { background: #f5f5f5; padding: 15px; margin: 10px 0; }\n        input, select { margin: 5px; padding: 5px; }\n        pre { background: #f0f0f0; padding: 10px; overflow-x: auto; font-size: 12px; }\n    </style>\n</head>\n<body>\n";

echo "<h1>📧 Email Test Utility</h1>\n";
echo "<p><strong>Environment:</strong> " . ENVIRONMENT . "</p>\n";
echo "<p><strong>Test Time:</strong> " . date('Y-m-d H:i:s') . "</p>\n";

// Test 1: PHPMailer SMTP Configuration
echo "<div class='test-section'>\n";
echo "<h3>Test 1: PHPMailer SMTP Configuration</h3>\n";

echo "<p><strong>SMTP Configuration from config.php:</strong></p>\n";
echo "<ul>\n";
echo "<li>SMTP Host: " . (defined('EMAIL_REMINDER_SMTP_HOST') ? EMAIL_REMINDER_SMTP_HOST : 'NOT DEFINED') . "</li>\n";
echo "<li>SMTP Port: " . (defined('EMAIL_REMINDER_SMTP_PORT') ? EMAIL_REMINDER_SMTP_PORT : 'NOT DEFINED') . "</li>\n";
echo "<li>SMTP Security: " . (defined('EMAIL_REMINDER_SMTP_SECURE') ? EMAIL_REMINDER_SMTP_SECURE : 'NOT DEFINED') . "</li>\n";
echo "<li>SMTP Username: " . (defined('EMAIL_REMINDER_SMTP_USERNAME') ? EMAIL_REMINDER_SMTP_USERNAME : 'NOT DEFINED') . "</li>\n";
echo "<li>SMTP Auth: " . (defined('EMAIL_REMINDER_SMTP_AUTH_ENABLED') && EMAIL_REMINDER_SMTP_AUTH_ENABLED ? 'Enabled' : 'Disabled') . "</li>\n";
echo "<li>From Email: " . (defined('EMAIL_REMINDER_SMTP_FROM_EMAIL') ? EMAIL_REMINDER_SMTP_FROM_EMAIL : 'NOT DEFINED') . "</li>\n";
echo "<li>From Name: " . (defined('EMAIL_REMINDER_SMTP_FROM_NAME') ? EMAIL_REMINDER_SMTP_FROM_NAME : 'NOT DEFINED') . "</li>\n";
echo "<li>Debug Level: " . (defined('EMAIL_REMINDER_SMTP_DEBUG_LEVEL') ? EMAIL_REMINDER_SMTP_DEBUG_LEVEL : 'NOT DEFINED') . "</li>\n";
echo "</ul>\n";

// Test PHPMailer class availability
echo "<p><strong>PHPMailer Status:</strong></p>\n";
try {
    if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        echo "<p class='success'>✅ PHPMailer class available</p>\n";
        
        // Test SMTP connection
        $testMail = new PHPMailer(true);
        $testMail->isSMTP();
        $testMail->Host = EMAIL_REMINDER_SMTP_HOST;
        $testMail->SMTPAuth = EMAIL_REMINDER_SMTP_AUTH_ENABLED;
        $testMail->Username = EMAIL_REMINDER_SMTP_USERNAME;
        $testMail->Password = EMAIL_REMINDER_SMTP_PASSWORD;
        $testMail->SMTPSecure = EMAIL_REMINDER_SMTP_SECURE;
        $testMail->Port = EMAIL_REMINDER_SMTP_PORT;
        $testMail->SMTPDebug = 0; // No debug output for this test
        
        echo "<p class='info'>📡 Testing SMTP connection to " . EMAIL_REMINDER_SMTP_HOST . ":" . EMAIL_REMINDER_SMTP_PORT . "...</p>\n";
        
    } else {
        echo "<p class='error'>❌ PHPMailer class not found</p>\n";
    }
} catch (Exception $e) {
    echo "<p class='error'>❌ PHPMailer error: " . htmlspecialchars($e->getMessage()) . "</p>\n";
}

echo "</div>\n";

// Test 2: PHPMailer SMTP Test
echo "<div class='test-section'>\n";
echo "<h3>Test 2: PHPMailer SMTP Test</h3>\n";

echo "<form method='post'>\n";
echo "<p>Test PHPMailer SMTP email delivery:</p>\n";
echo "<input type='email' name='test_email' placeholder='Enter test email address' required>\n";
echo "<input type='submit' name='test_phpmailer' value='Send PHPMailer Test Email'>\n";
echo "</form>\n";

if (isset($_POST['test_phpmailer']) && !empty($_POST['test_email'])) {
    $testEmail = filter_var($_POST['test_email'], FILTER_VALIDATE_EMAIL);
    if ($testEmail) {
        echo "<p><strong>Sending PHPMailer test email to:</strong> " . htmlspecialchars($testEmail) . "</p>\n";
        
        try {
            $mail = new PHPMailer(true);
            
            // SMTP configuration
            $mail->isSMTP();
            $mail->Host = EMAIL_REMINDER_SMTP_HOST;
            $mail->SMTPAuth = EMAIL_REMINDER_SMTP_AUTH_ENABLED;
            $mail->Username = EMAIL_REMINDER_SMTP_USERNAME;
            $mail->Password = EMAIL_REMINDER_SMTP_PASSWORD;
            $mail->SMTPSecure = EMAIL_REMINDER_SMTP_SECURE;
            $mail->Port = EMAIL_REMINDER_SMTP_PORT;
            $mail->SMTPDebug = 2; // Enable verbose debug for testing
            
            // Email content
            $mail->setFrom(EMAIL_REMINDER_SMTP_FROM_EMAIL, 'ProVal HVAC Test');
            $mail->addAddress($testEmail, 'Test Recipient');
            $mail->addReplyTo(EMAIL_REMINDER_SMTP_FROM_EMAIL, 'ProVal HVAC Test');
            
            $mail->isHTML(true);
            $mail->Subject = 'ProVal HVAC - PHPMailer SMTP Test';
            $mail->Body = "<h2>✅ PHPMailer SMTP Test Successful</h2>
                          <p>This email was sent via PHPMailer using SMTP configuration.</p>
                          <p><strong>Timestamp:</strong> " . date('Y-m-d H:i:s') . "</p>
                          <p><strong>SMTP Server:</strong> " . EMAIL_REMINDER_SMTP_HOST . ":" . EMAIL_REMINDER_SMTP_PORT . "</p>
                          <p><strong>Security:</strong> " . EMAIL_REMINDER_SMTP_SECURE . "</p>";
            $mail->AltBody = "PHPMailer SMTP Test Successful\n\nThis email was sent via PHPMailer using SMTP configuration.\nTimestamp: " . date('Y-m-d H:i:s');
            
            // Capture debug output
            ob_start();
            $success = $mail->send();
            $debugOutput = ob_get_clean();
            
            if ($success) {
                echo "<p class='success'>✅ PHPMailer SMTP email sent successfully!</p>\n";
                echo "<p class='info'>Check the recipient's inbox (including spam folder).</p>\n";
                if (!empty($debugOutput)) {
                    echo "<details><summary><strong>SMTP Debug Output:</strong></summary><pre>" . htmlspecialchars($debugOutput) . "</pre></details>\n";
                }
            } else {
                echo "<p class='error'>❌ Failed to send PHPMailer email.</p>\n";
                echo "<p class='error'>Error Info: " . htmlspecialchars($mail->ErrorInfo) . "</p>\n";
            }
            
        } catch (Exception $e) {
            echo "<p class='error'>❌ PHPMailer Exception: " . htmlspecialchars($e->getMessage()) . "</p>\n";
            if (isset($mail) && !empty($mail->ErrorInfo)) {
                echo "<p class='error'>Mail Error Info: " . htmlspecialchars($mail->ErrorInfo) . "</p>\n";
            }
        }
    } else {
        echo "<p class='error'>❌ Invalid email address provided.</p>\n";
    }
}

echo "</div>\n";

// Test 3: OTP Email Test
echo "<div class='test-section'>\n";
echo "<h3>Test 3: OTP Email Test</h3>\n";

echo "<form method='post'>\n";
echo "<p>Test OTP email sending:</p>\n";
echo "<input type='email' name='otp_test_email' placeholder='Enter test email address' required><br>\n";
echo "<input type='text' name='otp_test_name' placeholder='Recipient name' value='Test User' required><br>\n";
echo "<input type='text' name='otp_test_employee_id' placeholder='Employee ID' value='TEST001' required><br>\n";
echo "<input type='number' name='otp_test_unit_id' placeholder='Unit ID' value='1' required><br>\n";
echo "<input type='submit' name='test_otp_mail' value='Send OTP Test Email'>\n";
echo "</form>\n";

if (isset($_POST['test_otp_mail']) && !empty($_POST['otp_test_email'])) {
    $testEmail = filter_var($_POST['otp_test_email'], FILTER_VALIDATE_EMAIL);
    $testName = trim($_POST['otp_test_name']);
    $testEmployeeId = trim($_POST['otp_test_employee_id']);
    $testUnitId = (int)$_POST['otp_test_unit_id'];
    
    if ($testEmail && $testName && $testEmployeeId) {
        echo "<p><strong>Sending OTP test email to:</strong> " . htmlspecialchars($testEmail) . "</p>\n";
        
        try {
            $otpEmailService = new BasicOTPEmailService();
            $testOTP = '123456'; // Test OTP code
            $validityMinutes = 5;
            
            echo "<p class='info'>Test OTP Code: <strong>$testOTP</strong></p>\n";
            echo "<p class='info'>Validity: $validityMinutes minutes</p>\n";
            
            $result = $otpEmailService->sendOTP(
                $testEmail,
                $testName,
                $testOTP,
                $validityMinutes,
                $testEmployeeId,
                $testUnitId
            );
            
            if ($result['success']) {
                echo "<p class='success'>✅ OTP email sent successfully!</p>\n";
                echo "<p class='success'>Message: " . htmlspecialchars($result['message']) . "</p>\n";
            } else {
                echo "<p class='error'>❌ OTP email failed.</p>\n";
                echo "<p class='error'>Error: " . htmlspecialchars($result['error']) . "</p>\n";
                if (isset($result['exception'])) {
                    echo "<p class='error'>Exception: " . htmlspecialchars($result['exception']) . "</p>\n";
                }
            }
            
        } catch (Exception $e) {
            echo "<p class='error'>❌ Exception occurred: " . htmlspecialchars($e->getMessage()) . "</p>\n";
        }
    } else {
        echo "<p class='error'>❌ Please fill in all required fields with valid data.</p>\n";
    }
}

echo "</div>\n";

// Test 4: Email Log Analysis
echo "<div class='test-section'>\n";
echo "<h3>Test 4: Recent Email Logs</h3>\n";

echo "<p>Check your PHP error log for these patterns:</p>\n";
echo "<ul>\n";
echo "<li><code>[OTP EMAIL]</code> - OTP email related logs</li>\n";
echo "<li><code>OTP email sent successfully</code> - Success messages</li>\n";
echo "<li><code>OTP email sending failed</code> - Failure messages</li>\n";
echo "</ul>\n";

// Try to read recent log entries (if accessible)
$logPaths = [
    '/var/log/php_errors.log',
    '/opt/homebrew/var/log/php_error.log',
    ini_get('error_log')
];

foreach ($logPaths as $logPath) {
    if ($logPath && file_exists($logPath) && is_readable($logPath)) {
        echo "<p><strong>Found log file:</strong> $logPath</p>\n";
        
        // Get last 20 lines
        $logLines = [];
        $handle = fopen($logPath, 'r');
        if ($handle) {
            // Read from end of file
            fseek($handle, -2048, SEEK_END); // Last 2KB
            $content = fread($handle, 2048);
            fclose($handle);
            
            $lines = explode("\n", $content);
            $recentLines = array_slice($lines, -20);
            
            // Filter for email-related logs
            $emailLogs = array_filter($recentLines, function($line) {
                return strpos($line, 'OTP') !== false || strpos($line, 'mail') !== false;
            });
            
            if (!empty($emailLogs)) {
                echo "<p><strong>Recent email-related log entries:</strong></p>\n";
                echo "<pre>" . htmlspecialchars(implode("\n", $emailLogs)) . "</pre>\n";
            } else {
                echo "<p class='info'>No recent email-related log entries found in this file.</p>\n";
            }
        }
        break;
    }
}

echo "</div>\n";

// Instructions
echo "<div class='test-section'>\n";
echo "<h3>📋 PHPMailer SMTP Troubleshooting Guide</h3>\n";
echo "<ol>\n";
echo "<li><strong>Check SMTP Configuration:</strong> Verify host, port, username, and password in config.php</li>\n";
echo "<li><strong>Check Firewall:</strong> Ensure port " . EMAIL_REMINDER_SMTP_PORT . " is not blocked</li>\n";
echo "<li><strong>Check Spam Folder:</strong> SMTP emails often go to spam initially</li>\n";
echo "<li><strong>Check SSL/TLS:</strong> Verify " . EMAIL_REMINDER_SMTP_SECURE . " is supported by " . EMAIL_REMINDER_SMTP_HOST . "</li>\n";
echo "<li><strong>Authentication:</strong> Verify SMTP username and password are correct</li>\n";
echo "</ol>\n";

echo "<h4>Common SMTP Issues:</h4>\n";
echo "<ul>\n";
echo "<li><strong>Connection Timeout:</strong> Check if SMTP server is reachable</li>\n";
echo "<li><strong>Authentication Failed:</strong> Verify username/password or enable 'Less Secure Apps'</li>\n";
echo "<li><strong>SSL Certificate:</strong> Some hosts require specific SSL configuration</li>\n";
echo "<li><strong>Rate Limiting:</strong> SMTP providers may limit emails per hour</li>\n";
echo "</ul>\n";

echo "<h4>Debug Information:</h4>\n";
echo "<ul>\n";
echo "<li><strong>Current SMTP Host:</strong> " . EMAIL_REMINDER_SMTP_HOST . "</li>\n";
echo "<li><strong>Current SMTP Port:</strong> " . EMAIL_REMINDER_SMTP_PORT . "</li>\n";
echo "<li><strong>Current Security:</strong> " . EMAIL_REMINDER_SMTP_SECURE . "</li>\n";
echo "<li><strong>Debug Level:</strong> " . EMAIL_REMINDER_SMTP_DEBUG_LEVEL . " (2=verbose, 0=silent)</li>\n";
echo "</ul>\n";
echo "</div>\n";

echo "<hr>\n";
echo "<p style='color: red;'><strong>⚠️ SECURITY WARNING: Delete this file (test_email.php) before deploying to production!</strong></p>\n";
echo "</body>\n</html>\n";
?>