<?php
session_start();

// Load configuration first
require_once(__DIR__ . '/../../config/config.php');

// Validate session timeout
require_once(__DIR__ . '/../../security/session_timeout_middleware.php');
validateActiveSession();

require_once __DIR__ . '/../../config/db.class.php';
require_once __DIR__ . '/../../validation/InputValidator.php';

date_default_timezone_set("Asia/Kolkata");

// Show All PHP Errors
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Set JSON response header
header('Content-Type: application/json');

try {
    // Validate request method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        exit;
    }

    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid JSON input']);
        exit;
    }

    // Define validation rules
    $validationRules = [
        'user_id' => ['required' => true, 'validator' => 'validateInteger'],
        'action' => ['required' => true, 'validator' => 'validateEnum', 'options' => ['approve', 'reject']],
        'remarks' => ['required' => false, 'validator' => 'sanitizeString']
    ];

    // Validate input data
    $validation = InputValidator::validateInputData($validationRules, $input);
    if (!$validation['is_valid']) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Validation failed: ' . implode(', ', $validation['errors'])]);
        exit;
    }

    $user_id = $validation['validated_data']['user_id'];
    $action = $validation['validated_data']['action'];
    $remarks = $validation['validated_data']['remarks'] ?? '';

    // Check user permissions (must be department head or admin)
    if (!isset($_SESSION['is_department_head']) || $_SESSION['is_department_head'] !== 'Yes') {
        if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== 'Yes') {
            if (!isset($_SESSION['is_super_admin']) || $_SESSION['is_super_admin'] !== 'Yes') {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Insufficient permissions to perform this action']);
                exit;
            }
        }
    }

    // Check if user exists and is pending
    $user = DB::queryFirstRow("SELECT u.*, submitter.user_name as submitter_name
                               FROM users u
                               LEFT JOIN users submitter ON u.submitted_by = submitter.user_id
                               WHERE u.user_id = %i", $user_id);

    if (!$user) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }

    if ($user['user_status'] !== 'Pending') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'User is not in pending status']);
        exit;
    }

    // For department heads, verify they can approve users from their unit
    if ($_SESSION['is_department_head'] === 'Yes' && $_SESSION['is_admin'] !== 'Yes' && $_SESSION['is_super_admin'] !== 'Yes') {
        if ($user['unit_id'] != $_SESSION['unit_id']) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'You can only approve users from your unit']);
            exit;
        }
    }

    // Cannot approve own submissions
    if ($user['submitted_by'] == $_SESSION['user_id']) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Cannot approve your own submissions']);
        exit;
    }

    // Store original data for audit trail
    $original_data = json_encode([
        'user_status' => $user['user_status'],
        'checker_id' => $user['checker_id'],
        'checker_date' => $user['checker_date']
    ]);

    // Determine new status and perform action
    $new_status = ($action === 'approve') ? 'Active' : 'Inactive';
    $action_type = ($action === 'approve') ? 'Approved' : 'Rejected';

    // Start transaction
    DB::startTransaction();

    try {
        // Update user status
        $update_data = [
            'user_status' => $new_status,
            'checker_id' => $_SESSION['user_id'],
            'checker_date' => date('Y-m-d H:i:s'),
            'original_data' => $original_data
        ];

        DB::update('users', $update_data, 'user_id = %i', $user_id);

        // Insert workflow log entry
        $workflow_data = [
            'user_id' => $user_id,
            'action_type' => $action_type,
            'performed_by' => $_SESSION['user_id'],
            'action_date' => date('Y-m-d H:i:s'),
            'old_data' => $original_data,
            'new_data' => json_encode($update_data),
            'remarks' => $remarks,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ];

        DB::insert('user_workflow_log', $workflow_data);

        // Commit transaction
        DB::commit();

        // Success response
        echo json_encode([
            'success' => true,
            'message' => ucfirst($action) . 'd successfully',
            'user_id' => $user_id,
            'new_status' => $new_status
        ]);

    } catch (Exception $e) {
        DB::rollback();
        throw $e;
    }

} catch (Exception $e) {
    error_log("User approval error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error occurred while processing request']);
}
?>