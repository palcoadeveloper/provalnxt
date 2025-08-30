<?php
/**
 * Debug Login Script
 * This script helps diagnose login issues
 * 
 * SECURITY: Remove this file after debugging!
 */

// Include required files
require_once 'core/config/config.php';

// Only allow access in development or from localhost
if (ENVIRONMENT !== 'dev' && $_SERVER['REMOTE_ADDR'] !== '127.0.0.1') {
    http_response_code(403);
    die('Access denied. This debug script is only available in development mode.');
}

echo "<h1>🔍 Login Debug Information</h1>";
echo "<p><strong>Environment:</strong> " . ENVIRONMENT . "</p>";
echo "<p><strong>Debug Time:</strong> " . date('Y-m-d H:i:s') . "</p>";

// Test 1: Database Connection
echo "<h3>Database Connection Test</h3>";
try {
    require_once 'core/config/db.class.php';
    $test = DB::queryFirstRow("SELECT 1 as test");
    if ($test && $test['test'] == 1) {
        echo "<p style='color: green;'>✅ Database connection successful</p>";
    } else {
        echo "<p style='color: red;'>❌ Database connection failed</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Database error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Test 2: Check Required Functions
echo "<h3>Required Functions Test</h3>";
try {
    require_once 'core/security/auth_utils.php';
    if (function_exists('verifyUserCredentials')) {
        echo "<p style='color: green;'>✅ verifyUserCredentials function available</p>";
    } else {
        echo "<p style='color: red;'>❌ verifyUserCredentials function not found</p>";
    }
    
    if (function_exists('handleSuccessfulLogin')) {
        echo "<p style='color: green;'>✅ handleSuccessfulLogin function available</p>";
    } else {
        echo "<p style='color: red;'>❌ handleSuccessfulLogin function not found</p>";
    }
    
    if (function_exists('logSecurityEvent')) {
        echo "<p style='color: green;'>✅ logSecurityEvent function available</p>";
    } else {
        echo "<p style='color: red;'>❌ logSecurityEvent function not found</p>";
    }
    
    if (function_exists('getClientIP')) {
        echo "<p style='color: green;'>✅ getClientIP function available</p>";
    } else {
        echo "<p style='color: red;'>❌ getClientIP function not found</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error loading auth_utils: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Test 3: Check 2FA Components
echo "<h3>2FA Components Test</h3>";
try {
    require_once 'core/security/two_factor_auth.php';
    if (class_exists('TwoFactorAuth')) {
        echo "<p style='color: green;'>✅ TwoFactorAuth class available</p>";
    } else {
        echo "<p style='color: red;'>❌ TwoFactorAuth class not found</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error loading TwoFactorAuth: " . htmlspecialchars($e->getMessage()) . "</p>";
}

try {
    require_once 'core/email/BasicOTPEmailService.php';
    if (class_exists('BasicOTPEmailService')) {
        echo "<p style='color: green;'>✅ BasicOTPEmailService class available</p>";
    } else {
        echo "<p style='color: red;'>❌ BasicOTPEmailService class not found</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error loading BasicOTPEmailService: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Test 4: Check Database Tables
echo "<h3>Database Tables Test</h3>";
try {
    // Check users table
    $userCheck = DB::query("SHOW TABLES LIKE 'users'");
    if ($userCheck && count($userCheck) > 0) {
        echo "<p style='color: green;'>✅ 'users' table exists</p>";
        
        // Check user columns with detailed analysis
        $userColumns = DB::query("DESCRIBE users");
        $requiredColumns = ['employee_id', 'user_email', 'unit_id', 'user_name', 'user_type', 'user_domain_id'];
        $foundColumns = [];
        
        foreach ($userColumns as $column) {
            $foundColumns[] = $column['Field'];
        }
        
        echo "<details><summary><strong>All columns in users table (".count($foundColumns)."):</strong></summary>";
        echo "<pre>" . implode(", ", $foundColumns) . "</pre></details>";
        
        foreach ($requiredColumns as $col) {
            if (in_array($col, $foundColumns)) {
                echo "<p style='color: green;'>✅ Column '$col' exists in users table</p>";
            } else {
                echo "<p style='color: red;'>❌ Column '$col' missing in users table</p>";
            }
        }
        
        // Check user_type values in database
        try {
            $userTypeCheck = DB::query("SELECT DISTINCT user_type FROM users LIMIT 10");
            echo "<p><strong>Found user_type values:</strong> ";
            $types = array_column($userTypeCheck, 'user_type');
            echo "<code>" . implode(", ", $types) . "</code></p>";
            
            // Check if we have users with type 'E' vs 'employee'
            $employeeCount = DB::queryFirstField("SELECT COUNT(*) FROM users WHERE user_type = 'E'");
            $employeeWordCount = DB::queryFirstField("SELECT COUNT(*) FROM users WHERE user_type = 'employee'");
            echo "<p><strong>User type counts:</strong> Type 'E': $employeeCount, Type 'employee': $employeeWordCount</p>";
            
        } catch (Exception $e) {
            echo "<p style='color: red;'>❌ Could not check user_type values: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
    } else {
        echo "<p style='color: red;'>❌ 'users' table not found</p>";
    }
    
    // Check units table
    $unitsCheck = DB::query("SHOW TABLES LIKE 'units'");
    if ($unitsCheck && count($unitsCheck) > 0) {
        echo "<p style='color: green;'>✅ 'units' table exists</p>";
        
        // Check if 2FA columns exist
        $unitsColumns = DB::query("DESCRIBE units");
        $twoFAColumns = ['two_factor_enabled', 'otp_validity_minutes', 'otp_digits'];
        foreach ($twoFAColumns as $col) {
            $found = false;
            foreach ($unitsColumns as $column) {
                if ($column['Field'] === $col) {
                    $found = true;
                    break;
                }
            }
            if ($found) {
                echo "<p style='color: green;'>✅ 2FA column '$col' exists in units table</p>";
            } else {
                echo "<p style='color: orange;'>⚠️ 2FA column '$col' missing in units table (run database_updates_2fa.sql)</p>";
            }
        }
    } else {
        echo "<p style='color: red;'>❌ 'units' table not found</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Database tables check error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Test 5: Check Constants
echo "<h3>Configuration Constants Test</h3>";
$requiredConstants = ['BASE_URL', 'ENVIRONMENT', 'MAX_LOGIN_ATTEMPTS'];
foreach ($requiredConstants as $constant) {
    if (defined($constant)) {
        echo "<p style='color: green;'>✅ $constant = " . htmlspecialchars(constant($constant)) . "</p>";
    } else {
        echo "<p style='color: red;'>❌ $constant not defined</p>";
    }
}

// Test 6: Simulate User Lookup
echo "<h3>User Lookup Test</h3>";
echo "<form method='post'>";
echo "<p>Test user lookup (Employee ID): <input type='text' name='test_employee_id' placeholder='Enter Employee ID'>";
echo "<input type='submit' name='test_lookup' value='Test Lookup'></p>";
echo "</form>";

if (isset($_POST['test_lookup']) && !empty($_POST['test_employee_id'])) {
    $testEmployeeId = trim($_POST['test_employee_id']);
    echo "<p><strong>Testing lookup for Employee ID:</strong> " . htmlspecialchars($testEmployeeId) . "</p>";
    
    try {
        // Test user lookup (same query as getUserDetails function)
        $testUser = DB::queryFirstRow("
            SELECT u.*, un.unit_name, un.unit_site
            FROM users u 
            LEFT JOIN units un ON u.unit_id = un.unit_id 
            WHERE u.employee_id = %s AND u.user_type = 'E'", 
            $testEmployeeId
        );
        
        if ($testUser) {
            echo "<p style='color: green;'>✅ User found:</p>";
            echo "<ul>";
            echo "<li>Name: " . htmlspecialchars($testUser['user_name'] ?? 'N/A') . "</li>";
            echo "<li>Email: " . htmlspecialchars($testUser['user_email'] ?? 'N/A') . "</li>";
            echo "<li>Unit ID: " . htmlspecialchars($testUser['unit_id'] ?? 'N/A') . "</li>";
            echo "<li>Status: " . htmlspecialchars($testUser['user_status'] ?? 'N/A') . "</li>";
            echo "<li>Account Locked: " . htmlspecialchars($testUser['is_account_locked'] ?? 'N/A') . "</li>";
            echo "</ul>";
            
            // Check 2FA status for this unit
            if ($testUser['unit_id']) {
                try {
                    require_once 'core/security/two_factor_auth.php';
                    $twoFactorConfig = TwoFactorAuth::getUnitTwoFactorConfig($testUser['unit_id']);
                    if ($twoFactorConfig) {
                        echo "<p><strong>2FA Configuration for Unit " . $testUser['unit_id'] . ":</strong></p>";
                        echo "<ul>";
                        echo "<li>2FA Enabled: " . ($twoFactorConfig['two_factor_enabled'] ?? 'N/A') . "</li>";
                        echo "<li>OTP Validity: " . ($twoFactorConfig['otp_validity_minutes'] ?? 'N/A') . " minutes</li>";
                        echo "<li>OTP Digits: " . ($twoFactorConfig['otp_digits'] ?? 'N/A') . "</li>";
                        echo "</ul>";
                    } else {
                        echo "<p style='color: orange;'>⚠️ No 2FA configuration found for unit</p>";
                    }
                } catch (Exception $e) {
                    echo "<p style='color: red;'>❌ Error checking 2FA config: " . htmlspecialchars($e->getMessage()) . "</p>";
                }
            }
        } else {
            echo "<p style='color: red;'>❌ User not found</p>";
        }
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ User lookup error: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
}

echo "<hr>";
echo "<p style='color: red;'><strong>⚠️ SECURITY WARNING: Delete this file (debug_login.php) before deploying to production!</strong></p>";
?>