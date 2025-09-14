<?php

// Load configuration first - session is already started by config.php via session_init.php
require_once(__DIR__ . '/../../config/config.php');


// Validate session timeout
require_once(__DIR__ . '/../../security/session_timeout_middleware.php');
validateActiveSession();
include_once(__DIR__ . "/../../config/db.class.php");
date_default_timezone_set("Asia/Kolkata");


if($_GET['equipmentid']!='ALL')
{
    
   
    if($_GET['wfstageid']=='6'){
        
        if(!empty($_GET['planned_start_from']))
        {
            $results = DB::query("select 0 val_wf_current_stage, t1.unit_id, t1.equip_id as equipment_id,t2.equipment_code, t1.val_wf_id,val_wf_planned_start_date,'' actual_wf_start_datetime,'' actual_wf_end_datetime,'Workflow not yet initiated.' wf_stage_description
            from tbl_val_schedules t1, equipments t2
            where t1.equip_id=t2.equipment_id and t1.unit_id=%d and val_wf_id not in (select val_wf_id from tbl_val_wf_tracking_details) and t1.equip_id=%d and val_wf_planned_start_date BETWEEN %? AND %? ",intval($_GET['unitid']),intval($_GET['equipmentid']),date("Y-m-d",strtotime($_GET['planned_start_from'])),date("Y-m-d",strtotime($_GET['planned_start_to'])));
            
        }
        else{

            $results = DB::query("select 0 val_wf_current_stage, t1.unit_id, t1.equip_id as equipment_id,t2.equipment_code, t1.val_wf_id,val_wf_planned_start_date,'' actual_wf_start_datetime,'' actual_wf_end_datetime,'Workflow not yet initiated.' wf_stage_description
            from tbl_val_schedules t1, equipments t2
            where t1.equip_id=t2.equipment_id and t1.unit_id=%d and val_wf_id not in (select val_wf_id from tbl_val_wf_tracking_details) and t1.equip_id=%d ",intval($_GET['unitid']),intval($_GET['equipmentid']));
            
        }
        
        
        
    }
    
    if($_GET['wfstageid']<>'6'){
        
        if(!empty($_GET['planned_start_from']) && empty($_GET['actual_start_from']))
        {
            if($_GET['wfstageid']=='0'){
                $results = DB::query("select t1.val_wf_current_stage,t1.unit_id,t1.equipment_id,t4.equipment_code,t1.val_wf_id, val_wf_planned_start_date,t1.actual_wf_start_datetime,t1.actual_wf_end_datetime, wf_stage_description
from tbl_val_wf_tracking_details t1, tbl_val_schedules t2, workflow_stages t3, equipments t4
where t1.val_wf_id=t2.val_wf_id and t1.val_wf_current_stage=t3.wf_stage and t1.equipment_id=t4.equipment_id
and t3.wf_type='Validation' and t1.unit_id=%d and val_wf_planned_start_date BETWEEN %? AND %? and t1.equipment_id=%d


UNION
select 0 val_wf_current_stage, t1.unit_id, t1.equip_id as equipment_id,t2.equipment_code, t1.val_wf_id,val_wf_planned_start_date,'' actual_wf_start_datetime,'' actual_wf_end_datetime,'Workflow not yet initiated.' wf_stage_description
            from tbl_val_schedules t1, equipments t2
            where t1.equip_id=t2.equipment_id and t1.unit_id=%d and val_wf_id not in (select val_wf_id from tbl_val_wf_tracking_details) and t1.equip_id=%d and val_wf_planned_start_date BETWEEN %? AND %?




" ,intval($_GET['unitid']),date("Y-m-d",strtotime($_GET['planned_start_from'])),date("Y-m-d",strtotime($_GET['planned_start_to'])),intval($_GET['equipmentid']),intval($_GET['unitid']),intval($_GET['equipmentid']),date("Y-m-d",strtotime($_GET['planned_start_from'])),date("Y-m-d",strtotime($_GET['planned_start_to'])));
            }
            else {
                $results = DB::query("select t1.val_wf_current_stage,t1.unit_id,t1.equipment_id,t4.equipment_code,t1.val_wf_id, val_wf_planned_start_date,t1.actual_wf_start_datetime,t1.actual_wf_end_datetime, wf_stage_description
from tbl_val_wf_tracking_details t1, tbl_val_schedules t2, workflow_stages t3, equipments t4
where t1.val_wf_id=t2.val_wf_id and t1.val_wf_current_stage=t3.wf_stage and t1.equipment_id=t4.equipment_id
and t3.wf_type='Validation' and t1.unit_id=%d and t1.val_wf_current_stage=%s and val_wf_planned_start_date BETWEEN %? AND %? and t1.equipment_id=%d" ,intval($_GET['unitid']),$_GET['wfstageid'],date("Y-m-d",strtotime($_GET['planned_start_from'])),date("Y-m-d",strtotime($_GET['planned_start_to'])),intval($_GET['equipmentid']));
            }
            
        }
        else if(empty($_GET['planned_start_from']) && !empty($_GET['actual_start_from']))
        {
            if($_GET['wfstageid']=='0'){
                $results = DB::query("select t1.val_wf_current_stage,t1.unit_id,t1.equipment_id,t4.equipment_code,t1.val_wf_id, val_wf_planned_start_date,t1.actual_wf_start_datetime,t1.actual_wf_end_datetime, wf_stage_description
from tbl_val_wf_tracking_details t1, tbl_val_schedules t2, workflow_stages t3, equipments t4
where t1.val_wf_id=t2.val_wf_id and t1.val_wf_current_stage=t3.wf_stage and t1.equipment_id=t4.equipment_id
and t3.wf_type='Validation' and t1.unit_id=%d and date(t1.actual_wf_start_datetime) BETWEEN %? AND %? and t1.equipment_id=%d
" ,intval($_GET['unitid']),date("Y-m-d",strtotime($_GET['actual_start_from'])),date("Y-m-d",strtotime($_GET['actual_start_to'])),intval($_GET['equipmentid']));
            }
            else {
                $results = DB::query("select t1.val_wf_current_stage,t1.unit_id,t1.equipment_id,t4.equipment_code,t1.val_wf_id, val_wf_planned_start_date,t1.actual_wf_start_datetime,t1.actual_wf_end_datetime, wf_stage_description
from tbl_val_wf_tracking_details t1, tbl_val_schedules t2, workflow_stages t3, equipments t4
where t1.val_wf_id=t2.val_wf_id and t1.val_wf_current_stage=t3.wf_stage and t1.equipment_id=t4.equipment_id
and t3.wf_type='Validation' and t1.unit_id=%d and t1.val_wf_current_stage=%s and date(t1.actual_wf_start_datetime) BETWEEN %? AND %? and t1.equipment_id=%d" ,intval($_GET['unitid']),$_GET['wfstageid'],date("Y-m-d",strtotime($_GET['actual_start_from'])),date("Y-m-d",strtotime($_GET['actual_start_to'])),intval($_GET['equipmentid']));
            }
            
        }
        else if(!empty($_GET['planned_start_from']) && !empty($_GET['actual_start_from']))
        {
            if($_GET['wfstageid']=='0'){
                $results = DB::query("select t1.val_wf_current_stage,t1.unit_id,t1.equipment_id,t4.equipment_code,t1.val_wf_id, val_wf_planned_start_date,t1.actual_wf_start_datetime,t1.actual_wf_end_datetime, wf_stage_description
from tbl_val_wf_tracking_details t1, tbl_val_schedules t2, workflow_stages t3, equipments t4
where t1.val_wf_id=t2.val_wf_id and t1.val_wf_current_stage=t3.wf_stage and t1.equipment_id=t4.equipment_id
and t3.wf_type='Validation' and t1.unit_id=%d and (date(t1.actual_wf_start_datetime) BETWEEN %? AND %?) and (val_wf_planned_start_date BETWEEN %? AND %?) and t1.equipment_id=%d
UNION
select 0 val_wf_current_stage, t1.unit_id, t1.equip_id as equipment_id,t2.equipment_code, t1.val_wf_id,val_wf_planned_start_date,'' actual_wf_start_datetime,'' actual_wf_end_datetime,'Workflow not yet initiated.' wf_stage_description
            from tbl_val_schedules t1, equipments t2
            where t1.equip_id=t2.equipment_id and t1.unit_id=%d and val_wf_id not in (select val_wf_id from tbl_val_wf_tracking_details) and t1.equip_id=%d and val_wf_planned_start_date BETWEEN %? AND %?



" ,intval($_GET['unitid']),date("Y-m-d",strtotime($_GET['actual_start_from'])),date("Y-m-d",strtotime($_GET['actual_start_to'])),date("Y-m-d",strtotime($_GET['planned_start_from'])),date("Y-m-d",strtotime($_GET['planned_start_to'])),intval($_GET['equipmentid']),intval($_GET['unitid']),intval($_GET['equipmentid']),date("Y-m-d",strtotime($_GET['planned_start_from'])),date("Y-m-d",strtotime($_GET['planned_start_to'])));
            }
            else {
                $results = DB::query("select t1.val_wf_current_stage,t1.unit_id,t1.equipment_id,t4.equipment_code,t1.val_wf_id, val_wf_planned_start_date,t1.actual_wf_start_datetime,t1.actual_wf_end_datetime, wf_stage_description
from tbl_val_wf_tracking_details t1, tbl_val_schedules t2, workflow_stages t3, equipments t4
where t1.val_wf_id=t2.val_wf_id and t1.val_wf_current_stage=t3.wf_stage and t1.equipment_id=t4.equipment_id
and t3.wf_type='Validation' and t1.unit_id=%d and t1.val_wf_current_stage=%s and (date(t1.actual_wf_start_datetime) BETWEEN %? AND %?) and (val_wf_planned_start_date BETWEEN %? AND %?) and t1.equipment_id=%d" ,intval($_GET['unitid']),$_GET['wfstageid'],date("Y-m-d",strtotime($_GET['actual_start_from'])),date("Y-m-d",strtotime($_GET['actual_start_to'])),date("Y-m-d",strtotime($_GET['planned_start_from'])),date("Y-m-d",strtotime($_GET['planned_start_to'])),intval($_GET['equipmentid']));
            }
            
        }
        else if(empty($_GET['planned_start_from']) && empty($_GET['actual_start_from']))
        {
            if($_GET['wfstageid']=='0'){
                $results = DB::query("select t1.val_wf_current_stage,t1.unit_id,t1.equipment_id,t4.equipment_code,t1.val_wf_id, val_wf_planned_start_date,t1.actual_wf_start_datetime,t1.actual_wf_end_datetime, wf_stage_description
from tbl_val_wf_tracking_details t1, tbl_val_schedules t2, workflow_stages t3, equipments t4
where t1.val_wf_id=t2.val_wf_id and t1.val_wf_current_stage=t3.wf_stage and t1.equipment_id=t4.equipment_id
and t3.wf_type='Validation' and t1.unit_id=%d and t1.equipment_id=%d






" ,intval($_GET['unitid']),intval($_GET['equipmentid']));
            }
            else {
                $results = DB::query("select t1.val_wf_current_stage,t1.unit_id,t1.equipment_id,t4.equipment_code,t1.val_wf_id, val_wf_planned_start_date,t1.actual_wf_start_datetime,t1.actual_wf_end_datetime, wf_stage_description
from tbl_val_wf_tracking_details t1, tbl_val_schedules t2, workflow_stages t3, equipments t4
where t1.val_wf_id=t2.val_wf_id and t1.val_wf_current_stage=t3.wf_stage and t1.equipment_id=t4.equipment_id
and t3.wf_type='Validation' and t1.unit_id=%d and t1.val_wf_current_stage=%s and t1.equipment_id=%d" ,intval($_GET['unitid']),$_GET['wfstageid'],intval($_GET['equipmentid']));
            }
            
            
        }
    }
    
    
   
    
    
    
    
    
}
else 
{
    
    
    if($_GET['wfstageid']=='6'){


        if(!empty($_GET['planned_start_from']))
        {

            $results = DB::query("select 0 val_wf_current_stage, t1.unit_id, t1.equip_id as equipment_id,t2.equipment_code, t1.val_wf_id,val_wf_planned_start_date,'' actual_wf_start_datetime,'' actual_wf_end_datetime,'Workflow not yet initiated.' wf_stage_description
            from tbl_val_schedules t1, equipments t2
            where t1.equip_id=t2.equipment_id and t1.unit_id=%d and val_wf_id not in (select val_wf_id from tbl_val_wf_tracking_details) and val_wf_planned_start_date BETWEEN %? AND %?",intval($_GET['unitid']),date("Y-m-d",strtotime($_GET['planned_start_from'])),date("Y-m-d",strtotime($_GET['planned_start_to'])));
        }
        else
        {
            $results = DB::query("select 0 val_wf_current_stage, t1.unit_id, t1.equip_id as equipment_id,t2.equipment_code, t1.val_wf_id,val_wf_planned_start_date,'' actual_wf_start_datetime,'' actual_wf_end_datetime,'Workflow not yet initiated.' wf_stage_description
            from tbl_val_schedules t1, equipments t2
            where t1.equip_id=t2.equipment_id and t1.unit_id=%d and val_wf_id not in (select val_wf_id from tbl_val_wf_tracking_details)",intval($_GET['unitid']));


        }


       
        
    }
    
    if($_GET['wfstageid']<>'6'){
        
        if(!empty($_GET['planned_start_from']) && empty($_GET['actual_start_from']))
        {
            
            
            if($_GET['wfstageid']=='0'){
                $results = DB::query("select t1.val_wf_current_stage,t1.unit_id,t1.equipment_id,t4.equipment_code,t1.val_wf_id, val_wf_planned_start_date,t1.actual_wf_start_datetime,t1.actual_wf_end_datetime, wf_stage_description
from tbl_val_wf_tracking_details t1, tbl_val_schedules t2, workflow_stages t3, equipments t4
where t1.val_wf_id=t2.val_wf_id and t1.val_wf_current_stage=t3.wf_stage and t1.equipment_id=t4.equipment_id
and t3.wf_type='Validation' and t1.unit_id=%d and val_wf_planned_start_date BETWEEN %? AND %?

UNION
select 0 val_wf_current_stage, t1.unit_id, t1.equip_id as equipment_id,t2.equipment_code, t1.val_wf_id,val_wf_planned_start_date,'' actual_wf_start_datetime,'' actual_wf_end_datetime,'Workflow not yet initiated.' wf_stage_description
            from tbl_val_schedules t1, equipments t2
            where t1.equip_id=t2.equipment_id and t1.unit_id=%d and val_wf_id not in (select val_wf_id from tbl_val_wf_tracking_details)  and val_wf_planned_start_date BETWEEN %? AND %? 



" ,intval($_GET['unitid']),date("Y-m-d",strtotime($_GET['planned_start_from'])),date("Y-m-d",strtotime($_GET['planned_start_to'])),intval($_GET['unitid']),date("Y-m-d",strtotime($_GET['planned_start_from'])),date("Y-m-d",strtotime($_GET['planned_start_to'])));
            }
            else {
                $results = DB::query("select t1.val_wf_current_stage,t1.unit_id,t1.equipment_id,t4.equipment_code,t1.val_wf_id, val_wf_planned_start_date,t1.actual_wf_start_datetime,t1.actual_wf_end_datetime, wf_stage_description
from tbl_val_wf_tracking_details t1, tbl_val_schedules t2, workflow_stages t3, equipments t4
where t1.val_wf_id=t2.val_wf_id and t1.val_wf_current_stage=t3.wf_stage and t1.equipment_id=t4.equipment_id
and t3.wf_type='Validation' and t1.unit_id=%d and t1.val_wf_current_stage=%s and val_wf_planned_start_date BETWEEN %? AND %?" ,intval($_GET['unitid']),$_GET['wfstageid'],date("Y-m-d",strtotime($_GET['planned_start_from'])),date("Y-m-d",strtotime($_GET['planned_start_to'])));
            }
            
            
        }
        else if(empty($_GET['planned_start_from']) && !empty($_GET['actual_start_from']))
        {
            if($_GET['wfstageid']=='0'){
                $results = DB::query("select t1.val_wf_current_stage,t1.unit_id,t1.equipment_id,t4.equipment_code,t1.val_wf_id, val_wf_planned_start_date,t1.actual_wf_start_datetime,t1.actual_wf_end_datetime, wf_stage_description
from tbl_val_wf_tracking_details t1, tbl_val_schedules t2, workflow_stages t3, equipments t4
where t1.val_wf_id=t2.val_wf_id and t1.val_wf_current_stage=t3.wf_stage and t1.equipment_id=t4.equipment_id
and t3.wf_type='Validation' and t1.unit_id=%d and date(t1.actual_wf_start_datetime) BETWEEN %? AND %?


" ,intval($_GET['unitid']),date("Y-m-d",strtotime($_GET['actual_start_from'])),date("Y-m-d",strtotime($_GET['actual_start_to'])));
            }
            else {
                $results = DB::query("select t1.val_wf_current_stage,t1.unit_id,t1.equipment_id,t4.equipment_code,t1.val_wf_id, val_wf_planned_start_date,t1.actual_wf_start_datetime,t1.actual_wf_end_datetime, wf_stage_description
from tbl_val_wf_tracking_details t1, tbl_val_schedules t2, workflow_stages t3, equipments t4
where t1.val_wf_id=t2.val_wf_id and t1.val_wf_current_stage=t3.wf_stage and t1.equipment_id=t4.equipment_id
and t3.wf_type='Validation' and t1.unit_id=%d and t1.val_wf_current_stage=%s and date(t1.actual_wf_start_datetime) BETWEEN %? AND %?" ,intval($_GET['unitid']),$_GET['wfstageid'],date("Y-m-d",strtotime($_GET['actual_start_from'])),date("Y-m-d",strtotime($_GET['actual_start_to'])));
            }
            
        }
        else if(!empty($_GET['planned_start_from']) && !empty($_GET['actual_start_from']))
        {
            if($_GET['wfstageid']=='0'){
                $results = DB::query("select t1.val_wf_current_stage,t1.unit_id,t1.equipment_id,t4.equipment_code,t1.val_wf_id, val_wf_planned_start_date,t1.actual_wf_start_datetime,t1.actual_wf_end_datetime, wf_stage_description
from tbl_val_wf_tracking_details t1, tbl_val_schedules t2, workflow_stages t3, equipments t4
where t1.val_wf_id=t2.val_wf_id and t1.val_wf_current_stage=t3.wf_stage and t1.equipment_id=t4.equipment_id
and t3.wf_type='Validation' and t1.unit_id=%d and (date(t1.actual_wf_start_datetime) BETWEEN %? AND %?) and (val_wf_planned_start_date BETWEEN %? AND %?) 
UNION
select 0 val_wf_current_stage, t1.unit_id, t1.equip_id as equipment_id,t2.equipment_code, t1.val_wf_id,val_wf_planned_start_date,'' actual_wf_start_datetime,'' actual_wf_end_datetime,'Workflow not yet initiated.' wf_stage_description
            from tbl_val_schedules t1, equipments t2
            where t1.equip_id=t2.equipment_id and t1.unit_id=%d and val_wf_id not in (select val_wf_id from tbl_val_wf_tracking_details) and val_wf_planned_start_date BETWEEN %? AND %? 



" ,intval($_GET['unitid']),date("Y-m-d",strtotime($_GET['actual_start_from'])),date("Y-m-d",strtotime($_GET['actual_start_to'])),date("Y-m-d",strtotime($_GET['planned_start_from'])),date("Y-m-d",strtotime($_GET['planned_start_to'])),intval($_GET['unitid']),date("Y-m-d",strtotime($_GET['planned_start_from'])),date("Y-m-d",strtotime($_GET['planned_start_to'])));
            }
            else {
                $results = DB::query("select t1.val_wf_current_stage,t1.unit_id,t1.equipment_id,t4.equipment_code,t1.val_wf_id, val_wf_planned_start_date,t1.actual_wf_start_datetime,t1.actual_wf_end_datetime, wf_stage_description
from tbl_val_wf_tracking_details t1, tbl_val_schedules t2, workflow_stages t3, equipments t4
where t1.val_wf_id=t2.val_wf_id and t1.val_wf_current_stage=t3.wf_stage and t1.equipment_id=t4.equipment_id
and t3.wf_type='Validation' and t1.unit_id=%d and t1.val_wf_current_stage=%s and (date(t1.actual_wf_start_datetime) BETWEEN %? AND %?) and (val_wf_planned_start_date BETWEEN %? AND %?) " ,intval($_GET['unitid']),$_GET['wfstageid'],date("Y-m-d",strtotime($_GET['actual_start_from'])),date("Y-m-d",strtotime($_GET['actual_start_to'])),date("Y-m-d",strtotime($_GET['planned_start_from'])),date("Y-m-d",strtotime($_GET['planned_start_to'])));
            }
            
        }
        else if(empty($_GET['planned_start_from']) && empty($_GET['actual_start_from']))
        {
            if($_GET['wfstageid']=='0'){
                $results = DB::query("select t1.val_wf_current_stage,t1.unit_id,t1.equipment_id,t4.equipment_code,t1.val_wf_id, val_wf_planned_start_date,t1.actual_wf_start_datetime,t1.actual_wf_end_datetime, wf_stage_description
from tbl_val_wf_tracking_details t1, tbl_val_schedules t2, workflow_stages t3, equipments t4
where t1.val_wf_id=t2.val_wf_id and t1.val_wf_current_stage=t3.wf_stage and t1.equipment_id=t4.equipment_id
and t3.wf_type='Validation' and t1.unit_id=%d 
UNION
select 0 val_wf_current_stage, t1.unit_id, t1.equip_id,t2.equipment_code, t1.val_wf_id,val_wf_planned_start_date,'' actual_wf_start_datetime,'' actual_wf_end_datetime,'Workflow not yet initiated.' wf_stage_description
from tbl_val_schedules t1, equipments t2
where t1.equip_id=t2.equipment_id and t1.unit_id=%d and val_wf_id not in (select val_wf_id from tbl_val_wf_tracking_details)" ,intval($_GET['unitid']),intval($_GET['unitid']));
                
            }
            else {
                $results = DB::query("select t1.val_wf_current_stage,t1.unit_id,t1.equipment_id,t4.equipment_code,t1.val_wf_id, val_wf_planned_start_date,t1.actual_wf_start_datetime,t1.actual_wf_end_datetime, wf_stage_description
from tbl_val_wf_tracking_details t1, tbl_val_schedules t2, workflow_stages t3, equipments t4
where t1.val_wf_id=t2.val_wf_id and t1.val_wf_current_stage=t3.wf_stage and t1.equipment_id=t4.equipment_id
and t3.wf_type='Validation' and t1.unit_id=%d and t1.val_wf_current_stage=%s" ,intval($_GET['unitid']),$_GET['wfstageid']);
                
            }
            
           
            
            
        }
    }
    
    
       
}


echo "<table id='datagrid-report' class='table table-bordered'>
<thead>
<tr>
<th> # </th>
<th> Equipment code</th>
<th> Validation workflow ID </th>
<th> WF stage</th>
<th> Action</th>
</tr>
</thead><tbody>";

if(empty($results))
{
    //echo "<tr><td colspan='8'>Nothing is display.</td></tr>";
}
else
{
    $count=1;
    foreach ($results as $row) {
        
        echo "<tr>";
        echo "<td>".$count."</td>";
//        echo "<td>".$row["unit_id"]." </td>";
        echo "<td>".$row["equipment_code"]." </td>";
        
        echo "<td>".$row["val_wf_id"]." </td>";
        
  //      echo "<td>".$row["val_wf_planned_start_date"]." </td>";
  //      echo "<td>".$row["actual_wf_start_datetime"]." </td>";
 //       echo "<td>".$row["actual_wf_end_datetime"]." </td>";
        
        echo "<td>".$row["wf_stage_description"]." </td>";
       
        if($_GET['wfstageid']!='6')
        {
            if($row['val_wf_current_stage']==99){
                echo "<td>&nbsp;</td>";
            }
            else
            {
                echo "<td><a href='#' data-toggle='modal' data-target='#viewProtocolModal' data-load-url='viewtestdetails_modal.php?equipment_id=".$row["equipment_id"]."&val_wf_id=".$row["val_wf_id"]."' class='btn btn-sm btn-gradient-info btn-icon-text' role='button' aria-pressed='true'>View Tests</a>
<a href='#' data-toggle='modal' data-target='#viewProtocolModal' data-load-url='viewprotocol_modal.php?equipment_id=".$row["equipment_id"]."&val_wf_id=".$row["val_wf_id"]."' class='btn btn-sm btn-gradient-info btn-icon-text' role='button' aria-pressed='true'>View Report</a> ";
                
            }
        
        }
        else {
            echo "<td>&nbsp;</td>";
        }
        
        if ($_SESSION['is_admin']=="Yes" || $_SESSION['is_super_admin']=="Yes" ||   $_SESSION['is_qa_head']=="Yes" || $_SESSION['is_unit_head']=="Yes" ||$_SESSION['is_dept_head']=="Yes" ||$_SESSION['department_id']==1)
        {
            if ($row["val_wf_current_stage"] == '5') {
                $pdfTitle = 'Protocol Report';
                echo "<br/><br/><a href='core/pdf/view_pdf_with_footer.php?pdf_path=uploads/protocol-report-".$row["val_wf_id"].".pdf' data-toggle='modal' data-target='#imagepdfviewerModal' data-title='$pdfTitle' class='btn btn-gradient-dark btn-small btn-rounded' role='button'>Download Report</a> </td>";
            } else {
                echo "&nbsp;</td>";
            }
        }
        echo "</tr>";
       $count=$count+1;   
    }
 
    
    
    
    
}

echo " </tbody>
                    </table>";

