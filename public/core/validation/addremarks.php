<?php
// Load config first to ensure constants are defined
require_once('../config/config.php');

// Session is already started by config.php via session_init.php

// Validate session timeout and extend for transaction
require_once('../security/session_timeout_middleware.php');
validateActiveSession();
extendSessionForTransaction('add_remarks');
require_once '../config/db.class.php';
include_once '../security/auth_utils.php'; // This also includes config.php but we loaded it already
date_default_timezone_set("Asia/Kolkata");

// Set error handling for debugging
if (ENVIRONMENT === 'dev') {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}

/**
 * Detect operation type from workflow ID
 * @param string $val_wf_id Validation workflow ID
 * @param string $test_wf_id Test workflow ID
 * @return string Operation description
 */
function detectOperationType($val_wf_id, $test_wf_id, $operation_context = '') {
    $workflow_id = !empty($val_wf_id) ? $val_wf_id : $test_wf_id;
    
    // First try to detect from workflow ID patterns
    if (!empty($workflow_id)) {
        if (strpos($workflow_id, 'RT_SCH_GEN_') === 0) {
            return 'Routine test schedule generation failed.';
        } elseif (strpos($workflow_id, 'SCH_GEN_') === 0) {
            return 'Schedule generation failed.';
        } elseif (strpos($workflow_id, 'ADD_VAL_REQ_') === 0) {
            return 'Adhoc validation request submission failed.';
        } else {
            return 'Operation failed.';
        }
    }
    
    // If no workflow ID, use operation context
    if (!empty($operation_context)) {
        switch ($operation_context) {
            case 'schedule_generation':
                return 'Schedule generation failed.';
            case 'routine_test_schedule_generation':
                return 'Routine test schedule generation failed.';
            case 'adhoc_validation_request_submission':
                return 'Adhoc validation request submission failed.';
            case 'routine_test_addition':
                return 'Routine test addition failed.';
            case 'adhoc_validation_status_change':
                return 'Adhoc validation status change failed.';
            case 'routine_test_status_change':
                return 'Routine test status change failed.';
            default:
                return 'Operation failed.';
        }
    }
    
    // Default fallback
    return 'Authentication failed.';
}

/**
 * Detect the operation type for successful operations
 * @param string $val_wf_id Validation workflow ID
 * @param string $test_wf_id Test workflow ID
 * @param string $operation_context Operation context from frontend
 * @return string Operation description for success
 */
function detectOperationTypeSuccess($val_wf_id, $test_wf_id, $operation_context = '') {
    $workflow_id = !empty($val_wf_id) ? $val_wf_id : $test_wf_id;
    
    // First try to detect from workflow ID patterns
    if (!empty($workflow_id)) {
        if (strpos($workflow_id, 'RT_SCH_GEN_') === 0) {
            return 'Routine test schedule generation completed successfully.';
        } elseif (strpos($workflow_id, 'SCH_GEN_') === 0) {
            return 'Schedule generation completed successfully.';
        } elseif (strpos($workflow_id, 'ADD_VAL_REQ_') === 0) {
            return 'Adhoc validation request submission completed successfully.';
        } else {
            return 'Operation completed successfully.';
        }
    }
    
    // If no workflow ID, use operation context
    if (!empty($operation_context)) {
        switch ($operation_context) {
            case 'schedule_generation':
                return 'Schedule generation completed successfully.';
            case 'routine_test_schedule_generation':
                return 'Routine test schedule generation completed successfully.';
            case 'adhoc_validation_request_submission':
                return 'Adhoc validation request submission completed successfully.';
            case 'routine_test_addition':
                return 'Routine test addition completed successfully.';
            case 'adhoc_validation_status_change':
                return 'Adhoc validation status change completed successfully.';
            case 'routine_test_status_change':
                return 'Routine test status change completed successfully.';
            default:
                return 'Operation completed successfully.';
        }
    }
    
    // Default fallback
    return 'Authentication completed successfully.';
}

/**
 * Format workflow information for log descriptions
 * @param string $val_wf_id Validation workflow ID
 * @param string $test_wf_id Test workflow ID
 * @param string $operation_context Operation context from frontend
 * @param string $status_from Status changing from (for status changes)
 * @param string $status_to Status changing to (for status changes)
 * @return string Formatted workflow information
 */
function formatWorkflowInfo($val_wf_id, $test_wf_id, $operation_context = '', $status_from = '', $status_to = '') {
    // Special handling for status change operations
    if ($operation_context === 'adhoc_validation_status_change' && !empty($status_from) && !empty($status_to) && !empty($val_wf_id)) {
        return ' - Attempted to update adhoc validation request status.  Val WF ID: ' . htmlspecialchars($val_wf_id);
    }
    
    if ($operation_context === 'routine_test_status_change' && !empty($status_from) && !empty($status_to)) {
        return ' - Attempted to update routine test request status.';
    }
    
    $workflow_parts = array();
    
    // Only include workflow IDs that have values
    if (!empty($val_wf_id)) {
        $workflow_parts[] = 'Val WF ID: ' . htmlspecialchars($val_wf_id);
    }
    if (!empty($test_wf_id)) {
        $workflow_parts[] = 'Test WF ID: ' . htmlspecialchars($test_wf_id);
    }
    
    if (!empty($workflow_parts)) {
        return ' (' . implode(', ', $workflow_parts) . ')';
    } else {
        return ' - ' . detectOperationType($val_wf_id, $test_wf_id, $operation_context);
    }
}

/**
 * Format workflow information for successful operations
 * @param string $val_wf_id Validation workflow ID
 * @param string $test_wf_id Test workflow ID
 * @param string $operation_context Operation context from frontend
 * @param string $status_from Status changing from (for status changes)
 * @param string $status_to Status changing to (for status changes)
 * @return string Formatted workflow information for success
 */
function formatWorkflowInfoSuccess($val_wf_id, $test_wf_id, $operation_context = '', $status_from = '', $status_to = '') {
    // Special handling for status change operations
    if ($operation_context === 'adhoc_validation_status_change' && !empty($val_wf_id)) {
        return ' - Updated adhoc validation request status. Val WF ID: ' . htmlspecialchars($val_wf_id);
    }
    
    if ($operation_context === 'routine_test_status_change' && !empty($status_from) && !empty($status_to)) {
        return ' - Updated routine test request status.';
    }
    
    $workflow_parts = array();
    
    // Only include workflow IDs that have values
    if (!empty($val_wf_id)) {
        $workflow_parts[] = 'Val WF ID: ' . htmlspecialchars($val_wf_id);
    }
    if (!empty($test_wf_id)) {
        $workflow_parts[] = 'Test WF ID: ' . htmlspecialchars($test_wf_id);
    }
    
    if (!empty($workflow_parts)) {
        return ' (' . implode(', ', $workflow_parts) . ')';
    } else {
        return ' - ' . detectOperationTypeSuccess($val_wf_id, $test_wf_id, $operation_context);
    }
}

/**
 * Format workflow information for log descriptions when parentheses are already open
 * @param string $val_wf_id Validation workflow ID
 * @param string $test_wf_id Test workflow ID
 * @param string $operation_context Operation context from frontend
 * @param string $status_from Status changing from (for status changes)
 * @param string $status_to Status changing to (for status changes)
 * @return string Formatted workflow information continuation
 */
function formatWorkflowInfoContinuation($val_wf_id, $test_wf_id, $operation_context = '', $status_from = '', $status_to = '') {
    // Special handling for status change operations
    if ($operation_context === 'adhoc_validation_status_change' && !empty($status_from) && !empty($status_to) && !empty($val_wf_id)) {
        return ') - Attempted to update adhoc validation request. Val WF ID: ' . htmlspecialchars($val_wf_id);
    }
    
    if ($operation_context === 'routine_test_status_change' && !empty($status_from) && !empty($status_to)) {
        return ') - Attempted to update routine test request.';
    }
    
    $workflow_parts = array();
    
    // Only include workflow IDs that have values
    if (!empty($val_wf_id)) {
        $workflow_parts[] = 'Val WF ID: ' . htmlspecialchars($val_wf_id);
    }
    if (!empty($test_wf_id)) {
        $workflow_parts[] = 'Test WF ID: ' . htmlspecialchars($test_wf_id);
    }
    
    if (!empty($workflow_parts)) {
        return ', ' . implode(', ', $workflow_parts) . ')';
    } else {
        return ') - ' . detectOperationType($val_wf_id, $test_wf_id, $operation_context);
    }
}

// Debug - log request details (only in development)
if (ENVIRONMENT === 'dev') {
    error_log("AddRemarks - Request Method: " . $_SERVER['REQUEST_METHOD']);
    error_log("AddRemarks - POST Data: " . print_r($_POST, true));
    error_log("AddRemarks - Session Data: " . print_r($_SESSION, true));
    error_log("AddRemarks - Session CSRF: " . (isset($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : 'not set'));
    error_log("AddRemarks - POST CSRF: " . (isset($_POST['csrf_token']) ? $_POST['csrf_token'] : 'not set'));
}

// Set security headers if enabled
if (ENABLE_SECURITY_HEADERS) {
    // HSTS header
    $hstsHeader = 'max-age=' . HSTS_MAX_AGE;
    if (HSTS_INCLUDE_SUBDOMAINS) {
        $hstsHeader .= '; includeSubDomains';
    }
    if (HSTS_PRELOAD) {
        $hstsHeader .= '; preload';
    }
    header("Strict-Transport-Security: " . $hstsHeader);
    
    // Security headers are already set by config.php via security_middleware.php
    // Removed redundant manual headers to prevent duplication
}

// Check if this is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    session_write_close();
    echo json_encode([
        'status' => 'error',
        'message' => 'Method not allowed'
    ]);
    exit();
}

// Initialize response array
$response = [
    'status' => 'error',
    'message' => 'An unknown error occurred'
];

// Initialize response token variable (will be set after CSRF validation)
$newCsrfToken = null;

// Make sure user is logged in
if (!isset($_SESSION['logged_in_user'])) {
    $response['message'] = 'unauthorized';
    $response['redirect'] = '../login.php';
    $response['csrf_token'] = generateCSRFToken();
    session_write_close();
    echo json_encode($response);
    exit();
}

// Check for required POST parameters
$requiredFields = ['csrf_token', 'user_remark', 'user_password'];
$missingFields = [];
foreach ($requiredFields as $field) {
    if (!isset($_POST[$field]) || empty($_POST[$field])) {
        $missingFields[] = $field;
    }
}

if (!empty($missingFields)) {
    // Log the failure
    $user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
    $unit_id = isset($_SESSION['unit_id']) && is_numeric($_SESSION['unit_id']) ? (int)$_SESSION['unit_id'] : 0;
    $username = isset($_SESSION['user_domain_id']) ? $_SESSION['user_domain_id'] : 'unknown';
    
    // Extract workflow IDs for logging even if other fields are missing
    $val_wf_id = isset($_POST['wf_id']) ? $_POST['wf_id'] : '';
    $test_wf_id = isset($_POST['test_wf_id']) ? $_POST['test_wf_id'] : '';
    
    DB::insert('log', [
        'change_type' => 'add_remarks_failed',
        'table_name' => 'approver_remarks',
        'change_description' => 'Remarks submission failed - Missing required fields: ' . implode(', ', $missingFields) . ' by user ' . htmlspecialchars($username) . formatWorkflowInfo($val_wf_id, $test_wf_id, $operation_context, $status_from, $status_to),
        'change_by' => $user_id,
        'unit_id' => $unit_id
    ]);
    
    $response['status'] = 'error';
    $response['message'] = 'missing_required_fields';
    $response['missing_fields'] = $missingFields;
    $response['csrf_token'] = generateCSRFToken();
    if (ENVIRONMENT === 'dev') {
        error_log("AddRemarks - Missing required fields: " . implode(', ', $missingFields));
    }
    session_write_close();
    echo json_encode($response);
    exit();
}

// Verify CSRF token - but don't regenerate immediately
$tokenValidation = validateCSRFToken($_POST['csrf_token'], false);
if ($tokenValidation === false) {
    // Log the CSRF failure
    $user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
    $unit_id = isset($_SESSION['unit_id']) && is_numeric($_SESSION['unit_id']) ? (int)$_SESSION['unit_id'] : 0;
    $username = isset($_SESSION['user_domain_id']) ? $_SESSION['user_domain_id'] : 'unknown';
    
    // Extract workflow IDs for logging even though CSRF failed
    $val_wf_id = isset($_POST['wf_id']) ? $_POST['wf_id'] : '';
    $test_wf_id = isset($_POST['test_wf_id']) ? $_POST['test_wf_id'] : '';
    
    DB::insert('log', [
        'change_type' => 'add_remarks_failed',
        'table_name' => 'approver_remarks',
        'change_description' => 'Remarks submission failed - CSRF token validation failed by user ' . htmlspecialchars($username) . formatWorkflowInfo($val_wf_id, $test_wf_id, $operation_context, $status_from, $status_to),
        'change_by' => $user_id,
        'unit_id' => $unit_id
    ]);
    
    if (ENVIRONMENT === 'dev') {
        error_log("AddRemarks - CSRF validation failed");
    }
    $response['status'] = 'error';
    $response['message'] = 'security_error';
    $response['type'] = 'csrf_failure';
    $response['csrf_token'] = generateCSRFToken(); // Generate new token
    session_write_close();
    echo json_encode($response);
    exit();
}

// Token validation successful - generate new token for next request
$newCsrfToken = generateCSRFToken();
$response['csrf_token'] = $newCsrfToken;

// Extract workflow IDs, operation context, and status change info early for use in all scenarios (success and failure)
$val_wf_id = isset($_POST['wf_id']) ? $_POST['wf_id'] : '';
$test_wf_id = isset($_POST['test_wf_id']) ? $_POST['test_wf_id'] : '';
$operation_context = isset($_POST['operation_context']) ? $_POST['operation_context'] : '';
$status_from = isset($_POST['status_from']) ? $_POST['status_from'] : '';
$status_to = isset($_POST['status_to']) ? $_POST['status_to'] : '';

// Check for SQL injection in the remarks
if (ENVIRONMENT === 'dev') {
    error_log("AddRemarks - Checking for SQL injection in remark: " . $_POST['user_remark']);
}

if (detectSQLInjection($_POST['user_remark'])) {
    if (ENVIRONMENT === 'dev') {
        error_log("AddRemarks - SQL injection detected in user remark");
    }
    
    $username = isset($_SESSION['user_domain_id']) ? $_SESSION['user_domain_id'] : 
                (isset($_SESSION['emp_id']) ? $_SESSION['emp_id'] : 'unknown');
    
    // Ensure unit_id is a valid integer
    $unit_id = isset($_SESSION['unit_id']) && is_numeric($_SESSION['unit_id']) ? (int)$_SESSION['unit_id'] : 0;
    $user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
    
    // Log to main log table
    DB::insert('log', [
        'change_type' => 'add_remarks_failed',
        'table_name' => 'approver_remarks',
        'change_description' => 'Remarks submission failed - SQL injection attempt detected by user ' . htmlspecialchars($username) . formatWorkflowInfo($val_wf_id, $test_wf_id, $operation_context, $status_from, $status_to),
        'change_by' => $user_id,
        'unit_id' => $unit_id
    ]);
    
    // Log to security events
    logSecurityEvent($username, 'sql_injection_attempt', $user_id, $unit_id);
    
    $response['message'] = 'security_error';
    $response['type'] = 'sql_injection_attempt';
    $response['csrf_token'] = generateCSRFToken();
    session_write_close();
    echo json_encode($response);
    exit();
}

if (ENVIRONMENT === 'dev') {
    error_log("AddRemarks - No SQL injection detected, proceeding...");
}

// Determine user type based on session
$userType = ($_SESSION['logged_in_user'] == "employee") ? "E" : "V";
$username = $_SESSION['user_domain_id'];

if (ENVIRONMENT === 'dev') {
    error_log("AddRemarks - User type: $userType, Username: $username");
}

// Initialize remarks_id variable (will only be set after successful authentication)
$remarks_id = null;

try {
    // Initialize failed attempts if not set
    if (!isset($_SESSION['failed_attempts'])) {
        $_SESSION['failed_attempts'] = [];
    }
    if (!isset($_SESSION['failed_attempts'][$username])) {
        $_SESSION['failed_attempts'][$username] = 0;
    }

    if (ENVIRONMENT === 'dev') {
        error_log("AddRemarks - Getting password from POST and verifying credentials");
    }

    // Get password from POST and immediately clear it from memory
    $password = $_POST['user_password'];
    unset($_POST['user_password']); // Clear from POST array

    // Verify user credentials
    if (ENVIRONMENT === 'dev') {
        error_log("AddRemarks - Calling verifyUserCredentials for user: $username");
    }
    $authResult = verifyUserCredentials($username, $password, $userType);
    
    if (ENVIRONMENT === 'dev') {
        error_log("AddRemarks - verifyUserCredentials result: " . ($authResult ? 'SUCCESS' : 'FAILED'));
    }
    
    // Clear password from memory
    $password = null;
    unset($password);

    if ($authResult) {
        if (ENVIRONMENT === 'dev') {
            error_log("AddRemarks - Authentication successful, proceeding with database operations");
        }
        
        // Authentication successful - reset failed attempts and insert the remarks
        $_SESSION['failed_attempts'][$username] = 0;
        
        // Prepare database values, ensuring correct types
        $user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
        $remark = $_POST['user_remark'];
        
        if (ENVIRONMENT === 'dev') {
            error_log("AddRemarks - Database values - val_wf_id: '$val_wf_id', test_wf_id: '$test_wf_id', user_id: $user_id, remark: '$remark'");
        }
        
        // Insert the remarks
        if (ENVIRONMENT === 'dev') {
            error_log("AddRemarks - Inserting remark into approver_remarks table");
        }
        
        DB::insert('approver_remarks', [
            'val_wf_id' => $val_wf_id,
            'test_wf_id' => $test_wf_id,
            'user_id' => $user_id,
            'remarks' => $remark,
            'created_date_time' => DB::sqleval("NOW()")
        ]);
        
        // Get the actual insert ID
        $remarks_id = DB::insertId();
        
        if (ENVIRONMENT === 'dev') {
            error_log("AddRemarks - Remark inserted successfully with ID: " . $remarks_id);
        }
        
        // Ensure unit_id is properly cast to integer for log entry
        $unit_id = isset($_SESSION['unit_id']) && is_numeric($_SESSION['unit_id']) ? (int)$_SESSION['unit_id'] : 0;
        
        // Log successful action
        if (ENVIRONMENT === 'dev') {
            error_log("AddRemarks - Inserting log entry");
        }
        
        DB::insert('log', [
            'change_type' => 'add_remarks_success',
            'table_name' => 'approver_remarks',
            'change_description' => 'Remarks added by user ' . htmlspecialchars($username) . ' (Remarks ID: ' . $remarks_id . ')' . formatWorkflowInfoSuccess($val_wf_id, $test_wf_id, $operation_context, $status_from, $status_to),
            'change_by' => $user_id,
            'unit_id' => $unit_id
        ]);
        
        if (ENVIRONMENT === 'dev') {
            error_log("AddRemarks - Log entry inserted successfully");
        }
        
        // Return success JSON response
        if (ENVIRONMENT === 'dev') {
            error_log("AddRemarks - Returning success response");
        }
        
        // Generate a temporary authentication token for secure redirect-based operations
        // This token expires in 5 minutes and can only be used once
        $tempAuthToken = bin2hex(random_bytes(32));
        $_SESSION['temp_auth_token'] = $tempAuthToken;
        $_SESSION['temp_auth_expires'] = time() + 300; // 5 minutes
        
        // Store user remark temporarily for send back operations
        $_SESSION['temp_user_remark'] = $remark;
        
        $response['status'] = 'success';
        $response['message'] = 'remarks_added';
        $response['csrf_token'] = $newCsrfToken;
        $response['temp_auth_token'] = $tempAuthToken; // Include for redirect URLs
        session_write_close();
        echo json_encode($response);
        
        if (ENVIRONMENT === 'dev') {
            error_log("AddRemarks - Success response sent, exiting");
        }
        exit();
    } else {
        // Authentication failed - implement account locking
        $_SESSION['failed_attempts'][$username]++;
        
        // Get unit_id for logging
        $unit_id = isset($_SESSION['unit_id']) && is_numeric($_SESSION['unit_id']) ? (int)$_SESSION['unit_id'] : 0;
        
        // Check if max attempts reached
        if ($_SESSION['failed_attempts'][$username] >= MAX_LOGIN_ATTEMPTS) {
            // Lock the account using the same logic as in checklogin.php
            try {
                // Update the database to lock the account
                DB::update('users', ['is_account_locked' => 'Yes'], 'user_domain_id=%s', $username);
                
                $user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
                
                // Log to main log table
                DB::insert('log', [
                    'change_type' => 'add_remarks_failed',
                    'table_name' => 'approver_remarks',
                    'change_description' => 'Remarks submission failed - Account locked due to maximum failed attempts by user ' . htmlspecialchars($username) . formatWorkflowInfo($val_wf_id, $test_wf_id, $operation_context, $status_from, $status_to),
                    'change_by' => $user_id,
                    'unit_id' => $unit_id
                ]);
                
                // Log to security events
                logSecurityEvent($username, 'account_locked', $user_id, $unit_id);
                
                // Clear the session - this is important for security
                $_SESSION = array();
                if (ini_get("session.use_cookies")) {
                    $params = session_get_cookie_params();
                    setcookie(session_name(), '', time() - 42000,
                        $params["path"], $params["domain"],
                        $params["secure"], $params["httponly"]
                    );
                }
                session_destroy();
                
                // For AJAX requests, provide full absolute URL for redirection
                $redirect_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . 
                                "://" . $_SERVER['HTTP_HOST'] . 
                                dirname(dirname($_SERVER['PHP_SELF'])) . "/login.php?msg=acct_lckd";
                
                // Return appropriate response
                $response['status'] = 'error';
                $response['message'] = 'account_locked';
                $response['redirect'] = $redirect_url;
                $response['forceRedirect'] = true; // Add a special flag for forced redirect
                session_write_close();
                echo json_encode($response);
                exit();
            } catch (Exception $e) {
                $errorMessage = handleDatabaseError($e, "Account locking");
                $response['message'] = 'system_error';
                $response['csrf_token'] = generateCSRFToken();
                session_write_close();
                echo json_encode($response);
                exit();
            }
        } else {
            // Not locked yet, just log the failed attempt
            $user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
            
            // Log to main log table
            DB::insert('log', [
                'change_type' => 'add_remarks_failed',
                'table_name' => 'approver_remarks',
                'change_description' => 'Remarks submission failed - Invalid password attempt by user ' . htmlspecialchars($username) . ' (Attempt ' . $_SESSION['failed_attempts'][$username] . '/' . MAX_LOGIN_ATTEMPTS . formatWorkflowInfoContinuation($val_wf_id, $test_wf_id, $operation_context, $status_from, $status_to),
                'change_by' => $user_id,
                'unit_id' => $unit_id
            ]);
            
            // Log to security events
            logSecurityEvent($username, 'invalid_login', $user_id, $unit_id);
            
            // Return appropriate response with attempts left
            $response['status'] = 'error';
            $response['message'] = 'invalid_credentials';
            $response['attempts_left'] = MAX_LOGIN_ATTEMPTS - $_SESSION['failed_attempts'][$username];
            $response['csrf_token'] = generateCSRFToken();
            session_write_close();
            echo json_encode($response);
            exit();
        }
    }
} catch (Exception $e) {
    if (ENVIRONMENT === 'dev') {
        error_log("AddRemarks - Exception caught: " . $e->getMessage());
        error_log("AddRemarks - Exception trace: " . $e->getTraceAsString());
    }
    
    // Log the system error
    $user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
    $unit_id = isset($_SESSION['unit_id']) && is_numeric($_SESSION['unit_id']) ? (int)$_SESSION['unit_id'] : 0;
    $username = isset($_SESSION['user_domain_id']) ? $_SESSION['user_domain_id'] : 'unknown';
    
    try {
        DB::insert('log', [
            'change_type' => 'add_remarks_failed',
            'table_name' => 'approver_remarks',
            'change_description' => 'Remarks submission failed - System error occurred for user ' . htmlspecialchars($username) . ': ' . substr($e->getMessage(), 0, 200) . formatWorkflowInfo($val_wf_id, $test_wf_id, $operation_context, $status_from, $status_to),
            'change_by' => $user_id,
            'unit_id' => $unit_id
        ]);
    } catch (Exception $logException) {
        // If logging fails, just log to error log
        error_log("AddRemarks - Failed to log error to database: " . $logException->getMessage());
    }
    
    $errorMessage = handleDatabaseError($e, "Password verification");
    $response['message'] = 'system_error';
    $response['csrf_token'] = $newCsrfToken ?: generateCSRFToken();
    
    if (ENVIRONMENT === 'dev') {
        error_log("AddRemarks - Returning system error response");
    }
    
    session_write_close();
    echo json_encode($response);
    exit();
}
?>