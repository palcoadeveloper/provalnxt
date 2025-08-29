<?php
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
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Create a log file in a writable directory (commented out to avoid permission issues)
// $debug_file = dirname(__FILE__) . '/sendback_debug.log';
// file_put_contents($debug_file, date('Y-m-d H:i:s') . " - Request received\n", FILE_APPEND);
// file_put_contents($debug_file, date('Y-m-d H:i:s') . " - POST data: " . print_r($_POST, true) . "\n", FILE_APPEND);
// file_put_contents($debug_file, date('Y-m-d H:i:s') . " - SESSION data: " . print_r($_SESSION, true) . "\n", FILE_APPEND);

// Determine if this is a GET request (from redirect) or POST request (from AJAX)
$is_redirect_request = ($_SERVER['REQUEST_METHOD'] === 'GET');

if (!$is_redirect_request) {
    // Set content type to JSON for AJAX responses
    header('Content-Type: application/json');
}

try {
    // Check if user is logged in
    if (!isset($_SESSION['user_name'])) {
        if ($is_redirect_request) {
            header('Location: ../login.php');
            exit();
        }
        throw new Exception('User not logged in');
    }

    // Always verify authentication token for security
    if ($is_redirect_request) {
        // For GET requests, verify the temporary session token set by the modal
        if (!isset($_GET['auth_token']) || !isset($_SESSION['temp_auth_token']) || 
            $_GET['auth_token'] !== $_SESSION['temp_auth_token'] ||
            !isset($_SESSION['temp_auth_expires']) || 
            time() > $_SESSION['temp_auth_expires']) {
            
            // Clear expired or invalid token
            unset($_SESSION['temp_auth_token'], $_SESSION['temp_auth_expires']);
            header('Location: ../manageprotocols.php?msg=auth_expired');
            exit();
        }
        
        // Get user remark before clearing session variables
        $user_remark = isset($_SESSION['temp_user_remark']) ? $_SESSION['temp_user_remark'] : '';
        
        // Clear the temporary token and user remark after use (single-use)
        unset($_SESSION['temp_auth_token'], $_SESSION['temp_auth_expires'], $_SESSION['temp_user_remark']);
        
    } else {
        // For POST requests, verify CSRF token
        if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            // Debug information
            error_log("CSRF Debug - POST token: " . (isset($_POST['csrf_token']) ? $_POST['csrf_token'] : 'NOT SET'));
            error_log("CSRF Debug - SESSION token: " . (isset($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : 'NOT SET'));
            
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
    
    // Check if required parameters are present
    if (!isset($request_data['wf_id'])) {
        if ($is_redirect_request) {
            header('Location: ../manageprotocols.php?msg=missing_params');
            exit();
        }
        throw new Exception('Missing required parameter: wf_id');
    }

    // Determine the approval level (default to level 1 if not specified)
    $approval_level = isset($request_data['approval_level']) ? (int)$request_data['approval_level'] : 1;
    
    // Get approver remark based on level
    $approver_remark_field = ($approval_level == 1) ? 'level1_approver_remark' : ($approval_level == 2 ? 'level2_approver_remark' : 'level3_approver_remark');
    if (!isset($request_data[$approver_remark_field])) {
        if ($is_redirect_request) {
            header('Location: ../manageprotocols.php?msg=missing_params');
            exit();
        }
        throw new Exception("Missing required parameter: $approver_remark_field");
    }
    
    // Get the tracking ID
    if (!isset($request_data['val_wf_approval_tracking_id']) || empty($request_data['val_wf_approval_tracking_id'])) {
        if ($is_redirect_request) {
            header('Location: ../manageprotocols.php?msg=missing_params');
            exit();
        }
        throw new Exception('Missing val_wf_approval_tracking_id parameter');
    }

    // Include database connection - with error handling
    if (!file_exists('db.class.php')) {
        throw new Exception('Database class file not found');
    }
    include_once '../../config/db.class.php';

    // Get request data
    $val_wf_id = $request_data['wf_id'];
    // For GET requests, $user_remark is already set above; for POST requests, get from session
    if (!$is_redirect_request) {
        $user_remark = isset($_SESSION['temp_user_remark']) ? $_SESSION['temp_user_remark'] : '';
    }
    $approver_remark = $request_data[$approver_remark_field];
    $tracking_id = $request_data['val_wf_approval_tracking_id'];

    // Get required session data
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('User ID not found in session');
    }
    $user_id = $_SESSION['user_id'];
    
    // Check if this is a unit head for level 2
    $is_unit_head = isset($_SESSION['is_unit_head']) && $_SESSION['is_unit_head'] == "Yes";
    $is_qa_head = isset($_SESSION['is_qa_head']) && $_SESSION['is_qa_head'] == "Yes";
    // For level 1, department ID is required; for level 2, check if unit head
    if ($approval_level == 1 && !isset($_SESSION['department_id'])) {
        throw new Exception('Department ID not found in session for level 1 approval');
    } else if ($approval_level == 2 && !$is_unit_head) {
        throw new Exception('User is not authorized as unit head for level 2 approval');
    }
    else if ($approval_level == 3 && !$is_qa_head) {
        throw new Exception('User is not authorized as QA head for level 3 approval');
    }
    
    $department_id = isset($_SESSION['department_id']) ? $_SESSION['department_id'] : 0;
    $unit_id = isset($_SESSION['unit_id']) ? $_SESSION['unit_id'] : 0;

    // Get department name
    $department_name = "Unknown Department";
    try {
        $department_name = DB::queryFirstField("SELECT department_name FROM departments WHERE department_id = %i", $department_id);
    } catch (Exception $e) {
        // file_put_contents($debug_file, date('Y-m-d H:i:s') . " - Warning: Could not get department name: " . $e->getMessage() . "\n", FILE_APPEND);
    }

    // Current datetime
    $current_datetime = date('Y-m-d H:i:s');

    // Start a transaction
    DB::startTransaction();
    
    // Check if iteration is already inactive
    $iteration_status = "Active"; // Default to active if query fails
    try {
        $iteration_status = DB::queryFirstField("SELECT iteration_status FROM tbl_val_wf_approval_tracking_details 
            WHERE val_wf_approval_trcking_id = %i", $tracking_id);
    } catch (Exception $e) {
        // file_put_contents($debug_file, date('Y-m-d H:i:s') . " - Warning: Could not check iteration status: " . $e->getMessage() . "\n", FILE_APPEND);
    }

    if($iteration_status == 'Inactive') {
        if ($is_redirect_request) {
            DB::rollback();
            header('Location: ../manageprotocols.php?msg=already_sent_back');
            exit();
        } else {
            echo json_encode([
                'status' => 'error',
                'message' => 'already_sent_back',
                'csrf_token' => $new_csrf_token
            ]);
            DB::rollback();
            exit();
        }
    }
  
    // Set the workflow stage to return to based on approval level
    $return_stage = 1; // Always return to stage 1 when sent back
    
    // Log before update query
    // file_put_contents($debug_file, date('Y-m-d H:i:s') . " - About to execute update query for level: " . $approval_level . ", department: " . $department_id . "\n", FILE_APPEND);

    // Update the workflow stage
    DB::update('tbl_val_wf_tracking_details', [
        'val_wf_current_stage' => $return_stage
    ], 'val_wf_id=%s', $val_wf_id);
    
    // Determine which columns to update based on approval level and department ID
    try {
        if ($approval_level == 1) {
            // Level 1 approval send back
            switch ($department_id) {
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
                              $current_datetime, $user_id, $approver_remark, $user_id, $current_datetime, $tracking_id);
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
                              $current_datetime, $user_id, $approver_remark, $user_id, $current_datetime, $tracking_id);
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
                              $current_datetime, $user_id, $approver_remark, $user_id, $current_datetime, $tracking_id);
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
                              $current_datetime, $user_id, $approver_remark, $user_id, $current_datetime, $tracking_id);
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
                    
                    $dept_result = DB::queryFirstRow($equipment_dept_query, $val_wf_id);
                    
                    if ($dept_result && $dept_result['department_id'] == $department_id) {
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
                                  $current_datetime, $user_id, $approver_remark, $user_id, $current_datetime, $tracking_id);
                    } else {
                        // Department ID doesn't match any expected values
                        throw new Exception("Invalid department ID for level 1: " . $department_id);
                    }
                    break;
            }
        } elseif ($approval_level == 2)  {
            // Level 2 approval send back (for unit head)
            if ($is_unit_head) {
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
                          $current_datetime, $user_id, $approver_remark, $user_id, $current_datetime, $tracking_id);
            
                DB::query("update tbl_val_wf_tracking_details set val_wf_current_stage='1', stage_assigned_datetime=%? where val_wf_id=%s",DB::sqleval("NOW()"),$val_wf_id);

                //Add to Audit Tracking
            
            } else {
                throw new Exception("User is not authorized as unit head for level 2 approval");
            }
        }
        elseif ($approval_level == 3)  {
            // Level 3 approval send back (for unit head)
            if ($is_qa_head) {
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
                          $current_datetime, $user_id, $approver_remark, $user_id, $current_datetime, $tracking_id);
            
                DB::query("update tbl_val_wf_tracking_details set val_wf_current_stage='1', stage_assigned_datetime=%? where val_wf_id=%s",DB::sqleval("NOW()"),$val_wf_id);

                //Add to Audit Tracking
            
            } else {
                throw new Exception("User is not authorized as unit head for level 2 approval");
            }
        }
    } catch (Exception $e) {
        // file_put_contents($debug_file, date('Y-m-d H:i:s') . " - Error in update query: " . $e->getMessage() . "\n", FILE_APPEND);
        throw $e; // Re-throw to be caught by the outer try/catch
    }

    // Log after update query
    // file_put_contents($debug_file, date('Y-m-d H:i:s') . " - Update query executed successfully\n", FILE_APPEND);

    // Add audit trail entry
    try {
        $wf_stage = ($approval_level == 1) ? 'LEVEL1_SEND_BACK' : ($approval_level == 2 ? 'LEVEL2_SEND_BACK' : 'LEVEL3_SEND_BACK');
        $approver_type = ($approval_level == 1) ? "department" : ($approval_level == 2 ? "Unit head" : "QA head");
        

         DB::insert('log', [
            
            'change_type' => 'tran_teamapp_sendback',
            'table_name'=>'',
            'change_description'=>"Sent back by " . (($approval_level == 1) ? ($department_name ?? 'unknown') . " department" : ($approval_level == 2 ? "unit head" : "QA Head")) . ": " . $user_remark,
            'change_by'=>$_SESSION['user_id'],
            'unit_id' => $_SESSION['unit_id']
        ]);
    } catch (Exception $e) {
        // file_put_contents($debug_file, date('Y-m-d H:i:s') . " - Warning: Could not add audit trail: " . $e->getMessage() . "\n", FILE_APPEND);
        // Continue execution - this is not critical
    }

    // Commit the transaction
    DB::commit();

    // file_put_contents($debug_file, date('Y-m-d H:i:s') . " - Transaction committed successfully\n", FILE_APPEND);

    // Return success response
    if ($is_redirect_request) {
        header('Location: ../manageprotocols.php');
        exit();
    } else {
        echo json_encode([
            'status' => 'success',
            'message' => 'Report sent back successfully',
            'csrf_token' => $new_csrf_token
        ]);
    }
    
} catch (Exception $e) {
    // Rollback the transaction on error if it was started
    if (class_exists('DB') && method_exists('DB', 'rollback')) {
        try {
            DB::rollback();
        } catch (Exception $rollbackEx) {
            // file_put_contents($debug_file, date('Y-m-d H:i:s') . " - Error during rollback: " . $rollbackEx->getMessage() . "\n", FILE_APPEND);
        }
    }
    
    // Log the error
    $error_message = "Error in sendbackreport.php: " . $e->getMessage();
    // file_put_contents($debug_file, date('Y-m-d H:i:s') . " - ERROR: {$error_message}\n", FILE_APPEND);
    error_log($error_message);
    
    // Return error response
    if ($is_redirect_request) {
        header('Location: ../manageprotocols.php?msg=send_back_error');
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