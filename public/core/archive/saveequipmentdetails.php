<?php 
session_start();


// Validate session timeout
require_once('../../security/session_timeout_middleware.php');
validateActiveSession();
include_once ("../../config/db.class.php");
date_default_timezone_set("Asia/Kolkata");


if($_GET['mode']=='add')
{
 DB::insert('equipments', [
           
        
     
     'equipment_code'=>$_GET['equipment_code'],
     'unit_id'=>$_GET['unit_id'],
     'department_id'=>$_GET['department_id'],
     'equipment_category'=>$_GET['equipment_category'],
     'validation_frequency'=>$_GET['validation_frequency'],
     'area_served'=>$_GET['area_served'],
     'section'=>$_GET['section'],
     'design_acph'=>$_GET['design_acph'],
     'area_classification'=>$_GET['area_classification'],
     'area_classification_in_operation'=>$_GET['area_classification_in_operation'],
     'equipment_type'=>$_GET['equipment_type'],
     'design_cfm'=>$_GET['design_cfm'],
     'filteration_fresh_air'=>$_GET['filteration_fresh_air'],
     'filteration_pre_filter'=>$_GET['filteration_pre_filter'],
     'filteration_intermediate'=>$_GET['filteration_intermediate'],
     'filteration_final_filter_plenum'=>$_GET['filteration_final_filter_plenum'],
     'filteration_exhaust_pre_filter'=>$_GET['filteration_exhaust_pre_filter'],
     'filteration_exhaust_final_filter'=>$_GET['filteration_exhaust_final_filter'],
     'filteration_terminal_filter'=>$_GET['filteration_terminal_filter'],
     'filteration_terminal_filter_on_riser'=>$_GET['filteration_terminal_filter_on_riser'],
     'filteration_bibo_filter'=>$_GET['filteration_bibo_filter'],
     'filteration_relief_filter'=>$_GET['filteration_relief_filter'],
     'filteration_reativation_filter'=>$_GET['filteration_reativation_filter'],
     'equipment_status'=>$_GET['equipment_status'],
     'equipment_addition_date'=>$_GET['equipment_addition_date']

        ]);
        


DB::insert('log', [
    
    'change_type' => 'master_add_eq',
    'table_name'=>'equipments',
    'change_description'=>'Added a new equipment. Equipment ID:'.DB::insertId(),
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
    
    DB::query(
        
        "UPDATE equipments
SET

equipment_code =%?,
unit_id = %i,
department_id = %i,
equipment_category = %?,
validation_frequency = %?,
area_served = %?,
section = %?,
design_acph = %?,
area_classification = %?,
area_classification_in_operation = %?,
equipment_type = %?,
design_cfm = %?,
filteration_fresh_air = %?,
filteration_pre_filter = %?,
filteration_intermediate = %?,
filteration_final_filter_plenum = %?,
filteration_exhaust_pre_filter = %?,
filteration_exhaust_final_filter = %?,
filteration_terminal_filter = %?,
filteration_terminal_filter_on_riser = %?,
filteration_bibo_filter = %?,
filteration_relief_filter = %?,
filteration_reativation_filter = %?,
equipment_status = %?,

equipment_last_modification_datetime = %?,
equipment_addition_date = %?
WHERE equipment_id = %i;
",
        $_GET['equipment_code'],intval($_GET['unit_id']),intval($_GET['department_id']),$_GET['equipment_category'],
        $_GET['validation_frequency'],$_GET['area_served'],$_GET['section'],$_GET['design_acph'],$_GET['area_classification'],$_GET['area_classification_in_operation'],
        $_GET['equipment_type'],$_GET['design_cfm'],$_GET['filteration_fresh_air'],$_GET['filteration_pre_filter'],
        $_GET['filteration_intermediate'],$_GET['filteration_final_filter_plenum'],$_GET['filteration_exhaust_pre_filter'],
        $_GET['filteration_exhaust_final_filter'],$_GET['filteration_terminal_filter'],$_GET['filteration_terminal_filter_on_riser'],$_GET['filteration_bibo_filter'],$_GET['filteration_relief_filter'],
        $_GET['filteration_reativation_filter'],$_GET['equipment_status'],DB::sqleval("NOW()"),$_GET['equipment_addition_date'],intval($_GET['equipment_id'])
        
        
        );
    
    DB::insert('log', [
        
        'change_type' => 'master_update_eq',
        'table_name'=>'equipments',
        'change_description'=>'Modified an existing equipment. Equipment ID:'.$_GET['equipment_id'],
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