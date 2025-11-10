<?php
/**
 * ProVal HVAC - Secure Level 1 Approval Data Handler
 * 
 * Handles level 1 team approvals with comprehensive security features
 * 
 * Security Level: High
 * Authentication Required: Yes
 * Authorization Required: Department-based
 * Input Sources: GET parameters
 * 
 * @version 2.0 (Security Enhanced)
 * @author ProVal Security Team
 */

// =======================================================================================
// MANDATORY SECURITY HEADERS - DO NOT MODIFY ORDER
// =======================================================================================

// 1. CONFIGURATION - Always load first
require_once(__DIR__ . '/../../config/config.php');

// 2. SESSION VALIDATION - Critical for all authenticated pages
require_once('../../security/session_timeout_middleware.php');
validateActiveSession();

// 3. DATABASE CONNECTION - Use class-based approach
include_once("../../config/db.class.php");

// 4. TIMEZONE SETTING - Required for audit logs and timestamps
date_default_timezone_set("Asia/Kolkata");

// 5. SECURITY UTILITIES
require_once('../../validation/input_validation_utils.php');
require_once('../../security/secure_transaction_wrapper.php');

// =======================================================================================
// AUTHENTICATION & AUTHORIZATION
// =======================================================================================

// Enhanced authentication check
if (!isset($_SESSION['user_name'])) {
    error_log("Unauthorized access attempt to level1 approval from IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    header('Location: ../login.php?msg=authentication_required');
    exit;
}

// Verify department authorization for level 1 approvals
if (!isset($_SESSION['department_id'])) {
    if (class_exists('SecurityUtils')) {
        SecurityUtils::logSecurityEvent('unauthorized_approval_attempt', 'Missing department authorization for level1 approval', [
            'user_id' => $_SESSION['user_id'],
            'file' => basename(__FILE__)
        ]);
    }
    header('Location: ../manageprotocols.php?msg=access_denied');
    exit;
}

// =======================================================================================
// RATE LIMITING
// =======================================================================================

if (defined('RATE_LIMITING_ENABLED') && RATE_LIMITING_ENABLED) {
    require_once('../../security/rate_limiting_utils.php');
    
    if (SecurityUtils::checkRateLimit('level1_approval', 10, 300)) { // 10 approvals per 5 minutes
        header('HTTP/1.1 429 Too Many Requests');
        SecurityUtils::logSecurityEvent('rate_limit_exceeded', 'Level1 approval rate limit exceeded');
        header('Location: ../manageprotocols.php?msg=rate_limit_exceeded');
        exit;
    }
}

// =======================================================================================
// INPUT VALIDATION
// =======================================================================================

updateSessionActivity();

// Define validation rules for level 1 approval inputs
$validationRules = [
    'val_wf_id' => [
        'required' => true,
        'validator' => 'validateWorkflowId',
        'params' => []
    ],
    'val_wf_tracking_id' => [
        'required' => true,
        'validator' => 'validateInteger',
        'params' => [1, 999999999]
    ],
    'level1_approver_remark' => [
        'required' => false,
        'validator' => 'validateText',
        'params' => [InputValidator::MAX_LENGTH_LONG, false, true]
    ]
];

// Validate all input data
$validation = InputValidator::validatePostData($validationRules, $_GET);

if (!$validation['valid']) {
    if (class_exists('SecurityUtils')) {
        SecurityUtils::logSecurityEvent('level1_approval_validation_failed', 'Invalid input in level1 approval', [
            'errors' => $validation['errors'],
            'user_id' => $_SESSION['user_id']
        ]);
    }
    
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
// SECURE APPROVAL PROCESSING
// =======================================================================================

try {
    $result = executeSecureTransaction(function() use ($cleanData) {
        
        $departmentId = $_SESSION['department_id'];
        $userId = $_SESSION['user_id'];
        $approvalResult = [
            'approved' => false,
            'department_name' => '',
            'already_approved' => false,
            'workflow_completed' => false
        ];
        
        // Determine approval type based on department
        $approvalConfig = [
            1 => ['field' => 'level1_eng_approval_by', 'name' => 'Engineering', 'log_type' => 'tran_teamapp_eng'],
            8 => ['field' => 'level1_qa_approval_by', 'name' => 'QA', 'log_type' => 'tran_teamapp_qa'],
            7 => ['field' => 'level1_hse_approval_by', 'name' => 'EHS', 'log_type' => 'tran_teamapp_ehs'],
            0 => ['field' => 'level1_qc_approval_by', 'name' => 'QC', 'log_type' => 'tran_teamapp_qc'],
            6 => ['field' => 'level1_qc_approval_by', 'name' => 'Microbiology', 'log_type' => 'tran_teamapp_qc']
        ];
        
        // Default to user department if not in specific departments
        $config = $approvalConfig[$departmentId] ?? [
            'field' => 'level1_user_dept_approval_by', 
            'name' => 'User Department', 
            'log_type' => 'tran_teamapp_user'
        ];
        
        $approvalResult['department_name'] = $config['name'];
        
        // Check if already approved
        $existingApproval = DB::queryFirstField(
            "SELECT {$config['field']} FROM tbl_val_wf_approval_tracking_details 
             WHERE val_wf_id=%s AND val_wf_approval_trcking_id=%d",
            $cleanData['val_wf_id'], $cleanData['val_wf_tracking_id']
        );
        
        if (!empty($existingApproval)) {
            $approvalResult['already_approved'] = true;
            return $approvalResult;
        }
        
        // Process approval - update the appropriate field based on department
        $approvalField = str_replace('_by', '', $config['field']);
        $datetimeField = $approvalField . '_datetime';
        $remarksField = $approvalField . '_remarks';
        
        DB::query(
            "UPDATE tbl_val_wf_approval_tracking_details SET 
             {$datetimeField}=%?, {$config['field']}=%i, {$remarksField}=%s 
             WHERE val_wf_id=%s AND val_wf_approval_trcking_id=%d",
            DB::sqleval("NOW()"),
            $userId,
            $cleanData['level1_approver_remark'] ?? '',
            $cleanData['val_wf_id'],
            $cleanData['val_wf_tracking_id']
        );
        
        $updateCount = DB::affectedRows();
        
        if ($updateCount === 0) {
            throw new Exception("Failed to update approval - record not found or access denied");
        }
        
        // Insert audit log
        DB::insert('log', [
            'change_type' => $config['log_type'],
            'table_name' => 'tbl_val_wf_approval_tracking_details',
            'change_description' => "Level1 {$config['name']} approved. UserID:{$userId} WfID:{$cleanData['val_wf_id']}",
            'change_by' => $userId,
            'unit_id' => $_SESSION['unit_id'] ?? 0
        ]);
        
        $approvalResult['approved'] = true;
        
        // Check if all level 1 approvals are complete
        $pendingApprovals = DB::query(
            "SELECT val_wf_id FROM tbl_val_wf_approval_tracking_details 
             WHERE val_wf_id=%s AND val_wf_approval_trcking_id=%d AND iteration_status='Active' 
             AND (level1_user_dept_approval_by IS NULL OR level1_eng_approval_by IS NULL OR 
                  level1_hse_approval_by IS NULL OR level1_qc_approval_by IS NULL OR level1_qa_approval_by IS NULL)",
            $cleanData['val_wf_id'], $cleanData['val_wf_tracking_id']
        );
        
        if (empty($pendingApprovals)) {
            // All level 1 approvals complete - advance to next stage
            DB::query(
                "UPDATE tbl_val_wf_tracking_details SET val_wf_current_stage='3', stage_assigned_datetime=%?
                 WHERE val_wf_id=%s",
                DB::sqleval("NOW()"),
                $cleanData['val_wf_id']
            );

            // Insert audit trail for validation-level stage transition to 3
            DB::insert('audit_trail', [
                'val_wf_id' => $cleanData['val_wf_id'],
                'test_wf_id' => '', // Empty for validation-level events
                'user_id' => $_SESSION['user_id'],
                'user_type' => $_SESSION['logged_in_user'],
                'time_stamp' => DB::sqleval("NOW()"),
                'wf_stage' => '3' // Pending Level 2 Approval
            ]);

            $approvalResult['workflow_completed'] = true;
        }
        
        return $approvalResult;
        
    }, 'level1_approval_processing');
    
    // Log successful approval
    if (class_exists('SecurityUtils')) {
        SecurityUtils::logSecurityEvent('level1_approval_success', 'Level1 approval processed successfully', [
            'val_wf_id' => $cleanData['val_wf_id'],
            'department' => $result['department_name'],
            'workflow_advanced' => $result['workflow_completed'],
            'user_id' => $_SESSION['user_id']
        ]);
    }
    
    // Determine redirect message
    if ($result['already_approved']) {
        $message = 'already_approved_' . strtolower(str_replace(' ', '_', $result['department_name']));
    } else if ($result['workflow_completed']) {
        $message = 'approval_complete_workflow_advanced';
    } else {
        $message = 'approval_saved_successfully';
    }
    
    header("Location: ./../../../manageprotocols.php?msg={$message}");
    exit;
    
} catch (SecurityException $e) {
    error_log("Secure level1 approval failed: " . $e->getMessage());
    
    if (class_exists('SecurityUtils')) {
        SecurityUtils::logSecurityEvent('secure_level1_approval_failed', 'Security failure in level1 approval', [
            'error' => $e->getMessage(),
            'val_wf_id' => $cleanData['val_wf_id'] ?? 'unknown',
            'user_id' => $_SESSION['user_id'] ?? null
        ]);
    }
    
    header('Location: ./../../manageprotocols.php?msg=approval_failed_security');
    exit;
    
} catch (Exception $e) {
    error_log("Level1 approval error: " . $e->getMessage());
    
    if (class_exists('SecurityUtils')) {
        SecurityUtils::logSecurityEvent('level1_approval_error', 'Database error in level1 approval', [
            'error' => $e->getMessage(),
            'val_wf_id' => $cleanData['val_wf_id'] ?? 'unknown',
            'user_id' => $_SESSION['user_id'] ?? null
        ]);
    }
    
    header('Location: ../manageprotocols.php?msg=approval_failed');
    exit;
}

?>