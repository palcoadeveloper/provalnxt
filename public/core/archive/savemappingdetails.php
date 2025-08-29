<?php 
session_start();


// Validate session timeout
require_once('../../security/session_timeout_middleware.php');
validateActiveSession();
include_once ("../../config/db.class.php");
date_default_timezone_set("Asia/Kolkata");


if($_GET['mode']=='add')
{
  // Extract the numeric part before the hyphen from test_id
  $test_id = $_GET['test_id'];
  if(strpos($test_id, '-') !== false) {
    $test_id = substr($test_id, 0, strpos($test_id, '-'));
  }

  DB::insert('equipment_test_vendor_mapping', [
           
            'equipment_id' => $_GET['equipment_id'],
     'test_id' => $test_id,
            'test_type'=>$_GET['test_type'],
            'vendor_id'=>$_GET['vendor_id'],
            
     'mapping_status'=>$_GET['mapping_status']
        ]);
        


DB::insert('log', [
    
    'change_type' => 'master_add_etv',
    'table_name'=>'equipment_test_vendor_mapping',
    'change_description'=>'Added a new mapping. Mapping ID:'.DB::insertId(),
    'change_by'=>$_SESSION['user_id'],
    'unit_id' => $_SESSION['unit_id']
    
    
    
    ]);

if(DB::affectedRows()>0)
{
    echo "success";
    
}
else
{
    echo "failure";
}
}
else if($_GET['mode']=='modify')
{
    
    // Extract the numeric part before the hyphen from test_id
    $test_id = $_GET['test_id'];
    if(strpos($test_id, '-') !== false) {
      $test_id = substr($test_id, 0, strpos($test_id, '-'));
    }
    
    DB::query("UPDATE equipment_test_vendor_mapping SET equipment_id=%i , test_id=%? ,test_type=%s, vendor_id=%s,mapping_status=%s  
WHERE mapping_id=%i",$_GET['equipment_id'],$test_id,$_GET['test_type'],$_GET['vendor_id'],$_GET['mapping_status'],$_GET['mapping_id']);
 
    if($_GET['vendorchangeforalltests']==1)
    {

         DB::query("UPDATE equipment_test_vendor_mapping SET  vendor_id=%s 
WHERE equipment_id=%i and vendor_id!=0 ",$_GET['vendor_id'],$_GET['equipment_id']);
 
$log_change_description ='Modified an existing mapping. Mapping ID:'.$_GET['mapping_id'].' Vendor changed for all tests. Equipment ID:'.$_GET['equipment_id'];

    }
    else
    {
      $log_change_description ='Modified an existing mapping. Mapping ID:'.$_GET['mapping_id'];
  
    }


    DB::insert('log', [
        
        'change_type' => 'master_update_etv',
        'table_name'=>'equipment_test_vendor_mapping',
        'change_description'=>$log_change_description,
        'change_by'=>$_SESSION['user_id'],
        'unit_id' => $_SESSION['unit_id']
        
        
        
    ]);
    if(DB::affectedRows()>0)
    {
        echo "success";
        
    }
    else
    {
        echo "failure";
    }
}

?>