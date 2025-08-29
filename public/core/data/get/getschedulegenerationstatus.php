<?php

// Load configuration first
require_once(__DIR__ . '/../../config/config.php');

// Session is already started by config.php via session_init.php


// Validate session timeout
require_once('../../security/session_timeout_middleware.php');
validateActiveSession();
require_once '../../config/db.class.php';
date_default_timezone_set("Asia/Kolkata");

$result =DB::queryFirstField("call usp_GenerateSchedules (%d,%d)", intval($_GET['unitid']), intval($_GET['schyear']));




//$results=DB::queryFirstField("select @test");

//$results =DB::queryFirstField("call generate_schedule(%i,%s)", 8,'2021');

 if($result=="current_year_sch_pending" || $result=="invalid_year")
{
    DB::insert('log', [
        
        'change_type' => 'tran_vsch_gen_failed',
        'table_name'=>'',
        'change_description'=>'Validation schedule generation failed as validation protocols scheduled to run in the current year are not complete. User ID:'.$_SESSION['user_id'].' UnitID:'.intval($_GET['unitid']),
        'change_by'=>$_SESSION['user_id'],
        'unit_id' => $_SESSION['unit_id']
    ]);
    echo "All the validation protocols scheduled to run in the current year are not complete.";
  
    
}
else if($result=="already_exists")
{
    DB::insert('log', [
        
        'change_type' => 'tran_vsch_gen_failed',
        'table_name'=>'',
        'change_description'=>'Validation schedule generation failed as schedule for the year is already under process. User ID:'.$_SESSION['user_id'].' UnitID:'.intval($_GET['unitid']),
        'change_by'=>$_SESSION['user_id'],
        'unit_id' => $_SESSION['unit_id']
    ]);
    echo "A schdule generation request for the unit is already under process.";
    
    
}
else if($result=="current_year_sch_test_pending")
{
    DB::insert('log', [
        
        'change_type' => 'tran_vsch_gen_failed',
        'table_name'=>'',
        'change_description'=>'Validation schedule generation failed as all tests pertaining to the validation protocols scheduled to run in the current year are not complete. User ID:'.$_SESSION['user_id'].' UnitID:'.intval($_GET['unitid']),
        'change_by'=>$_SESSION['user_id'],
        'unit_id' => $_SESSION['unit_id']
    ]);
    echo "All tests pertaining to the validation protocols scheduled to run in the current year are not complete.";
    
    
}
else //if($results=="success")
{
    
    $scheduleid=DB::queryFirstField("select schedule_id from tbl_val_wf_schedule_requests 
where unit_id=%d and schedule_year=%d",intval($_GET['unitid']), $_GET['schyear']);
    // create a new cURL resource
    $ch = curl_init();
    
    // set URL and other appropriate options

    curl_setopt($ch, CURLOPT_URL,BASE_URL."generateschedulereport.php?unit_id=".$_GET['unitid']."&sch_id=".$scheduleid."&sch_year=".$_GET['schyear']."&user_name=".urlencode($_SESSION['user_name']));
    curl_setopt($ch, CURLOPT_HEADER, 0);
    
    // grab URL and pass it to the browser
    $output=curl_exec($ch);
//    echo $output;
    // close cURL resource, and free up system resources
    curl_close($ch);
    echo "The schedule is successfully generated.";
    
    DB::insert('log', [
        
        'change_type' => 'tran_vsch_gen',
        'table_name'=>'',
        'change_description'=>'Validation schedule Generated. User ID:'.$_SESSION['user_id'].' Sch ID:'.$scheduleid.' UnitID:'.intval($_GET['unitid']).'.Year:'. $_GET['schyear'],
        'change_by'=>$_SESSION['user_id'],
        'unit_id' => $_SESSION['unit_id']
    ]);
}
