<?php
/**
 * Debug script for template upload issues
 */
session_start();

// Validate session timeout
require_once('../security/session_timeout_middleware.php');
validateActiveSession();
require_once('../config/config.php');
require_once('../config/db.class.php');

// Set JSON response header
header('Content-Type: application/json');

// Initialize session for testing
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
    $_SESSION['user_name'] = 'Debug User';
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$debug_info = [
    'timestamp' => date('Y-m-d H:i:s'),
    'request_method' => $_SERVER['REQUEST_METHOD'],
    'session_data' => [
        'user_id' => $_SESSION['user_id'] ?? 'not set',
        'user_name' => $_SESSION['user_name'] ?? 'not set',
        'csrf_token_exists' => isset($_SESSION['csrf_token'])
    ],
    'post_data' => $_POST,
    'files_data' => $_FILES,
    'get_data' => $_GET,
    'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'not set',
    'content_length' => $_SERVER['CONTENT_LENGTH'] ?? 'not set'
];

// Check template directory
$template_dir = '../uploads/templates/';
$debug_info['directory_check'] = [
    'exists' => is_dir($template_dir),
    'writable' => is_writable($template_dir),
    'permissions' => substr(sprintf('%o', fileperms($template_dir)), -4) ?? 'unknown'
];

// Check database connection
try {
    $template_count = DB::queryFirstField("SELECT COUNT(*) FROM raw_data_templates");
    $debug_info['database_check'] = [
        'status' => 'success',
        'template_count' => $template_count
    ];
} catch (Exception $e) {
    $debug_info['database_check'] = [
        'status' => 'error',
        'message' => $e->getMessage()
    ];
}

// Check if this is a test upload request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'test_upload') {
    $debug_info['test_upload'] = 'Test upload request received';
    
    // Validate basic requirements
    $debug_info['validation_checks'] = [
        'test_id_provided' => isset($_POST['test_id']),
        'test_id_numeric' => isset($_POST['test_id']) && is_numeric($_POST['test_id']),
        'effective_date_provided' => isset($_POST['effective_date']),
        'file_provided' => isset($_FILES['template_file']),
        'csrf_token_provided' => isset($_POST['csrf_token']),
        'csrf_token_matches' => isset($_POST['csrf_token']) && $_POST['csrf_token'] === $_SESSION['csrf_token']
    ];
    
    if (isset($_FILES['template_file'])) {
        $file = $_FILES['template_file'];
        $debug_info['file_details'] = [
            'name' => $file['name'] ?? 'not set',
            'type' => $file['type'] ?? 'not set',
            'size' => $file['size'] ?? 'not set',
            'error' => $file['error'] ?? 'not set',
            'error_message' => $file['error'] === UPLOAD_ERR_OK ? 'No error' : 'Upload error: ' . $file['error'],
            'tmp_name_exists' => isset($file['tmp_name']) && file_exists($file['tmp_name'])
        ];
    }
}

echo json_encode($debug_info, JSON_PRETTY_PRINT);
?>