<?php

// Load configuration first
require_once(__DIR__ . '/../../config/config.php');

// Session is already started by config.php via session_init.php


// Validate session timeout
require_once('../../security/session_timeout_middleware.php');
validateActiveSession();
require_once '../../config/db.class.php';
date_default_timezone_set("Asia/Kolkata");

$unit_id = intval($_GET['unitid']);
$schedule_year = intval($_GET['schyear']);

// Get the unit's validation scheduling logic
$validation_logic = DB::queryFirstField(
    "SELECT validation_scheduling_logic FROM units WHERE unit_id = %d", 
    $unit_id
);

// Default to dynamic if not found
if (!$validation_logic) {
    $validation_logic = 'dynamic';
}

// Call the appropriate stored procedure based on validation scheduling logic
if ($validation_logic === 'fixed') {
    $result = DB::queryFirstField("call USP_FIXED_CREATESCHEDULES (%d,%d)", $unit_id, $schedule_year);
} else {
    // Temporarily disable ONLY_FULL_GROUP_BY mode to fix stored procedure compatibility
    $current_sql_mode = DB::queryFirstField("SELECT @@sql_mode");
    DB::query("SET sql_mode = (SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''))");
    
    try {
        $result = DB::queryFirstField("call USP_DYNAMIC_GENERATESCHEDULES (%d,%d)", $unit_id, $schedule_year);
    } finally {
        // Restore original SQL mode
        DB::query("SET sql_mode = %s", $current_sql_mode);
    }
}




//$results=DB::queryFirstField("select @test");

//$results =DB::queryFirstField("call generate_schedule(%i,%s)", 8,'2021');

 if($result=="current_year_sch_pending" || $result=="invalid_year")
{
    DB::insert('log', [
        
        'change_type' => 'tran_vsch_gen_failed',
        'table_name'=>'',
        'change_description'=>'Validation schedule generation failed as validation protocols scheduled to run in the current year are not complete. User ID:'.$_SESSION['user_id'].' UnitID:'.$unit_id.' Logic:'.$validation_logic,
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
        'change_description'=>'Validation schedule generation failed as schedule for the year is already under process. User ID:'.$_SESSION['user_id'].' UnitID:'.$unit_id.' Logic:'.$validation_logic,
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
        'change_description'=>'Validation schedule generation failed as all tests pertaining to the validation protocols scheduled to run in the current year are not complete. User ID:'.$_SESSION['user_id'].' UnitID:'.$unit_id.' Logic:'.$validation_logic,
        'change_by'=>$_SESSION['user_id'],
        'unit_id' => $_SESSION['unit_id']
    ]);
    echo "All tests pertaining to the validation protocols scheduled to run in the current year are not complete.";
    
    
}
else //if($results=="success")
{
    
    $scheduleid=DB::queryFirstField("select schedule_id from tbl_val_wf_schedule_requests 
where unit_id=%d and schedule_year=%d", $unit_id, $schedule_year);
    // create a new cURL resource
    $ch = curl_init();
    
    // set URL and other appropriate options

    curl_setopt($ch, CURLOPT_URL,BASE_URL."generateschedulereport.php?unit_id=".$unit_id."&sch_id=".$scheduleid."&sch_year=".$schedule_year."&user_name=".urlencode($_SESSION['user_name']));
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
        'change_description'=>'Validation schedule Generated. User ID:'.$_SESSION['user_id'].' Sch ID:'.$scheduleid.' UnitID:'.$unit_id.' Logic:'.$validation_logic.' Year:'.$schedule_year,
        'change_by'=>$_SESSION['user_id'],
        'unit_id' => $_SESSION['unit_id']
    ]);
}
