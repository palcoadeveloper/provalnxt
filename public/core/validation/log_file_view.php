<?php

// Load configuration first
require_once('../config/config.php');

// Session is already started by config.php via session_init.php

// Include XSS protection middleware
require_once('../security/xss_integration_middleware.php');

// Validate session timeout
require_once('../security/session_timeout_middleware.php');
validateActiveSession();

// Check for proper authentication
if (!isset($_SESSION['logged_in_user']) || !isset($_SESSION['user_name'])) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit;
}

include_once '../config/db.class.php';
require_once '../security/secure_query_wrapper.php';
date_default_timezone_set("Asia/Kolkata");

// Validate inputs with XSS protection
if (!isset($_POST['upload_id']) || !isset($_POST['file_type']) || !isset($_POST['test_val_wf_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Missing required parameters']);
    exit;
}

// Secure input validation
$uploadId = secure_post('upload_id', 'int', 0);
$fileType = secure_post('file_type', 'string', '');
$filePath = secure_post('file_path', 'string', '');
$testWfId = secure_post('test_val_wf_id', 'string', '');
$viewId = secure_post('view_id', 'string', '');

// Validate required fields
if ($uploadId <= 0 || empty($fileType) || empty($testWfId)) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Invalid parameters']);
    exit;
}

// Get session variables safely
$username = $_SESSION['user_name'];
$userid = $_SESSION['user_id'];
$timestamp = date('Y-m-d H:i:s');
$unit_id = !empty($_SESSION['unit_id']) ? $_SESSION['unit_id'] : 0;

// Format description for log
$fileTypeReadable = str_replace('_', ' ', $fileType);
$fileTypeReadable = ucwords($fileTypeReadable);

// Prepare description text
$description = "User {$username} viewed {$fileTypeReadable} document. Upload ID: {$uploadId}, Test WF ID: {$testWfId}";
if (!empty($viewId)) {
    $description .= ", View ID: {$viewId}";
}
$description .= ".";

// Prepare data for insertion into log table
try {
    // Insert into log table using the existing schema
    DB::insert('log', [
        'change_type' => 'tran_file_view',
        'table_name' => 'tbl_uploads',
        'change_description' => $description,
        'change_by' => $userid,
        'change_datetime' => DB::sqleval("NOW()"),
        'unit_id' => $unit_id
    ]);
    
    // Return success response
    header('Content-Type: application/json');
    echo json_encode(['status' => 'success', 'message' => 'File view logged successfully']);
    
} catch (Exception $e) {
    // Log the error
    error_log("File view logging error: " . $e->getMessage());
    
    // Return error response
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Database error occurred']);
}
?>