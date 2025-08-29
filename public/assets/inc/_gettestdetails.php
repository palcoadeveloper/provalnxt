<?php
// Start the session
//session_start();


// Load configuration first
require_once(__DIR__."/../../core/config/config.php");
include_once (__DIR__."/../../core/config/db.class.php");
date_default_timezone_set("Asia/Kolkata");
//include $_SERVER['DOCUMENT_ROOT']."/public/core/config/db.class.php";

$results = DB::query("select t2.test_id,test_name,test_description from tests t1, equipment_test_vendor_mapping t2
where t1.test_id=t2.test_id and equipment_id=%d ", $_GET['equipmentid']);
$output='';

if(!empty($results))
{
    foreach ($results as $row) {
        
        $output=$output. "<option value='".$row['test_id']."'>".$row['test_id']."-".$row['test_name']."-".$row['test_description']."</option>";
        
    }
    
    echo $output;
    
}