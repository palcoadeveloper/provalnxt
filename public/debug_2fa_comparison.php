<?php
/**
 * 2FA Performance Comparison
 * Tests login timing with and without 2FA operations
 */

echo "=== 2FA Performance Comparison Debug ===\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n\n";

require_once 'core/config/config.php';
require_once 'core/security/session_init.php';
require_once 'core/config/db.class.php';
require_once 'core/security/auth_utils.php';
require_once 'core/security/two_factor_auth.php';

// Test 1: Simulate Login WITHOUT 2FA
echo "=== TEST 1: Login WITHOUT 2FA ===\n";

$test1Start = microtime(true);

// Step 1: User lookup
$userLookupStart = microtime(true);
try {
    $testUser = DB::queryFirstRow(
        "SELECT user_id, user_domain_id, user_name, user_email, u1.unit_id, unit_name, unit_site, 
                department_id, is_qa_head, is_unit_head, is_admin, is_super_admin, 
                is_account_locked, is_dept_head, user_status, user_password, employee_id
         FROM users u1 LEFT JOIN units u2 ON u1.unit_id = u2.unit_id
         WHERE user_domain_id = %s and user_type='employee' LIMIT 1", 
        'testuser'
    );
    $userLookupEnd = microtime(true);
    echo "1. User lookup query: " . round(($userLookupEnd - $userLookupStart) * 1000, 2) . " ms\n";
} catch (Exception $e) {
    echo "1. User lookup error: " . $e->getMessage() . "\n";
    $userLookupEnd = microtime(true);
}

// Step 2: Password verification simulation
$passwordStart = microtime(true);
// Simulate password comparison (development mode)
$password = 'testpassword';
$hashedPassword = 'testpassword'; // In dev mode, plain text comparison
$passwordMatch = ($password === $hashedPassword);
$passwordEnd = microtime(true);
echo "2. Password verification: " . round(($passwordEnd - $passwordStart) * 1000, 2) . " ms\n";

// Step 3: Session setup simulation
$sessionStart = microtime(true);
// Simulate session variable setting (without actual session due to CLI)
$sessionEnd = microtime(true);
echo "3. Session setup: " . round(($sessionEnd - $sessionStart) * 1000, 2) . " ms\n";

$test1End = microtime(true);
$test1Duration = ($test1End - $test1Start) * 1000;
echo "TOTAL WITHOUT 2FA: " . round($test1Duration, 2) . " ms\n\n";

// Test 2: Simulate Login WITH 2FA
echo "=== TEST 2: Login WITH 2FA ===\n";

$test2Start = microtime(true);

// Step 1: Same user lookup
$userLookupStart2 = microtime(true);
try {
    $testUser2 = DB::queryFirstRow(
        "SELECT user_id, user_domain_id, user_name, user_email, u1.unit_id, unit_name, unit_site, 
                department_id, is_qa_head, is_unit_head, is_admin, is_super_admin, 
                is_account_locked, is_dept_head, user_status, user_password, employee_id
         FROM users u1 LEFT JOIN units u2 ON u1.unit_id = u2.unit_id
         WHERE user_domain_id = %s and user_type='employee' LIMIT 1", 
        'testuser'
    );
    $userLookupEnd2 = microtime(true);
    echo "1. User lookup query: " . round(($userLookupEnd2 - $userLookupStart2) * 1000, 2) . " ms\n";
} catch (Exception $e) {
    echo "1. User lookup error: " . $e->getMessage() . "\n";
    $userLookupEnd2 = microtime(true);
}

// Step 2: Same password verification
$passwordStart2 = microtime(true);
$passwordMatch2 = ($password === $hashedPassword);
$passwordEnd2 = microtime(true);
echo "2. Password verification: " . round(($passwordEnd2 - $passwordStart2) * 1000, 2) . " ms\n";

// Step 3: 2FA Configuration Check
$twoFAConfigStart = microtime(true);
try {
    // This is the actual call from checklogin.php
    $twoFactorConfig = TwoFactorAuth::getUnitTwoFactorConfig(1); // Using unit_id = 1
    $twoFAConfigEnd = microtime(true);
    echo "3. 2FA config check: " . round(($twoFAConfigEnd - $twoFAConfigStart) * 1000, 2) . " ms\n";
    
    if ($twoFactorConfig) {
        echo "   - 2FA Status: " . ($twoFactorConfig['two_factor_enabled'] === 'Yes' ? 'ENABLED' : 'DISABLED') . "\n";
    } else {
        echo "   - 2FA Status: NOT FOUND\n";
    }
} catch (Exception $e) {
    echo "3. 2FA config error: " . $e->getMessage() . "\n";
    $twoFAConfigEnd = microtime(true);
}

// Step 4: OTP Session Creation
$otpSessionStart = microtime(true);
try {
    // This is the actual call from checklogin.php
    $otpSession = TwoFactorAuth::createOTPSession(
        1,  // user_id
        1,  // unit_id  
        'TEST001',  // employee_id
        '127.0.0.1',  // ip_address
        'Test User Agent'  // user_agent
    );
    $otpSessionEnd = microtime(true);
    echo "4. OTP session creation: " . round(($otpSessionEnd - $otpSessionStart) * 1000, 2) . " ms\n";
    
    if ($otpSession) {
        echo "   - Session Token: " . substr($otpSession['session_token'], 0, 10) . "...\n";
        echo "   - OTP Code: " . $otpSession['otp_code'] . "\n";
    }
} catch (Exception $e) {
    echo "4. OTP session creation error: " . $e->getMessage() . "\n";
    $otpSessionEnd = microtime(true);
}

// Step 5: Email Sending Simulation
$emailStart = microtime(true);
try {
    require_once 'core/email/SmartOTPEmailSender.php';
    $smartEmailSender = new SmartOTPEmailSender();
    
    // Check what mode it would use (but don't actually send)
    $healthCheck = $smartEmailSender->healthCheck();
    $emailEnd = microtime(true);
    echo "5. Email sender initialization: " . round(($emailEnd - $emailStart) * 1000, 2) . " ms\n";
    echo "   - Would use async: " . (!$healthCheck['should_use_sync'] ? 'YES' : 'NO') . "\n";
} catch (Exception $e) {
    echo "5. Email sender error: " . $e->getMessage() . "\n";
    $emailEnd = microtime(true);
}

$test2End = microtime(true);
$test2Duration = ($test2End - $test2Start) * 1000;
echo "TOTAL WITH 2FA: " . round($test2Duration, 2) . " ms\n\n";

// Comparison
echo "=== PERFORMANCE COMPARISON ===\n";
echo "Without 2FA: " . round($test1Duration, 2) . " ms\n";
echo "With 2FA:    " . round($test2Duration, 2) . " ms\n";
echo "2FA Overhead: " . round($test2Duration - $test1Duration, 2) . " ms\n";
echo "Performance Impact: " . round((($test2Duration - $test1Duration) / $test1Duration) * 100, 1) . "%\n\n";

if ($test2Duration > 2000) {
    echo "❌ PROBLEM IDENTIFIED: 2FA process is taking > 2 seconds\n";
    if (($twoFAConfigEnd - $twoFAConfigStart) * 1000 > 500) {
        echo "🔍 BOTTLENECK: 2FA config check is slow\n";
    }
    if (($otpSessionEnd - $otpSessionStart) * 1000 > 500) {
        echo "🔍 BOTTLENECK: OTP session creation is slow\n";
    }
    if (($emailEnd - $emailStart) * 1000 > 500) {
        echo "🔍 BOTTLENECK: Email sender initialization is slow\n";
    }
} else if ($test2Duration > $test1Duration + 100) {
    echo "⚠️  MINOR IMPACT: 2FA adds some overhead but within acceptable range\n";
} else {
    echo "✅ 2FA PERFORMANCE: Minimal impact on login performance\n";
}

echo "\n=== RECOMMENDATIONS ===\n";
if ($test2Duration > 2000) {
    echo "1. Investigate database query performance\n";
    echo "2. Check for missing indexes on units and user_otp_sessions tables\n";
    echo "3. Consider caching 2FA configuration\n";
} else {
    echo "1. 2FA performance appears acceptable in simulation\n";
    echo "2. Actual delay may be in different part of login flow\n";
    echo "3. Consider testing actual HTTP request conditions\n";
}
?>