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

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

try {
    // Validate CSRF token
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!$csrf_token || $csrf_token !== $_SESSION['csrf_token']) {
        throw new InvalidArgumentException("Invalid security token");
    }
    
    // Get input parameters
    $test_val_wf_id = $_POST['test_val_wf_id'] ?? '';
    $instrument_id = $_POST['instrument_id'] ?? '';
    
    // Validate required parameters
    if (empty($test_val_wf_id) || empty($instrument_id)) {
        throw new InvalidArgumentException("Missing required parameters: test_val_wf_id or instrument_id");
    }
    
    // Get user information from session
    $user_id = intval($_SESSION['user_id'] ?? 1);
    $user_unit_id = getUserUnitId();
    
    
    // Ensure unit_id is a valid integer for database
    if ($user_unit_id === '' || $user_unit_id === null) {
        $user_unit_id = 0;
    }
    $user_unit_id = intval($user_unit_id);
    
    // Verify instrument exists and check calibration status
    $instrument_check = DB::queryFirstRow(
        "SELECT instrument_id, instrument_type, instrument_status, calibration_due_on,
                DATE(calibration_due_on) as calibration_due_date,
                CASE 
                    WHEN calibration_due_on IS NULL THEN 'Unknown'
                    WHEN DATE(calibration_due_on) >= CURDATE() THEN 'Valid'
                    ELSE 'Expired'
                END as calibration_status
         FROM instruments 
         WHERE instrument_id = %s 
         AND instrument_status = 'Active'",
        $instrument_id
    );
    
    if (!$instrument_check) {
        throw new InvalidArgumentException("Invalid instrument or instrument not accessible");
    }
    
    // Check if calibration has expired
    if ($instrument_check['calibration_status'] === 'Expired') {
        throw new InvalidArgumentException("Cannot add instrument: Calibration expired on " . date('d.m.Y', strtotime($instrument_check['calibration_due_on'])));
    }
    
    // Check if calibration status is unknown (no calibration date)
    if ($instrument_check['calibration_status'] === 'Unknown') {
        throw new InvalidArgumentException("Cannot add instrument: Calibration due date not set");
    }
    
    // Check if instrument is already added to this test
    $existing_mapping = DB::queryFirstRow(
        "SELECT mapping_id FROM test_instruments 
         WHERE test_val_wf_id = %s 
         AND instrument_id = %s 
         AND is_active = 1",
        $test_val_wf_id,
        $instrument_id
    );
    
    if ($existing_mapping) {
        throw new InvalidArgumentException("Instrument is already added to this test");
    }
    
    // Insert instrument mapping
    $mapping_id = DB::insert('test_instruments', [
        'test_val_wf_id' => $test_val_wf_id,
        'instrument_id' => $instrument_id,
        'added_by' => $user_id,
        'unit_id' => $user_unit_id,
        'is_active' => 1
    ]);
    
    if (!$mapping_id) {
        throw new Exception("Failed to add instrument to test");
    }
    
    // Insert log entry
    DB::insert('log', [
        'change_type' => 'test_instrument_add',
        'table_name' => 'test_instruments',
        'change_description' => sprintf(
            'Added instrument %s (%s) to test workflow %s',
            $instrument_check['instrument_type'] ?? 'Unknown',
            $instrument_check['instrument_id'],
            $test_val_wf_id
        ),
        'change_by' => $user_id,
        'unit_id' => $user_unit_id
    ]);
    
    // Generate new CSRF token
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Instrument added successfully',
        'mapping_id' => $mapping_id,
        'csrf_token' => $_SESSION['csrf_token']
    ]);
    
} catch (InvalidArgumentException $e) {
    error_log("Validation error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("Database error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to add instrument. Please try again. Error: ' . $e->getMessage()
    ]);
}
?>