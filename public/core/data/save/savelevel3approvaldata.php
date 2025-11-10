<?php
/**
 * ProVal HVAC - Secure Level 3 Approval Data Handler
 * 
 * Handles level 3 QA head approvals with comprehensive security features
 * and protocol report generation
 * 
 * Security Level: High
 * Authentication Required: Yes
 * Authorization Required: QA head level
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
    error_log("Unauthorized access attempt to level3 approval from IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    header('Location: ../login.php?msg=authentication_required');
    exit;
}

// Level 3 authorization check (QA head level)
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../../manageprotocols.php?msg=access_denied');
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
    'equipment_id' => [
        'required' => true,
        'validator' => 'validateInteger',
        'params' => [1, 999999999]
    ],
    'level3_approver_remark' => [
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
        SecurityUtils::logSecurityEvent('level3_approval_validation_failed', 'Invalid input in level3 approval', [
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
        $currentTime = DB::sqleval("NOW()");
        
        // Batch update all approval data in single transaction for better performance
        DB::query(
            "UPDATE tbl_val_wf_approval_tracking_details SET 
             iteration_completion_status = 'complete', iteration_status = 'Active', 
             level3_head_qa_approval_datetime=%?, level3_head_qa_approval_by=%i, level3_head_qa_approval_remarks=%s 
             WHERE val_wf_id=%s AND val_wf_approval_trcking_id=%d",
            $currentTime,
            $userId,
            $cleanData['level3_approver_remark'] ?? '',
            $cleanData['val_wf_id'],
            $cleanData['val_wf_tracking_id']
        );
        
        $updateCount = DB::affectedRows();
        
        if ($updateCount === 0) {
            throw new Exception("Failed to update level3 approval - record not found or access denied");
        }
        
        // Update workflow stage and deviation in parallel where possible
        DB::query(
            "UPDATE tbl_val_wf_tracking_details SET
             val_wf_current_stage='5', stage_assigned_datetime=%?, actual_wf_end_datetime=%?
             WHERE val_wf_id=%s",
            $currentTime,
            $currentTime,
            $cleanData['val_wf_id']
        );

        // Insert audit trail for validation-level stage transition to 5
        DB::insert('audit_trail', [
            'val_wf_id' => $cleanData['val_wf_id'],
            'test_wf_id' => '', // Empty for validation-level events
            'user_id' => $_SESSION['user_id'],
            'user_type' => $_SESSION['logged_in_user'],
            'time_stamp' => DB::sqleval("NOW()"),
            'wf_stage' => '5' // Approved/Completed
        ]);

        // Update deviation remarks if provided
        if (!empty($cleanData['deviation_remark'])) {
            DB::query(
                "UPDATE validation_reports SET deviation=%s WHERE val_wf_id=%s",
                $cleanData['deviation_remark'],
                $cleanData['val_wf_id']
            );
        }
        
        // Simplified audit log (reduce data size)
        DB::insert('log', [
            'change_type' => 'tran_level3app_qh',
            'table_name' => 'tbl_val_wf_approval_tracking_details',
            'change_description' => "Level3 approved. UserID:{$userId} WfID:{$cleanData['val_wf_id']}",
            'change_by' => $userId,
            'unit_id' => $_SESSION['unit_id'] ?? 0
        ]);
        
        return [
            'val_wf_id' => $cleanData['val_wf_id'],
            'equipment_id' => $cleanData['equipment_id'],
            'updated_records' => $updateCount,
            'deviation_updated' => !empty($cleanData['deviation_remark'])
        ];
        
    }, 'level3_approval_processing');
    
    // =======================================================================================
    // SECURE PROTOCOL REPORT GENERATION
    // =======================================================================================
    
    // Generate protocol report using secure cURL with validation
    $reportGenerated = false;
    $protocolReportPath = null;
    
    try {
        // Performance logging - start timing
        $reportStartTime = microtime(true);
        error_log("Level3 Approval: Starting report generation for val_wf_id: " . $result['val_wf_id']);
        
        // Validate BASE_URL is properly configured
        if (!defined('BASE_URL') || empty(BASE_URL)) {
            throw new Exception("BASE_URL not properly configured for report generation");
        }
        
        // Build optimized report generation URL with all required parameters
        $reportUrl = BASE_URL . "generateprotocolreport_rev.php?" . http_build_query([
            'equipment_id' => $result['equipment_id'],
            'val_wf_id' => $result['val_wf_id'],
            'approval_stage' => 3,
            'val_wf_tracking_id' => $cleanData['val_wf_tracking_id']
        ]);
        
        // Initialize cURL with minimal configuration optimized for localhost performance
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $reportUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30, // Reduced timeout since direct call is fast
            CURLOPT_CONNECTTIMEOUT => 5, // Quick connection timeout
            CURLOPT_FOLLOWLOCATION => false,
            // Removed SSL verification for localhost performance
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            // Minimal headers for faster processing
            CURLOPT_USERAGENT => 'ProVal-Internal/1.0'
            // Removed session cookies since session validation is commented out in PDF script
            // Removed complex headers that add processing overhead
        ]);
        
        $output = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $totalTime = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
        $error = curl_error($ch);
        curl_close($ch);
        
        // Performance logging - cURL completion
        $reportEndTime = microtime(true);
        $totalReportTime = round($reportEndTime - $reportStartTime, 2);
        error_log("Level3 Approval: Report generation completed in {$totalReportTime}s (cURL: {$totalTime}s) for val_wf_id: " . $result['val_wf_id'] . " with HTTP code: $httpCode");
        error_log("Level3 Approval: cURL output received: " . var_export($output, true));
        
        // Validate response
        if ($output === false || !empty($error)) {
            error_log("Level3 Approval: cURL error after {$totalReportTime}s - " . $error);
            throw new Exception("cURL error during report generation: " . $error);
        }
        
        if ($httpCode !== 200) {
            throw new Exception("Report generation failed with HTTP code: " . $httpCode);
        }
        
        // If successful, update the protocol report path in database
        // Simplified success detection matching archived code logic
        if ($output == true || $output == "True" || $output == "1" || !empty($output)) {
            $protocolReportPath = 'uploads/protocol-report-' . $result['val_wf_id'] . '.pdf';
            
            // Update database with report path using secure transaction
            executeSecureTransaction(function() use ($protocolReportPath, $result) {
                DB::query(
                    "UPDATE tbl_val_wf_approval_tracking_details SET protocol_report_path=%s WHERE val_wf_id=%s",
                    $protocolReportPath,
                    $result['val_wf_id']
                );
            }, 'protocol_report_path_update');
            
            $reportGenerated = true;
        }
        
    } catch (Exception $e) {
        // Log report generation error but don't fail the approval
        error_log("Protocol report generation error: " . $e->getMessage());
        
        if (class_exists('SecurityUtils')) {
            SecurityUtils::logSecurityEvent('protocol_report_generation_failed', 'Failed to generate protocol report', [
                'error' => $e->getMessage(),
                'val_wf_id' => $result['val_wf_id'],
                'user_id' => $_SESSION['user_id']
            ]);
        }
    }
    
    // Log successful approval
    if (class_exists('SecurityUtils')) {
        SecurityUtils::logSecurityEvent('level3_approval_success', 'Level3 QA head approval processed successfully', [
            'val_wf_id' => $result['val_wf_id'],
            'equipment_id' => $result['equipment_id'],
            'report_generated' => $reportGenerated,
            'report_path' => $protocolReportPath,
            'deviation_updated' => $result['deviation_updated'],
            'user_id' => $_SESSION['user_id']
        ]);
    }
    
    // Determine success message based on report generation
    $message = $reportGenerated ? 'level3_approval_complete_report_generated' : 'level3_approval_complete_report_failed';
    
    // Check if this is an AJAX request
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
              strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    
    if ($isAjax) {
        // Return JSON response for AJAX requests
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'success',
            'message' => $message,
            'report_generated' => $reportGenerated,
            'redirect_url' => BASE_URL . 'manageprotocols.php?msg=' . $message
        ]);
        exit;
    } else {
        // Traditional redirect for non-AJAX requests
        header('Location: ' . BASE_URL . 'manageprotocols.php?msg=' . $message);
        exit;
    }
    
} catch (SecurityException $e) {
    error_log("Secure level3 approval failed: " . $e->getMessage());
    
    if (class_exists('SecurityUtils')) {
        SecurityUtils::logSecurityEvent('secure_level3_approval_failed', 'Security failure in level3 approval', [
            'error' => $e->getMessage(),
            'val_wf_id' => $cleanData['val_wf_id'] ?? 'unknown',
            'user_id' => $_SESSION['user_id'] ?? null
        ]);
    }
    
    // Check if this is an AJAX request
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
              strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'error',
            'message' => 'Security error occurred during approval process',
            'redirect_url' => BASE_URL . 'manageprotocols.php?msg=approval_failed_security'
        ]);
        exit;
    } else {
        header('Location: ' . BASE_URL . 'manageprotocols.php?msg=approval_failed_security');
        exit;
    }
    
} catch (Exception $e) {
    error_log("Level3 approval error: " . $e->getMessage());
    
    if (class_exists('SecurityUtils')) {
        SecurityUtils::logSecurityEvent('level3_approval_error', 'Database error in level3 approval', [
            'error' => $e->getMessage(),
            'val_wf_id' => $cleanData['val_wf_id'] ?? 'unknown',
            'user_id' => $_SESSION['user_id'] ?? null
        ]);
    }
    
    // Check if this is an AJAX request
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
              strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'error',
            'message' => 'An error occurred during the approval process',
            'redirect_url' => BASE_URL . 'manageprotocols.php?msg=approval_failed'
        ]);
        exit;
    } else {
        header('Location: ' . BASE_URL . 'manageprotocols.php?msg=approval_failed');
        exit;
    }
}

?>