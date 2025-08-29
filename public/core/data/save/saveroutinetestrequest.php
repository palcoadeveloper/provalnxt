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

require_once '../../config/db.class.php';
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
class RoutineTestInputValidator {
    public static function validateRoutineTestData() {
        $required_fields = ['unitid', 'equipment_id', 'testid', 'freq', 'startdate'];
        
        $validated_data = [];
        
        foreach ($required_fields as $field) {
            $value = safe_get($field, 'string', '');
            
            if (empty($value)) {
                throw new InvalidArgumentException("Missing required field: $field");
            }
            
            $validated_data[$field] = $value;
        }
        
        // Validate numeric fields
        if (!is_numeric($validated_data['unitid'])) {
            throw new InvalidArgumentException("Invalid unit ID");
        }
        
        if (!is_numeric($validated_data['equipment_id'])) {
            throw new InvalidArgumentException("Invalid equipment ID");
        }
        
        if (!is_numeric($validated_data['testid'])) {
            throw new InvalidArgumentException("Invalid test ID");
        }
        
        // Validate frequency values
        $valid_frequencies = ['Q', 'H', 'Y', '2Y', 'ADHOC'];
        if (!in_array($validated_data['freq'], $valid_frequencies)) {
            throw new InvalidArgumentException("Invalid frequency value");
        }
        
        // Validate date format
        $date = DateTime::createFromFormat('Y-m-d', $validated_data['startdate']);
        if (!$date || $date->format('Y-m-d') !== $validated_data['startdate']) {
            throw new InvalidArgumentException("Invalid start date format");
        }
        
        return $validated_data;
    }
    
    public static function calculateTestReferenceDate($frequency, $start_date) {
        $test_ref_date = '';
        
        switch ($frequency) {
            case 'Q':
                $prev_date = $start_date . ' -3 months';
                $test_ref_date = date("Y-m-d", strtotime($prev_date));
                break;
            case 'H':
                $prev_date = $start_date . ' -6 months -1 day';
                $test_ref_date = date("Y-m-d", strtotime($prev_date));
                break;
            case 'Y':
                $prev_date = $start_date . ' -12 months';
                $test_ref_date = date("Y-m-d", strtotime($prev_date));
                break;
            case '2Y':
                $prev_date = $start_date . ' -24 months';
                $test_ref_date = date("Y-m-d", strtotime($prev_date));
                break;
            case 'ADHOC':
                // For ad-hoc tests, use the selected start date as reference
                $test_ref_date = $start_date;
                break;
            default:
                throw new InvalidArgumentException("Unsupported frequency type");
        }
        
        return $test_ref_date;
    }
}

try {
    // Validate input data
    $validated_data = RoutineTestInputValidator::validateRoutineTestData();
    
    // Check if vendor mapping exists
    $vendor_id = DB::queryFirstField(
        "SELECT vendor_id FROM equipment_test_vendor_mapping 
        WHERE equipment_id = %i AND test_id = %i AND mapping_status = 'Active'",
        intval($validated_data['equipment_id']),
        intval($validated_data['testid'])
    );
    
    if (!$vendor_id) {
        throw new Exception("No active vendor mapping found for this equipment-test combination");
    }
    
    // Calculate test reference date
    $test_ref_date = RoutineTestInputValidator::calculateTestReferenceDate(
        $validated_data['freq'], 
        $validated_data['startdate']
    );
    
    // Execute secure transaction
    $result = executeSecureTransaction(function() use ($validated_data, $test_ref_date) {
        // Insert routine test request
        DB::insert('tbl_routine_tests_requests', [
            'unit_id' => intval($validated_data['unitid']),
            'equipment_id' => intval($validated_data['equipment_id']),
            'test_id' => intval($validated_data['testid']),
            'test_frequency' => $validated_data['freq'],
            'test_planned_start_date' => $test_ref_date,
            'routine_test_status' => 1,
            'routine_test_requested_by' => $_SESSION['user_id'],
            'adhoc_frequency' => ($validated_data['freq'] == 'ADHOC') ? 'adhoc' : 'scheduled'
        ]);
        
        $routine_test_req_id = DB::insertId();
        
        if ($routine_test_req_id <= 0) {
            throw new Exception("Failed to insert routine test request");
        }
        
        // For ADHOC requests, create immediate workflow entry
        if ($validated_data['freq'] == 'ADHOC') {
            // Generate unique workflow ID
            $routine_test_wf_id = 'R-' . $validated_data['unitid'] . '-' . 
                                 $validated_data['equipment_id'] . '-' . 
                                 $validated_data['testid'] . '-' . time();
            
            // Create immediate workflow entry
            DB::insert('tbl_routine_test_schedules', [
                'unit_id' => intval($validated_data['unitid']),
                'equip_id' => intval($validated_data['equipment_id']),
                'test_id' => intval($validated_data['testid']),
                'routine_test_wf_id' => $routine_test_wf_id,
                'routine_test_wf_planned_start_date' => $test_ref_date,
                'routine_test_wf_status' => 'Active',
                'is_adhoc' => 'Y',
                'requested_by_user_id' => $_SESSION['user_id'],
                'routine_test_req_id' => $routine_test_req_id
            ]);
            
            $workflow_affected = DB::affectedRows();
            
            if ($workflow_affected <= 0) {
                throw new Exception("Failed to create ad-hoc workflow entry");
            }
            
            // Log the ad-hoc workflow creation
            DB::insert('log', [
                'change_type' => 'tran_rtadhoc_add',
                'table_name' => 'tbl_routine_test_schedules',
                'change_description' => 'Added ad-hoc routine test workflow. RT WF ID: ' . $routine_test_wf_id,
                'change_by' => $_SESSION['user_id'],
                'unit_id' => $_SESSION['unit_id']
            ]);
        }
        
        // Log the routine test request addition
        DB::insert('log', [
            'change_type' => 'tran_rtreq_add',
            'table_name' => 'tbl_routine_tests_requests',
            'change_description' => 'Added a new routine test request. Test ID:' . $routine_test_req_id . 
                                  '. Frequency: ' . $validated_data['freq'],
            'change_by' => $_SESSION['user_id'],
            'unit_id' => $_SESSION['unit_id']
        ]);
        
        return $routine_test_req_id;
    });
    
    if ($result) {
        echo "success";
    } else {
        echo "failure";
    }
    
} catch (InvalidArgumentException $e) {
    error_log("Routine test validation error: " . $e->getMessage());
    echo json_encode(['error' => $e->getMessage()]);
} catch (Exception $e) {
    error_log("Routine test request error: " . $e->getMessage());
    echo json_encode(['error' => 'Database error occurred: ' . $e->getMessage()]);
}

?>