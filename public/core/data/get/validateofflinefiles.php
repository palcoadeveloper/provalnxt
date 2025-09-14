<?php
require_once('../../config/config.php');

// Validate session timeout
require_once('../../security/session_timeout_middleware.php');
validateActiveSession();

// Use centralized session validation
require_once('../../security/session_validation.php');
validateUserSession();

require_once("../../config/db.class.php");

// Set content type to JSON
header('Content-Type: application/json');

// Additional security validation - validate user type
$userType = $_SESSION['logged_in_user'] ?? '';
if (!in_array($userType, ['employee', 'vendor'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

try {
    // Get input parameters
    $test_val_wf_id = $_GET['test_val_wf_id'] ?? '';
    
    // Validate required parameters
    if (empty($test_val_wf_id)) {
        throw new InvalidArgumentException("Missing required parameter: test_val_wf_id");
    }
    
    // First check if this is actually an offline mode test
    $test_mode_data = DB::queryFirstRow("
        SELECT ts.data_entry_mode, ts.test_wf_current_stage, t.paper_on_glass_enabled
        FROM tbl_test_schedules_tracking ts
        INNER JOIN tests t ON t.test_id = ts.test_id
        WHERE ts.test_wf_id = %s
    ", $test_val_wf_id);
    
    if (!$test_mode_data) {
        throw new Exception("Test workflow not found: " . $test_val_wf_id);
    }
    
    // If not offline mode, validation passes
    if ($test_mode_data['data_entry_mode'] !== 'offline' || 
        ($test_mode_data['paper_on_glass_enabled'] ?? 'No') !== 'Yes') {
        echo json_encode([
            'status' => 'success',
            'isValid' => true,
            'message' => 'Not offline mode - validation skipped'
        ]);
        exit();
    }
    
    // Check for required documents in offline mode
    $required_uploads = DB::queryFirstRow("
        SELECT upload_path_raw_data, upload_path_test_certificate, upload_path_master_certificate
        FROM tbl_uploads 
        WHERE test_wf_id = %s 
        AND upload_action IS NULL
        AND upload_status = 'Active'
        ORDER BY uploaded_datetime DESC
        LIMIT 1
    ", $test_val_wf_id);
    
    $missing_files = [];
    
    if (!$required_uploads || empty($required_uploads['upload_path_raw_data'])) {
        $missing_files[] = 'Raw Data';
    }
    
    if (!$required_uploads || empty($required_uploads['upload_path_test_certificate'])) {
        $missing_files[] = 'Test Certificate';
    }
    
    if (!$required_uploads || empty($required_uploads['upload_path_master_certificate'])) {
        $missing_files[] = 'Master Certificate';
    }
    
    if (!empty($missing_files)) {
        $message = "For offline mode tests, the following files must be uploaded before submission: " . implode(', ', $missing_files);
        
        echo json_encode([
            'status' => 'error',
            'isValid' => false,
            'message' => $message,
            'missing_files' => $missing_files
        ]);
    } else {
        echo json_encode([
            'status' => 'success',
            'isValid' => true,
            'message' => 'All required files are uploaded'
        ]);
    }
    
} catch (InvalidArgumentException $e) {
    error_log("Validation error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'isValid' => false,
        'message' => $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("Database error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'isValid' => false,
        'message' => 'Failed to validate files. Please try again.'
    ]);
}
?>