<?php
/**
 * Approve Termination Request (QA Head)
 * Approves termination and moves from 98B to 98C/98 (terminated)
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

// Check if user is QA Head
if (!isset($_SESSION['is_qa_head']) || $_SESSION['is_qa_head'] !== 'Yes') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Access denied. Only QA Head can approve termination requests.']);
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

// Approver remarks from modal (user_remark is the standard modal parameter)
$approver_remarks = isset($_POST['user_remark']) ? trim($_POST['user_remark']) : '';

// Accept password from modal for verification
if (!isset($_POST['user_password']) || empty(trim($_POST['user_password']))) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Password is required']);
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
unset($_POST['user_password']);

$authResult = verifyUserCredentials($username, $password, $userType);

$password = null;
unset($password);

if (!$authResult) {
    $_SESSION['failed_attempts'][$username]++;

    if ($_SESSION['failed_attempts'][$username] >= MAX_LOGIN_ATTEMPTS) {
        DB::update('users', ['is_account_locked' => 'Yes'], 'user_domain_id=%s', $username);
        logSecurityEvent($username, 'account_locked', $_SESSION['user_id'] ?? 0, $_SESSION['unit_id'] ?? 0);
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
        $attemptsLeft = MAX_LOGIN_ATTEMPTS - $_SESSION['failed_attempts'][$username];
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

$_SESSION['failed_attempts'][$username] = 0;

$val_wf_id = trim($_POST['val_wf_id']);

// Validate format
if (!preg_match('/^[A-Za-z0-9\-_]+$/', $val_wf_id)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid workflow ID format']);
    exit();
}

include_once(__DIR__ . "/../../config/db.class.php");
date_default_timezone_set("Asia/Kolkata");

try {
    // Verify validation exists and is in correct stage (98B)
    $validation = DB::queryFirstRow(
        "SELECT val_wf_current_stage, unit_id, equipment_id FROM tbl_val_wf_tracking_details WHERE val_wf_id = %s",
        $val_wf_id
    );

    if (!$validation) {
        echo json_encode(['status' => 'error', 'message' => 'Validation workflow not found']);
        exit();
    }

    // Verify validation is in stage 98B (Reviewed by Engg Dept Head)
    if ($validation['val_wf_current_stage'] !== '98B') {
        echo json_encode(['status' => 'error', 'message' => 'Invalid workflow stage. Can only approve termination requests in stage 98B.']);
        exit();
    }

    // Verify user has access to this unit
    if ((int)$validation['unit_id'] !== (int)$_SESSION['unit_id']) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Access denied to this unit']);
        exit();
    }

    // Update to stage 98 (Termination Approved by QA Head) with approver remarks
    $update_result = DB::update('tbl_val_wf_tracking_details', [
        'val_wf_current_stage' => '98',
        'stage_assigned_datetime' => date('Y-m-d H:i:s'),
        'actual_wf_end_datetime' => date('Y-m-d H:i:s'),
        'last_modified_date_time' => date('Y-m-d H:i:s'),
        'status' => 'Terminated',
        'tr_approver_remarks' => $approver_remarks // Save QA Head remarks
    ], 'val_wf_id = %s', $val_wf_id);

    if ($update_result) {
        // Update schedule status to Inactive
        DB::update('tbl_val_schedules', [
            'val_wf_status' => 'Inactive'
        ], 'val_wf_id = %s', $val_wf_id);

        // Insert audit trail for validation-level termination (stage 98 - Final)
        DB::insert('audit_trail', [
            'val_wf_id' => $val_wf_id,
            'test_wf_id' => '', // Empty for validation-level events
            'user_id' => $_SESSION['user_id'],
            'user_type' => $_SESSION['logged_in_user'],
            'time_stamp' => DB::sqleval("NOW()"),
            'wf_stage' => '98' // Terminated (final state)
        ]);

        // Log the action
        DB::insert('log', [
            'change_type' => 'termination_approved',
            'table_name' => 'tbl_val_wf_tracking_details',
            'change_description' => 'Termination approved by QA Head for workflow: ' . $val_wf_id,
            'change_by' => $_SESSION['user_id'],
            'unit_id' => $_SESSION['unit_id']
        ]);

        echo json_encode(['status' => 'success', 'message' => 'Termination approved. Validation has been terminated.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to update workflow stage']);
    }

} catch (Exception $e) {
    error_log("Error approving termination: " . $e->getMessage());
    http_response_code(500);
    ob_clean(); // Clear any previous output
    echo json_encode(['status' => 'error', 'message' => 'Database error occurred']);
}
ob_end_flush(); // Send the buffered output
?>