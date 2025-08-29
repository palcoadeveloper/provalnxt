<?php
/**
 * ProVal HVAC - Secure Report Data Save Handler
 * 
 * Handles validation report data saving with comprehensive security features
 * 
 * Security Level: High
 * Authentication Required: Yes
 * Input Sources: GET/POST parameters
 * 
 * @version 2.0 (Security Enhanced)
 * @author ProVal Security Team
 */

// IMMEDIATE DEBUG - First thing the script does
error_log("DEBUG: savereportdata.php - Script started");
error_log("DEBUG: Request method: " . $_SERVER['REQUEST_METHOD']);
error_log("DEBUG: Raw POST: " . json_encode($_POST));
error_log("DEBUG: Raw GET: " . json_encode($_GET));

// =======================================================================================
// MANDATORY SECURITY HEADERS - DO NOT MODIFY ORDER
// =======================================================================================

// 1. CONFIGURATION - Always load first
error_log("DEBUG: Loading config.php");
require_once(__DIR__ . '/../../config/config.php');
error_log("DEBUG: config.php loaded");
// Note: config.php automatically includes session_init.php which starts session

// 2. SESSION VALIDATION - Critical for all authenticated pages
error_log("DEBUG: Loading session timeout middleware");
require_once('../../security/session_timeout_middleware.php');
error_log("DEBUG: About to validate session");
validateActiveSession();
error_log("DEBUG: Session validation completed");

// 3. DATABASE CONNECTION - Use class-based approach
error_log("DEBUG: Loading database class");
include_once("../../config/db.class.php");
error_log("DEBUG: Database class loaded");

// 4. TIMEZONE SETTING - Required for audit logs and timestamps
date_default_timezone_set("Asia/Kolkata");
error_log("DEBUG: Timezone set");

// 5. SECURITY UTILITIES
error_log("DEBUG: Loading security utilities");
require_once('../../validation/input_validation_utils.php');
require_once('../../security/secure_transaction_wrapper.php');
error_log("DEBUG: Security utilities loaded");

// =======================================================================================
// AUTHENTICATION & AUTHORIZATION
// =======================================================================================

// Enhanced authentication check
error_log("DEBUG: Checking authentication");
error_log("DEBUG: Session user_name: " . ($_SESSION['user_name'] ?? 'not_set'));
error_log("DEBUG: Session data: " . json_encode($_SESSION));

if (!isset($_SESSION['user_name'])) {
    // Log unauthorized access attempt
    error_log("DEBUG: Authentication failed - redirecting to login");
    error_log("Unauthorized access attempt to: " . $_SERVER['REQUEST_URI'] . " from IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    header('Location: ../login.php?msg=authentication_required');
    exit;
}
error_log("DEBUG: Authentication passed");

// Optional: Role-based access control for report data modification
$allowedRoles = ['admin', 'supervisor', 'analyst', 'user']; // Define roles that can save reports
$userRole = $_SESSION['user_role'] ?? '';

if (!empty($userRole) && !in_array($userRole, $allowedRoles)) {
    if (class_exists('SecurityUtils')) {
        SecurityUtils::logSecurityEvent('unauthorized_report_access', 'Insufficient privileges for report data save', [
            'required_roles' => $allowedRoles,
            'user_role' => $userRole,
            'page' => basename(__FILE__)
        ]);
    }
    header('HTTP/1.1 403 Forbidden');
    die('Access denied: Insufficient privileges to modify report data');
}

// =======================================================================================
// RATE LIMITING
// =======================================================================================

// Prevent report data abuse - limit to reasonable number of saves per minute
if (defined('RATE_LIMITING_ENABLED') && RATE_LIMITING_ENABLED) {
    require_once('../../security/rate_limiting_utils.php');
    
    if (SecurityUtils::checkRateLimit('report_data_save', 20, 60)) { // 20 saves per minute
        header('HTTP/1.1 429 Too Many Requests');
        SecurityUtils::logSecurityEvent('rate_limit_exceeded', 'Report data save rate limit exceeded');
        die('Too many report saves. Please try again later.');
    }
}

// =======================================================================================
// INPUT VALIDATION & PROCESSING
// =======================================================================================

// Update session activity for user interaction
updateSessionActivity();

// Enhanced text cleaning function
function cleanTextInput($text) {
    if (is_null($text)) return '';
    
    // Convert all types of line breaks to a standard format
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    
    // Replace multiple consecutive line breaks with a single one
    $text = preg_replace('/\n{3,}/', "\n\n", $text);
    
    // Trim whitespace from beginning and end
    $text = trim($text);
    
    // Additional security: Remove potential XSS patterns
    $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    
    return $text;
}

// Define comprehensive validation rules for all inputs
$validationRules = [
    'val_wf_id' => [
        'required' => true,
        'validator' => 'validateText',
        'params' => [50, true, false] // Max length 50, required, no HTML allowed
    ],
    'deviations' => [
        'required' => false,
        'validator' => 'validateText',
        'params' => [InputValidator::MAX_LENGTH_LONG, false, true]
    ],
    'summary' => [
        'required' => false,
        'validator' => 'validateText',
        'params' => [InputValidator::MAX_LENGTH_LONG, false, true]
    ],
    'recommendation' => [
        'required' => false,
        'validator' => 'validateText',
        'params' => [InputValidator::MAX_LENGTH_LONG, false, true]
    ],
    'deviation_review' => [
        'required' => false,
        'validator' => 'validateText',
        'params' => [InputValidator::MAX_LENGTH_LONG, false, true]
    ],
    'user_team' => [
        'required' => true,
        'validator' => 'validateInteger',
        'params' => [0, 999999]
    ],
    'engg_team' => [
        'required' => true,
        'validator' => 'validateInteger',
        'params' => [0, 999999]
    ],
    'hse_team' => [
        'required' => true,
        'validator' => 'validateInteger',
        'params' => [0, 999999]
    ],
    'qc_team' => [
        'required' => true,
        'validator' => 'validateInteger',
        'params' => [0, 999999]
    ],
    'qa_team' => [
        'required' => true,
        'validator' => 'validateInteger',
        'params' => [0, 999999]
    ]
];

// Add validation rules for test observations (testid-1 through testid-16)
for ($i = 1; $i <= 16; $i++) {
    $validationRules["testid-{$i}"] = [
        'required' => false,
        'validator' => 'validateText',
        'params' => [InputValidator::MAX_LENGTH_MEDIUM, false, true]
    ];
}

// Validate all input data
error_log("DEBUG: Starting input validation");
error_log("DEBUG: Using POST data for validation");
$validation = InputValidator::validatePostData($validationRules, $_POST);
error_log("DEBUG: Validation result: " . json_encode($validation));

if (!$validation['valid']) {
    // Log validation failure
    if (class_exists('SecurityUtils')) {
        SecurityUtils::logSecurityEvent('report_validation_failed', 'Invalid input detected in report data save', [
            'errors' => $validation['errors'],
            'file' => basename(__FILE__),
            'user_id' => $_SESSION['user_id']
        ]);
    }
    
    // Return error response
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid input data',
        'errors' => $validation['errors']
    ]);
    exit;
}

$cleanData = $validation['data'];

// =======================================================================================
// DEBUG INFORMATION
// =======================================================================================

error_log("DEBUG: Starting savereportdata.php processing");
error_log("DEBUG: Cleaned data received: " . json_encode($cleanData));
error_log("DEBUG: Session user_id: " . ($_SESSION['user_id'] ?? 'not_set'));
error_log("DEBUG: Form method used: " . $_SERVER['REQUEST_METHOD']);
error_log("DEBUG: POST data: " . json_encode($_POST));
error_log("DEBUG: GET data: " . json_encode($_GET));

// =======================================================================================
// SECURE TRANSACTION PROCESSING
// =======================================================================================

try {
    // Execute all database operations within a secure transaction
    $result = executeSecureTransaction(function() use ($cleanData) {
        
        error_log("DEBUG: Inside secure transaction");
        
        // Prepare datetime values
        $currentDateTime = date('Y-m-d H:i:s');
        $iterationStatus = 'Active';
        $completionStatus = 'pending';
        $submittedBy = $_SESSION['user_id'] ?? 0;
        
        error_log("DEBUG: Transaction variables prepared - DateTime: $currentDateTime, SubmittedBy: $submittedBy");
        
        // Step 1: Update validation reports with cleaned data
        error_log("DEBUG: About to execute validation_reports UPDATE query");
        error_log("DEBUG: val_wf_id for update: " . $cleanData['val_wf_id']);
        
        DB::query("UPDATE validation_reports SET 
            deviation=%s, summary=%s, recommendationn=%s, deviation_review=%s,
            test1_observation=%s, test2_observation=%s, test3_observation=%s, test4_observation=%s,
            test5_observation=%s, test6_observation=%s, test7_observation=%s, test8_observation=%s,
            test9_observation=%s, test10_observation=%s, test11_observation=%s, test12_observation=%s,
            test13_observation=%s, test14_observation=%s, test15_observation=%s, test16_observation=%s
            
            WHERE val_wf_id=%s",
            $cleanData['deviations'],
            $cleanData['summary'],
            $cleanData['recommendation'],
            $cleanData['deviation_review'],
            $cleanData['testid-1'] ?? '',
            $cleanData['testid-2'] ?? '',
            $cleanData['testid-3'] ?? '',
            $cleanData['testid-4'] ?? '',
            $cleanData['testid-5'] ?? '',
            $cleanData['testid-6'] ?? '',
            $cleanData['testid-7'] ?? '',
            $cleanData['testid-8'] ?? '',
            $cleanData['testid-9'] ?? '',
            $cleanData['testid-10'] ?? '',
            $cleanData['testid-11'] ?? '',
            $cleanData['testid-12'] ?? '',
            $cleanData['testid-13'] ?? '',
            $cleanData['testid-14'] ?? '',
            $cleanData['testid-15'] ?? '',
            $cleanData['testid-16'] ?? '',
            $cleanData['val_wf_id']
        );
        
        error_log("DEBUG: validation_reports UPDATE query executed");
        
        $reportUpdateCount = DB::affectedRows();
        error_log("DEBUG: Affected rows from UPDATE: " . $reportUpdateCount);
        
        // Check if the record exists (0 affected rows could mean no changes were needed)
        $recordExists = DB::queryFirstField("SELECT COUNT(*) FROM validation_reports WHERE val_wf_id=%s", $cleanData['val_wf_id']);
        
        if ($recordExists == 0) {
            error_log("DEBUG: ERROR - No record found in validation_reports for val_wf_id: " . $cleanData['val_wf_id']);
            throw new Exception("Report not found or access denied for val_wf_id: " . $cleanData['val_wf_id']);
        }
        
        error_log("DEBUG: Record exists and update completed (affected rows: $reportUpdateCount)");
        
        // Step 2: Prepare approval values
        $level1_user_dept_approval_by = ($cleanData['user_team'] == 0) ? 0 : $cleanData['user_team'];
        $level1_eng_approval_by = ($cleanData['engg_team'] == 0) ? 0 : $cleanData['engg_team'];
        $level1_hse_approval_by = ($cleanData['hse_team'] == 0) ? 0 : $cleanData['hse_team'];
        $level1_qc_approval_by = ($cleanData['qc_team'] == 0) ? 0 : $cleanData['qc_team'];
        $level1_qa_approval_by = ($cleanData['qa_team'] == 0) ? 0 : $cleanData['qa_team'];
        
        // Step 3: Call the stored procedure for approval tracking
        error_log("DEBUG: About to call USP_InsertApprovalTracking stored procedure");
        
        DB::query("CALL USP_InsertApprovalTracking(%s, %t, %s, %s, %t, %s, %i, %i, %i, %i, %i)",
            $cleanData['val_wf_id'],
            $currentDateTime,
            $completionStatus,
            $iterationStatus,
            $currentDateTime,
            $submittedBy,
            $level1_eng_approval_by,
            $level1_hse_approval_by,
            $level1_qc_approval_by,
            $level1_qa_approval_by,
            $level1_user_dept_approval_by
        );
        
        // Step 4: Update approval tracking details based on team selections
        $teamUpdates = [
            'engg_team' => 'level1_eng_approval_by',
            'hse_team' => 'level1_hse_approval_by',
            'qc_team' => 'level1_qc_approval_by',
            'qa_team' => 'level1_qa_approval_by',
            'user_team' => 'level1_user_dept_approval_by'
        ];
        
        foreach ($teamUpdates as $teamParam => $dbField) {
            if ($cleanData[$teamParam] == 0) {
                DB::query("UPDATE tbl_val_wf_approval_tracking_details SET {$dbField}=%i WHERE val_wf_id=%s",
                    0, $cleanData['val_wf_id']);
            }
        }
        
        // Step 5: Update workflow stage
        DB::query("UPDATE tbl_val_wf_tracking_details SET 
            val_wf_current_stage=%s, stage_assigned_datetime=%? 
            WHERE val_wf_id=%s",
            '2', 
            DB::sqleval("NOW()"), 
            $cleanData['val_wf_id']
        );
        
        // Step 6: Check if all teams are skipped and update stage accordingly
        if ($cleanData['engg_team'] == 0 && $cleanData['hse_team'] == 0 && 
            $cleanData['qc_team'] == 0 && $cleanData['qa_team'] == 0 && 
            $cleanData['user_team'] == 0) {
            
            // If all teams are skipped, move to stage 3
            DB::query("UPDATE tbl_val_wf_tracking_details SET 
                val_wf_current_stage=%s, stage_assigned_datetime=%? 
                WHERE val_wf_id=%s",
                '3', 
                DB::sqleval("NOW()"), 
                $cleanData['val_wf_id']
            );
        }
        
        // Step 7: Insert audit log entry
        DB::insert('log', [
            'change_type' => 'tran_submitted_approval',
            'table_name' => 'validation_reports',
            'change_description' => 'Validation study submitted for team approval. UserID:' . $submittedBy . ' WfID:' . $cleanData['val_wf_id'],
            'change_by' => $submittedBy,
            'unit_id' => $_SESSION['unit_id'] ?? 0
        ]);
        
        return [
            'val_wf_id' => $cleanData['val_wf_id'],
            'updated_records' => $reportUpdateCount,
            'workflow_stage' => ($cleanData['engg_team'] == 0 && $cleanData['hse_team'] == 0 && 
                                $cleanData['qc_team'] == 0 && $cleanData['qa_team'] == 0 && 
                                $cleanData['user_team'] == 0) ? '3' : '2'
        ];
        
    }, 'validation_report_data_save');
    
    error_log("DEBUG: Secure transaction completed successfully");
    error_log("DEBUG: Transaction result: " . json_encode($result));
    
    // Transaction completed successfully
    if (class_exists('SecurityUtils')) {
        SecurityUtils::logSecurityEvent('report_data_saved', 'Validation report data saved successfully', [
            'val_wf_id' => $result['val_wf_id'],
            'workflow_stage' => $result['workflow_stage'],
            'user_id' => $_SESSION['user_id']
        ]);
    }
    
    // Redirect to management page
    header('Location: ' . BASE_URL . 'manageprotocols.php');
    exit;
    
} catch (SecurityException $e) {
    // Session validation failed or transaction rolled back due to security issues
    error_log("DEBUG: SecurityException caught: " . $e->getMessage());
    error_log("DEBUG: SecurityException trace: " . $e->getTraceAsString());
    error_log("Secure report data save failed: " . $e->getMessage());
    
    if (class_exists('SecurityUtils')) {
        SecurityUtils::logSecurityEvent('secure_report_save_failed', 'Secure transaction failed during report data save', [
            'error' => $e->getMessage(),
            'val_wf_id' => $cleanData['val_wf_id'] ?? 'unknown',
            'user_id' => $_SESSION['user_id'] ?? null
        ]);
    }
    
    header('Location: ' . BASE_URL . 'manageprotocols.php?msg=save_failed_security');
    exit;
    
} catch (Exception $e) {
    // Other database or processing errors
    error_log("DEBUG: General Exception caught: " . $e->getMessage());
    error_log("DEBUG: General Exception trace: " . $e->getTraceAsString());
    error_log("Report data save error: " . $e->getMessage());
    
    if (class_exists('SecurityUtils')) {
        SecurityUtils::logSecurityEvent('report_save_error', 'Database error during report data save', [
            'error' => $e->getMessage(),
            'val_wf_id' => $cleanData['val_wf_id'] ?? 'unknown',
            'user_id' => $_SESSION['user_id'] ?? null
        ]);
    }
    
    header('Location: ' . BASE_URL . 'manageprotocols.php?msg=save_failed');
    exit;
}

// =======================================================================================
// SECURITY HEADERS FOR RESPONSE
// =======================================================================================

// Set security context for this operation
if (function_exists('setSecurityContext')) {
    setSecurityContext('report_data_save', 'Validation report data save operation');
}

// Apply security headers
if (function_exists('getSecurityManager')) {
    $securityManager = getSecurityManager();
    $securityManager->applyHeaders();
}

error_log("DEBUG: Script reached the end");

?>