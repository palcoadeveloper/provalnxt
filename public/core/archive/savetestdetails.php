<?php 
session_start();

// Include XSS protection middleware (auto-initializes)
require_once('../../security/xss_integration_middleware.php');

// Validate session timeout
require_once('../../security/session_timeout_middleware.php');
validateActiveSession();

// Include rate limiting
require_once('../../security/rate_limiting_utils.php');

include_once ("../../config/db.class.php");
date_default_timezone_set("Asia/Kolkata");

// Apply rate limiting for form submissions
if (!RateLimiter::checkRateLimit('form_submission')) {
    http_response_code(429);
    echo json_encode(['error' => 'Rate limit exceeded. Too many form submissions.']);
    exit();
}

// Validate CSRF token for POST requests using simple approach (consistent with rest of application)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid CSRF token']);
        exit();
    }
}

// Get safe input values
$mode = safe_get('mode', 'string', '');

if($mode === 'add')
{
    // Get and validate input parameters safely
    $test_name = safe_get('test_name', 'string', '');
    $test_description = safe_get('test_description', 'string', '');
    $test_performed_by = safe_get('test_performed_by', 'string', '');
    $test_purpose = safe_get('test_purpose', 'string', '');
    $test_status = safe_get('test_status', 'string', '');
    
    // Basic validation
    if (empty($test_name) || empty($test_description)) {
        echo json_encode(['error' => 'Test name and description are required']);
        exit();
    }
    
    // Additional XSS detection on critical fields
    if (XSSPrevention::detectXSS($test_name) || XSSPrevention::detectXSS($test_description)) {
        XSSPrevention::logXSSAttempt($test_name . ' | ' . $test_description, 'save_test_details');
        echo json_encode(['error' => 'Invalid input detected']);
        exit();
    }
    
    try {
        DB::insert('tests', [
            'test_name' => $test_name,
            'test_description' => $test_description,
            'test_performed_by' => $test_performed_by,
            'test_purpose' => $test_purpose,
            'test_status' => $test_status
        ]);

        $testId = DB::insertId();

        DB::insert('log', [
            'change_type' => 'master_add_test',
            'table_name' => 'tests',
            'change_description' => 'Added a new test. Test ID:' . $testId,
            'change_by' => $_SESSION['user_id'],
            'unit_id' => $_SESSION['unit_id']
        ]);

        if(DB::affectedRows() > 0) {
            echo "success";
        } else {
            echo "failure";
        }
    } catch (Exception $e) {
        error_log("Error saving test details: " . $e->getMessage());
        echo json_encode(['error' => 'Database error occurred']);
    }
}
else if($mode === 'modify')
{    
    // Get and validate input parameters safely
    $test_id = safe_get('test_id', 'int', 0);
    $test_name = safe_get('test_name', 'string', '');
    $test_description = safe_get('test_description', 'string', '');
    $test_performed_by = safe_get('test_performed_by', 'string', '');
    $test_purpose = safe_get('test_purpose', 'string', '');
    $test_status = safe_get('test_status', 'string', '');
    
    // Validation
    if ($test_id <= 0 || empty($test_name) || empty($test_description)) {
        echo json_encode(['error' => 'Invalid test ID or missing required fields']);
        exit();
    }
    
    // Additional XSS detection
    if (XSSPrevention::detectXSS($test_name) || XSSPrevention::detectXSS($test_description)) {
        XSSPrevention::logXSSAttempt($test_name . ' | ' . $test_description, 'modify_test_details');
        echo json_encode(['error' => 'Invalid input detected']);
        exit();
    }
    
    try {
        DB::query("UPDATE tests SET test_name=%s, test_description=%s, test_performed_by=%s, test_purpose=%s, test_status=%s  
        WHERE test_id=%i", $test_name, $test_description, $test_performed_by, $test_purpose, $test_status, $test_id);
        
        DB::insert('log', [
            'change_type' => 'master_update_test',
            'table_name' => 'tests',
            'change_description' => 'Modified an existing test. Test ID:' . $test_id,
            'change_by' => $_SESSION['user_id'],
            'unit_id' => $_SESSION['unit_id']
        ]);
        
        if(DB::affectedRows() > 0) {
            echo "success";
        } else {
            echo "failure";
        }
    } catch (Exception $e) {
        error_log("Error modifying test details: " . $e->getMessage());
        echo json_encode(['error' => 'Database error occurred']);
    }
} else {
    echo json_encode(['error' => 'Invalid mode specified']);
}

?>