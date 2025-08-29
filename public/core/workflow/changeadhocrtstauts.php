<?php 

session_start();


// Validate session timeout
require_once('../security/session_timeout_middleware.php');
validateActiveSession();
require_once '../config/db.class.php';
date_default_timezone_set("Asia/Kolkata");


try {
    if(!isset($_GET['rtwfid']) || empty($_GET['rtwfid'])) {
        echo "error_no_rtwfid";
        return;
    }
    
    if(!isset($_GET['status'])) {
        echo "error_no_status";
        return;
    }
   
    $is_rt_running = DB::queryFirstRow("SELECT routine_test_wf_id,routine_test_wf_current_stage FROM tbl_routine_test_wf_tracking_details
where routine_test_wf_id='".$_GET['rtwfid']."'");

    // Check if a row was returned
if ($is_rt_running) {
    // A row was returned, and you can access the values using $is_rt_running['routine_test_wf_id'] and $is_rt_running['routine_test_wf_current_stage']
    $routine_test_wf_id = $is_rt_running['routine_test_wf_id'];
    $routine_test_wf_current_stage = $is_rt_running['routine_test_wf_current_stage'];

    echo "running";
} else {
    // No row was returned, get current status before updating
    $current_status = DB::queryFirstField("SELECT routine_test_wf_status FROM tbl_routine_test_schedules WHERE routine_test_wf_id='".$_GET['rtwfid']."'");
    
    if(!$current_status) {
        echo "error_not_found";
        return;
    }
    
    $status_from = $current_status ?: 'Unknown';
    $status_to = '';
    
    if($_GET['status'] == 0)
    {
        $status_to = 'Inactive';
        $results =DB::query("update tbl_routine_test_schedules set routine_test_wf_status='Inactive' where routine_test_wf_id='".$_GET['rtwfid']."'");
    }
    else{
        // For activation, perform date validation
        $planned_date = DB::queryFirstField("SELECT routine_test_wf_planned_start_date FROM tbl_routine_test_schedules WHERE routine_test_wf_id='".$_GET['rtwfid']."'");
        
        // Check if planned date has passed
        if (strtotime($planned_date) < strtotime(date('Y-m-d'))) {
            echo "expired_date|".$planned_date;
            
            // Log the failed activation attempt
            DB::insert('log', [
                'change_type' => 'tran_rtadhoc_activation_failed_expired',
                'table_name' => 'tbl_routine_test_schedules',
                'change_description' => 'Failed to activate ad-hoc routine test - planned date expired. RT WF ID: '.$_GET['rtwfid'].', Planned Date: '.$planned_date,
                'change_by' => $_SESSION['user_id'],
                'unit_id' => $_SESSION['unit_id']
            ]);
            return;
        }
        
        // Check for conflicting active routine tests (same equipment and test)
        $equip_test_info = DB::queryFirstRow("SELECT equip_id, test_id FROM tbl_routine_test_schedules WHERE routine_test_wf_id='".$_GET['rtwfid']."'");
        $conflicts = DB::queryFirstField("SELECT COUNT(*) FROM tbl_routine_test_schedules WHERE equip_id=".$equip_test_info['equip_id']." AND test_id=".$equip_test_info['test_id']." AND routine_test_wf_status='Active' AND is_adhoc='Y' AND routine_test_wf_id!='".$_GET['rtwfid']."'");
        
        if($conflicts > 0) {
            echo "conflict_exists";
            
            // Log the failed activation attempt
            DB::insert('log', [
                'change_type' => 'tran_rtadhoc_activation_failed_conflict',
                'table_name' => 'tbl_routine_test_schedules',
                'change_description' => 'Failed to activate ad-hoc routine test - active routine test already exists for same equipment/test. RT WF ID: '.$_GET['rtwfid'],
                'change_by' => $_SESSION['user_id'],
                'unit_id' => $_SESSION['unit_id']
            ]);
            return;
        }
        
        // Proceed with activation
        $status_to = 'Active';
        $results =DB::query("update tbl_routine_test_schedules set routine_test_wf_status='Active' where routine_test_wf_id='".$_GET['rtwfid']."'");
    }
    
    if(DB::affectedRows()>0)
    {
        echo "success";
        
        // Log the successful status change with detailed transition information
        DB::insert('log', [
            'change_type' => 'tran_rtadhoc_update',
            'table_name'=>'tbl_routine_test_schedules',
            'change_description'=>'Updated adhoc routine test request status from ' . $status_from . ' to ' . $status_to . '. RT WF ID: '.$_GET['rtwfid'],
            'change_by'=>$_SESSION['user_id'],
            'unit_id' => $_SESSION['unit_id']
        ]);
        
    }
    else
    {
        echo "failure";
        
        // Log the failed status change attempt
        DB::insert('log', [
            'change_type' => 'tran_rtadhoc_update_failed',
            'table_name'=>'tbl_routine_test_schedules',
            'change_description'=>'Failed to update adhoc routine test request status from ' . $status_from . ' to ' . $status_to . '. RT WF ID: '.$_GET['rtwfid'],
            'change_by'=>$_SESSION['user_id'],
            'unit_id' => $_SESSION['unit_id']
        ]);
    }
}

   
} catch (Exception $e) {
    
    echo "Exception:" . $e->getMessage();
    //print "something went wrong, caught yah! n";
} finally {
    //print "this part is always executed n";
}

?>