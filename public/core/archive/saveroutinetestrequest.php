<?php 

session_start();


// Validate session timeout
require_once('../../security/session_timeout_middleware.php');
validateActiveSession();
require_once '../../config/db.class.php';
date_default_timezone_set("Asia/Kolkata");


try {
    $results =DB::queryFirstField("select vendor_id from equipment_test_vendor_mapping where equipment_id=%d and test_id=%d and mapping_status='Active';",intval($_GET['equipment_id']),intval($_GET['testid']));
    
    if($_GET['freq']=='Q')
    {
        $prev_date=$_GET["startdate"].' -3 months';
        $test_ref_date=date("Y-m-d",strtotime($prev_date));
    }
    else if($_GET['freq']=='H')
    {
        $prev_date=$_GET["startdate"].' -6 months'.'-1 day';
        $test_ref_date=date("Y-m-d",strtotime($prev_date));
    }
    else if($_GET['freq']=='Y')
    {
        $prev_date=$_GET["startdate"].' -12 months';
        $test_ref_date=date("Y-m-d",strtotime($prev_date));
    }
    else if($_GET['freq']=='2Y')
    {
        $prev_date=$_GET["startdate"].' -24 months';
        $test_ref_date=date("Y-m-d",strtotime($prev_date));
    }
    else if($_GET['freq']=='ADHOC')
    {
        // For ad-hoc tests, use the selected start date as reference
        $test_ref_date=$_GET["startdate"];
    }
    
    
    $query=DB::insert('tbl_routine_tests_requests',[
        'unit_id'=> $_GET['unitid'],
        'equipment_id'=>$_GET['equipment_id'],
        'test_id' => $_GET['testid'],
        'test_frequency' => $_GET['freq'],
        'test_planned_start_date' => $test_ref_date,
        'routine_test_status'=>1,
        'routine_test_requested_by'=>$_SESSION['user_id'],
        'adhoc_frequency' => ($_GET['freq'] == 'ADHOC') ? 'adhoc' : 'scheduled'
    ]);
    
    
    if(DB::affectedRows()>0)
    {
        $routine_test_req_id = DB::insertId();
        
        // For ADHOC requests, create immediate workflow entry in tbl_routine_test_schedules
        if($_GET['freq'] == 'ADHOC')
        {
            // Generate unique workflow ID
            $routine_test_wf_id = 'R-' . $_GET['unitid'] . '-' . $_GET['equipment_id'] . '-' . $_GET['testid'] . '-' . time();
            
            // Create immediate workflow entry
            $workflow_insert = DB::insert('tbl_routine_test_schedules', [
                'unit_id' => $_GET['unitid'],
                'equip_id' => $_GET['equipment_id'],
                'test_id' => $_GET['testid'],
                'routine_test_wf_id' => $routine_test_wf_id,
                'routine_test_wf_planned_start_date' => $test_ref_date,
                'routine_test_wf_status' => 'Active',
                'is_adhoc' => 'Y',
                'requested_by_user_id' => $_SESSION['user_id'],
                'routine_test_req_id' => $routine_test_req_id
            ]);
            
            if(DB::affectedRows() > 0)
            {
                // Log the ad-hoc workflow creation
                DB::insert('log', [
                    'change_type' => 'tran_rtadhoc_add',
                    'table_name' => 'tbl_routine_test_schedules',
                    'change_description' => 'Added ad-hoc routine test workflow. RT WF ID: ' . $routine_test_wf_id,
                    'change_by' => $_SESSION['user_id'],
                    'unit_id' => $_SESSION['unit_id']
                ]);
            }
        }
        
        echo "success";
        
    }
    else
    {
        echo "failure";
    }
    
    // Log the routine test request addition
    DB::insert('log', [
        'change_type' => 'tran_rtreq_add',
        'table_name'=>'tbl_routine_tests_requests',
        'change_description'=>'Added a new routine test request. Test ID:' . $routine_test_req_id . '. Frequency: ' . $_GET['freq'],
        'change_by'=>$_SESSION['user_id'],
        'unit_id' => $_SESSION['unit_id']
    ]);
} catch (Exception $e) {
    
    echo "Exception:" . $e->getMessage();
    //print "something went wrong, caught yah! n";
} finally {
    //print "this part is always executed n";
}










?>