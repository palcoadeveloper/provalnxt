<?php 
session_start();

// Validate session timeout
require_once('../../security/session_timeout_middleware.php');
validateActiveSession();

// Include security utilities
require_once('../../security/rate_limiting_utils.php');
require_once('../../security/secure_query_wrapper.php');

include_once ("../../config/db.class.php");
include_once ("getpassword.php");
include_once ("../../email/sendemail.php");
date_default_timezone_set("Asia/Kolkata");

// Check rate limiting for form submissions
$rateLimitResult = RateLimiter::checkRateLimit('form_submissions');
if (!$rateLimitResult['allowed']) {
    http_response_code(429);
    echo "failure";
    exit();
}

// Validate CSRF token using simple approach (consistent with rest of application)
if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    RateLimiter::recordFailure('form_submissions', null, 'csrf_failure');
    http_response_code(403);
    echo "failure";
    exit();
}

//$password=password_generate(7);
$password='palcoa123';
$message="";
// Secure input handling
$mode = secure_post('mode', 'string');
$employee_id = secure_post('employee_id', 'string');
$user_name = secure_post('user_name', 'string');
$vendor_id = secure_post('vendor_id', 'int');
$user_mobile = secure_post('user_mobile', 'string');
$user_email = secure_post('user_email', 'string');
$domain_id = secure_post('domain_id', 'string');
$user_status = secure_post('user_status', 'string');

if($mode == 'addv')
{
    // Validate required fields
    if (empty($employee_id) || empty($user_name) || empty($vendor_id)) {
        RateLimiter::recordFailure('form_submissions', null, 'invalid_input');
        echo "failure";
        exit();
    }
    
    // Validate email format if provided
    if (!empty($user_email) && !filter_var($user_email, FILTER_VALIDATE_EMAIL)) {
        RateLimiter::recordFailure('form_submissions', null, 'invalid_email');
        echo "failure";
        exit();
    }
    
    try {
        SecureDB::secureInsert('users', [
            'employee_id' => $employee_id,
            'user_type' => 'vendor',
            'user_name' => $user_name,
            'vendor_id' => $vendor_id,
            'user_mobile' => $user_mobile,
            'user_email' => $user_email,
            'user_domain_id' => $domain_id,
            'is_default_password' => 'No',
            'user_status' => $user_status
        ]);
        
        if(DB::affectedRows()>0) {
            // Record successful operation
            RateLimiter::recordSuccess('form_submissions');
            
            // Log the operation securely
            SecureDB::secureInsert('log', [
                'change_type' => 'master_users_addv',
                'table_name' => 'users',
                'change_description' => 'Added vendor employee. User Name: '. $user_name,
                'change_by' => $_SESSION['user_id'],
                'unit_id' => $_SESSION['unit_id']
            ]);
            
            echo "success";
        } else {
            RateLimiter::recordFailure('form_submissions', null, 'database_error');
            echo "failure";
        }
        
    } catch (Exception $e) {
        RateLimiter::recordFailure('form_submissions', null, 'database_exception');
        SecurityUtils::logSecurityEvent('database_error', 'Database error in saveuserdetails.php', [
            'error' => $e->getMessage(),
            'user_id' => $_SESSION['user_id'] ?? 'unknown'
        ]);
        echo "failure";
    }


//send_email(1,$_GET['user_name'],$_GET['employee_id'],$password,$_GET['user_email']);
}

else if($mode == 'addc')
{
    // Additional secure input handling for employee users
    $unit_id = secure_post('unit_id', 'int');
    $department_id = secure_post('department_id', 'int');
    $is_qa_head = secure_post('is_qa_head', 'string');
    $is_unit_head = secure_post('is_unit_head', 'string');
    $is_admin = secure_post('is_admin', 'string');
    $is_super_admin = secure_post('is_super_admin', 'string');
    $is_dept_head = secure_post('is_dept_head', 'string');
    
    // Validate required fields
    if (empty($employee_id) || empty($user_name) || empty($unit_id)) {
        RateLimiter::recordFailure('form_submissions', null, 'invalid_input');
        echo "failure";
        exit();
    }
    
    try {
        SecureDB::secureInsert('users', [
            'employee_id' => $employee_id,
            'user_type' => 'employee',
            'user_name' => $user_name,
            'vendor_id' => 0,
            'user_mobile' => $user_mobile,
            'user_email' => $user_email,
            'user_domain_id' => $domain_id,
            'unit_id' => $unit_id,
            'department_id' => $department_id,
            'is_qa_head' => $is_qa_head,
            'is_unit_head' => $is_unit_head,
            'is_admin' => $is_admin,
            'is_super_admin' => $is_super_admin,
            'is_dept_head' => $is_dept_head,
            'user_status' => $user_status
        ]);
    
        $affectedRows = DB::affectedRows();
        error_log("DEBUG - Affected rows: " . $affectedRows);
        
        if($affectedRows > 0) {
            // Record successful operation
            RateLimiter::recordSuccess('form_submissions');
            
            // Log the operation securely
            SecureDB::secureInsert('log', [
                'change_type' => 'master_users_addc',
                'table_name' => 'users',
                'change_description' => 'Added internal user. User Name: '. $user_name,
                'change_by' => $_SESSION['user_id'],
                'unit_id' => $_SESSION['unit_id']
            ]);
            
            echo "success";
        } else {
            RateLimiter::recordFailure('form_submissions', null, 'database_error');
            error_log("DEBUG - No rows affected during insert");
            echo "failure - no rows inserted";
        }
        
    } catch (Exception $e) {
        RateLimiter::recordFailure('form_submissions', null, 'database_exception');
        SecurityUtils::logSecurityEvent('database_error', 'Database error in saveuserdetails.php (addc)', [
            'error' => $e->getMessage(),
            'user_id' => $_SESSION['user_id'] ?? 'unknown'
        ]);
        error_log("DEBUG - Exception caught: " . $e->getMessage());
        echo "failure - database error: " . $e->getMessage();
    }
    
    
}






else if($mode=='modifyc')
{
    $user_id = secure_post('user_id', 'int');
    $existingData = DB::queryFirstRow("SELECT * FROM users WHERE user_id = %i", $user_id);

    $paramToColumnMapping = [
        'user_status' => 'user_status',
       'user_locked' => 'is_account_locked',
        // Add more mappings as needed
    ];

// Initialize an array to store the changes
    $changes = [];

 // Compare each field and update if necessary
    foreach ($_POST as $param => $value) {
        // Check if the parameter has a mapping
        if (array_key_exists($param, $paramToColumnMapping)) {
            $column = $paramToColumnMapping[$param];

            // Ensure the field exists in the database table
            if (array_key_exists($column, $existingData) && $existingData[$column] != $value) {
                // Record the change
                $changes[$column] = [
                    'old_value' => $existingData[$column],
                    'new_value' => $value,
                ];

               
            }
        }
    }
    
    // Additional secure input handling for modify operations
    $unit_id = secure_post('unit_id', 'int');
    $department_id = secure_post('department_id', 'int');
    $is_qa_head = secure_post('is_qa_head', 'string');
    $is_unit_head = secure_post('is_unit_head', 'string');
    $is_admin = secure_post('is_admin', 'string');
    $is_super_admin = secure_post('is_super_admin', 'string');
    $is_dept_head = secure_post('is_dept_head', 'string');
    $user_locked = secure_post('user_locked', 'string');
    
    DB::query("UPDATE users SET employee_id=%s , user_name=%s ,user_mobile=%s, user_email=%s,
unit_id=%i,department_id=%i,is_qa_head=%s,is_unit_head=%s,is_admin=%s,is_super_admin=%s,is_dept_head=%s,user_domain_id=%s,
user_status=%s, user_last_modification_datetime=%?, is_account_locked=%s
WHERE user_id=%i", $employee_id, $user_name, $user_mobile, $user_email,
        $unit_id, $department_id, $is_qa_head, $is_unit_head, $is_admin, $is_super_admin, $is_dept_head, $domain_id,
        $user_status, DB::sqleval("NOW()"), $user_locked, $user_id);
    if(DB::affectedRows()>0)
    {
        echo "success";
        
    }
    else
    {
        echo "failure";
    }

    // Log or handle the changes as needed
    if (!empty($changes)) {
        // Example: Log changes to a file or database
        // logChanges($userId, $changes);
      
        foreach ($changes as $field => $change) {
           // echo $field;
            if($field=='user_status')
            {
                if($change['new_value']=='Inactive')
                {
                    $message="User disabled.";

                }
                else
                {
                    $message="User enabled.";

                }
                
                break;
            }
           


        }
        
    }
   // echo $message;
    DB::insert('log', [
        
        'change_type' => 'master_users_updatec',
        'table_name'=>'users',
        'change_description'=>(!empty($message))?($message.' User Name: '. $user_name):'Modified an existing internal employee. User Name: '. $user_name,
        'change_by'=>$_SESSION['user_id'],
        'unit_id' => $_SESSION['unit_id']
        
        
        
    ]);
}

else if($mode=='modifyv')
{
    $user_id = secure_post('user_id', 'int');
    $existingData = DB::queryFirstRow("SELECT * FROM users WHERE user_id = %i", $user_id);

    $paramToColumnMapping = [
        'user_status' => 'user_status',
       'user_locked' => 'is_account_locked',
        // Add more mappings as needed
    ];

// Initialize an array to store the changes
    $changes = [];

 // Compare each field and update if necessary
    foreach ($_POST as $param => $value) {
        // Check if the parameter has a mapping
        if (array_key_exists($param, $paramToColumnMapping)) {
            $column = $paramToColumnMapping[$param];

            // Ensure the field exists in the database table
            if (array_key_exists($column, $existingData) && $existingData[$column] != $value) {
                // Record the change
                $changes[$column] = [
                    'old_value' => $existingData[$column],
                    'new_value' => $value,
                ];

               
            }
        }
    }

    // Additional secure input handling for vendor modify operations
    $user_locked = secure_post('user_locked', 'string');
    
    DB::query("UPDATE users SET employee_id=%s , user_name=%s ,user_mobile=%s, user_email=%s,vendor_id=%i,
user_status=%s, user_last_modification_datetime=%?,is_account_locked=%s,user_domain_id=%s
WHERE user_id=%i", $employee_id, $user_name, $user_mobile, $user_email, $vendor_id, $user_status,
        DB::sqleval("NOW()"), $user_locked, $domain_id, $user_id);
    if(DB::affectedRows()>0)
    {
        echo "success";
        
    }
    else
    {
        echo "failure";
    }
 // Log or handle the changes as needed
    if (!empty($changes)) {
        // Example: Log changes to a file or database
        // logChanges($userId, $changes);
       
        foreach ($changes as $field => $change) {
          //  echo $field;
            if($field=='user_status')
            {
                if($change['new_value']=='Inactive')
                {
                    $message=$message."User disabled.";

                }
                else
                {
$message=$message."User enabled.";

                }
                
                
            }
         if($field=='is_account_locked')
            {
                if($change['new_value']=='Yes')
                {
                    $message=$message."User account locked.";

                }
                else
                {
                    $message=$message."User account unlocked.";

                }
                
               
            }
           


        }
        
    }

    
    DB::insert('log', [
        
        'change_type' => 'master_users_updatev',
        'table_name'=>'users',
        'change_description'=>(!empty($message))?($message.' User Name:'. $user_name):'Modified an existing vendor employee. User Name: '. $user_name,
        'change_by'=>$_SESSION['user_id'],
        'unit_id' => $_SESSION['unit_id']
        
        
        
    ]);
    
}
?>