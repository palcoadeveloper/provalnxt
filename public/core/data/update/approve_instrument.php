<?php
session_start();

// Load configuration first
require_once(__DIR__ . '/../../config/config.php');

// Validate session timeout
require_once(__DIR__ . '/../../security/session_timeout_middleware.php');

// Include XSS protection middleware
require_once(__DIR__ . '/../../security/xss_integration_middleware.php');

// Only validate session if we're in a web request
if (!empty($_SERVER['REQUEST_METHOD'])) {
    validateActiveSession();
}

require_once __DIR__ . '/../../config/db.class.php';
date_default_timezone_set("Asia/Kolkata");

// Set content type to JSON
header('Content-Type: application/json');

try {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        throw new Exception('Invalid CSRF token');
    }

    // Get and validate input parameters
    $instrument_id = trim($_POST['instrument_id'] ?? '');
    $action = trim($_POST['action'] ?? '');
    $remarks = trim($_POST['remarks'] ?? '');

    // Basic validation
    if (empty($instrument_id)) {
        throw new Exception('Instrument ID is required');
    }

    if (!in_array($action, ['approve', 'reject'])) {
        throw new Exception('Invalid action specified');
    }

    if ($action === 'reject' && empty($remarks)) {
        throw new Exception('Rejection reason is required');
    }

    // Get current user information
    $current_user_id = $_SESSION['user_id'] ?? 0;
    $is_admin = (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === 'Yes') ||
               (isset($_SESSION['is_super_admin']) && $_SESSION['is_super_admin'] === 'Yes');
    $is_vendor = (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'vendor');
    $current_vendor_id = $_SESSION['vendor_id'] ?? 0;

    if (!$current_user_id) {
        throw new Exception('User not authenticated');
    }

    // Get the instrument details
    $instrument = DB::queryFirstRow(
        "SELECT i.*, v.vendor_name
         FROM instruments i
         LEFT JOIN vendors v ON i.vendor_id = v.vendor_id
         WHERE i.instrument_id = %s",
        $instrument_id
    );

    if (!$instrument) {
        throw new Exception('Instrument not found');
    }

    // Check if instrument is in pending status
    if ($instrument['instrument_status'] !== 'Pending') {
        throw new Exception('Only pending instruments can be approved or rejected');
    }

    // Validate permissions
    $can_approve = false;

    if ($is_admin) {
        // Admin users can approve any pending instrument
        $can_approve = true;
    } elseif ($is_vendor && $current_vendor_id > 0) {
        // Vendor users can approve if:
        // 1. The instrument belongs to their vendor
        // 2. They are not the original submitter
        if ($instrument['vendor_id'] == $current_vendor_id &&
            $instrument['submitted_by'] != $current_user_id) {
            $can_approve = true;
        }
    }

    if (!$can_approve) {
        throw new Exception('You do not have permission to approve/reject this instrument');
    }

    // Store original data for audit trail
    $original_data = json_encode([
        'instrument_status' => $instrument['instrument_status'],
        'submitted_by' => $instrument['submitted_by'],
        'checker_id' => $instrument['checker_id'],
        'checker_action' => $instrument['checker_action'],
        'checker_date' => $instrument['checker_date'],
        'checker_remarks' => $instrument['checker_remarks']
    ]);

    // Prepare update data
    $update_data = [
        'checker_id' => $current_user_id,
        'checker_action' => ucfirst($action) . 'd', // 'Approved' or 'Rejected'
        'checker_date' => date('Y-m-d H:i:s'),
        'checker_remarks' => $remarks
    ];

    // Set new instrument status based on action
    if ($action === 'approve') {
        $update_data['instrument_status'] = 'Active';
    } else {
        $update_data['instrument_status'] = 'Inactive';
    }

    // Begin transaction
    DB::startTransaction();

    // Update the instrument
    $update_result = DB::update('instruments', $update_data, 'instrument_id=%s', $instrument_id);

    if (!$update_result) {
        throw new Exception('Failed to update instrument status');
    }

    // Log the workflow action
    $log_data = [
        'instrument_id' => $instrument_id,
        'action_type' => ucfirst($action) . 'd',
        'performed_by' => $current_user_id,
        'action_date' => date('Y-m-d H:i:s'),
        'old_data' => $original_data,
        'new_data' => json_encode($update_data),
        'remarks' => $remarks,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
    ];

    $log_result = DB::insert('instrument_workflow_log', $log_data);

    if (!$log_result) {
        throw new Exception('Failed to log workflow action');
    }

    // Commit transaction
    DB::commit();

    // Log successful action
    error_log("Instrument $action successful: $instrument_id by user $current_user_id");

    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Instrument ' . $action . 'd successfully',
        'instrument_id' => $instrument_id,
        'new_status' => $update_data['instrument_status']
    ]);

} catch (Exception $e) {
    // Rollback transaction if it was started
    if (DB::$dbh && DB::$dbh->inTransaction()) {
        DB::rollback();
    }

    error_log("Instrument approval error: " . $e->getMessage());
    error_log("Instrument approval stack trace: " . $e->getTraceAsString());

    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

?>