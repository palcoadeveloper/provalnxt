<?php
// auth_utils.php - Common authentication functions

// Note: config.php is loaded by the main application

/**
 * Verify user credentials using the same logic as checklogin.php
 * @param string $username The username to verify
 * @param string $password The password to verify
 * @param string $userType The user type (E for Employee, V for Vendor)
 * @return array|bool User data array if authentication successful, false otherwise
 */
function verifyUserCredentials($username, $password, $userType) {
    // Get user details using secure parameterized queries
    $user = getUserDetails($userType, $username);
   
    if (!$user) {
        //$protocol = FORCE_HTTPS ? 'https://' : 'http://';
        //header('Location: ' . $protocol . $_SERVER['HTTP_HOST'] . '/proval4/public/login.php?msg=invld_acct');
        header('Location: ' . BASE_URL. 'login.php?msg=invld_acct');
        logSecurityEvent($username, 'unknown_user_login_failed', $username, 99);
        exit();
    }
    elseif($user['is_account_locked']=="Yes"){
         $unit_id = isset($user['unit_id']) && $user['unit_id'] !== null ? $user['unit_id'] : 0;
        logSecurityEvent($username, 'account_locked_accessed', $username, $unit_id);
        //$protocol = FORCE_HTTPS ? 'https://' : 'http://';
        //header('Location: ' . $protocol . $_SERVER['HTTP_HOST'] . '/proval4/public/login.php?msg=acct_lckd');
        header('Location: ' . BASE_URL. 'login.php?msg=acct_lckd');
        exit();
    }
        // Authentication failed
        elseif ($user['user_status']=="Inactive") {
            $unit_id = isset($user['unit_id']) && $user['unit_id'] !== null ? $user['unit_id'] : 0;
            logSecurityEvent($username, 'account_inactive', $username, $unit_id);
        //  $protocol = FORCE_HTTPS ? 'https://' : 'http://';
           header('Location: ' . BASE_URL. 'login.php?msg=acct_inactive');
           exit();
        }
    $unit_id = isset($user['unit_id']) && $user['unit_id'] !== null ? $user['unit_id'] : 0;

    // Authentication logic
    if (ENVIRONMENT === 'dev') {
        // Even in development, use proper password hashing
        // Development environment: Use plain text
        if ($password== $user['user_password']) {
            
            return $user;
        }
        return false;
    } elseif (ENVIRONMENT === 'prod') {
        // Production environment: Use LDAP
        $ldap_connection = ldap_connect(LDAP_URL);
        if (!$ldap_connection) {
            error_log("LDAP connection failed");
            return false;
        }

        ldap_set_option($ldap_connection, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($ldap_connection, LDAP_OPT_REFERRALS, 0);
        
        try {
            $ldapbind = @ldap_bind($ldap_connection, $username . '@cipla.com', $password);
            if ($ldapbind) {
                return $user;
            }
            return false;
        } catch (Exception $e) {
            error_log("LDAP error: " . $e->getMessage());
            return false;
        } finally {
            // Always close LDAP connection
            if ($ldap_connection) {
                ldap_close($ldap_connection);
            }
        }
    }
    
    return false;
}

/**
 * Get user details from the database
 * @param string $userType Type of user (E for Employee, V for Vendor)
 * @param string $username Username to look up
 * @return array|bool User data or false if not found
 */
function getUserDetails($userType, $username) {
    
    // Using prepared statements to prevent SQL injection
    if ($userType === "E") { // Employee
        try{
        $user = DB::queryFirstRow(
            "SELECT user_id, user_domain_id, user_name, u1.unit_id, unit_name, unit_site, 
                    department_id, is_qa_head, is_unit_head, is_admin, is_super_admin, 
                    is_account_locked, is_dept_head, user_status, user_password, employee_id
             FROM users u1 LEFT JOIN units u2 ON u1.unit_id = u2.unit_id
             WHERE user_domain_id = %s and user_type='employee'", 
            $username
        );
        } catch (Exception $e) {
    $errorMessage = handleDatabaseError($e, "User authentication");

    header('Location:' . BASE_URL. 'login.php?msg=system_error&ref=' . urlencode($errorMessage));
    exit();
}
    } elseif ($userType === "V") { // Vendor
        try{
        $user = DB::queryFirstRow(
            "SELECT user_id, employee_id, user_name, vendor_name, is_account_locked, 
                    user_password, is_default_password, user_status, user_domain_id 
             FROM users u, vendors v 
             WHERE u.vendor_id = v.vendor_id 
               AND u.user_type = 'vendor' 
               AND user_domain_id = %s",
            $username
        );
        } catch (Exception $e) {
    $errorMessage = handleDatabaseError($e, "User authentication");
    header('Location:' . BASE_URL. 'login.php?msg=system_error&ref=' . urlencode($errorMessage));
    exit();
}
    }

    // Check if we got a user back
    if (!$user) {
      
        return false;
    }
  
   
    
    return $user;
}

/**
 * Detect potential SQL injection attempts with reduced false positives
 * @param string $input Input to check
 * @return bool True if SQL injection detected, false otherwise
 */
function detectSQLInjection($input) {
    // More specific SQL injection patterns to reduce false positives
    $patterns = [
        // High-confidence SQL injection patterns
        '/\'\s*OR\s*\'.*=.*\'/i',       // ' OR '1'='1
        '/\'\s*OR\s*1\s*=\s*1/i',       // ' OR 1=1
        '/\'\s*AND\s*\'.*=.*\'/i',      // ' AND '1'='1
        '/\'\s*AND\s*1\s*=\s*1/i',     // ' AND 1=1
        '/\'\s*UNION\s+SELECT\s+/i',    // UNION SELECT
        '/;\s*SELECT\s+/i',             // ; SELECT
        '/;\s*INSERT\s+/i',             // ; INSERT
        '/;\s*UPDATE\s+/i',             // ; UPDATE  
        '/;\s*DELETE\s+/i',             // ; DELETE
        '/;\s*DROP\s+/i',               // ; DROP
        '/--\s*$/',                     // SQL comment at end of line
        '/\/\*.*\*\//',                 // Block comments
        '/DROP\s+TABLE/i',              // DROP TABLE
        '/ALTER\s+TABLE/i',             // ALTER TABLE
        '/EXEC\s+XP/i',                 // EXEC XP
        '/INSERT\s+INTO/i',             // INSERT INTO
        '/DELETE\s+FROM/i',             // DELETE FROM
        '/UPDATE\s+.*\s+SET/i',         // UPDATE ... SET
        '/CREATE\s+TABLE/i',            // CREATE TABLE
        '/TRUNCATE\s+TABLE/i',          // TRUNCATE TABLE
        
        // Context-aware patterns (more specific)
        '/\'\s*;\s*SELECT/i',           // '; SELECT
        '/\'\s*;\s*DROP/i',             // '; DROP
        '/\bOR\s+1\s*=\s*1\b/i',        // OR 1=1 (word boundary)
        '/\bAND\s+1\s*=\s*1\b/i',       // AND 1=1 (word boundary)
        '/\bOR\s+\'.*\'\s*=\s*\'/i',    // OR 'x'='x'
        '/\bAND\s+\'.*\'\s*=\s*\'/i',   // AND 'x'='x'
        
        // Hex and other encoding attempts
        '/0x[0-9a-f]+/i',               // Hexadecimal values
        '/CHAR\s*\(/i',                 // CHAR() function
        '/ASCII\s*\(/i',                // ASCII() function
        '/CONCAT\s*\(/i',               // CONCAT() function in SQL context
        
        // Time-based and blind injection patterns
        '/WAITFOR\s+DELAY/i',           // WAITFOR DELAY
        '/SLEEP\s*\(/i',                // SLEEP() function
        '/BENCHMARK\s*\(/i',            // BENCHMARK() function
        
        // Information gathering functions
        '/@@VERSION/i',                 // @@VERSION
        '/USER\s*\(\s*\)/i',           // USER()
        '/DATABASE\s*\(\s*\)/i',       // DATABASE()
        '/VERSION\s*\(\s*\)/i'         // VERSION()
    ];
    
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $input)) {
            return true;
        }
    }
    
    // Additional checks for suspicious character combinations
    // Check for multiple quotes with SQL keywords
    if (preg_match('/\'.*\'.*\b(SELECT|INSERT|UPDATE|DELETE|DROP|UNION)\b/i', $input)) {
        return true;
    }
    
    // Check for SQL injection with encoded characters
    if (preg_match('/%27|%22|%2D%2D|%3B/', $input)) {
        return true;
    }
    
    return false;
}

/**
 * Get client IP address with IPv4 preference
 * @param bool $forceIPv4 Whether to force IPv4 format
 * @return string IP address
 */
function getClientIP($forceIPv4 = true) {
    // Possible server variables that might contain the client IP
    $ipSources = [
        'HTTP_CLIENT_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_FORWARDED',
        'HTTP_X_CLUSTER_CLIENT_IP',
        'HTTP_FORWARDED_FOR',
        'HTTP_FORWARDED',
        'REMOTE_ADDR'
    ];
    
    foreach ($ipSources as $source) {
        if (isset($_SERVER[$source]) && !empty($_SERVER[$source])) {
            // For sources that might contain multiple IPs (e.g., X-Forwarded-For)
            if (strpos($_SERVER[$source], ',') !== false) {
                // Get the first IP in the list (client's original IP)
                $ips = explode(',', $_SERVER[$source]);
                $ip = trim($ips[0]);
            } else {
                $ip = $_SERVER[$source];
            }
            
            // Validate that this is actually an IP address
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                // If we need IPv4 and this is IPv6, try to convert
                if ($forceIPv4 && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                    // Handle localhost
                    if ($ip === '::1') {
                        return '127.0.0.1';
                    }
                    
                    // Handle IPv4-mapped IPv6 addresses (::ffff:192.0.2.128)
                    if (preg_match('/^::ffff:(\d+\.\d+\.\d+\.\d+)$/', $ip, $matches)) {
                        return $matches[1];
                    }
                    
                    // For other IPv6 addresses, we can't reliably convert to IPv4
                    // Log this case if needed
                    if (function_exists('error_log')) {
                        error_log("IPv6 address could not be converted to IPv4: $ip");
                    }
                }
                
                return $ip;
            }
        }
    }
    
    // Fallback: if we couldn't find any valid IP
    return '0.0.0.0';
}

/**
 * Log security events
 * @param string $username Username involved in the event
 * @param string $eventType Type of security event
 * @param int|null $userId User ID if available
 * @param int|null $unitId Unit ID if available
 * @return string IP address from which the event originated
 */
function logSecurityEvent($username, $eventType, $userId = null, $unitId = null) {
    $ip = getClientIP(true);
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    $timestamp = date('Y-m-d H:i:s');
    
    // Create description based on event type
    switch ($eventType) {
        case 'unknown_user_login_failed':
            $description = "Unknown user login failed. Entered user name: {$username}";
            $userId=null;
            $unitId=null;
            break;
        case 'csrf_failure':
            $description = "CSRF token validation failed for user: {$username}";
            break;
        case 'sql_injection_attempt':
            $description = "Potential SQL injection attempt on account: {$username}";
            break;
        case 'account_locked':
            $description = "Login failed. User {$username} is locked.";
            break;
        case 'account_locked_accessed':
            $description = "Access attempt on locked account: {$username}";
            break;
        case 'account_inactive':
            $description = "Access attempt on inactive account: {$username}";
            break;
        case 'invalid_login':
            $description = "Invalid login credentials for user: {$username}";
            break;
        default:
            $description = "Security event ({$eventType}) for user: {$username}";
    }
    try{
    // Insert into log table
    DB::insert('log', [
        'change_type' => 'security_error',
        'table_name' => '',
        'change_description' => $description." From IP: {$ip}",
        'change_by' => $userId,
        'unit_id' => $unitId
    ]);
    } catch (Exception $e) {
    $errorMessage = handleDatabaseError($e, "User authentication");
    header('Location:' . BASE_URL . ' login.php?msg=system_error&ref=' . urlencode($errorMessage));
    exit();
}
    error_log("[SECURITY EVENT] {$eventType}: User {$username} from IP {$ip} - {$description}");
    
    return $ip;
}

/**
 * Handle account locking after too many failed attempts
 * @param string $username Username to lock
 * @param int $userId User ID
 * @param int $unitId Unit ID
 */
function handleAccountLocking($username, $unitId) {
    if (!isset($_SESSION['failed_attempts'][$username])) {
        $_SESSION['failed_attempts'][$username] = 0;
    }
    
    $_SESSION['failed_attempts'][$username]++;
    if ($_SESSION['failed_attempts'][$username] >= MAX_LOGIN_ATTEMPTS) {
        try{
        // Use prepared statements for all database operations
        DB::update('users', ['is_account_locked' => 'Yes'], 'user_domain_id=%s', $username);
       
        } catch (Exception $e) {
    $errorMessage = handleDatabaseError($e, "User authentication");
    header('Location:' . BASE_URL. 'login.php?msg=system_error&ref=' . urlencode($errorMessage));
    exit();
}
        logSecurityEvent($username, 'account_locked', $username, $unitId);
        header('Location:' . BASE_URL . 'login.php?msg=acct_lckd');
        exit();
        
    } else {
        logSecurityEvent($username, 'invalid_login', $username, $unitId);
        return false;
    }
}


/**
 * Function to handle successful login - kept local to this file since it's specific to login flow
 * @param array $user User data
 * @param string $userType User type (E or V)
 */
function handleSuccessfulLogin($user, $userType)
{
    // Reset failed attempts
    $_SESSION['failed_attempts'][$user['employee_id']] = 0;
    $_SESSION['login_failed'] = "No";
    
    // Set login timestamp for session expiry checks
    $_SESSION['login_time'] = time();
    $_SESSION['last_activity'] = time(); // Initialize activity tracking

    // Regenerate session ID to prevent session fixation (but keep session data)
    session_regenerate_id(false);

    if ($userType === "E") { // Employee
        $_SESSION['logged_in_user'] = "employee";
        $_SESSION['account_name'] = htmlspecialchars($user['user_domain_id']);
        $_SESSION['user_id'] = (int)$user['user_id'];
        $_SESSION['user_name'] = htmlspecialchars($user['user_name']);
        $_SESSION['unit_id'] = (int)$user['unit_id'];
        $_SESSION['unit_name'] = htmlspecialchars($user['unit_name']);
        $_SESSION['unit_site'] = htmlspecialchars($user['unit_site']);
        $_SESSION['department_id'] = (int)$user['department_id'];
        $_SESSION['is_qa_head'] = $user['is_qa_head'];
        $_SESSION['is_unit_head'] = $user['is_unit_head'];
        $_SESSION['is_admin'] = $user['is_admin'];
        $_SESSION['is_super_admin'] = $user['is_super_admin'];
        $_SESSION['is_dept_head'] = $user['is_dept_head'];
        $_SESSION['employee_id'] = htmlspecialchars($user['employee_id']);
        $_SESSION['user_domain_id'] = htmlspecialchars($user['user_domain_id']);
        try{
        // Log the login with proper escaping
        DB::insert('log', [
            'change_type' => 'tran_login_int_emp',
            'table_name' => '',
            'change_description' => 'User ' . htmlspecialchars($user['user_domain_id']) . ' logged into the system.',
            'change_by' => (int)$user['user_id'],
            'unit_id' => (int)$user['unit_id']
        ]);
        } catch (Exception $e) {
            $errorMessage = handleDatabaseError($e, "User authentication");
            header('Location:' . BASE_URL. 'login.php?msg=system_error&ref=' . urlencode($errorMessage));
            exit();
        }
    } elseif ($userType === "V") { // Vendor
        $_SESSION['logged_in_user'] = "vendor";
        $_SESSION['account_name'] = htmlspecialchars($user['user_domain_id']);
        $_SESSION['user_id'] = (int)$user['user_id'];
        $_SESSION['user_name'] = htmlspecialchars($user['user_name']);
        $_SESSION['employee_id'] = "";
        $_SESSION['emp_id'] = htmlspecialchars($user['employee_id']);
        $_SESSION['unit_id'] = "";
        $_SESSION['department_id'] = "";
        $_SESSION['is_qa_head'] = "";
        $_SESSION['is_unit_head'] = "";
        $_SESSION['is_admin'] = "";
        $_SESSION['is_super_admin'] = "";
        $_SESSION['user_domain_id'] = htmlspecialchars($user['user_domain_id']);
        $_SESSION['vendor_name'] = htmlspecialchars($user['vendor_name']);
        try{
        // Log the login with proper escaping
        DB::insert('log', [
            'change_type' => 'tran_login_ext_emp',
            'table_name' => '',
            'change_description' => 'Vendor employee ' . htmlspecialchars($user['user_domain_id']) . ' logged into the system.',
            'change_by' => (int)$user['user_id'],
            'unit_id' => 0
        ]); 
        } catch (Exception $e) {
            $errorMessage = handleDatabaseError($e, "User authentication");
            header('Location:' . BASE_URL. 'login.php?msg=system_error&ref=' . urlencode($errorMessage));
            exit();
        }
    }
    
    // Debug before redirect
    error_log("Before redirect - Session user_name: " . ($_SESSION['user_name'] ?? 'NOT SET'));
    error_log("All session data before redirect: " . print_r($_SESSION, true));

    // Write session data to storage before redirect
    session_write_close();
    // Use absolute URL for redirect
    // $protocol = FORCE_HTTPS ? 'https://' : 'http://';
    // header('Location: ' . $protocol . $_SERVER['HTTP_HOST'] . '/proval4/public/home.php');
    header('Location:' . BASE_URL. 'home.php');

    exit();
}

/**
 * Sanitize and log database errors without exposing sensitive details
 * @param Exception $e The exception to handle
 * @param string $context Where the error occurred
 * @return string Generic error message for the user
 */
function handleDatabaseError($e, $context = '') {
    // Generate a unique error reference code
    $errorRef = uniqid();
    
    // Log the actual error with the reference code
    error_log("[DB ERROR REF:{$errorRef}] {$context}: " . $e->getMessage());
    
    // Return a generic message with the reference code
    return "System error occurred (Ref: {$errorRef}). Please contact support.";
}

/**
 * Generate a new CSRF token if one doesn't exist or is expired
 * @return string The CSRF token
 */
function generateCSRFToken() {
    error_log("Generating CSRF token. Current session token: " . ($_SESSION['csrf_token'] ?? 'not set'));
    if (empty($_SESSION['csrf_token']) || empty($_SESSION['csrf_token_time']) || 
        (time() - $_SESSION['csrf_token_time']) > 3600) { // 1 hour expiration
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();
        error_log("New CSRF token generated: " . $_SESSION['csrf_token']);
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate a CSRF token against the stored session token
 * @param string $token Token to validate
 * @param bool $regenerate Whether to generate a new token after validation
 * @return bool True if token is valid, false otherwise
 */
function validateCSRFToken($token, $regenerate = true) {
    error_log("=== CSRF TOKEN VALIDATION DEBUG ===");
    error_log("Session ID: " . session_id());
    error_log("Session token: " . ($_SESSION['csrf_token'] ?? 'not set'));
    error_log("Session token time: " . ($_SESSION['csrf_token_time'] ?? 'not set'));
    error_log("Received token: " . $token);
    error_log("Token lengths - Session: " . strlen($_SESSION['csrf_token'] ?? '') . ", Received: " . strlen($token));
    
    if (!isset($_SESSION['csrf_token'])) {
        error_log("CSRF validation failed - no session token");
        return false;
    }
    
    if ($token !== $_SESSION['csrf_token']) {
        error_log("CSRF validation failed - tokens don't match");
        error_log("Expected: " . $_SESSION['csrf_token']);
        error_log("Received: " . $token);
        return false;
    }
    
    // Check token age (optional)
    if (isset($_SESSION['csrf_token_time']) && (time() - $_SESSION['csrf_token_time']) > 3600) {
        error_log("CSRF validation failed - token expired");
        return false;
    }
    
    // Generate a new token for next request (prevent replay attacks)
    if ($regenerate) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();
        error_log("New CSRF token generated after validation: " . $_SESSION['csrf_token']);
    }
    
    error_log("CSRF validation successful");
    return true;
}

?>