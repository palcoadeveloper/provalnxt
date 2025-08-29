<?php
session_start();


// Validate session timeout
require_once('../security/session_timeout_middleware.php');
validateActiveSession();
if (!isset($_SESSION['user_name'])) {
    // Return error if not logged in
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit;
}

include_once '../config/db.class.php';
date_default_timezone_set("Asia/Kolkata");

// Verify CSRF token
if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
    exit;
}

// Validate inputs
if (!isset($_POST['upload_id']) || !isset($_POST['file_type']) || !isset($_POST['val_wf_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Missing required parameters']);
    exit;
}

// Get variables from POST
$uploadId = $_POST['upload_id'];
$fileType = $_POST['file_type'];
$filePath = isset($_POST['file_path']) ? $_POST['file_path'] : '';
$valWfId = $_POST['val_wf_id'];
$username = $_SESSION['user_name'];
$userid = $_SESSION['user_id'];
$timestamp = date('Y-m-d H:i:s');
$unit_id = !empty($_SESSION['unit_id']) ? $_SESSION['unit_id'] : 0;

// Format description for log
$fileTypeReadable = str_replace('_', ' ', $fileType);
$fileTypeReadable = ucwords($fileTypeReadable);

// Determine the appropriate approval level description based on HTTP_REFERER
$referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
$approval_description = '';

if (strpos($referer, 'pendingforlevel1submission.php') !== false) {
    // This is from submission page
    $approval_description = 'Team Approval Submission Pending';
} elseif (strpos($referer, 'pendingforlevel1approval.php') !== false) {
    // This is from level 1 approval page
    $department_name = '';
    if (!empty($_SESSION['department_id'])) {
        $department_name = DB::queryFirstField("SELECT department_name FROM departments WHERE department_id = %i", $_SESSION['department_id']);
    }
    if (empty($department_name)) {
        $department_name = 'Unknown Department';
    }
    $approval_description = 'Team Approval ' . $department_name;
} elseif (strpos($referer, 'pendingforlevel2approval.php') !== false) {
    // This is from level 2 approval page
    $approval_description = 'Unit Head Approval';
} elseif (strpos($referer, 'pendingforlevel3approval.php') !== false) {
    // This is from level 3 approval page
    $approval_description = 'QA Head Approval';
} else {
    // Fallback for other validation approval pages
    $approval_description = 'Approval Process';
}

// Prepare description text for validation workflow
$description = "User {$username} viewed {$fileTypeReadable} document for VAL WF ID: {$valWfId} - {$approval_description}. Upload ID: {$uploadId}.";

// Prepare data for insertion into log table
try {
    // Insert into log table using the existing schema
    DB::insert('log', [
        'change_type' => 'tran_file_view_validation',
        'table_name' => 'tbl_uploads',
        'change_description' => $description,
        'change_by' => $userid,
        'change_datetime' => $timestamp,
        'unit_id' => $unit_id
    ]);
    
    // Return success response
    header('Content-Type: application/json');
    echo json_encode(['status' => 'success', 'message' => 'File view logged successfully']);
} catch (Exception $e) {
    // Return error response
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
?> 