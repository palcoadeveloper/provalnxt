<?php 

// Load configuration first
require_once('../config/config.php');

// Session is already started by config.php via session_init.php

// Validate session timeout
require_once('../security/session_timeout_middleware.php');
validateActiveSession();
require_once '../config/db.class.php';
date_default_timezone_set("Asia/Kolkata");

// Input validation
if (!isset($_GET['routine_test_request_id']) || empty($_GET['routine_test_request_id'])) {
    echo json_encode(['error' => 'Missing routine test request ID']);
    exit();
}

if (!is_numeric($_GET['routine_test_request_id'])) {
    echo json_encode(['error' => 'Invalid routine test request ID']);
    exit();
}

try {
   
    // Get current status along with equipment code and test ID
    $request_details = DB::queryFirstRow("SELECT t1.routine_test_status, t1.test_id, t2.equipment_code 
        FROM tbl_routine_tests_requests t1 
        LEFT JOIN equipments t2 ON t1.equipment_id = t2.equipment_id 
        WHERE t1.routine_test_request_id = %i", intval($_GET['routine_test_request_id']));

    if (!$request_details) {
        echo "failure";
        return;
    }
    
    $current_status = $request_details['routine_test_status'];
    $test_id = $request_details['test_id'];
    $equipment_code = $request_details['equipment_code'];
    
    $new_status = '';
    
    // Update status based on current status
    if($current_status == 0)
    {
        $results = DB::query("UPDATE tbl_routine_tests_requests SET routine_test_status=1 WHERE routine_test_request_id=%i", intval($_GET['routine_test_request_id']));
        $new_status = 'Active';
    }
    else if($current_status == 1){
        $results = DB::query("UPDATE tbl_routine_tests_requests SET routine_test_status=0 WHERE routine_test_request_id=%i", intval($_GET['routine_test_request_id']));
        $new_status = 'Inactive';
    }
    
    if(DB::affectedRows()>0)
    {
        echo "success";
        
        // Log with standardized format including Equipment Code and Test ID
        DB::insert('log', [
            'change_type' => 'tran_rtreq_update',
            'table_name'=>'tbl_routine_tests_requests',
            'change_description'=>'Updated Routine Test request status. Routine Test Request ID:' . $_GET['routine_test_request_id'] . '. Status changed to ' . $new_status,
            'change_by'=>$_SESSION['user_id'],
            'unit_id' => $_SESSION['unit_id']
        ]);
        
    }
    else
    {
        echo "failure";
    }

   
} 
catch (Exception $e) {
    // Log the database error
    require_once('../error/error_logger.php');
    logDatabaseError("Database error in changeroutinetestreqstatus.php: " . $e->getMessage(), [
        'operation_name' => 'routine_test_request_status_change',
        'routine_test_request_id' => $_GET['routine_test_request_id'] ?? null
    ]);
    
    echo "Exception:" . $e->getMessage();
}
?>










?>