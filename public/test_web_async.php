<?php
/**
 * Web-based Async Email Test
 * Simulates the exact conditions of the login flow from web browser
 */

require_once 'core/config/config.php';
require_once 'core/security/session_init.php';
require_once 'core/config/db.class.php';
require_once 'core/email/SmartOTPEmailSender.php';

header('Content-Type: text/html');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Web Async Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .result { background: white; padding: 15px; border-radius: 5px; margin: 10px 0; border-left: 4px solid #007bff; }
        .success { border-left-color: #28a745; }
        .timing { font-weight: bold; color: #007bff; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 3px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>🌐 Web Environment Async Email Test</h1>
    <p><strong>Environment:</strong> <?php echo php_sapi_name(); ?></p>
    <p><strong>Timestamp:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>

    <?php
    $smartSender = new SmartOTPEmailSender();
    
    echo '<div class="result">';
    echo '<h3>1. Health Check</h3>';
    $healthCheck = $smartSender->healthCheck();
    echo '<pre>' . json_encode($healthCheck, JSON_PRETTY_PRINT) . '</pre>';
    echo '</div>';
    
    echo '<div class="result">';
    echo '<h3>2. Performance Test (Login Flow Simulation)</h3>';
    
    $testStart = microtime(true);
    
    $result = $smartSender->sendOTP(
        'web-test@example.com',
        'Web Test User',
        '123456',
        5,
        'WEB001',
        1,
        true // isLoginFlow = true (matches actual login)
    );
    
    $testEnd = microtime(true);
    $duration = ($testEnd - $testStart) * 1000;
    
    echo '<p class="timing">⏱️ Duration: ' . round($duration, 2) . ' ms</p>';
    echo '<p><strong>Success:</strong> ' . ($result['success'] ? '✅ YES' : '❌ NO') . '</p>';
    echo '<p><strong>Async Used:</strong> ' . (isset($result['async']) && $result['async'] ? '✅ YES' : '❌ NO') . '</p>';
    echo '<pre>' . json_encode($result, JSON_PRETTY_PRINT) . '</pre>';
    echo '</div>';
    
    if ($duration < 100) {
        echo '<div class="result success">';
        echo '<h3>🎉 SUCCESS!</h3>';
        echo '<p>✅ Email completed in <strong>' . round($duration, 2) . ' ms</strong></p>';
        echo '<p>✅ Login should now redirect instantly to verify_otp.php</p>';
        echo '<p>✅ No more 2.27s delay during login process</p>';
        echo '</div>';
    } else {
        echo '<div class="result" style="border-left-color: #dc3545;">';
        echo '<h3>⚠️ Issue Detected</h3>';
        echo '<p>❌ Email took <strong>' . round($duration, 2) . ' ms</strong> (should be <100ms)</p>';
        echo '</div>';
    }
    ?>
    
    <div class="result">
        <h3>📋 Next Steps</h3>
        <ol>
            <li>Test actual login at <a href="login.php">login.php</a></li>
            <li>Verify instant redirect to verify_otp.php</li>
            <li>Confirm email is sent during initial login (not just resend)</li>
        </ol>
    </div>
</body>
</html>