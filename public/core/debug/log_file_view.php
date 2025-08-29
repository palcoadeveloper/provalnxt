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

// Validate inputs
if (!isset($_POST['upload_id']) || !isset($_POST['file_type']) || !isset($_POST['test_val_wf_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Missing required parameters']);
    exit;
}

// Get variables from POST
$uploadId = $_POST['upload_id'];
$fileType = $_POST['file_type'];
$filePath = isset($_POST['file_path']) ? $_POST['file_path'] : '';
$testWfId = $_POST['test_val_wf_id'];
$username = $_SESSION['user_name'];
$userid = $_SESSION['user_id'];
$timestamp = date('Y-m-d H:i:s');
$unit_id=$_SESSION['unit_id'];
// Set the default value for unit_id
$unit_id_value = 0;

// Override with $unit_id if it exists and is not empty
if (isset($unit_id) && !empty($unit_id)) {
    $unit_id_value = $unit_id;
}

// Format description for log
$fileTypeReadable = str_replace('_', ' ', $fileType);
$fileTypeReadable = ucwords($fileTypeReadable);

// Prepare description text
$description = "User {$username} viewed {$fileTypeReadable} document. Upload ID: {$uploadId}, Test WF ID: {$testWfId}.";

// Prepare data for insertion into log table
try {
    // Insert into log table using the existing schema
    DB::insert('log', [
        'change_type' => 'tran_file_view',
        'table_name' => 'tbl_uploads',
        'change_description' => $description,
        'change_by' => $userid,
        'change_datetime' => $timestamp,
        'unit_id' => $unit_id_value // Using upload_id as the unit_id for reference
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