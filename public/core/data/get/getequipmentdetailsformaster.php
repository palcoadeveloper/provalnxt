<?php

session_start();



// Load configuration first
require_once(__DIR__ . '/../../config/config.php');
// Validate session timeout
require_once('../../security/session_timeout_middleware.php');
validateActiveSession();
require_once '../../config/db.class.php';

date_default_timezone_set("Asia/Kolkata");



if ($_GET['unit_id'] != 'Select') {
    
    $equipment_details = DB::query("SELECT equipment_id, equipment_code 
                                   FROM equipments 
                                   WHERE unit_id = %i", 
                                   intval($_GET['unit_id']));

$result="";

if(empty($equipment_details))
{
    
}
else 
{
  
    foreach ($equipment_details as $row) {
        $result=$result. "<option value='".$row['equipment_id']."'>".$row['equipment_code']."</option>";
               
    }
    
    echo $result;
    
}


}


