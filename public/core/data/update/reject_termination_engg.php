<?php
/**
 * Reject Termination Request (Engineering Dept Head)
 * Rejects termination and moves from 98A to 98D
 */
ob_start(); // Start output buffering to ensure clean JSON response
require_once(__DIR__ . '/../../config/config.php');

// Validate session timeout
require_once(__DIR__ . '/../../security/session_timeout_middleware.php');
validateActiveSession();

// Include auth utilities for password verification
require_once(__DIR__ . '/../../security/auth_utils.php');

// Set JSON response header
header('Content-Type: application/json; charset=UTF-8');

// Check authentication
if (!isset($_SESSION['logged_in_user']) || !isset($_SESSION['user_name'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit();
}

// Check if user is Engineering Department Head
if (!isset($_SESSION['is_dept_head']) || $_SESSION['is_dept_head'] !== 'Yes' ||
    !isset($_SESSION['department_id']) || (int)$_SESSION['department_id'] !== 1) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Access denied. Only Engineering Department Head can reject termination requests.']);
    exit();
}

// Validate CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
    exit();
}

// Validate required parameters
if (!isset($_POST['val_wf_id']) || empty(trim($_POST['val_wf_id']))) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing validation workflow ID']);
    exit();
}

if (!isset($_POST['rejection_reason']) || empty(trim($_POST['rejection_reason']))) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Rejection reason is required']);
    exit();
}

$val_wf_id = trim($_POST['val_wf_id']);
$rejection_reason = trim($_POST['rejection_reason']);

// Accept optional remarks from modal (user_remark is the standard modal parameter)
$user_remarks = isset($_POST['user_remark']) ? trim($_POST['user_remark']) : '';

// Accept password from modal for verification
if (!isset($_POST['user_password']) || empty(trim($_POST['user_password']))) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Password is required']);
    exit();
}

// Validate format
if (!preg_match('/^[A-Za-z0-9\-_]+$/', $val_wf_id)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid workflow ID format']);
    exit();
}

// Determine user type
$userType = ($_SESSION['logged_in_user'] == "employee") ? "E" : "V";
$username = $_SESSION['user_domain_id'];

// Initialize failed attempts tracking
if (!isset($_SESSION['failed_attempts'])) {
    $_SESSION['failed_attempts'] = [];
}
if (!isset($_SESSION['failed_attempts'][$username])) {
    $_SESSION['failed_attempts'][$username] = 0;
}

// Get password and verify credentials
$password = trim($_POST['user_password']);
unset($_POST['user_password']); // Clear from POST array

// Verify user credentials
$authResult = verifyUserCredentials($username, $password, $userType);

// Clear password from memory
$password = null;
unset($password);

if (!$authResult) {
    // Authentication failed - increment failed attempts
    $_SESSION['failed_attempts'][$username]++;

    // Check if account should be locked
    if ($_SESSION['failed_attempts'][$username] >= MAX_LOGIN_ATTEMPTS) {
        // Lock the account
        DB::update('users', ['is_account_locked' => 'Yes'], 'user_domain_id=%s', $username);

        // Log the lockout
        logSecurityEvent($username, 'account_locked', $_SESSION['user_id'] ?? 0, $_SESSION['unit_id'] ?? 0);

        // Clear session
        session_destroy();

        http_response_code(401);
        echo json_encode([
            'status' => 'error',
            'message' => 'account_locked',
            'redirect' => BASE_URL . 'login.php?msg=acct_lckd',
            'forceRedirect' => true
        ]);
        exit();
    } else {
        // Not locked yet - return error with attempts remaining
        $attemptsLeft = MAX_LOGIN_ATTEMPTS - $_SESSION['failed_attempts'][$username];

        // Log failed attempt
        logSecurityEvent($username, 'invalid_login', $_SESSION['user_id'] ?? 0, $_SESSION['unit_id'] ?? 0);

        http_response_code(401);
        echo json_encode([
            'status' => 'error',
            'message' => 'invalid_credentials',
            'attempts_left' => $attemptsLeft
        ]);
        exit();
    }
}

// Authentication successful - reset failed attempts
$_SESSION['failed_attempts'][$username] = 0;

include_once(__DIR__ . "/../../config/db.class.php");
date_default_timezone_set("Asia/Kolkata");

try {
    // Verify validation exists and is in correct stage (98A)
    $validation = DB::queryFirstRow(
        "SELECT val_wf_current_stage, unit_id, stage_before_termination FROM tbl_val_wf_tracking_details WHERE val_wf_id = %s",
        $val_wf_id
    );

    if (!$validation) {
        echo json_encode(['status' => 'error', 'message' => 'Validation workflow not found']);
        exit();
    }

    // Verify validation is in stage 98A (Termination Requested)
    if ($validation['val_wf_current_stage'] !== '98A') {
        echo json_encode(['status' => 'error', 'message' => 'Invalid workflow stage. Can only reject termination requests in stage 98A.']);
        exit();
    }

    // Verify user has access to this unit
    if ((int)$validation['unit_id'] !== (int)$_SESSION['unit_id']) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Access denied to this unit']);
        exit();
    }

    // Check if this was a non-started validation (stage_before_termination is NULL)
    if (empty($validation['stage_before_termination'])) {
        // Non-started validation - append '-TRR' to archive the rejected request
        $new_val_wf_id = $val_wf_id . '-TRR';

        error_log("Engineering rejection: Non-started validation $val_wf_id being archived as $new_val_wf_id");

        // Combine rejection reason with optional user remarks
        $combined_remarks = 'REJECTED: ' . $rejection_reason;
        if (!empty($user_remarks)) {
            $combined_remarks .= ' | Additional remarks: ' . $user_remarks;
        }

        $update_result = DB::update('tbl_val_wf_tracking_details', [
            'val_wf_id' => $new_val_wf_id,
            'val_wf_current_stage' => '98D', // Mark as rejected by engineering
            'stage_assigned_datetime' => date('Y-m-d H:i:s'),
            'last_modified_date_time' => date('Y-m-d H:i:s'),
            'tr_reviewer_remarks' => $combined_remarks,
            'stage_before_termination' => NULL
        ], 'val_wf_id = %s', $val_wf_id);
    } else {
        // Started validation - restore to original workflow stage
        $restored_stage = $validation['stage_before_termination'];

        error_log("Engineering rejection: Restoring validation $val_wf_id to stage $restored_stage");

        // Combine rejection reason with optional user remarks
        $combined_remarks = 'REJECTED: ' . $rejection_reason;
        if (!empty($user_remarks)) {
            $combined_remarks .= ' | Additional remarks: ' . $user_remarks;
        }

        $update_result = DB::update('tbl_val_wf_tracking_details', [
            'val_wf_current_stage' => $restored_stage, // Restore to original stage
            'stage_assigned_datetime' => date('Y-m-d H:i:s'),
            'last_modified_date_time' => date('Y-m-d H:i:s'),
            'tr_reviewer_remarks' => $combined_remarks,
            'stage_before_termination' => NULL // Clear the saved stage
        ], 'val_wf_id = %s', $val_wf_id);
    }

    if ($update_result) {
        // Insert audit trail for validation-level stage change
        $audit_stage = empty($validation['stage_before_termination']) ? '98D' : $validation['stage_before_termination'];
        DB::insert('audit_trail', [
            'val_wf_id' => $val_wf_id,
            'test_wf_id' => '', // Empty for validation-level events
            'user_id' => $_SESSION['user_id'],
            'user_type' => $_SESSION['logged_in_user'],
            'time_stamp' => DB::sqleval("NOW()"),
            'wf_stage' => $audit_stage // 98D for non-started, or restored stage for started validations
        ]);

        // Log the action
        DB::insert('log', [
            'change_type' => 'termination_rejected_engg',
            'table_name' => 'tbl_val_wf_tracking_details',
            'change_description' => 'Termination rejected by Engineering Dept Head for workflow: ' . $val_wf_id . '. Reason: ' . substr($rejection_reason, 0, 200),
            'change_by' => $_SESSION['user_id'],
            'unit_id' => $_SESSION['unit_id']
        ]);

        echo json_encode(['status' => 'success', 'message' => 'Termination request rejected by Engineering Department']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to update workflow stage']);
    }

} catch (Exception $e) {
    error_log("Error rejecting termination by Engineering: " . $e->getMessage());
    http_response_code(500);
    ob_clean(); // Clear any previous output
    echo json_encode(['status' => 'error', 'message' => 'Database error occurred']);
}
ob_end_flush(); // Send the buffered output
?>