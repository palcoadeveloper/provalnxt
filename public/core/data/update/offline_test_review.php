<?php

// Load configuration first
require_once(__DIR__ . '/../../config/config.php');
// Include XSS protection middleware (auto-initializes)
require_once('../../security/xss_integration_middleware.php');

// Session is already started by config.php via session_init.php

// Validate session timeout
require_once('../../security/session_timeout_middleware.php');
validateActiveSession();

// Include rate limiting
require_once('../../security/rate_limiting_utils.php');

// Include secure transaction wrapper
require_once('../../security/secure_transaction_wrapper.php');

// Include authentication utilities for password verification
require_once('../../security/auth_utils.php');

require_once '../../config/db.class.php';
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

// Validate CSRF token for POST requests using simple approach (consistent with rest of application)
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
class OfflineTestReviewValidator {
    public static function validateReviewData() {
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
        if (!in_array($validated_data['action'], ['approve', 'reject'])) {
            throw new InvalidArgumentException("Invalid action value");
        }
        
        // Validate numeric fields
        if (!is_numeric($validated_data['test_id'])) {
            throw new InvalidArgumentException("Invalid test ID");
        }
        
        // Validate optional password and remarks if provided (for modal-based submissions)
        if (isset($_POST['user_password']) && isset($_POST['user_remark'])) {
            $validated_data['user_password'] = $_POST['user_password'];
            $validated_data['user_remark'] = trim($_POST['user_remark']);
            
            if (empty($validated_data['user_remark'])) {
                throw new InvalidArgumentException("Remarks are required");
            }
            
            $validated_data['has_credentials'] = true;
        } else {
            $validated_data['has_credentials'] = false;
        }
        
        return $validated_data;
    }
}

try {
    // Debug logging
    error_log("Offline test review request received. POST data: " . json_encode($_POST));
    
    // Validate input data
    $validated_data = OfflineTestReviewValidator::validateReviewData();
    
    // Check if test is in correct stage (1PRV)
    $test_data = DB::queryFirstRow(
        "SELECT test_wf_current_stage, test_performed_by, data_entry_mode FROM tbl_test_schedules_tracking 
        WHERE test_wf_id = %s AND test_id = %i",
        $validated_data['test_wf_id'],
        intval($validated_data['test_id'])
    );
    
    if (!$test_data) {
        throw new Exception("Test not found");
    }
    
    if (!in_array($test_data['test_wf_current_stage'], ['1PRV', '3BPRV'])) {
        throw new Exception("Test is not in reviewable stage");
    }
    
    // Check if current user is not the same user who performed the test
    if ($test_data['test_performed_by'] == $_SESSION['user_id']) {
        throw new Exception("You cannot review your own test");
    }
    
    // Verify user credentials if password is provided (for modal-based submissions)
    if ($validated_data['has_credentials']) {
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
        
        // Verify user credentials
        $authResult = verifyUserCredentials($username, $password, $userType);
        
        // Clear password from memory
        $password = null;
        unset($password);
        
        if ($authResult) {
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
        } else {
            // Authentication failed - implement account locking
            $_SESSION['failed_attempts'][$username]++;
            
            // Get unit_id for logging
            $unit_id = isset($_SESSION['unit_id']) && is_numeric($_SESSION['unit_id']) ? (int)$_SESSION['unit_id'] : 0;
            
            // Generate new CSRF token for response
            $newCsrfToken = generateCSRFToken();
            $response = ['csrf_token' => $newCsrfToken];
            
            // Check if max attempts reached
            if ($_SESSION['failed_attempts'][$username] >= MAX_LOGIN_ATTEMPTS) {
                // Lock the account using the same logic as in addremarks.php
                try {
                    // Update the database to lock the account
                    DB::update('users', ['is_account_locked' => 'Yes'], 'user_domain_id=%s', $username);
                    
                    $user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
                    
                    // Log to main log table
                    DB::insert('log', [
                        'change_type' => 'offline_test_review_failed',
                        'table_name' => 'tbl_test_schedules_tracking',
                        'change_description' => 'Offline review failed - Account locked. User:' . htmlspecialchars($username) . 
                                              ', Test WF:' . $validated_data['test_wf_id'] . ', Val WF:' . $validated_data['val_wf_id'],
                        'change_by' => $user_id,
                        'unit_id' => $unit_id
                    ]);
                    
                    // Log to security events
                    logSecurityEvent($username, 'account_locked', $user_id, $unit_id);
                    
                    // Clear the session - this is important for security
                    $_SESSION = array();
                    if (ini_get("session.use_cookies")) {
                        $params = session_get_cookie_params();
                        setcookie(session_name(), '', time() - 42000,
                            $params["path"], $params["domain"],
                            $params["secure"], $params["httponly"]
                        );
                    }
                    session_destroy();
                    
                    // For AJAX requests, provide full absolute URL for redirection
                    $redirect_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . 
                                    "://" . $_SERVER['HTTP_HOST'] . 
                                    dirname(dirname($_SERVER['PHP_SELF'])) . "/login.php?msg=acct_lckd";
                    
                    // Return appropriate response
                    $response['status'] = 'error';
                    $response['message'] = 'account_locked';
                    $response['redirect'] = $redirect_url;
                    $response['forceRedirect'] = true; // Add a special flag for forced redirect
                    $response['csrf_token'] = generateCSRFToken();
                    session_write_close();
                    echo json_encode($response);
                    exit();
                } catch (Exception $e) {
                    $errorMessage = handleDatabaseError($e, "Account locking during offline test review");
                    $response['status'] = 'error';
                    $response['message'] = 'system_error';
                    $response['csrf_token'] = generateCSRFToken();
                    session_write_close();
                    echo json_encode($response);
                    exit();
                }
            } else {
                // Not locked yet, just log the failed attempt
                $user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
                
                // Log to main log table
                DB::insert('log', [
                    'change_type' => 'offline_test_review_failed',
                    'table_name' => 'tbl_test_schedules_tracking',
                    'change_description' => 'Offline review failed - Invalid password. User:' . htmlspecialchars($username) . 
                                          ' (Attempt ' . $_SESSION['failed_attempts'][$username] . '/' . MAX_LOGIN_ATTEMPTS . 
                                          '), Test WF:' . $validated_data['test_wf_id'] . ', Val WF:' . $validated_data['val_wf_id'],
                    'change_by' => $user_id,
                    'unit_id' => $unit_id
                ]);
                
                // Log to security events
                logSecurityEvent($username, 'invalid_login', $user_id, $unit_id);
                
                // Return appropriate response with attempts left
                $response['status'] = 'error';
                $response['message'] = 'invalid_credentials';
                $response['attempts_left'] = MAX_LOGIN_ATTEMPTS - $_SESSION['failed_attempts'][$username];
                $response['csrf_token'] = generateCSRFToken();
                session_write_close();
                echo json_encode($response);
                exit();
            }
        }
    }
    
    // Execute secure transaction
    $result = executeSecureTransaction(function() use ($validated_data, $test_data) {
        
        if ($validated_data['action'] === 'approve') {
            // Approve action: Update stage to '2'
            DB::update('tbl_test_schedules_tracking', 
                ['test_wf_current_stage' => '2'], 
                'test_wf_id=%s', 
                $validated_data['test_wf_id']
            );
            
            // Insert approve log entry
            $unit_id = (!empty($_SESSION['unit_id']) && is_numeric($_SESSION['unit_id'])) ? (int)$_SESSION['unit_id'] : null;
            DB::insert('log', [
                'change_type' => 'tran_off_creview_approve',
                'table_name' => 'tbl_test_schedules_tracking',
                'change_description' => 'Offline test approved. User:' . $_SESSION['user_id'] . 
                                      ', Test WF:' . $validated_data['test_wf_id'] . 
                                      ', Val WF:' . $validated_data['val_wf_id'],
                'change_by' => $_SESSION['user_id'],
                'unit_id' => $unit_id
            ]);
            
        } else if ($validated_data['action'] === 'reject') {
            // Check if this is an offline vendor rejection - only these are allowed
            $is_offline_vendor_rejection = (
                $test_data['data_entry_mode'] === 'offline' &&
                $_SESSION['logged_in_user'] === 'vendor'
            );
            
            if ($is_offline_vendor_rejection) {
                // Reject action: Update stage to '1RRV' (only for offline vendor rejections)
                DB::update('tbl_test_schedules_tracking', 
                    ['test_wf_current_stage' => '1RRV'], 
                    'test_wf_id=%s', 
                    $validated_data['test_wf_id']
                );
                
                $unit_id = (!empty($_SESSION['unit_id']) && is_numeric($_SESSION['unit_id'])) ? (int)$_SESSION['unit_id'] : null;
                
                // Set all active test finalisation details to Inactive
                DB::query("
                    UPDATE tbl_test_finalisation_details 
                    SET status = 'Inactive'
                    WHERE test_wf_id = %s AND status = 'Active'
                ", $validated_data['test_wf_id']);
                
                // Insert audit trail with wf_stage = '1RRV'
                DB::insert('audit_trail', [
                    'val_wf_id' => $validated_data['val_wf_id'],
                    'test_wf_id' => $validated_data['test_wf_id'],
                    'user_id' => $_SESSION['user_id'],
                    'user_type' => $_SESSION['logged_in_user'],
                    'time_stamp' => DB::sqleval("NOW()"),
                    'wf_stage' => '1RRV'
                ]);
                
                // Insert specialized activity log for offline vendor rejection
                DB::insert('log', [
                    'change_type' => 'tran_off_ereview_reject',
                    'table_name' => 'tbl_test_schedules_tracking',
                    'change_description' => 'Offline test rejected, finalisation set inactive. User:' . $_SESSION['user_id'] . 
                                          ', Test WF:' . $validated_data['test_wf_id'] . 
                                          ', Val WF:' . $validated_data['val_wf_id'],
                    'change_by' => $_SESSION['user_id'],
                    'unit_id' => $unit_id
                ]);
            } else {
                // Rejection not allowed for current user type or data entry mode
                throw new Exception("Test rejection is only allowed for offline tests by vendor users");
            }
        }
        
        return true;
    });
    
    if ($result) {
        echo json_encode([
            'status' => 'success', 
            'message' => 'Test ' . $validated_data['action'] . 'd successfully',
            'csrf_token' => generateCSRFToken()
        ]);
    } else {
        echo json_encode([
            'status' => 'error', 
            'message' => 'Failed to process request',
            'csrf_token' => generateCSRFToken()
        ]);
    }
    
} catch (InvalidArgumentException $e) {
    error_log("Offline test review validation error: " . $e->getMessage());
    echo json_encode([
        'status' => 'error', 
        'message' => $e->getMessage(),
        'csrf_token' => generateCSRFToken()
    ]);
} catch (Exception $e) {
    error_log("Offline test review error: " . $e->getMessage());
    echo json_encode([
        'status' => 'error', 
        'message' => 'Database error occurred: ' . $e->getMessage(),
        'csrf_token' => generateCSRFToken()
    ]);
}

?>