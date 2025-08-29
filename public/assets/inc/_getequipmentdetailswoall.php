<?php
// Start the session
//session_start();


// Load configuration first
require_once(__DIR__."/../../core/config/config.php");
include_once (__DIR__."/../../core/config/db.class.php");
date_default_timezone_set("Asia/Kolkata");
//include $_SERVER['DOCUMENT_ROOT']."/public/core/config/db.class.php";

$results = DB::query("SELECT equipment_id,equipment_code,equipment_category,e.department_id,d.department_name FROM equipments e, departments d
where e.department_id=d.department_id and unit_id=%d order by d.department_name", $_GET['unitid']);

$output="<option value='select'>Select</option>";
if(!empty($results))
{
    foreach ($results as $row) {
        
        $output=$output. "<option value='".$row['equipment_id']."'>"."(".$row['equipment_code'].") - (".$row['equipment_category'].") - (".$row['department_name'].")</option>";
        
    }
    
    echo $output;
    
}