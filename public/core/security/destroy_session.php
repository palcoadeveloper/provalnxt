<?php
require_once('../config/config.php');

// Session is already started by config.php via session_init.php

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

// Validate the request is for session destruction
$action = isset($_POST['action']) ? $_POST['action'] : '';
$reason = isset($_POST['reason']) ? $_POST['reason'] : 'timeout';

if ($action !== 'destroy_session' && $action !== 'session_timeout') {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
    exit;
}

// Store session variables before destruction for logging
$account_name = !empty($_SESSION['account_name']) ? $_SESSION['account_name'] : 'Unknown';
$user_id = !empty($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
$unit_id = !empty($_SESSION['unit_id']) ? $_SESSION['unit_id'] : 0;
$session_id = session_id();

// Mark session as destroyed (prevents back button access while keeping session for logging)
$_SESSION['session_destroyed'] = true;
$_SESSION['destruction_time'] = time();
$_SESSION['destruction_reason'] = $reason;

// Clear all other session variables but keep destruction markers
$user_vars = ['employee_id', 'vendor_id', 'logged_in_user', 'account_name', 'user_id', 'unit_id'];
$keep_vars = ['session_destroyed', 'destruction_time', 'destruction_reason'];

foreach ($_SESSION as $key => $value) {
    if (!in_array($key, $keep_vars)) {
        unset($_SESSION[$key]);
    }
}

// Log the session destruction
include_once("../config/db.class.php");

try {
    $change_description = ($action === 'session_timeout') 
        ? "User $account_name session automatically timed out ($reason)"
        : "User $account_name session destroyed ($reason)";
        
    DB::insert('log', [
        'change_type' => ($action === 'session_timeout') ? 'tran_session_timeout' : 'tran_session_destroy',
        'table_name' => '',
        'change_description' => $change_description,
        'change_by' => 0,
        'unit_id' => $unit_id
    ]);
} catch (Exception $e) {
    // Log error but don't fail the session destruction
    error_log("Session destruction logging failed: " . $e->getMessage());
}

// Return success response
header('Content-Type: application/json');
echo json_encode([
    'status' => 'success', 
    'message' => 'Session destroyed successfully',
    'session_id' => $session_id
]);
exit;
?>