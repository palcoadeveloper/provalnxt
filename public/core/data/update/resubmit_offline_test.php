<?php

// Load configuration first
require_once(__DIR__ . '/../../config/config.php');
// Include XSS protection middleware (auto-initializes)
require_once(__DIR__ . '/../../security/xss_integration_middleware.php');

// Session is already started by config.php via session_init.php

// Validate session timeout
require_once(__DIR__ . '/../../security/session_timeout_middleware.php');
validateActiveSession();

// Include rate limiting
require_once(__DIR__ . '/../../security/rate_limiting_utils.php');

// Include secure transaction wrapper
require_once(__DIR__ . '/../../security/secure_transaction_wrapper.php');

require_once(__DIR__ . '/../../config/db.class.php');
date_default_timezone_set("Asia/Kolkata");

// Apply rate limiting for form submissions
if (!RateLimiter::checkRateLimit('form_submission')) {
    http_response_code(429);
    echo json_encode([
        'status' => 'error',
        'message' => 'Rate limit exceeded. Too many form submissions.',
        'csrf_token' => generateCSRFToken()
    ]);
    exit();
}

// Validate CSRF token for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        http_response_code(403);
        echo json_encode([
            'status' => 'error',
            'message' => 'security_error',
            'csrf_token' => generateCSRFToken()
        ]);
        exit();
    }
}

// Input validation helper

class OfflineTestResubmitValidator {
    public static function validateResubmitData() {
        $required_fields = ['action', 'test_wf_id', 'val_wf_id', 'test_id'];
        
        $validated_data = [];
        
        foreach ($required_fields as $field) {
            $value = isset($_POST[$field]) ? trim($_POST[$field]) : '';
            
            if (empty($value)) {
                throw new InvalidArgumentException("Missing required field: $field");
            }
            
            $validated_data[$field] = $value;
        }
        
        // Validate action values
        if (!in_array($validated_data['action'], ['resubmit'])) {
            throw new InvalidArgumentException("Invalid action value");
        }
        
        // Validate numeric fields
        if (!is_numeric($validated_data['test_id'])) {
            throw new InvalidArgumentException("Invalid test ID");
        }
        
        // Validate password and remarks (required from modal)
        if (!isset($_POST['user_password']) || !isset($_POST['user_remark'])) {
            throw new InvalidArgumentException("Password and remarks are required");
        }
        
        $validated_data['user_password'] = $_POST['user_password'];
        $validated_data['user_remark'] = trim($_POST['user_remark']);
        
        if (empty($validated_data['user_remark'])) {
            throw new InvalidArgumentException("Remarks are required");
        }
        
        return $validated_data;
    }
}

try {
    // Debug logging
    error_log("Offline test resubmit request received. POST data: " . json_encode($_POST));
    
    // Validate input data
    $validated_data = OfflineTestResubmitValidator::validateResubmitData();
    
    // Check if test is in correct stage (1RRV)
    $test_data = DB::queryFirstRow(
        "SELECT test_wf_current_stage, test_performed_by, data_entry_mode FROM tbl_test_schedules_tracking 
        WHERE test_wf_id = %s AND test_id = %i",
        $validated_data['test_wf_id'],
        intval($validated_data['test_id'])
    );
    
    if (!$test_data) {
        throw new Exception("Test not found");
    }
    
    if ($test_data['test_wf_current_stage'] !== '1RRV') {
        throw new Exception("Test is not in resubmittable stage");
    }
    
    // Check if test data has been finalized first
    $finalization_check = DB::queryFirstRow(
        "SELECT test_finalised_by FROM tbl_test_finalisation_details 
        WHERE test_wf_id = %s AND status = 'Active'",
        $validated_data['test_wf_id']
    );
    
    if (!$finalization_check) {
        throw new Exception("Test data must be finalized before submission to checker");
    }
    
    // Verify user credentials (similar to offline_test_review.php)
    $username = $_SESSION['user_domain_id'];
    $userType = $_SESSION['logged_in_user'] === 'employee' ? 'E' : 'V';
    $password = $validated_data['user_password'];
    
    // Clear password from validated data immediately
    unset($validated_data['user_password']);
    
    // Initialize failed attempts tracking
    if (!isset($_SESSION['failed_attempts'])) {
        $_SESSION['failed_attempts'] = [];
    }
    if (!isset($_SESSION['failed_attempts'][$username])) {
        $_SESSION['failed_attempts'][$username] = 0;
    }
    
    // Include authentication utilities
    require_once('../../security/auth_utils.php');
    
    // Verify user credentials
    $authResult = verifyUserCredentials($username, $password, $userType);
    
    // Clear password from memory
    $password = null;
    unset($password);
    
    if (!$authResult) {
        // Authentication failed
        $_SESSION['failed_attempts'][$username]++;
        
        // Get unit_id for logging
        $unit_id = isset($_SESSION['unit_id']) && is_numeric($_SESSION['unit_id']) ? (int)$_SESSION['unit_id'] : 0;
        
        // Check if max attempts reached
        if ($_SESSION['failed_attempts'][$username] >= MAX_LOGIN_ATTEMPTS) {
            // Lock the account
            DB::update('users', ['is_account_locked' => 'Yes'], 'user_domain_id=%s', $username);
            
            $user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
            
            // Log account lock
            DB::insert('log', [
                'change_type' => 'offline_test_resubmit_failed',
                'table_name' => 'tbl_test_schedules_tracking',
                'change_description' => 'Test resubmit failed - Account locked. User:' . htmlspecialchars($username) . 
                                      ', Test WF:' . $validated_data['test_wf_id'] . ', Val WF:' . $validated_data['val_wf_id'],
                'change_by' => $user_id,
                'unit_id' => $unit_id
            ]);
            
            // Clear the session
            $_SESSION = array();
            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000,
                    $params["path"], $params["domain"],
                    $params["secure"], $params["httponly"]
                );
            }
            session_destroy();
            
            echo json_encode([
                'status' => 'error',
                'message' => 'account_locked',
                'redirect' => 'login.php?msg=acct_lckd',
                'forceRedirect' => true,
                'csrf_token' => generateCSRFToken()
            ]);
            exit();
        } else {
            // Log failed attempt
            $user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
            
            DB::insert('log', [
                'change_type' => 'offline_test_resubmit_failed',
                'table_name' => 'tbl_test_schedules_tracking',
                'change_description' => 'Test resubmit failed - Invalid password. User:' . htmlspecialchars($username) . 
                                      ' (Attempt ' . $_SESSION['failed_attempts'][$username] . '/' . MAX_LOGIN_ATTEMPTS . 
                                      '), Test WF:' . $validated_data['test_wf_id'] . ', Val WF:' . $validated_data['val_wf_id'],
                'change_by' => $user_id,
                'unit_id' => $unit_id
            ]);
            
            echo json_encode([
                'status' => 'error',
                'message' => 'invalid_credentials',
                'attempts_left' => MAX_LOGIN_ATTEMPTS - $_SESSION['failed_attempts'][$username],
                'csrf_token' => generateCSRFToken()
            ]);
            exit();
        }
    }
    
    // Authentication successful - reset failed attempts
    $_SESSION['failed_attempts'][$username] = 0;
    
    // Add remark to approver_remarks table
    DB::insert('approver_remarks', [
        'val_wf_id' => $validated_data['val_wf_id'],
        'test_wf_id' => $validated_data['test_wf_id'],
        'user_id' => $_SESSION['user_id'],
        'remarks' => $validated_data['user_remark'],
        'created_date_time' => DB::sqleval("NOW()")
    ]);
    
    // Check if current user is the same user who performed the test (they should be able to resubmit)
    if ($test_data['test_performed_by'] != $_SESSION['user_id']) {
        throw new Exception("You can only resubmit tests you performed");
    }
    
    // Execute secure transaction
    // Check if this is an offline paper-on-glass test that requires upload validation
    if ($test_data['data_entry_mode'] === 'offline') {
        // Check if this test has paper-on-glass enabled
        $paper_on_glass_check = DB::queryFirstRow(
            "SELECT paper_on_glass_enabled FROM tests 
            WHERE test_id = (SELECT test_id FROM tbl_test_schedules_tracking WHERE test_wf_id = %s)",
            $validated_data['test_wf_id']
        );
        
        if ($paper_on_glass_check && $paper_on_glass_check['paper_on_glass_enabled'] === 'Yes') {
            // Validate that required uploads exist for offline mode
            $required_uploads = DB::queryFirstRow("
                SELECT upload_path_raw_data, upload_path_test_certificate, upload_path_master_certificate
                FROM tbl_uploads
                WHERE test_wf_id = %s
                AND upload_action IS NULL
                AND upload_status = 'Active'
                ORDER BY uploaded_datetime DESC
                LIMIT 1
            ", $validated_data['test_wf_id']);

            if (!$required_uploads ||
                empty($required_uploads['upload_path_raw_data']) ||
                empty($required_uploads['upload_path_test_certificate']) ||
                empty($required_uploads['upload_path_master_certificate'])) {

                echo json_encode([
                    'status' => 'error',
                    'message' => 'No uploaded documents found for offline test. Please upload Raw Data, Certificate and Master Certificate files before submitting.',
                    'csrf_token' => generateCSRFToken()
                ]);
                exit();
            }
            
            error_log("[RESUBMIT VALIDATION] Upload validation passed for test_wf_id: " . $validated_data['test_wf_id']);
        }
    }
    $result = executeSecureTransaction(function() use ($validated_data, $test_data) {
        
        // Resubmit action: Update stage from '1RRV' back to '1PRV' (awaiting checker review)
        DB::update('tbl_test_schedules_tracking', 
            ['test_wf_current_stage' => '1PRV'], 
            'test_wf_id=%s', 
            $validated_data['test_wf_id']
        );
        
        $unit_id = (!empty($_SESSION['unit_id']) && is_numeric($_SESSION['unit_id'])) ? (int)$_SESSION['unit_id'] : null;
        
        // Insert audit trail with wf_stage = '1PRV'
        DB::insert('audit_trail', [
            'val_wf_id' => $validated_data['val_wf_id'],
            'test_wf_id' => $validated_data['test_wf_id'],
            'user_id' => $_SESSION['user_id'],
            'user_type' => $_SESSION['logged_in_user'],
            'time_stamp' => DB::sqleval("NOW()"),
            'wf_stage' => '1PRV'
        ]);
        
        // Insert activity log for resubmission
        DB::insert('log', [
            'change_type' => 'tran_off_test_resubmit',
            'table_name' => 'tbl_test_schedules_tracking',
            'change_description' => 'Offline test resubmitted for checker review after rejection. User ID:' . 
                                  $_SESSION['user_id'] . ', Test Workflow ID:' . 
                                  $validated_data['test_wf_id'] . ', Validation Workflow ID:' . 
                                  $validated_data['val_wf_id'],
            'change_by' => $_SESSION['user_id'],
            'unit_id' => $unit_id
        ]);
        
        return true;
    });
    
    if ($result) {
        echo json_encode([
            'status' => 'success', 
            'message' => 'Test resubmitted successfully for checker review',
            'csrf_token' => generateCSRFToken()
        ]);
    } else {
        echo json_encode([
            'status' => 'error', 
            'message' => 'Failed to resubmit test',
            'csrf_token' => generateCSRFToken()
        ]);
    }
    
} catch (InvalidArgumentException $e) {
    error_log("Offline test resubmit validation error: " . $e->getMessage());
    echo json_encode([
        'status' => 'error', 
        'message' => $e->getMessage(),
        'csrf_token' => generateCSRFToken()
    ]);
} catch (Exception $e) {
    error_log("Offline test resubmit error: " . $e->getMessage());
    echo json_encode([
        'status' => 'error', 
        'message' => 'Database error occurred: ' . $e->getMessage(),
        'csrf_token' => generateCSRFToken()
    ]);
}

?>