<?php 
session_start();


// Load configuration first
require_once(__DIR__ . '/../../config/config.php');
// Include XSS protection middleware (auto-initializes)
require_once('../../security/xss_integration_middleware.php');

// Validate session timeout
require_once('../../security/session_timeout_middleware.php');
validateActiveSession();

// Include rate limiting
require_once('../../security/rate_limiting_utils.php');

// Include secure transaction wrapper
require_once('../../security/secure_transaction_wrapper.php');

include_once("../../config/db.class.php");
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

// Input validation helper
class TestDetailsValidator {
    public static function validateTestData($mode) {
        $required_fields = ['test_name', 'test_description'];
        
        if ($mode === 'modify') {
            $required_fields[] = 'test_id';
        }
        
        $validated_data = [];
        
        foreach ($required_fields as $field) {
            $value = safe_get($field, 'string', '');
            
            if (empty($value)) {
                throw new InvalidArgumentException("$field is required");
            }
            
            // Additional XSS detection on critical fields
            if (XSSPrevention::detectXSS($value)) {
                XSSPrevention::logXSSAttempt($value, 'save_test_details');
                throw new InvalidArgumentException("Invalid input detected in $field");
            }
            
            $validated_data[$field] = $value;
        }
        
        // Validate optional fields
        $optional_fields = ['test_performed_by', 'test_purpose', 'test_status'];
        
        foreach ($optional_fields as $field) {
            $value = safe_get($field, 'string', '');
            
            if (!empty($value) && XSSPrevention::detectXSS($value)) {
                XSSPrevention::logXSSAttempt($value, 'save_test_details');
                throw new InvalidArgumentException("Invalid input detected in $field");
            }
            
            $validated_data[$field] = $value;
        }
        
        // Validate test_id for modify mode
        if ($mode === 'modify') {
            $test_id = safe_get('test_id', 'int', 0);
            if ($test_id <= 0) {
                throw new InvalidArgumentException("Invalid test ID");
            }
            $validated_data['test_id'] = $test_id;
        }
        
        return $validated_data;
    }
}

// Get safe input values
$mode = safe_get('mode', 'string', '');

if ($mode === 'add') {
    try {
        // Validate input data
        $validated_data = TestDetailsValidator::validateTestData('add');
        
        // Execute secure transaction
        $result = executeSecureTransaction(function() use ($validated_data) {
            // Insert test record
            DB::insert('tests', [
                'test_name' => $validated_data['test_name'],
                'test_description' => $validated_data['test_description'],
                'test_performed_by' => $validated_data['test_performed_by'],
                'test_purpose' => $validated_data['test_purpose'],
                'test_status' => $validated_data['test_status']
            ]);
            
            $testId = DB::insertId();
            
            if ($testId <= 0) {
                throw new Exception("Failed to insert test record");
            }
            
            // Insert log entry
            DB::insert('log', [
                'change_type' => 'master_add_test',
                'table_name' => 'tests',
                'change_description' => 'Added a new test. Test ID:' . $testId,
                'change_by' => $_SESSION['user_id'],
                'unit_id' => $_SESSION['unit_id']
            ]);
            
            return $testId;
        });
        
        if ($result > 0) {
            echo "success";
        } else {
            echo "failure";
        }
        
    } catch (InvalidArgumentException $e) {
        error_log("Test validation error: " . $e->getMessage());
        echo json_encode(['error' => $e->getMessage()]);
    } catch (Exception $e) {
        error_log("Test add error: " . $e->getMessage());
        echo json_encode(['error' => 'Database error occurred']);
    }
}
else if ($mode === 'modify') {    
    try {
        // Validate input data
        $validated_data = TestDetailsValidator::validateTestData('modify');
        
        // Execute secure transaction
        $result = executeSecureTransaction(function() use ($validated_data) {
            // Update test record
            DB::query(
                "UPDATE tests SET 
                test_name = %s, 
                test_description = %s, 
                test_performed_by = %s, 
                test_purpose = %s, 
                test_status = %s  
                WHERE test_id = %i", 
                $validated_data['test_name'], 
                $validated_data['test_description'], 
                $validated_data['test_performed_by'], 
                $validated_data['test_purpose'], 
                $validated_data['test_status'], 
                $validated_data['test_id']
            );
            
            $affected_rows = DB::affectedRows();
            
            if ($affected_rows === 0) {
                throw new Exception("No test record was updated - test may not exist");
            }
            
            // Insert log entry
            DB::insert('log', [
                'change_type' => 'master_update_test',
                'table_name' => 'tests',
                'change_description' => 'Modified an existing test. Test ID:' . $validated_data['test_id'],
                'change_by' => $_SESSION['user_id'],
                'unit_id' => $_SESSION['unit_id']
            ]);
            
            return $affected_rows;
        });
        
        if ($result > 0) {
            echo "success";
        } else {
            echo "failure";
        }
        
    } catch (InvalidArgumentException $e) {
        error_log("Test validation error: " . $e->getMessage());
        echo json_encode(['error' => $e->getMessage()]);
    } catch (Exception $e) {
        error_log("Test modify error: " . $e->getMessage());
        echo json_encode(['error' => 'Database error occurred']);
    }
} else {
    echo json_encode(['error' => 'Invalid mode specified']);
}

?>