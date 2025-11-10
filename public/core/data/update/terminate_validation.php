<?php
require_once(__DIR__ . '/../../config/config.php');

// Validate session timeout
require_once(__DIR__ . '/../../security/session_timeout_middleware.php');
validateActiveSession();

// Include auth utilities for password verification
require_once(__DIR__ . '/../../security/auth_utils.php');

// Check authentication and authorization
if (!isset($_SESSION['logged_in_user']) || !isset($_SESSION['user_name'])) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit();
}

// Check if user belongs to engineering department (department_id = 1)
if (!isset($_SESSION['department_id']) || (int)$_SESSION['department_id'] !== 1) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['status' => 'error', 'message' => 'Access denied. This functionality is restricted to engineering department users only.']);
    exit();
}

// Validate CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
    exit();
}

// Validate required parameters
if (!isset($_POST['val_wf_id']) || empty(trim($_POST['val_wf_id']))) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['status' => 'error', 'message' => 'Missing validation workflow ID']);
    exit();
}

$val_wf_id = trim($_POST['val_wf_id']);

// Accept optional remarks from modal (user_remark is the standard modal parameter)
$user_remarks = isset($_POST['user_remark']) ? trim($_POST['user_remark']) : '';

// Accept termination reason and remarks from SweetAlert (mandatory)
if (!isset($_POST['termination_reason']) || empty(trim($_POST['termination_reason']))) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['status' => 'error', 'message' => 'Termination reason is required']);
    exit();
}

if (!isset($_POST['termination_remarks']) || empty(trim($_POST['termination_remarks']))) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['status' => 'error', 'message' => 'Termination remarks are required']);
    exit();
}

$termination_reason = trim($_POST['termination_reason']);
$termination_remarks = trim($_POST['termination_remarks']);

// Validate reason is not "Select"
if ($termination_reason === 'Select') {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['status' => 'error', 'message' => 'Please select a valid termination reason']);
    exit();
}

// Accept password from modal for verification
if (!isset($_POST['user_password']) || empty(trim($_POST['user_password']))) {
    header('HTTP/1.1 400 Bad Request');
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

        header('HTTP/1.1 401 Unauthorized');
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

        header('HTTP/1.1 401 Unauthorized');
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

// Validate that val_wf_id is alphanumeric with allowed characters
if (!preg_match('/^[A-Za-z0-9\-_]+$/', $val_wf_id)) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['status' => 'error', 'message' => 'Invalid validation workflow ID format']);
    exit();
}

include_once(__DIR__ . "/../../config/db.class.php");
date_default_timezone_set("Asia/Kolkata");

try {
    // First, check if the validation exists and is not already terminated or approved
    $existing_validation = DB::queryFirstRow(
        "SELECT val_wf_current_stage, equipment_id, unit_id FROM tbl_val_wf_tracking_details WHERE val_wf_id = %s",
        $val_wf_id
    );

    if (!$existing_validation) {
        // Check if it's a non-initiated validation (exists in schedules but not in tracking)
        $scheduled_validation = DB::queryFirstRow(
            "SELECT equip_id, unit_id FROM tbl_val_schedules WHERE val_wf_id = %s",
            $val_wf_id
        );

        if (!$scheduled_validation) {
            echo json_encode(['status' => 'error', 'message' => 'Validation workflow not found']);
            exit();
        }

        // For non-initiated validations, insert termination request status
        // Determine termination stage based on user role
        $termination_stage = '98A'; // Default for engineering users
        if (isset($_SESSION['is_dept_head']) && $_SESSION['is_dept_head'] === 'Yes') {
            $termination_stage = '98B'; // Engineering department head
        }

        // Insert termination request status into tracking table
        // For non-initiated validations, there's no previous stage (stage_before_termination stays NULL)
        DB::insert('tbl_val_wf_tracking_details', [
            'val_wf_id' => $val_wf_id,
            'equipment_id' => $scheduled_validation['equip_id'],
            'unit_id' => $scheduled_validation['unit_id'],
            'val_wf_current_stage' => $termination_stage,
            'actual_wf_start_datetime' => date('Y-m-d H:i:s'),
            'wf_initiated_by_user_id' => $_SESSION['user_id'],
            'stage_assigned_datetime' => date('Y-m-d H:i:s'),
            'last_modified_date_time' => date('Y-m-d H:i:s'),
            'stage_before_termination' => NULL, // No previous stage for non-initiated validations
            'tr_termination_reason' => $termination_reason, // Termination reason from dropdown
            'tr_termination_remarks' => $termination_remarks, // Termination remarks from SweetAlert
            'tr_reviewer_remarks' => $user_remarks // Optional remarks from password modal
        ]);

        // Insert audit trail for validation-level termination stage
        DB::insert('audit_trail', [
            'val_wf_id' => $val_wf_id,
            'test_wf_id' => '', // Empty for validation-level events
            'user_id' => $_SESSION['user_id'],
            'user_type' => $_SESSION['logged_in_user'],
            'time_stamp' => DB::sqleval("NOW()"),
            'wf_stage' => $termination_stage // 98A or 98B
        ]);

        // Log the termination action to database
        DB::insert('log', [
            'change_type' => 'termination_requested',
            'table_name' => 'tbl_val_wf_tracking_details',
            'change_description' => 'Termination request submitted for non-initiated validation: ' . $val_wf_id . ' (Stage: ' . $termination_stage . ')',
            'change_by' => $_SESSION['user_id'],
            'unit_id' => $_SESSION['unit_id']
        ]);

        error_log("Validation terminated by user " . $_SESSION['user_id'] . " for val_wf_id: " . $val_wf_id);

        echo json_encode(['status' => 'success', 'message' => 'Termination request submitted successfully']);
        exit();
    }

    // Check if validation is already approved (stage 5) or terminated (stage 99)
    if ($existing_validation['val_wf_current_stage'] == 5) {
        echo json_encode(['status' => 'error', 'message' => 'Cannot terminate an approved validation']);
        exit();
    }

    if ($existing_validation['val_wf_current_stage'] == 99 || $existing_validation['val_wf_current_stage'] == 98) {
        echo json_encode(['status' => 'error', 'message' => 'Validation is already terminated']);
        exit();
    }

    // Check if validation is already in termination process (98A-98E)
    if (in_array($existing_validation['val_wf_current_stage'], ['98A', '98B', '98C', '98D', '98E'])) {
        echo json_encode(['status' => 'error', 'message' => 'Validation is already in termination process']);
        exit();
    }

    // Determine termination stage based on user role
    $termination_stage = '98A'; // Default for engineering users
    if (isset($_SESSION['is_dept_head']) && $_SESSION['is_dept_head'] === 'Yes') {
        $termination_stage = '98B'; // Engineering department head
    }

    // Save the current stage before changing to termination stage
    $current_stage = $existing_validation['val_wf_current_stage'];

    // Debug logging
    error_log("TERMINATION DEBUG - val_wf_id: $val_wf_id, current_stage: $current_stage, termination_stage: $termination_stage");

    // Update the validation to termination request status
    $update_result = DB::update('tbl_val_wf_tracking_details', [
        'val_wf_current_stage' => $termination_stage,
        'stage_assigned_datetime' => date('Y-m-d H:i:s'),
        'last_modified_date_time' => date('Y-m-d H:i:s'),
        'stage_before_termination' => $current_stage, // Save the original stage
        'tr_termination_reason' => $termination_reason, // Termination reason from dropdown
        'tr_termination_remarks' => $termination_remarks, // Termination remarks from SweetAlert
        'tr_reviewer_remarks' => $user_remarks // Optional remarks from password modal
    ], 'val_wf_id = %s', $val_wf_id);

    if ($update_result) {
        // Verify the save worked
        $verification = DB::queryFirstRow("SELECT stage_before_termination FROM tbl_val_wf_tracking_details WHERE val_wf_id = %s", $val_wf_id);
        error_log("TERMINATION VERIFY - val_wf_id: $val_wf_id, saved stage_before_termination: " . ($verification['stage_before_termination'] ?? 'NULL'));

        // Insert audit trail for validation-level termination stage
        DB::insert('audit_trail', [
            'val_wf_id' => $val_wf_id,
            'test_wf_id' => '', // Empty for validation-level events
            'user_id' => $_SESSION['user_id'],
            'user_type' => $_SESSION['logged_in_user'],
            'time_stamp' => DB::sqleval("NOW()"),
            'wf_stage' => $termination_stage // 98A or 98B
        ]);

        // Log the termination action to database
        DB::insert('log', [
            'change_type' => 'termination_requested',
            'table_name' => 'tbl_val_wf_tracking_details',
            'change_description' => 'Termination request submitted for validation: ' . $val_wf_id . ' (was in stage: ' . $current_stage . ', now in: ' . $termination_stage . ')',
            'change_by' => $_SESSION['user_id'],
            'unit_id' => $_SESSION['unit_id']
        ]);

        error_log("Termination request submitted by user " . $_SESSION['user_id'] . " for val_wf_id: " . $val_wf_id . " with stage: " . $termination_stage);

        echo json_encode(['status' => 'success', 'message' => 'Termination request submitted successfully']);
    } else {
        error_log("TERMINATION ERROR - Failed to update validation for val_wf_id: $val_wf_id");
        echo json_encode(['status' => 'error', 'message' => 'Failed to submit termination request']);
    }

} catch (Exception $e) {
    error_log("Error terminating validation: " . $e->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['status' => 'error', 'message' => 'Database error occurred']);
}
?>