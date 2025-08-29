<?php

// Load configuration first
require_once(__DIR__ . '/../../config/config.php');

// Session is already started by config.php via session_init.php


// Validate session timeout
require_once(__DIR__ . '/../../security/session_timeout_middleware.php');
validateActiveSession();
if(!isset($_SESSION['user_name']))
{
   header('Location:'.BASE_URL .'login.php');
}

// Include the MeekroDB library
require_once __DIR__ . '/../../config/db.class.php';


$query="SELECT user_id, user_name FROM users";


if ($_GET['department_id']=="98")
{
$query=$query." where user_type='vendor' and user_status='Active'";

}
else if($_GET['department_id']=="99")
{
    $equipment_id=DB::queryFirstField("select equip_id from tbl_val_schedules where val_wf_id=%s",$_GET['val_wf_id']);
    
    
    
    $department_id=DB::queryFirstField("select department_id from equipments where equipment_id=%i",$equipment_id);
    

    
   // if($department_id==0 || $department_id==1 || $department_id==7 || $department_id==8)
   // {

   //   $query='';

   // }
   // else
   // {
        $query=$query." where department_id=".$department_id." and unit_id=".$_SESSION['unit_id']." and user_status='Active'";
   // }
}
else if($_GET['department_id']=="0")
{
  $query=$query." where department_id in (".$_GET['department_id'].",6) and unit_id=".$_SESSION['unit_id']." and user_status='Active'";
}
else
{
$query=$query." where department_id=".$_GET['department_id']." and unit_id=".$_SESSION['unit_id']." and user_status='Active'";
}

//echo $query;
if($query=='')
{

}
else
{
  // Fetch departments from the database
$departments = DB::query($query);

// Check if departments are fetched successfully
if ($departments) {
  // Return the departments as JSON
  echo json_encode($departments);
} else {
  // Handle the case where fetching departments failed
  header('HTTP/1.1 500 Internal Server Error');
  echo json_encode(['error' => 'Failed to fetch departments']);
}

}


?>