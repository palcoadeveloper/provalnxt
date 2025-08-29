<?php

// Load configuration first - session is already started by config.php via session_init.php
require_once(__DIR__ . '/../../config/config.php');

// Validate session timeout
require_once(__DIR__ . '/../../security/session_timeout_middleware.php');
validateActiveSession();
require_once(__DIR__ . "/../../config/db.class.php");


    $result=DB::query("SELECT equipment_id, equipment_code FROM equipments where unit_id=".$_GET['unitid']." and equipment_code like '%".$_GET['term']."%'");

$response = array();

foreach($result as $row)
{
    $response[]= array("id"=>$row['equipment_id'],"value"=>$row['equipment_code']);


}






echo json_encode($response);


?>