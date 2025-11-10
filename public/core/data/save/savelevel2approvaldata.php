<?php
/**
 * ProVal HVAC - Secure Level 2 Approval Data Handler
 * 
 * Handles level 2 unit head approvals with comprehensive security features
 * 
 * Security Level: High
 * Authentication Required: Yes
 * Authorization Required: Unit head level
 * Input Sources: GET parameters
 * 
 * @version 2.0 (Security Enhanced)
 * @author ProVal Security Team
 */

// =======================================================================================
// MANDATORY SECURITY HEADERS - DO NOT MODIFY ORDER
// =======================================================================================

require_once(__DIR__ . '/../../config/config.php');
require_once('../../security/session_timeout_middleware.php');
validateActiveSession();
include_once("../../config/db.class.php");
date_default_timezone_set("Asia/Kolkata");
require_once('../../validation/input_validation_utils.php');
require_once('../../security/secure_transaction_wrapper.php');

// =======================================================================================
// AUTHENTICATION & AUTHORIZATION
// =======================================================================================

if (!isset($_SESSION['user_name'])) {
    error_log("Unauthorized access attempt to level2 approval from IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    header('Location: ../login.php?msg=authentication_required');
    exit;
}

// Level 2 authorization check (unit head level)
// Add specific role/department checks here if needed
if (!isset($_SESSION['user_id'])) {
    header('Location: ../manageprotocols.php?msg=access_denied');
    exit;
}

// =======================================================================================
// INPUT VALIDATION
// =======================================================================================

updateSessionActivity();

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
    'level2_approver_remark' => [
        'required' => false,
        'validator' => 'validateText',
        'params' => [InputValidator::MAX_LENGTH_LONG, false, true]
    ],
    'deviation_remark' => [
        'required' => false,
        'validator' => 'validateText',
        'params' => [InputValidator::MAX_LENGTH_LONG, false, true]
    ]
];

$validation = InputValidator::validatePostData($validationRules, $_GET);

if (!$validation['valid']) {
    if (class_exists('SecurityUtils')) {
        SecurityUtils::logSecurityEvent('level2_approval_validation_failed', 'Invalid input in level2 approval', [
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
        
        $userId = $_SESSION['user_id'];
        
        // Step 1: Update level 2 approval details
        DB::query(
            "UPDATE tbl_val_wf_approval_tracking_details SET 
             level2_unit_head_approval_datetime=%?, level2_unit_head_approval_by=%i, level2_unit_head_approval_remarks=%s  
             WHERE val_wf_id=%s AND val_wf_approval_trcking_id=%d",
            DB::sqleval("NOW()"),
            $userId,
            $cleanData['level2_approver_remark'] ?? '',
            $cleanData['val_wf_id'],
            $cleanData['val_wf_tracking_id']
        );
        
        $updateCount = DB::affectedRows();
        
        if ($updateCount === 0) {
            throw new Exception("Failed to update level2 approval - record not found or access denied");
        }
        
        // Step 2: Update deviation remarks if provided
        if (!empty($cleanData['deviation_remark'])) {
            DB::query(
                "UPDATE validation_reports SET deviation=%s WHERE val_wf_id=%s",
                $cleanData['deviation_remark'],
                $cleanData['val_wf_id']
            );
        }
        
        // Step 3: Advance workflow to stage 4
        DB::query(
            "UPDATE tbl_val_wf_tracking_details SET val_wf_current_stage='4', stage_assigned_datetime=%?
             WHERE val_wf_id=%s",
            DB::sqleval("NOW()"),
            $cleanData['val_wf_id']
        );

        // Insert audit trail for validation-level stage transition to 4
        DB::insert('audit_trail', [
            'val_wf_id' => $cleanData['val_wf_id'],
            'test_wf_id' => '', // Empty for validation-level events
            'user_id' => $_SESSION['user_id'],
            'user_type' => $_SESSION['logged_in_user'],
            'time_stamp' => DB::sqleval("NOW()"),
            'wf_stage' => '4' // Pending Level 3 Approval
        ]);

        // Step 4: Insert audit log
        DB::insert('log', [
            'change_type' => 'tran_level2app_uh',
            'table_name' => 'tbl_val_wf_approval_tracking_details',
            'change_description' => "Level2 Unit Head approved. UserID:{$userId} WfID:{$cleanData['val_wf_id']}",
            'change_by' => $userId,
            'unit_id' => $_SESSION['unit_id'] ?? 0
        ]);
        
        return [
            'val_wf_id' => $cleanData['val_wf_id'],
            'updated_records' => $updateCount,
            'deviation_updated' => !empty($cleanData['deviation_remark'])
        ];
        
    }, 'level2_approval_processing');
    
    // Log successful approval
    if (class_exists('SecurityUtils')) {
        SecurityUtils::logSecurityEvent('level2_approval_success', 'Level2 unit head approval processed successfully', [
            'val_wf_id' => $result['val_wf_id'],
            'deviation_updated' => $result['deviation_updated'],
            'user_id' => $_SESSION['user_id']
        ]);
    }
    
    header('Location: ../../../manageprotocols.php?msg=level2_approval_saved_successfully');
    exit;
    
} catch (SecurityException $e) {
    error_log("Secure level2 approval failed: " . $e->getMessage());
    
    if (class_exists('SecurityUtils')) {
        SecurityUtils::logSecurityEvent('secure_level2_approval_failed', 'Security failure in level2 approval', [
            'error' => $e->getMessage(),
            'val_wf_id' => $cleanData['val_wf_id'] ?? 'unknown',
            'user_id' => $_SESSION['user_id'] ?? null
        ]);
    }
    
    header('Location: ../../../manageprotocols.php?msg=approval_failed_security');
    exit;
    
} catch (Exception $e) {
    error_log("Level2 approval error: " . $e->getMessage());
    
    if (class_exists('SecurityUtils')) {
        SecurityUtils::logSecurityEvent('level2_approval_error', 'Database error in level2 approval', [
            'error' => $e->getMessage(),
            'val_wf_id' => $cleanData['val_wf_id'] ?? 'unknown',
            'user_id' => $_SESSION['user_id'] ?? null
        ]);
    }
    
    header('Location: ../../../manageprotocols.php?msg=approval_failed');
    exit;
}

?>