<?php 

session_start();


// Validate session timeout
require_once('../../security/session_timeout_middleware.php');
validateActiveSession();
require_once '../../config/db.class.php';
date_default_timezone_set("Asia/Kolkata");


try {
   
//echo "call USP_ADDVALREQADHOC(".intval($_GET['unitid']).", ".intval($_GET['equipment_id']).", '".date("Y-m-d",strtotime($_GET['startdate']))."')";
    $addhoc_v_sch_id =DB::queryFirstField("call USP_ADDVALREQADHOC(".intval($_GET['unitid']).", ".intval($_GET['equipment_id']).", '".date("Y-m-d",strtotime($_GET['startdate']))."',".intval($_SESSION['user_id']).")");
    
    
    if(DB::affectedRows()>0)
    {
         $adhoc_val_wf_id=DB::queryFirstField('select val_wf_id from tbl_val_schedules where val_sch_id='.intval($addhoc_v_sch_id));

    DB::insert('log', [
        
        'change_type' => 'tran_valadhoc_add',
        'table_name'=>'tbl_val_schedules',
        'change_description'=>'Added ad-hoc validation request. Val WF ID:'.$adhoc_val_wf_id,
        'change_by'=>$_SESSION['user_id'],
        'unit_id' => $_SESSION['unit_id']
        
        
        
    ]);
        echo "success";
        
    }
    else
    {
        echo "failure";
    }

   
} catch (Exception $e) {
    
    echo "Exception:" . $e->getMessage();
    //print "something went wrong, caught yah! n";
} finally {
    //print "this part is always executed n";
}










?>