# ProVal HVAC Security Reference Guide

## Overview
This document provides comprehensive security guidelines and implementation patterns for ProVal HVAC developers. All new code must follow these security standards.

---

## 1. STANDARD PHP FILE SECURITY TEMPLATE

### Mandatory Structure for All PHP Files

```php
<?php
/**
 * ProVal HVAC - [File Description]
 * 
 * Security Level: [High/Medium/Low]
 * Authentication Required: [Yes/No]
 * Input Sources: [GET/POST/FILES/SESSION]
 */

// 1. CONFIGURATION - Always load first
require_once('./core/config.php');
// Note: config.php automatically includes session_init.php

// 2. SECURITY MIDDLEWARE - Critical for all files
require_once('core/session_timeout_middleware.php');
validateActiveSession();

// 3. DATABASE CONNECTION - Use class-based approach
include_once("core/db.class.php");

// 4. TIMEZONE SETTING - Required for audit logs
date_default_timezone_set("Asia/Kolkata");

// 5. AUTHENTICATION CHECK - Customize based on requirements
if (!isset($_SESSION['user_name'])) {
    header('Location: login.php?msg=authentication_required');
    exit;
}

// 6. INPUT VALIDATION - Load utilities if processing user input
if ($_SERVER['REQUEST_METHOD'] === 'POST' || !empty($_GET)) {
    require_once('core/input_validation_utils.php');
}

// 7. RATE LIMITING - For sensitive operations
if (RATE_LIMITING_ENABLED) {
    require_once('core/rate_limiting_utils.php');
}

// 8. SECURITY LOGGING - For high-security operations
require_once('core/security_middleware.php');

// Your application logic starts here...
?>
```

---

## 2. INPUT VALIDATION & SANITIZATION

### A. Text Input Processing

```php
// Standard text cleaning function (already implemented)
function cleanTextInput($text) {
    if (is_null($text)) return '';
    
    // Convert all types of line breaks to a standard format
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    
    // Replace multiple consecutive line breaks with a single one
    $text = preg_replace('/\n{3,}/', "\n\n", $text);
    
    // Trim whitespace from beginning and end
    $text = trim($text);
    
    return $text;
}

// Example usage - Clean all input data before processing
$cleanDeviation = cleanTextInput($_POST['deviation'] ?? '');
$cleanSummary = cleanTextInput($_POST['summary'] ?? '');
$cleanRecommendation = cleanTextInput($_POST['recommendation'] ?? '');
```

### B. Advanced Input Validation using InputValidator Class

```php
// Validate different types of input
$validatedEmail = InputValidator::validateEmail($_POST['email']);
$validatedInteger = InputValidator::validateInteger($_POST['count'], 1, 1000);
$validatedDate = InputValidator::validateDate($_POST['date']);
$validatedWorkflowId = InputValidator::validateWorkflowId($_POST['workflow_id']);

// Batch validation with rules
$validationRules = [
    'name' => [
        'required' => true,
        'validator' => 'validateText',
        'params' => [InputValidator::MAX_LENGTH_MEDIUM, true, false]
    ],
    'email' => [
        'required' => true,
        'validator' => 'validateEmail'
    ],
    'description' => [
        'required' => false,
        'validator' => 'validateText',
        'params' => [InputValidator::MAX_LENGTH_LONG, false, true]
    ]
];

$validation = InputValidator::validatePostData($validationRules, $_POST);

if ($validation['valid']) {
    $cleanData = $validation['data'];
    // Process clean data
} else {
    $errors = $validation['errors'];
    // Handle validation errors
}
```

### C. XSS Prevention

```php
// Always escape output in HTML context
echo htmlspecialchars($userInput, ENT_QUOTES, 'UTF-8');

// For JSON responses
header('Content-Type: application/json; charset=UTF-8');
echo json_encode([
    'status' => 'success',
    'data' => htmlspecialchars($data, ENT_QUOTES, 'UTF-8')
]);
```

---

## 3. DATABASE SECURITY

### A. Parameterized Queries (MANDATORY)

```php
// CORRECT - Always use parameterized queries
DB::query("UPDATE validation_reports SET 
    deviation=%s, summary=%s, recommendation=%s 
    WHERE report_id=%i AND user_id=%i", 
    $cleanDeviation, $cleanSummary, $cleanRecommendation, $reportId, $_SESSION['user_id']);

// CORRECT - SELECT with parameters
$results = DB::query("SELECT * FROM users WHERE department_id=%i AND status=%s", 
    $departmentId, 'active');

// WRONG - Never use direct concatenation
// $query = "UPDATE table SET field='" . $_POST['data'] . "'"; // NEVER DO THIS

// Parameter types:
// %s = string
// %i = integer
// %f = float
// %d = double
```

### B. Data Sanitization Before Database Operations

```php
// Clean and validate before database operations
$validationRules = [
    'deviation' => ['required' => true, 'validator' => 'sanitizeString'],
    'summary' => ['required' => true, 'validator' => 'sanitizeString'],
    'test_id' => ['required' => true, 'validator' => 'validateInteger', 'params' => [1]]
];

$validation = InputValidator::validatePostData($validationRules);

if ($validation['valid']) {
    $data = $validation['data'];
    
    // Use SecureTransaction for database operations with session validation
    try {
        executeSecureTransaction(function() use ($data) {
            DB::query("INSERT INTO validation_reports (deviation, summary, test_id, created_by) 
                       VALUES (%s, %s, %i, %i)",
                       $data['deviation'], $data['summary'], $data['test_id'], $_SESSION['user_id']);
        }, 'validation_report_insert');
    } catch (SecurityException $e) {
        // Handle session validation failure
        error_log("Secure database operation failed: " . $e->getMessage());
    }
}
```

---

## 4. FILE UPLOAD SECURITY

### A. Secure File Upload Implementation

```php
// Validate uploaded file
if (isset($_FILES['upload']) && $_FILES['upload']['error'] === UPLOAD_ERR_OK) {
    
    // Define allowed file types for this specific operation
    $allowedTypes = ['pdf', 'docx', 'xlsx', 'jpg', 'png'];
    $maxSize = 5 * 1024 * 1024; // 5MB
    
    $validation = FileUploadValidator::validateFile($_FILES['upload'], $allowedTypes, $maxSize);
    
    if ($validation['valid']) {
        $safeFilename = $validation['sanitized_name'];
        $uploadPath = '/secure/uploads/' . $safeFilename;
        
        // Additional security checks
        if (FileUploadValidator::scanForMalware($_FILES['upload']['tmp_name'])) {
            if (move_uploaded_file($_FILES['upload']['tmp_name'], $uploadPath)) {
                // Log the upload
                SecurityUtils::logSecurityEvent('file_upload', 'File uploaded successfully', [
                    'filename' => $safeFilename,
                    'size' => $_FILES['upload']['size']
                ]);
                
                // Save to database
                DB::query("INSERT INTO uploaded_files (filename, original_name, file_size, uploaded_by) 
                           VALUES (%s, %s, %i, %i)",
                           $safeFilename, $_FILES['upload']['name'], $_FILES['upload']['size'], $_SESSION['user_id']);
            }
        } else {
            SecurityUtils::logSecurityEvent('malicious_upload_blocked', 'Malicious file upload blocked', [
                'filename' => $_FILES['upload']['name']
            ]);
        }
    } else {
        // Handle validation errors
        $errors = $validation['errors'];
    }
}
```

---

## 5. SESSION MANAGEMENT

### A. Session Activity Tracking

```php
// Update session activity for user interactions
updateSessionActivity();

// Extend session for long operations
extendSessionForTransaction('report_generation');

// Complete transaction when done
completeTransaction();

// Check remaining session time
$remainingTime = getRemainingSessionTime();
if ($remainingTime < 300) { // Less than 5 minutes
    // Warn user about impending timeout
}
```

### B. Session Security Best Practices

```php
// Check for session hijacking
if (isset($_SESSION['user_ip']) && $_SESSION['user_ip'] !== SecurityUtils::getClientIP()) {
    SecurityUtils::logSecurityEvent('session_hijack_attempt', 'IP address mismatch detected');
    destroySession();
    redirectToLogin('security_violation');
}

// Regenerate session ID for sensitive operations
if ($sensitiveOperation) {
    session_regenerate_id(true);
}
```

---

## 6. SECURITY HEADERS & HTTPS

### A. Setting Security Headers

```php
// Set security context for specific operations
setSecurityContext('pdf_generation', 'PDF generation and download');

// Apply security headers
$securityManager = getSecurityManager();
$securityManager->applyHeaders();

// For AJAX responses
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    
    header('Content-Type: application/json; charset=UTF-8');
    $securityManager->applyHeaders(true); // Force headers for AJAX
}
```

### B. HTTPS Enforcement

```php
// Check if HTTPS is required for sensitive operations
if (FORCE_HTTPS && !isSecureRequest()) {
    redirectToHttps();
}

// For API endpoints, return JSON error for HTTP requests
if (!isSecureRequest() && isApiRequest()) {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error',
        'message' => 'HTTPS required for this operation',
        'code' => 'HTTPS_REQUIRED'
    ]);
    exit;
}
```

---

## 7. ERROR HANDLING & LOGGING

### A. Security Event Logging

```php
// Log security-relevant events
SecurityUtils::logSecurityEvent('login_attempt', 'User login attempt', [
    'username' => $username,
    'ip' => SecurityUtils::getClientIP(),
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
]);

// Log failed operations
SecurityUtils::logSecurityEvent('unauthorized_access', 'Access denied to restricted resource', [
    'resource' => $_SERVER['REQUEST_URI'],
    'user_id' => $_SESSION['user_id'] ?? null
]);
```

### B. Safe Error Display

```php
try {
    // Database operation
    $result = DB::query("SELECT * FROM sensitive_table WHERE id=%i", $id);
} catch (Exception $e) {
    // Log detailed error
    error_log("Database error: " . $e->getMessage());
    
    // Show safe error to user
    if (ENVIRONMENT === 'dev') {
        echo "Database error: " . htmlspecialchars($e->getMessage());
    } else {
        echo "An error occurred. Please try again later.";
    }
    
    // Log security event
    SecurityUtils::logSecurityEvent('database_error', 'Database operation failed');
}
```

---

## 8. SECURE TRANSACTION MANAGEMENT

### A. Using SecureTransaction Wrapper

The `SecureTransaction` class provides mandatory session validation for all database transactions with automatic rollback capabilities.

```php
// Include the secure transaction wrapper
require_once('core/secure_transaction_wrapper.php');

// Simple secure transaction using static method
try {
    $result = SecureTransaction::execute(function() {
        // All database operations within this block are protected
        DB::query("INSERT INTO validation_reports (title, status) VALUES (%s, %s)", 
                  $title, 'pending');
        
        $reportId = DB::insertId();
        
        DB::query("INSERT INTO audit_log (action, report_id, user_id) VALUES (%s, %i, %i)",
                  'report_created', $reportId, $_SESSION['user_id']);
        
        return $reportId;
        
    }, 'validation_report_creation');
    
    // Transaction committed successfully
    echo "Report created successfully with ID: " . $result;
    
} catch (SecurityException $e) {
    // Session validation failed or transaction rolled back
    error_log("Security transaction failed: " . $e->getMessage());
    echo "Operation failed due to security constraints.";
}
```

### B. Complex Transaction Management

For multi-step operations requiring manual control:

```php
// Manual transaction control for complex operations
$transaction = new SecureTransaction('complex_validation_workflow');

try {
    // Begin transaction with session validation
    $transaction->begin();
    
    // Step 1: Create validation record
    DB::query("INSERT INTO validation_reports (title, created_by) VALUES (%s, %i)",
              $title, $_SESSION['user_id']);
    $reportId = DB::insertId();
    
    // Step 2: Process file uploads (if any)
    if (!empty($_FILES['documents'])) {
        foreach ($_FILES['documents'] as $file) {
            // File processing with validation
            // ... file upload logic
        }
    }
    
    // Step 3: Update workflow status
    DB::query("INSERT INTO workflow_tracking (report_id, stage, assigned_to) VALUES (%i, %s, %i)",
              $reportId, 'initial_review', $_SESSION['user_id']);
    
    // Step 4: Send notifications
    // ... notification logic
    
    // Commit all changes
    $transaction->commit();
    
} catch (Exception $e) {
    // Automatic rollback on any failure
    error_log("Complex transaction failed: " . $e->getMessage());
    throw $e;
}
```

### C. Convenience Function Usage

For simple cases, use the convenience function:

```php
// Using convenience function
$newUserId = executeSecureTransaction(function() {
    // Create new user with validation
    DB::query("INSERT INTO users (username, email, department) VALUES (%s, %s, %s)",
              $username, $email, $department);
    
    $userId = DB::insertId();
    
    // Set initial permissions
    DB::query("INSERT INTO user_permissions (user_id, permission_level) VALUES (%i, %s)",
              $userId, 'standard');
    
    return $userId;
    
}, 'user_creation');
```

### D. Session Integration Features

SecureTransaction automatically integrates with session management:

```php
// The wrapper automatically:
// 1. Validates session before starting transaction
// 2. Extends session for the operation using extendSessionForTransaction()
// 3. Validates session again before commit
// 4. Calls completeTransaction() on successful commit
// 5. Rolls back on session failures or exceptions

// Manual session validation (done automatically by SecureTransaction)
$transaction = new SecureTransaction('manual_example');

try {
    $transaction->begin(); // Validates session and extends it
    
    // Your database operations here
    
    $transaction->commit(); // Re-validates session and commits
    
} catch (SecurityException $e) {
    // Session became invalid during transaction
    // Automatic rollback already performed
}
```

---

## 9. RATE LIMITING & ABUSE PREVENTION

### A. Implementing Rate Limiting

```php
// Check rate limit for sensitive operations
if (SecurityUtils::checkRateLimit('password_reset', 3, 900)) { // 3 attempts in 15 minutes
    header('HTTP/1.1 429 Too Many Requests');
    SecurityUtils::logSecurityEvent('rate_limit_exceeded', 'Password reset rate limit exceeded');
    die('Too many password reset attempts. Please try again later.');
}

// For login attempts
if (RateLimiter::checkRateLimit('login_attempts')) {
    $rateLimitResult = RateLimiter::getRateLimitStatus('login_attempts');
    $waitTime = ceil(($rateLimitResult['lockout_expires'] - time()) / 60);
    
    die("Too many login attempts. Account locked for $waitTime minutes.");
}
```

---

## 9. AUTHENTICATION & AUTHORIZATION

### A. Multi-Level Authentication Check

```php
// Basic authentication check
if (!isset($_SESSION['user_name'])) {
    header('Location: login.php?msg=login_required');
    exit;
}

// Role-based access control
$requiredRoles = ['admin', 'supervisor'];
$userRole = $_SESSION['user_role'] ?? '';

if (!in_array($userRole, $requiredRoles)) {
    SecurityUtils::logSecurityEvent('unauthorized_access', 'Insufficient privileges', [
        'required_roles' => $requiredRoles,
        'user_role' => $userRole
    ]);
    header('HTTP/1.1 403 Forbidden');
    die('Access denied: Insufficient privileges');
}

// Resource-specific authorization
$resourceId = $_GET['id'] ?? 0;
if (!canUserAccessResource($_SESSION['user_id'], $resourceId)) {
    SecurityUtils::logSecurityEvent('unauthorized_resource_access', 'User attempted to access restricted resource');
    die('Access denied');
}
```

---

## 10. SECURITY TESTING CHECKLIST

### A. Pre-Deployment Security Checks

- [ ] All user inputs are validated and sanitized
- [ ] Database queries use parameterized statements
- [ ] File uploads are properly validated
- [ ] Authentication is properly implemented
- [ ] Session management is secure
- [ ] Security headers are set
- [ ] Error handling doesn't leak sensitive information
- [ ] Rate limiting is implemented for sensitive operations
- [ ] Security events are properly logged
- [ ] HTTPS is enforced where required

### B. Code Review Security Points

- [ ] No direct use of `$_GET`, `$_POST`, `$_FILES` without validation
- [ ] No SQL query concatenation
- [ ] All outputs are escaped in HTML context
- [ ] Sensitive operations have additional authorization checks
- [ ] File paths are validated to prevent directory traversal
- [ ] Session data is validated before use
- [ ] Error messages don't reveal system information

---

## 11. COMMON SECURITY VULNERABILITIES TO AVOID

### A. SQL Injection Prevention

```php
// WRONG - Vulnerable to SQL injection
$query = "SELECT * FROM users WHERE name = '" . $_POST['name'] . "'";

// CORRECT - Use parameterized queries
$result = DB::query("SELECT * FROM users WHERE name = %s", $_POST['name']);
```

### B. XSS Prevention

```php
// WRONG - Vulnerable to XSS
echo "<div>" . $_POST['comment'] . "</div>";

// CORRECT - Escape output
echo "<div>" . htmlspecialchars($_POST['comment'], ENT_QUOTES, 'UTF-8') . "</div>";
```

### C. Directory Traversal Prevention

```php
// WRONG - Vulnerable to directory traversal
$filename = $_GET['file'];
include("files/" . $filename);

// CORRECT - Validate filename
$filename = InputValidator::validateFilename($_GET['file']);
if ($filename !== false && file_exists("files/" . $filename)) {
    include("files/" . $filename);
}
```

---

## 12. EMERGENCY SECURITY PROCEDURES

### A. Security Incident Response

1. **Immediate Response**
   - Log the incident with full details
   - Block the attacking IP if possible
   - Preserve evidence (logs, database state)

2. **Investigation**
   - Review security logs
   - Check for data compromise
   - Identify attack vectors

3. **Recovery**
   - Apply security patches
   - Update security measures
   - Monitor for follow-up attacks

### B. Security Monitoring

```php
// Monitor for suspicious patterns
if (SecurityUtils::detectSuspiciousPatterns($_POST['input'])) {
    SecurityUtils::logSecurityEvent('suspicious_input', 'Malicious pattern detected', [
        'input' => substr($_POST['input'], 0, 100),
        'pattern_type' => 'sql_injection_attempt'
    ]);
    
    // Block the request
    header('HTTP/1.1 400 Bad Request');
    die('Invalid input detected');
}
```

---

## Conclusion

This security reference guide must be followed for all new development in the ProVal HVAC system. Regular security reviews and updates to this guide ensure the system remains protected against evolving threats.

**Remember: Security is not optional - it's a fundamental requirement for all code in this system.**