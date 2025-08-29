<?php
/**
 * ProVal HVAC - Security Template for New PHP Files
 * 
 * Copy this template for all new PHP files to ensure consistent security implementation
 * 
 * Security Level: High
 * Authentication Required: Yes
 * Input Sources: GET/POST/FILES/SESSION
 * 
 * @version 1.0
 * @author ProVal Security Team
 */

// =======================================================================================
// MANDATORY SECURITY HEADERS - DO NOT MODIFY ORDER
// =======================================================================================

// 1. CONFIGURATION - Always load first
require_once('./core/config.php');
// Note: config.php automatically includes session_init.php which starts session

// 2. SESSION VALIDATION - Critical for all authenticated pages
require_once('core/session_timeout_middleware.php');
validateActiveSession();

// 3. DATABASE CONNECTION - Use class-based approach
include_once("core/db.class.php");

// 4. TIMEZONE SETTING - Required for audit logs and timestamps
date_default_timezone_set("Asia/Kolkata");

// =======================================================================================
// AUTHENTICATION & AUTHORIZATION
// =======================================================================================

// Basic authentication check - customize based on requirements
if (!isset($_SESSION['user_name'])) {
    // Log unauthorized access attempt
    error_log("Unauthorized access attempt to: " . $_SERVER['REQUEST_URI'] . " from IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    header('Location: login.php?msg=authentication_required');
    exit;
}

// Optional: Role-based access control (uncomment and customize as needed)
/*
$requiredRoles = ['admin', 'supervisor']; // Define required roles for this page
$userRole = $_SESSION['user_role'] ?? '';

if (!in_array($userRole, $requiredRoles)) {
    if (class_exists('SecurityUtils')) {
        SecurityUtils::logSecurityEvent('unauthorized_access', 'Insufficient privileges', [
            'required_roles' => $requiredRoles,
            'user_role' => $userRole,
            'page' => basename(__FILE__)
        ]);
    }
    header('HTTP/1.1 403 Forbidden');
    die('Access denied: Insufficient privileges');
}
*/

// Optional: Resource-specific authorization (uncomment and customize as needed)
/*
$resourceId = $_GET['id'] ?? 0;
if (!canUserAccessResource($_SESSION['user_id'], $resourceId)) {
    if (class_exists('SecurityUtils')) {
        SecurityUtils::logSecurityEvent('unauthorized_resource_access', 'User attempted to access restricted resource', [
            'resource_id' => $resourceId,
            'user_id' => $_SESSION['user_id']
        ]);
    }
    die('Access denied to this resource');
}
*/

// =======================================================================================
// INPUT VALIDATION & SECURITY UTILITIES
// =======================================================================================

// Load input validation utilities if processing user input
if ($_SERVER['REQUEST_METHOD'] === 'POST' || !empty($_GET)) {
    require_once('core/input_validation_utils.php');
}

// Load secure transaction wrapper for database operations
require_once('core/secure_transaction_wrapper.php');

// Load rate limiting for sensitive operations
if (defined('RATE_LIMITING_ENABLED') && RATE_LIMITING_ENABLED) {
    require_once('core/rate_limiting_utils.php');
    
    // Example: Check rate limit for sensitive operations
    /*
    if (SecurityUtils::checkRateLimit('sensitive_operation', 5, 300)) { // 5 attempts in 5 minutes
        header('HTTP/1.1 429 Too Many Requests');
        SecurityUtils::logSecurityEvent('rate_limit_exceeded', 'Rate limit exceeded for sensitive operation');
        die('Too many attempts. Please try again later.');
    }
    */
}

// Load security middleware
require_once('core/security_middleware.php');

// =======================================================================================
// INPUT PROCESSING EXAMPLE
// =======================================================================================

// Example: Process form input securely
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Update session activity for user interactions
    if (function_exists('updateSessionActivity')) {
        updateSessionActivity();
    }
    
    // Define validation rules for your specific inputs
    $validationRules = [
        'name' => [
            'required' => true,
            'validator' => 'validateText',
            'params' => [InputValidator::MAX_LENGTH_MEDIUM, true, false]
        ],
        'email' => [
            'required' => false,
            'validator' => 'validateEmail'
        ],
        'description' => [
            'required' => false,
            'validator' => 'validateText',
            'params' => [InputValidator::MAX_LENGTH_LONG, false, true]
        ]
        // Add more fields as needed
    ];
    
    // Validate all POST data
    $validation = InputValidator::validatePostData($validationRules, $_POST);
    
    if ($validation['valid']) {
        $cleanData = $validation['data'];
        
        // Example: Save to database using secure transactions
        try {
            // Use SecureTransaction for automatic session validation and rollback
            $recordId = executeSecureTransaction(function() use ($cleanData) {
                // Use parameterized queries - NEVER concatenate user input
                DB::query("INSERT INTO your_table (name, email, description, created_by) 
                           VALUES (%s, %s, %s, %i)",
                           $cleanData['name'], 
                           $cleanData['email'], 
                           $cleanData['description'], 
                           $_SESSION['user_id']);
                
                $recordId = DB::insertId();
                
                // Additional database operations within same transaction
                DB::query("INSERT INTO audit_log (action, table_name, record_id, user_id) 
                           VALUES (%s, %s, %i, %i)",
                           'record_created', 'your_table', $recordId, $_SESSION['user_id']);
                
                return $recordId;
                
            }, 'database_operation');
            
            // Log successful operation
            if (class_exists('SecurityUtils')) {
                SecurityUtils::logSecurityEvent('data_insert', 'Record created successfully', [
                    'table' => 'your_table',
                    'record_id' => $recordId,
                    'user_id' => $_SESSION['user_id']
                ]);
            }
            
            $success_message = "Data saved successfully with ID: " . $recordId;
            
        } catch (SecurityException $e) {
            // Session validation failed or transaction rolled back
            error_log("Secure transaction failed in " . basename(__FILE__) . ": " . $e->getMessage());
            
            if (class_exists('SecurityUtils')) {
                SecurityUtils::logSecurityEvent('secure_transaction_failed', 'Secure database operation failed', [
                    'file' => basename(__FILE__),
                    'error' => $e->getMessage(),
                    'user_id' => $_SESSION['user_id'] ?? null
                ]);
            }
            
            $error_message = "Operation failed due to security constraints. Please try again.";
            
        } catch (Exception $e) {
            // Other database errors
            error_log("Database error in " . basename(__FILE__) . ": " . $e->getMessage());
            
            // Show safe error message to user
            if (defined('ENVIRONMENT') && ENVIRONMENT === 'dev') {
                $error_message = "Database error: " . htmlspecialchars($e->getMessage());
            } else {
                $error_message = "An error occurred. Please try again later.";
            }
        }
        
    } else {
        // Handle validation errors
        $errors = $validation['errors'];
        $error_message = "Please correct the following errors: " . implode(', ', $errors);
        
        // Log validation failure
        if (class_exists('SecurityUtils')) {
            SecurityUtils::logSecurityEvent('input_validation_failed', 'Invalid input detected', [
                'errors' => $errors,
                'file' => basename(__FILE__)
            ]);
        }
    }
}

// =======================================================================================
// FILE UPLOAD HANDLING EXAMPLE
// =======================================================================================

// Example: Handle file uploads securely
if (isset($_FILES['upload']) && $_FILES['upload']['error'] === UPLOAD_ERR_OK) {
    
    // Define allowed file types for this specific operation
    $allowedTypes = ['pdf', 'docx', 'xlsx', 'jpg', 'png'];
    $maxSize = 5 * 1024 * 1024; // 5MB
    
    $validation = FileUploadValidator::validateFile($_FILES['upload'], $allowedTypes, $maxSize);
    
    if ($validation['valid']) {
        $safeFilename = $validation['sanitized_name'];
        $uploadPath = 'uploads/' . $safeFilename; // Ensure this directory is secure
        
        // Additional security checks
        if (FileUploadValidator::scanForMalware($_FILES['upload']['tmp_name'])) {
            if (move_uploaded_file($_FILES['upload']['tmp_name'], $uploadPath)) {
                
                // Log successful upload
                if (class_exists('SecurityUtils')) {
                    SecurityUtils::logSecurityEvent('file_upload', 'File uploaded successfully', [
                        'filename' => $safeFilename,
                        'original_name' => $_FILES['upload']['name'],
                        'size' => $_FILES['upload']['size'],
                        'user_id' => $_SESSION['user_id']
                    ]);
                }
                
                // Save file info to database
                DB::query("INSERT INTO uploaded_files (filename, original_name, file_size, uploaded_by, created_at) 
                           VALUES (%s, %s, %i, %i, NOW())",
                           $safeFilename, 
                           $_FILES['upload']['name'], 
                           $_FILES['upload']['size'], 
                           $_SESSION['user_id']);
                
                $success_message = "File uploaded successfully.";
                
            } else {
                $error_message = "Failed to save uploaded file.";
            }
        } else {
            // Malicious file detected
            if (class_exists('SecurityUtils')) {
                SecurityUtils::logSecurityEvent('malicious_upload_blocked', 'Malicious file upload blocked', [
                    'filename' => $_FILES['upload']['name'],
                    'user_id' => $_SESSION['user_id']
                ]);
            }
            $error_message = "File upload blocked: Security violation detected.";
        }
    } else {
        $error_message = "File upload error: " . implode(', ', $validation['errors']);
    }
}

// =======================================================================================
// SECURITY HEADERS FOR RESPONSES
// =======================================================================================

// Set security context for this page
if (function_exists('setSecurityContext')) {
    setSecurityContext('general_page', 'General application page');
}

// Apply security headers
if (function_exists('getSecurityManager')) {
    $securityManager = getSecurityManager();
    $securityManager->applyHeaders();
}

// For AJAX responses
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    
    header('Content-Type: application/json; charset=UTF-8');
    
    // Return JSON response
    echo json_encode([
        'status' => isset($success_message) ? 'success' : (isset($error_message) ? 'error' : 'unknown'),
        'message' => $success_message ?? $error_message ?? '',
        'data' => [] // Add any response data here
    ]);
    exit;
}

// =======================================================================================
// HTML OUTPUT BEGINS HERE
// =======================================================================================
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>ProVal HVAC - [Page Title]</title>
    
    <!-- Security meta tags -->
    <meta http-equiv="X-Content-Type-Options" content="nosniff">
    <meta http-equiv="X-Frame-Options" content="SAMEORIGIN">
    <meta http-equiv="X-XSS-Protection" content="1; mode=block">
    
    <!-- Include your CSS files here -->
</head>
<body>
    
    <!-- Display success/error messages -->
    <?php if (isset($success_message)): ?>
        <div class="alert alert-success">
            <?= htmlspecialchars($success_message, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-error">
            <?= htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>
    
    <!-- Your HTML content here -->
    <h1>ProVal HVAC - Secure Page Template</h1>
    
    <!-- Example secure form -->
    <form method="POST" enctype="multipart/form-data">
        
        <!-- CSRF token (uncomment if CSRF protection is implemented) -->
        <?php /*
        if (function_exists('generateCSRFToken')) {
            echo '<input type="hidden" name="csrf_token" value="' . generateCSRFToken() . '">';
        }
        */ ?>
        
        <div class="form-group">
            <label for="name">Name (Required):</label>
            <input type="text" 
                   id="name" 
                   name="name" 
                   value="<?= htmlspecialchars($_POST['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                   maxlength="255"
                   required>
        </div>
        
        <div class="form-group">
            <label for="email">Email:</label>
            <input type="email" 
                   id="email" 
                   name="email" 
                   value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        </div>
        
        <div class="form-group">
            <label for="description">Description:</label>
            <textarea id="description" 
                      name="description" 
                      maxlength="1000"><?= htmlspecialchars($_POST['description'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
        </div>
        
        <div class="form-group">
            <label for="upload">File Upload:</label>
            <input type="file" 
                   id="upload" 
                   name="upload" 
                   accept=".pdf,.docx,.xlsx,.jpg,.png">
            <small>Allowed: PDF, DOCX, XLSX, JPG, PNG (Max: 5MB)</small>
        </div>
        
        <button type="submit">Submit Securely</button>
    </form>
    
    <!-- Include your JavaScript files here -->
    <script>
        // Example: Session timeout warning
        <?php if (function_exists('getRemainingSessionTime')): ?>
        var sessionTimeout = <?= getRemainingSessionTime() * 1000 ?>; // Convert to milliseconds
        
        if (sessionTimeout < 300000) { // Less than 5 minutes
            console.warn('Session will expire soon');
            // Implement session timeout warning UI
        }
        <?php endif; ?>
        
        // Example: Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            var name = document.getElementById('name').value.trim();
            if (!name) {
                alert('Name is required');
                e.preventDefault();
            }
        });
    </script>
    
</body>
</html>

<?php
// =======================================================================================
// CLEANUP AND FINAL SECURITY MEASURES
// =======================================================================================

// Clear sensitive variables (if any were used)
if (isset($password)) {
    $password = null;
    unset($password);
}

// Log page access for audit trail (optional)
if (class_exists('SecurityUtils') && defined('LOG_PAGE_ACCESS') && LOG_PAGE_ACCESS) {
    SecurityUtils::logSecurityEvent('page_access', 'Page accessed', [
        'page' => basename(__FILE__),
        'user_id' => $_SESSION['user_id'] ?? null,
        'ip' => SecurityUtils::getClientIP()
    ]);
}
?>