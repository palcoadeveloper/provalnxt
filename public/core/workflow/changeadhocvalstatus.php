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
if (!isset($_GET['valwfid']) || empty($_GET['valwfid'])) {
    echo json_encode(['error' => 'Missing validation workflow ID']);
    exit();
}

if (!isset($_GET['status']) || !in_array($_GET['status'], ['0', '1'])) {
    echo json_encode(['error' => 'Invalid status value. Must be 0 or 1']);
    exit();
}

try {
   
    $is_val_running = DB::queryFirstRow("SELECT val_wf_id,val_wf_current_stage FROM tbl_val_wf_tracking_details
where val_wf_id=%s", $_GET['valwfid']);

    // Check if a row was returned
if ($is_val_running) {
    // A row was returned, and you can access the values using $is_val_running['val_wf_id'] and $is_val_running['val_wf_current_stage']
    $val_wf_id = $is_val_running['val_wf_id'];
    $val_wf_current_stage = $is_val_running['val_wf_current_stage'];

    // Do something with the values
    echo "running";
} else {
    // No row was returned, get current status before updating
    $current_status = DB::queryFirstField("SELECT val_wf_status FROM tbl_val_schedules WHERE val_wf_id=%s", $_GET['valwfid']);
    
    $status_from = $current_status ?: 'Unknown';
    $status_to = '';
    
    if($_GET['status'] == 0)
    {
        $status_to = 'Inactive';
        $results = DB::query("UPDATE tbl_val_schedules SET val_wf_status='Inactive' WHERE val_wf_id=%s", $_GET['valwfid']);
    }
    else{
        $status_to = 'Active';
        $results = DB::query("UPDATE tbl_val_schedules SET val_wf_status='Active' WHERE val_wf_id=%s", $_GET['valwfid']);
    }
    
    if(DB::affectedRows()>0)
    {
        echo "success";
        
        // Log the successful status change with detailed transition information
        DB::insert('log', [
            'change_type' => 'tran_valadhoc_update',
            'table_name'=>'tbl_val_schedules',
            'change_description'=>'Updated adhoc validation request status from ' . $status_from . ' to ' . $status_to . '. Val WF ID: '.$_GET['valwfid'],
            'change_by'=>$_SESSION['user_id'],
            'unit_id' => $_SESSION['unit_id']
        ]);
        
    }
    else
    {
        echo "failure";
        
        // Log the failed status change attempt
        DB::insert('log', [
            'change_type' => 'tran_valadhoc_update_failed',
            'table_name'=>'tbl_val_schedules',
            'change_description'=>'Failed to update adhoc validation request status from ' . $status_from . ' to ' . $status_to . '. Val WF ID: '.$_GET['valwfid'],
            'change_by'=>$_SESSION['user_id'],
            'unit_id' => $_SESSION['unit_id']
        ]);
    }
}





   
} catch (Exception $e) {
    // Log the database error
    require_once('../error/error_logger.php');
    logDatabaseError("Database error in changeadhocvalstatus.php: " . $e->getMessage(), [
        'operation_name' => 'adhoc_validation_status_change',
        'val_wf_id' => $_GET['valwfid'] ?? null,
        'requested_status' => $_GET['status'] ?? null
    ]);
    
    echo "Exception:" . $e->getMessage();
}
?>










?>