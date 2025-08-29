<?php 
session_start();


// Validate session timeout
require_once('../../security/session_timeout_middleware.php');
validateActiveSession();
include_once ("../../config/db.class.php");
date_default_timezone_set("Asia/Kolkata");


if($_GET['mode']=='add')
{
 DB::insert('vendors', [
           
            'vendor_name' => $_GET['vendor_name'],
            'vendor_spoc_name'=>$_GET['spoc_name'],
            'vendor_spoc_mobile'=>$_GET['spoc_mobile'],
            'vendor_spoc_email'=>$_GET['spoc_email'],
     'vendor_status'=>$_GET['vendor_status']
        ]);
        


DB::insert('log', [
    
    'change_type' => 'master_add_vendors',
    'table_name'=>'vendors',
    'change_description'=>'Added a new vendor. Vendor ID:'.DB::insertId(),
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
    
    DB::query("UPDATE vendors SET vendor_name=%s , vendor_spoc_name=%? ,vendor_spoc_mobile=%?, vendor_spoc_email=%s,vendor_status=%s  
WHERE vendor_id=%i",$_GET['vendor_name'],$_GET['spoc_name'],$_GET['spoc_mobile'],$_GET['spoc_email'],$_GET['vendor_status'],$_GET['vendor_id']);
   
    DB::insert('log', [
        
        'change_type' => 'master_update_vendors',
        'table_name'=>'vendors',
        'change_description'=>'Modified an existing vendor. Vendor ID:'.$_GET['vendor_id'],
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