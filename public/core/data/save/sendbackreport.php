<?php

// Load configuration first
require_once(__DIR__ . '/../../config/config.php');
// Include XSS protection middleware (auto-initializes)
require_once('../../security/xss_integration_middleware.php');

// Force the session to be written to storage and then re-read immediately.
// This prevents a race condition by ensuring we get the latest session data
// before performing CSRF validation.
if (session_status() == PHP_SESSION_ACTIVE) {
    session_commit();
}
session_start();

// Validate session timeout
require_once('../../security/session_timeout_middleware.php');
validateActiveSession();

// Include rate limiting
require_once('../../security/rate_limiting_utils.php');

// Include secure transaction wrapper
require_once('../../security/secure_transaction_wrapper.php');

// Apply rate limiting for form submissions (with error handling)
try {
    if (!RateLimiter::checkRateLimit('form_submission')) {
        http_response_code(429);
        echo json_encode(['error' => 'Rate limit exceeded. Too many form submissions.']);
        exit();
    }
} catch (Exception $e) {
    // Log the error but continue processing (rate limiting is not critical for functionality)
    error_log("Rate limiting error: " . $e->getMessage());
}

// Determine if this is a GET request (from redirect) or POST request (from AJAX)
$is_redirect_request = ($_SERVER['REQUEST_METHOD'] === 'GET');

if (!$is_redirect_request) {
    // Set content type to JSON for AJAX responses
    header('Content-Type: application/json');
}

// Input validation helper
class SendBackValidator {
    public static function validateSendBackData($request_data, $is_redirect_request) {
        $validated_data = [];
        
        // Check if required parameters are present
        if (!isset($request_data['wf_id']) || empty($request_data['wf_id'])) {
            throw new InvalidArgumentException('Missing required parameter: wf_id');
        }
        
        // XSS detection on workflow ID
        if (XSSPrevention::detectXSS($request_data['wf_id'])) {
            XSSPrevention::logXSSAttempt($request_data['wf_id'], 'sendback_report');
            throw new InvalidArgumentException('Invalid input detected in workflow ID');
        }
        
        $validated_data['val_wf_id'] = $request_data['wf_id'];
        
        // Determine the approval level (default to level 1 if not specified)
        $approval_level = isset($request_data['approval_level']) ? (int)$request_data['approval_level'] : 1;
        
        if (!in_array($approval_level, [1, 2, 3])) {
            throw new InvalidArgumentException('Invalid approval level');
        }
        
        $validated_data['approval_level'] = $approval_level;
        
        // Get approver remark based on level
        $approver_remark_field = ($approval_level == 1) ? 'level1_approver_remark' : 
                                ($approval_level == 2 ? 'level2_approver_remark' : 'level3_approver_remark');
        
        if (!isset($request_data[$approver_remark_field]) || empty($request_data[$approver_remark_field])) {
            throw new InvalidArgumentException("Missing required parameter: $approver_remark_field");
        }
        
        // XSS detection on approver remark
        if (XSSPrevention::detectXSS($request_data[$approver_remark_field])) {
            XSSPrevention::logXSSAttempt($request_data[$approver_remark_field], 'sendback_report');
            throw new InvalidArgumentException('Invalid input detected in approver remark');
        }
        
        $validated_data['approver_remark'] = $request_data[$approver_remark_field];
        
        // Get the tracking ID
        if (!isset($request_data['val_wf_approval_tracking_id']) || 
            empty($request_data['val_wf_approval_tracking_id']) ||
            !is_numeric($request_data['val_wf_approval_tracking_id'])) {
            throw new InvalidArgumentException('Missing or invalid val_wf_approval_tracking_id parameter');
        }
        
        $validated_data['tracking_id'] = intval($request_data['val_wf_approval_tracking_id']);
        
        return $validated_data;
    }
    
    public static function validateSessionData($approval_level) {
        // Check if user is logged in
        if (!isset($_SESSION['user_name']) || !isset($_SESSION['user_id'])) {
            throw new Exception('User not logged in or missing user data');
        }
        
        // Check authorization based on approval level
        $is_unit_head = isset($_SESSION['is_unit_head']) && $_SESSION['is_unit_head'] == "Yes";
        $is_qa_head = isset($_SESSION['is_qa_head']) && $_SESSION['is_qa_head'] == "Yes";
        
        if ($approval_level == 1 && !isset($_SESSION['department_id'])) {
            throw new Exception('Department ID not found in session for level 1 approval');
        } else if ($approval_level == 2 && !$is_unit_head) {
            throw new Exception('User is not authorized as unit head for level 2 approval');
        } else if ($approval_level == 3 && !$is_qa_head) {
            throw new Exception('User is not authorized as QA head for level 3 approval');
        }
        
        return [
            'user_id' => $_SESSION['user_id'],
            'department_id' => isset($_SESSION['department_id']) ? $_SESSION['department_id'] : 0,
            'unit_id' => isset($_SESSION['unit_id']) ? $_SESSION['unit_id'] : 0,
            'is_unit_head' => $is_unit_head,
            'is_qa_head' => $is_qa_head
        ];
    }
}

try {
    // Always verify authentication token for security
    if ($is_redirect_request) {
        // For GET requests, verify the temporary session token set by the modal
        if (!isset($_GET['auth_token']) || !isset($_SESSION['temp_auth_token']) || 
            $_GET['auth_token'] !== $_SESSION['temp_auth_token'] ||
            !isset($_SESSION['temp_auth_expires']) || 
            time() > $_SESSION['temp_auth_expires']) {
            
            // Clear expired or invalid token
            unset($_SESSION['temp_auth_token'], $_SESSION['temp_auth_expires']);
            header('Location: ' . BASE_URL . 'manageprotocols.php?msg=auth_expired');
            exit();
        }
        
        // Get user remark before clearing session variables
        $user_remark = isset($_SESSION['temp_user_remark']) ? $_SESSION['temp_user_remark'] : '';
        
        // Clear the temporary token and user remark after use (single-use)
        unset($_SESSION['temp_auth_token'], $_SESSION['temp_auth_expires'], $_SESSION['temp_user_remark']);
        
    } else {
        // For POST requests, verify CSRF token
        if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            // Generate a new CSRF token
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            throw new Exception('CSRF token validation failed');
        }

        // Generate a new CSRF token for the next request
        $new_csrf_token = bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = $new_csrf_token;
    }

    // Get parameters from either GET or POST
    $request_data = $is_redirect_request ? $_GET : $_POST;
    
    // Validate input data
    $validated_data = SendBackValidator::validateSendBackData($request_data, $is_redirect_request);
    $session_data = SendBackValidator::validateSessionData($validated_data['approval_level']);
    
    // Include database connection - with error handling
    $db_class_path = __DIR__ . '/../../config/db.class.php';
    if (!file_exists($db_class_path)) {
        throw new Exception('Database class file not found at: ' . $db_class_path);
    }
    include_once $db_class_path;

    // For GET requests, $user_remark is already set above; for POST requests, get from session
    if (!$is_redirect_request) {
        $user_remark = isset($_SESSION['temp_user_remark']) ? $_SESSION['temp_user_remark'] : '';
    }

    // Get department name
    $department_name = "Unknown Department";
    try {
        if ($session_data['department_id'] > 0) {
            $department_name = DB::queryFirstField("SELECT department_name FROM departments WHERE department_id = %i", $session_data['department_id']);
        }
    } catch (Exception $e) {
        error_log("Warning: Could not get department name: " . $e->getMessage());
    }

    // Execute secure transaction
    $result = executeSecureTransaction(function() use ($validated_data, $session_data, $user_remark, $department_name) {
        // Check if iteration is already inactive
        $iteration_status = "Active"; // Default to active if query fails
        try {
            $iteration_status = DB::queryFirstField("SELECT iteration_status FROM tbl_val_wf_approval_tracking_details 
                WHERE val_wf_approval_trcking_id = %i", $validated_data['tracking_id']);
        } catch (Exception $e) {
            error_log("Warning: Could not check iteration status: " . $e->getMessage());
        }

        if($iteration_status == 'Inactive') {
            throw new Exception('Workflow iteration is already inactive - cannot send back');
        }
      
        // Set the workflow stage to return to based on approval level
        $return_stage = 1; // Always return to stage 1 when sent back
        
        // Update the workflow stage
        DB::update('tbl_val_wf_tracking_details', [
            'val_wf_current_stage' => $return_stage
        ], 'val_wf_id=%s', $validated_data['val_wf_id']);
        
        $current_datetime = date('Y-m-d H:i:s');
        
        // Determine which columns to update based on approval level and department ID
        if ($validated_data['approval_level'] == 1) {
            // Level 1 approval send back
            switch ($session_data['department_id']) {
                case 0: // QC Department
                    DB::query("UPDATE tbl_val_wf_approval_tracking_details SET 
                              level1_qc_approval_datetime = %s, 
                              level1_qc_approval_by = %i, 
                              level1_qc_approval_remarks = %s,
                              iteration_status = 'Inactive',
                              iteration_completion_status = 'sent_back',
                              iteration_rejected_by = %i,
                              iteration_rejected_datetime = %s 
                              WHERE val_wf_approval_trcking_id = %i", 
                              $current_datetime, $session_data['user_id'], $validated_data['approver_remark'], 
                              $session_data['user_id'], $current_datetime, $validated_data['tracking_id']);
                    break;
                    
                case 1: // Engineering Department
                    DB::query("UPDATE tbl_val_wf_approval_tracking_details SET 
                              level1_eng_approval_datetime = %s, 
                              level1_eng_approval_by = %i, 
                              level1_eng_approval_remarks = %s,
                              iteration_status = 'Inactive',
                              iteration_completion_status = 'sent_back',
                              iteration_rejected_by = %i,
                              iteration_rejected_datetime = %s 
                              WHERE val_wf_approval_trcking_id = %i", 
                              $current_datetime, $session_data['user_id'], $validated_data['approver_remark'], 
                              $session_data['user_id'], $current_datetime, $validated_data['tracking_id']);
                    break;
                    
                case 7: // HSE Department
                    DB::query("UPDATE tbl_val_wf_approval_tracking_details SET 
                              level1_hse_approval_datetime = %s, 
                              level1_hse_approval_by = %i, 
                              level1_hse_approval_remarks = %s, 
                              iteration_status = 'Inactive',
                              iteration_completion_status = 'sent_back',
                              iteration_rejected_by = %i,
                              iteration_rejected_datetime = %s 
                              WHERE val_wf_approval_trcking_id = %i", 
                              $current_datetime, $session_data['user_id'], $validated_data['approver_remark'], 
                              $session_data['user_id'], $current_datetime, $validated_data['tracking_id']);
                    break;
                    
                case 8: // QA Department
                    DB::query("UPDATE tbl_val_wf_approval_tracking_details SET 
                              level1_qa_approval_datetime = %s, 
                              level1_qa_approval_by = %i, 
                              level1_qa_approval_remarks = %s,
                              iteration_status = 'Inactive',
                              iteration_completion_status = 'sent_back',
                              iteration_rejected_by = %i,
                              iteration_rejected_datetime = %s 
                              WHERE val_wf_approval_trcking_id = %i", 
                              $current_datetime, $session_data['user_id'], $validated_data['approver_remark'], 
                              $session_data['user_id'], $current_datetime, $validated_data['tracking_id']);
                    break;
                    
                default:
                    // For other department IDs, check if it matches the equipment's department
                    $equipment_dept_query = "SELECT department_id 
                                            FROM equipments 
                                            WHERE equipment_id = (
                                                SELECT equipment_id 
                                                FROM tbl_val_wf_tracking_details 
                                                WHERE val_wf_id = %s
                                            )";
                    
                    $dept_result = DB::queryFirstRow($equipment_dept_query, $validated_data['val_wf_id']);
                    
                    if ($dept_result && $dept_result['department_id'] == $session_data['department_id']) {
                        // This is the user department
                        DB::query("UPDATE tbl_val_wf_approval_tracking_details SET 
                                  level1_user_dept_approval_datetime = %s, 
                                  level1_user_dept_approval_by = %i, 
                                  level1_user_dept_approval_remarks = %s,
                                  iteration_status = 'Inactive',
                                  iteration_completion_status = 'sent_back',
                                  iteration_rejected_by = %i,
                                  iteration_rejected_datetime = %s 
                                  WHERE val_wf_approval_trcking_id = %i", 
                                  $current_datetime, $session_data['user_id'], $validated_data['approver_remark'], 
                                  $session_data['user_id'], $current_datetime, $validated_data['tracking_id']);
                    } else {
                        // Department ID doesn't match any expected values
                        throw new Exception("Invalid department ID for level 1: " . $session_data['department_id']);
                    }
                    break;
            }
        } elseif ($validated_data['approval_level'] == 2)  {
            // Level 2 approval send back (for unit head)
            if ($session_data['is_unit_head']) {
                // For level 2, unit head is approving
                DB::query("UPDATE tbl_val_wf_approval_tracking_details SET 
                          level2_unit_head_approval_datetime = %s, 
                          level2_unit_head_approval_by = %i, 
                          level2_unit_head_approval_remarks = %s,
                          iteration_status = 'Inactive',
                          iteration_completion_status = 'sent_back',
                          iteration_rejected_by = %i,
                          iteration_rejected_datetime = %s 
                          WHERE val_wf_approval_trcking_id = %i", 
                          $current_datetime, $session_data['user_id'], $validated_data['approver_remark'], 
                          $session_data['user_id'], $current_datetime, $validated_data['tracking_id']);
            
                DB::query("update tbl_val_wf_tracking_details set val_wf_current_stage='1', stage_assigned_datetime=%? where val_wf_id=%s",
                         DB::sqleval("NOW()"), $validated_data['val_wf_id']);
            } else {
                throw new Exception("User is not authorized as unit head for level 2 approval");
            }
        }
        elseif ($validated_data['approval_level'] == 3)  {
            // Level 3 approval send back (for QA head)
            if ($session_data['is_qa_head']) {
                // For level 3, qa head is approving
                DB::query("UPDATE tbl_val_wf_approval_tracking_details SET 
                          level3_head_qa_approval_datetime = %s, 
                          level3_head_qa_approval_by = %i, 
                          level3_head_qa_approval_remarks = %s,
                          iteration_status = 'Inactive',
                          iteration_completion_status = 'sent_back',
                          iteration_rejected_by = %i,
                          iteration_rejected_datetime = %s 
                          WHERE val_wf_approval_trcking_id = %i", 
                          $current_datetime, $session_data['user_id'], $validated_data['approver_remark'], 
                          $session_data['user_id'], $current_datetime, $validated_data['tracking_id']);
            
                DB::query("update tbl_val_wf_tracking_details set val_wf_current_stage='1', stage_assigned_datetime=%? where val_wf_id=%s",
                         DB::sqleval("NOW()"), $validated_data['val_wf_id']);
            } else {
                throw new Exception("User is not authorized as QA head for level 3 approval");
            }
        }

        // Add audit trail entry
        try {
            $wf_stage = ($validated_data['approval_level'] == 1) ? 'LEVEL1_SEND_BACK' : 
                       ($validated_data['approval_level'] == 2 ? 'LEVEL2_SEND_BACK' : 'LEVEL3_SEND_BACK');
            $approver_type = ($validated_data['approval_level'] == 1) ? "department" : 
                           ($validated_data['approval_level'] == 2 ? "Unit head" : "QA head");
            
            DB::insert('log', [
                'change_type' => 'tran_teamapp_sendback',
                'table_name' => '',
                'change_description' => "Sent back by " . 
                    (($validated_data['approval_level'] == 1) ? 
                        ($department_name ?? 'unknown') . " department" : 
                        ($validated_data['approval_level'] == 2 ? "unit head" : "QA Head")) . 
                    ": " . $user_remark,
                'change_by' => $_SESSION['user_id'],
                'unit_id' => $_SESSION['unit_id']
            ]);
        } catch (Exception $e) {
            error_log("Warning: Could not add audit trail: " . $e->getMessage());
            // Continue execution - this is not critical
        }
        
        return true;
    });

    // Return success response
    if ($is_redirect_request) {
        header('Location: ' . BASE_URL . 'manageprotocols.php');
        exit();
    } else {
        echo json_encode([
            'status' => 'success',
            'message' => 'Report sent back successfully',
            'csrf_token' => $new_csrf_token
        ]);
    }
    
} catch (InvalidArgumentException $e) {
    error_log("SendBack validation error: " . $e->getMessage());
    
    if ($is_redirect_request) {
        header('Location: ' . BASE_URL . 'manageprotocols.php?msg=validation_error');
        exit();
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage(),
            'csrf_token' => isset($new_csrf_token) ? $new_csrf_token : bin2hex(random_bytes(32))
        ]);
    }
} catch (Exception $e) {
    // Log the error
    $error_message = "Error in sendbackreport.php: " . $e->getMessage();
    error_log($error_message);
    
    // Return error response
    if ($is_redirect_request) {
        header('Location: ' . BASE_URL . 'manageprotocols.php?msg=send_back_error');
        exit();
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'server_exception',
            'details' => $e->getMessage(),
            'csrf_token' => isset($new_csrf_token) ? $new_csrf_token : bin2hex(random_bytes(32))
        ]);
    }
}
exit();
?>