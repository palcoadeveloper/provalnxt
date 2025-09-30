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
//include_once("getpassword.php");
//include_once("../../email/sendemail.php");
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
class UserDetailsValidator {
    public static function validateUserData($mode, $user_type) {
        $common_fields = ['employee_id', 'user_name', 'user_mobile', 'user_email', 'domain_id', 'user_status'];
        $required_fields = ['employee_id', 'user_name'];
        
        if ($user_type === 'vendor') {
            $required_fields[] = 'vendor_id';
        } else if ($user_type === 'employee') {
            $required_fields[] = 'unit_id';
        }
        
        if ($mode === 'modify') {
            $required_fields[] = 'user_id';
        }
        
        $validated_data = [];
        
        foreach ($common_fields as $field) {
            $value = safe_post($field, 'string', '');
            
            if (in_array($field, $required_fields) && empty($value)) {
                throw new InvalidArgumentException("Missing required field: $field");
            }
            
            // XSS detection on critical fields
            if (in_array($field, ['user_name', 'employee_id']) && 
                !empty($value) && XSSPrevention::detectXSS($value)) {
                XSSPrevention::logXSSAttempt($value, 'save_user_details');
                throw new InvalidArgumentException("Invalid input detected in $field");
            }
            
            $validated_data[$field] = $value;
        }
        
        // Validate email format if provided
        if (!empty($validated_data['user_email']) && 
            !filter_var($validated_data['user_email'], FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException("Invalid email format");
        }
        
        // Validate mobile format if provided (basic numeric check)
        if (!empty($validated_data['user_mobile']) && 
            !preg_match('/^[0-9+\-\s()]+$/', $validated_data['user_mobile'])) {
            throw new InvalidArgumentException("Invalid mobile number format");
        }
        
        // Additional fields based on user type
        if ($user_type === 'vendor') {
            $vendor_id = safe_post('vendor_id', 'int', 0);
            if ($vendor_id <= 0) {
                throw new InvalidArgumentException("Invalid vendor ID");
            }
            $validated_data['vendor_id'] = $vendor_id;
            
        } else if ($user_type === 'employee') {
            $unit_id = safe_post('unit_id', 'int', 0);
            if ($unit_id <= 0) {
                throw new InvalidArgumentException("Invalid unit ID");
            }
            $validated_data['unit_id'] = $unit_id;
            
            $validated_data['department_id'] = safe_post('department_id', 'int', 0);
            $validated_data['is_qa_head'] = safe_post('is_qa_head', 'string', 'No');
            $validated_data['is_unit_head'] = safe_post('is_unit_head', 'string', 'No');
            $validated_data['is_admin'] = safe_post('is_admin', 'string', 'No');
            $validated_data['is_super_admin'] = safe_post('is_super_admin', 'string', 'No');
            $validated_data['is_dept_head'] = safe_post('is_dept_head', 'string', 'No');
        }
        
        if ($mode === 'modify') {
            $user_id = safe_post('user_id', 'int', 0);
            if ($user_id <= 0) {
                throw new InvalidArgumentException("Invalid user ID");
            }
            $validated_data['user_id'] = $user_id;
            $validated_data['user_locked'] = safe_post('user_locked', 'string', 'No');
        }
        
        return $validated_data;
    }
}

$password = 'palcoa123'; // Default password
$message = "";
$mode = safe_post('mode', 'string', '');

if ($mode == 'addv') {
    try {
        // Validate input data for vendor user
        $validated_data = UserDetailsValidator::validateUserData('add', 'vendor');

        // Determine workflow requirements for engineering users
        $requires_approval = false;
        $final_status = $validated_data['user_status'];

        // Only non-admin engineering users require approval for vendor employees
        if (!empty($_SESSION['user_type']) && $_SESSION['user_type'] === 'engineering' &&
            $_SESSION['is_admin'] !== 'Yes' && $_SESSION['is_super_admin'] !== 'Yes' &&
            $_SESSION['is_dept_head'] !== 'Yes') {
            $requires_approval = true;
            $final_status = 'Pending';
        }

        // Execute secure transaction
        $result = executeSecureTransaction(function() use ($validated_data, $requires_approval, $final_status) {
            // Insert vendor user record
            $insert_data = [
                'employee_id' => $validated_data['employee_id'],
                'user_type' => 'vendor',
                'user_name' => $validated_data['user_name'],
                'vendor_id' => $validated_data['vendor_id'],
                'user_mobile' => $validated_data['user_mobile'],
                'user_email' => $validated_data['user_email'],
                'user_domain_id' => $validated_data['domain_id'],
                'is_default_password' => 'No',
                'user_status' => $final_status,
                'user_created_datetime' => date('Y-m-d H:i:s'),
                'unit_id' => $_SESSION['unit_id']
            ];

            // Add workflow fields if approval required
            if ($requires_approval) {
                $insert_data['submitted_by'] = $_SESSION['user_id'];
                $insert_data['original_data'] = json_encode([
                    'intended_status' => $validated_data['user_status']
                ]);
            }

            DB::insert('users', $insert_data);

            $user_id = DB::insertId();

            if ($user_id <= 0) {
                throw new Exception("Failed to insert vendor user record");
            }

            // Insert workflow log entry if approval required
            if ($requires_approval) {
                DB::insert('user_workflow_log', [
                    'user_id' => $user_id,
                    'action_type' => 'Created',
                    'performed_by' => $_SESSION['user_id'],
                    'action_date' => date('Y-m-d H:i:s'),
                    'new_data' => json_encode($insert_data),
                    'remarks' => 'Vendor employee created by non-admin engineering user - pending approval',
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
                ]);
            }

            // Insert log entry
            DB::insert('log', [
                'change_type' => 'master_users_addv',
                'table_name' => 'users',
                'change_description' => 'Added vendor employee. User Name: '. $validated_data['user_name'] .
                                       ($requires_approval ? ' (Pending approval)' : ''),
                'change_by' => $_SESSION['user_id'],
                'unit_id' => $_SESSION['unit_id']
            ]);

            return $user_id;
        });
        
        if ($result > 0) {
            echo "success";
        } else {
            echo "failure";
        }
        
    } catch (InvalidArgumentException $e) {
        error_log("User validation error: " . $e->getMessage());
        echo json_encode(['error' => $e->getMessage()]);
    } catch (Exception $e) {
        error_log("User add error: " . $e->getMessage());
        echo json_encode(['error' => 'Database error occurred']);
    }
}
else if ($mode == 'addc') {
    try {
        // Validate input data for employee user
        $validated_data = UserDetailsValidator::validateUserData('add', 'employee');
        
        // Execute secure transaction
        $result = executeSecureTransaction(function() use ($validated_data) {
            // Insert employee user record
            DB::insert('users', [
                'employee_id' => $validated_data['employee_id'],
                'user_type' => 'employee',
                'user_name' => $validated_data['user_name'],
                'vendor_id' => 0,
                'user_mobile' => $validated_data['user_mobile'],
                'user_email' => $validated_data['user_email'],
                'user_domain_id' => $validated_data['domain_id'],
                'unit_id' => $validated_data['unit_id'],
                'department_id' => $validated_data['department_id'],
                'is_qa_head' => $validated_data['is_qa_head'],
                'is_unit_head' => $validated_data['is_unit_head'],
                'is_admin' => $validated_data['is_admin'],
                'is_super_admin' => $validated_data['is_super_admin'],
                'is_dept_head' => $validated_data['is_dept_head'],
                'user_status' => $validated_data['user_status'],
                'user_created_datetime' => date('Y-m-d H:i:s')
            ]);
            
            $user_id = DB::insertId();
            
            if ($user_id <= 0) {
                throw new Exception("Failed to insert employee user record");
            }
            
            // Insert log entry
            DB::insert('log', [
                'change_type' => 'master_users_addc',
                'table_name' => 'users',
                'change_description' => 'Added internal user. User Name: '. $validated_data['user_name'],
                'change_by' => $_SESSION['user_id'],
                'unit_id' => $_SESSION['unit_id']
            ]);
            
            return $user_id;
        });
        
        if ($result > 0) {
            echo "success";
        } else {
            echo "failure";
        }
        
    } catch (InvalidArgumentException $e) {
        error_log("User validation error: " . $e->getMessage());
        echo json_encode(['error' => $e->getMessage()]);
    } catch (Exception $e) {
        error_log("User add error: " . $e->getMessage());
        echo json_encode(['error' => 'Database error occurred']);
    }
}
else if ($mode == 'modifyc') {
    try {
        // Validate input data for employee user modification
        $validated_data = UserDetailsValidator::validateUserData('modify', 'employee');
        
        // Get existing data for change tracking
        $existingData = DB::queryFirstRow("SELECT * FROM users WHERE user_id = %i", $validated_data['user_id']);
        
        if (!$existingData) {
            throw new Exception("User not found");
        }
        
        // Track changes for logging
        $changes = [];
        $paramToColumnMapping = [
            'user_status' => 'user_status',
            'user_locked' => 'is_account_locked'
        ];
        
        foreach ($paramToColumnMapping as $param => $column) {
            $new_value = safe_post($param, 'string', '');
            if (!empty($new_value) && isset($existingData[$column]) && $existingData[$column] != $new_value) {
                $changes[$column] = [
                    'old_value' => $existingData[$column],
                    'new_value' => $new_value
                ];
            }
        }
        
        // Execute secure transaction
        $result = executeSecureTransaction(function() use ($validated_data, $changes) {
            // Update employee user record
            DB::query(
                "UPDATE users SET 
                employee_id = %s, 
                user_name = %s,
                user_mobile = %s, 
                user_email = %s,
                unit_id = %i,
                department_id = %i,
                is_qa_head = %s,
                is_unit_head = %s,
                is_admin = %s,
                is_super_admin = %s,
                is_dept_head = %s,
                user_domain_id = %s,
                user_status = %s, 
                user_last_modification_datetime = %s,
                is_account_locked = %s
                WHERE user_id = %i", 
                $validated_data['employee_id'], 
                $validated_data['user_name'], 
                $validated_data['user_mobile'], 
                $validated_data['user_email'],
                $validated_data['unit_id'], 
                $validated_data['department_id'], 
                $validated_data['is_qa_head'], 
                $validated_data['is_unit_head'], 
                $validated_data['is_admin'], 
                $validated_data['is_super_admin'], 
                $validated_data['is_dept_head'], 
                $validated_data['domain_id'],
                $validated_data['user_status'], 
                date('Y-m-d H:i:s'), 
                $validated_data['user_locked'], 
                $validated_data['user_id']
            );
            
            $affected_rows = DB::affectedRows();
            
            if ($affected_rows === 0) {
                throw new Exception("No user record was updated");
            }
            
            // Determine log message based on changes
            $message = "";
            if (!empty($changes)) {
                foreach ($changes as $field => $change) {
                    if ($field == 'user_status') {
                        $message = ($change['new_value'] == 'Inactive') ? "User disabled." : "User enabled.";
                        break;
                    }
                }
            }
            
            // Insert log entry
            DB::insert('log', [
                'change_type' => 'master_users_updatec',
                'table_name' => 'users',
                'change_description' => !empty($message) ? 
                    ($message . ' User Name: ' . $validated_data['user_name']) : 
                    ('Modified an existing internal employee. User Name: ' . $validated_data['user_name']),
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
        error_log("User validation error: " . $e->getMessage());
        echo json_encode(['error' => $e->getMessage()]);
    } catch (Exception $e) {
        error_log("User modify error: " . $e->getMessage());
        echo json_encode(['error' => 'Database error occurred']);
    }
}
else if ($mode == 'modifyv') {
    try {
        // Validate input data for vendor user modification
        $validated_data = UserDetailsValidator::validateUserData('modify', 'vendor');

        // Get existing data for change tracking
        $existingData = DB::queryFirstRow("SELECT * FROM users WHERE user_id = %i", $validated_data['user_id']);

        if (!$existingData) {
            throw new Exception("User not found");
        }

        // Determine workflow requirements for engineering users
        $requires_approval = false;
        $final_status = $validated_data['user_status'];

        // Only non-admin engineering users require approval for vendor employee modifications
        if (!empty($_SESSION['user_type']) && $_SESSION['user_type'] === 'engineering' &&
            $_SESSION['is_admin'] !== 'Yes' && $_SESSION['is_super_admin'] !== 'Yes' &&
            $_SESSION['is_dept_head'] !== 'Yes') {

            // Check if there are any substantive changes that require approval
            $requires_approval_fields = ['user_status', 'employee_id', 'user_name', 'user_email', 'user_mobile', 'vendor_id'];
            foreach ($requires_approval_fields as $field) {
                $db_field = ($field === 'user_locked') ? 'is_account_locked' : $field;
                $new_value = ($field === 'user_locked') ? $validated_data['user_locked'] : $validated_data[$field];

                if (isset($existingData[$db_field]) && $existingData[$db_field] != $new_value) {
                    $requires_approval = true;
                    $final_status = 'Pending';
                    break;
                }
            }
        }

        // Track changes for logging
        $changes = [];
        $paramToColumnMapping = [
            'user_status' => 'user_status',
            'user_locked' => 'is_account_locked'
        ];

        foreach ($paramToColumnMapping as $param => $column) {
            $new_value = safe_post($param, 'string', '');
            if (!empty($new_value) && isset($existingData[$column]) && $existingData[$column] != $new_value) {
                $changes[$column] = [
                    'old_value' => $existingData[$column],
                    'new_value' => $new_value
                ];
            }
        }

        // Execute secure transaction
        $result = executeSecureTransaction(function() use ($validated_data, $changes, $requires_approval, $final_status, $existingData) {
            // Store original data for audit trail
            $original_data = json_encode($existingData);

            // Build update data
            $update_data = [
                'employee_id' => $validated_data['employee_id'],
                'user_name' => $validated_data['user_name'],
                'user_mobile' => $validated_data['user_mobile'],
                'user_email' => $validated_data['user_email'],
                'vendor_id' => $validated_data['vendor_id'],
                'user_status' => $final_status,
                'user_last_modification_datetime' => date('Y-m-d H:i:s'),
                'is_account_locked' => $validated_data['user_locked'],
                'user_domain_id' => $validated_data['domain_id']
            ];

            // Add workflow fields if approval required
            if ($requires_approval) {
                $update_data['submitted_by'] = $_SESSION['user_id'];
                $update_data['original_data'] = json_encode([
                    'intended_status' => $validated_data['user_status'],
                    'previous_data' => $existingData
                ]);
                // Clear checker fields if setting to pending
                $update_data['checker_id'] = null;
                $update_data['checker_date'] = null;
            }

            // Update vendor user record
            DB::query(
                "UPDATE users SET
                employee_id = %s,
                user_name = %s,
                user_mobile = %s,
                user_email = %s,
                vendor_id = %i,
                user_status = %s,
                user_last_modification_datetime = %s,
                is_account_locked = %s,
                user_domain_id = %s,
                submitted_by = %s,
                original_data = %s,
                checker_id = %s,
                checker_date = %s
                WHERE user_id = %i",
                $update_data['employee_id'],
                $update_data['user_name'],
                $update_data['user_mobile'],
                $update_data['user_email'],
                $update_data['vendor_id'],
                $update_data['user_status'],
                $update_data['user_last_modification_datetime'],
                $update_data['is_account_locked'],
                $update_data['user_domain_id'],
                $update_data['submitted_by'] ?? null,
                $update_data['original_data'] ?? null,
                $update_data['checker_id'] ?? null,
                $update_data['checker_date'] ?? null,
                $validated_data['user_id']
            );

            $affected_rows = DB::affectedRows();

            if ($affected_rows === 0) {
                throw new Exception("No user record was updated");
            }

            // Insert workflow log entry if approval required
            if ($requires_approval) {
                DB::insert('user_workflow_log', [
                    'user_id' => $validated_data['user_id'],
                    'action_type' => 'Modified',
                    'performed_by' => $_SESSION['user_id'],
                    'action_date' => date('Y-m-d H:i:s'),
                    'old_data' => $original_data,
                    'new_data' => json_encode($update_data),
                    'remarks' => 'Vendor employee modified by non-admin engineering user - pending approval',
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
                ]);
            }

            // Determine log message based on changes
            $message = "";
            if (!empty($changes)) {
                foreach ($changes as $field => $change) {
                    if ($field == 'user_status') {
                        $message .= ($change['new_value'] == 'Inactive') ? "User disabled." : "User enabled.";
                    }
                    if ($field == 'is_account_locked') {
                        $message .= ($change['new_value'] == 'Yes') ? "User account locked." : "User account unlocked.";
                    }
                }
            }

            // Insert log entry
            DB::insert('log', [
                'change_type' => 'master_users_updatev',
                'table_name' => 'users',
                'change_description' => !empty($message) ?
                    ($message . ' User Name: ' . $validated_data['user_name']) :
                    ('Modified an existing vendor employee. User Name: ' . $validated_data['user_name']) .
                    ($requires_approval ? ' (Pending approval)' : ''),
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
        error_log("User validation error: " . $e->getMessage());
        echo json_encode(['error' => $e->getMessage()]);
    } catch (Exception $e) {
        error_log("User modify error: " . $e->getMessage());
        echo json_encode(['error' => 'Database error occurred']);
    }
} else {
    echo json_encode(['error' => 'Invalid mode specified']);
}

?>