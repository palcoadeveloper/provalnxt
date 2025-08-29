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
        
        
       
        $results = DB::query("select 0 routine_test_wf_current_stage, t1.unit_id, t1.equip_id as equipment_id,t2.equipment_code, t1.routine_test_wf_id,routine_test_wf_planned_start_date,'' actual_wf_start_datetime,'' actual_wf_end_datetime,'Workflow not yet initiated.' wf_stage_description
        from tbl_routine_test_schedules t1, equipments t2
        where t1.equip_id=t2.equipment_id and t1.unit_id=%d and routine_test_wf_id not in (select routine_test_wf_id from tbl_routine_test_wf_tracking_details) and t1.equip_id=%d",intval($_GET['unitid']),intval($_GET['equipmentid']));
        
        
    }
    
    if($_GET['wfstageid']<>'6'){
        
        if(!empty(date("Y-m-d",strtotime($_GET['planned_start_from']))) && empty(date("Y-m-d",strtotime($_GET['actual_start_from']))))
        {
            if($_GET['wfstageid']=='0'){
                $results = DB::query("select t1.routine_test_wf_current_stage,t1.unit_id,t1.equipment_id,t4.equipment_code,t1.routine_test_wf_id, routine_test_wf_planned_start_date,t1.actual_wf_start_datetime,t1.actual_wf_end_datetime, wf_stage_description
from tbl_routine_test_wf_tracking_details t1, tbl_routine_test_schedules t2, workflow_stages t3, equipments t4
where t1.routine_test_wf_id=t2.routine_test_wf_id and t1.routine_test_wf_current_stage=t3.wf_stage and t1.equipment_id=t4.equipment_id
and t3.wf_type='RT' and t1.unit_id=%d and routine_test_wf_planned_start_date BETWEEN %? AND %? and t1.equipment_id=%d" ,intval($_GET['unitid']),date("Y-m-d",strtotime($_GET['planned_start_from'])),date("Y-m-d",strtotime($_GET['planned_start_to'])),intval($_GET['equipmentid']));
            }
            else {
                $results = DB::query("select t1.routine_test_wf_current_stage,t1.unit_id,t1.equipment_id,t4.equipment_code,t1.routine_test_wf_id, routine_test_wf_planned_start_date,t1.actual_wf_start_datetime,t1.actual_wf_end_datetime, wf_stage_description
from tbl_routine_test_wf_tracking_details t1, tbl_routine_test_schedules t2, workflow_stages t3, equipments t4
where t1.routine_test_wf_id=t2.routine_test_wf_id and t1.routine_test_wf_current_stage=t3.wf_stage and t1.equipment_id=t4.equipment_id
and t3.wf_type='RT' and t1.unit_id=%d and t1.routine_test_wf_current_stage=%s and routine_test_wf_planned_start_date BETWEEN %? AND %? and t1.equipment_id=%d" ,intval($_GET['unitid']),$_GET['wfstageid'],date("Y-m-d",strtotime($_GET['planned_start_from'])),date("Y-m-d",strtotime($_GET['planned_start_to'])),intval($_GET['equipmentid']));
            }
            
        }
        else if(empty(date("Y-m-d",strtotime($_GET['planned_start_from']))) && !empty(date("Y-m-d",strtotime($_GET['actual_start_from']))))
        {
            if($_GET['wfstageid']=='0'){
                $results = DB::query("select t1.routine_test_wf_current_stage,t1.unit_id,t1.equipment_id,t4.equipment_code,t1.routine_test_wf_id, routine_test_wf_planned_start_date,t1.actual_wf_start_datetime,t1.actual_wf_end_datetime, wf_stage_description
from tbl_routine_test_wf_tracking_details t1, tbl_routine_test_schedules t2, workflow_stages t3, equipments t4
where t1.routine_test_wf_id=t2.routine_test_wf_id and t1.routine_test_wf_current_stage=t3.wf_stage and t1.equipment_id=t4.equipment_id
and t3.wf_type='RT' and t1.unit_id=%d and date(t1.actual_wf_start_datetime) BETWEEN %? AND %? and t1.equipment_id=%d" ,intval($_GET['unitid']),date("Y-m-d",strtotime($_GET['actual_start_from'])),date("Y-m-d",strtotime($_GET['actual_start_to'])),intval($_GET['equipmentid']));
            }
            else {
                $results = DB::query("select t1.routine_test_wf_current_stage,t1.unit_id,t1.equipment_id,t4.equipment_code,t1.routine_test_wf_id, routine_test_wf_planned_start_date,t1.actual_wf_start_datetime,t1.actual_wf_end_datetime, wf_stage_description
from tbl_routine_test_wf_tracking_details t1, tbl_routine_test_schedules t2, workflow_stages t3, equipments t4
where t1.routine_test_wf_id=t2.routine_test_wf_id and t1.routine_test_wf_current_stage=t3.wf_stage and t1.equipment_id=t4.equipment_id
and t3.wf_type='RT' and t1.unit_id=%d and t1.routine_test_wf_current_stage=%s and date(t1.actual_wf_start_datetime) BETWEEN %? AND %? and t1.equipment_id=%d" ,intval($_GET['unitid']),$_GET['wfstageid'],date("Y-m-d",strtotime($_GET['actual_start_from'])),date("Y-m-d",strtotime($_GET['actual_start_to'])),intval($_GET['equipmentid']));
            }
            
        }
        else if(!empty(date("Y-m-d",strtotime($_GET['planned_start_from']))) && !empty(date("Y-m-d",strtotime($_GET['actual_start_from']))))
        {
            if($_GET['wfstageid']=='0'){
                $results = DB::query("select t1.routine_test_wf_current_stage,t1.unit_id,t1.equipment_id,t4.equipment_code,t1.routine_test_wf_id, routine_test_wf_planned_start_date,t1.actual_wf_start_datetime,t1.actual_wf_end_datetime, wf_stage_description
from tbl_routine_test_wf_tracking_details t1, tbl_routine_test_schedules t2, workflow_stages t3, equipments t4
where t1.routine_test_wf_id=t2.routine_test_wf_id and t1.routine_test_wf_current_stage=t3.wf_stage and t1.equipment_id=t4.equipment_id
and t3.wf_type='RT' and t1.unit_id=%d and (date(t1.actual_wf_start_datetime) BETWEEN %? AND %?) and (routine_test_wf_planned_start_date BETWEEN %? AND %?) and t1.equipment_id=%d" ,intval($_GET['unitid']),date("Y-m-d",strtotime($_GET['actual_start_from'])),date("Y-m-d",strtotime($_GET['actual_start_to'])),date("Y-m-d",strtotime($_GET['planned_start_from'])),date("Y-m-d",strtotime($_GET['planned_start_to'])),intval($_GET['equipmentid']));
            }
            else {
                $results = DB::query("select t1.routine_test_wf_current_stage,t1.unit_id,t1.equipment_id,t4.equipment_code,t1.routine_test_wf_id, routine_test_wf_planned_start_date,t1.actual_wf_start_datetime,t1.actual_wf_end_datetime, wf_stage_description
from tbl_routine_test_wf_tracking_details t1, tbl_routine_test_schedules t2, workflow_stages t3, equipments t4
where t1.routine_test_wf_id=t2.routine_test_wf_id and t1.routine_test_wf_current_stage=t3.wf_stage and t1.equipment_id=t4.equipment_id
and t3.wf_type='RT' and t1.unit_id=%d and t1.routine_test_wf_current_stage=%s and (date(t1.actual_wf_start_datetime) BETWEEN %? AND %?) and (routine_test_wf_planned_start_date BETWEEN %? AND %?) and t1.equipment_id=%d" ,intval($_GET['unitid']),$_GET['wfstageid'],date("Y-m-d",strtotime($_GET['actual_start_from'])),date("Y-m-d",strtotime($_GET['actual_start_to'])),date("Y-m-d",strtotime($_GET['planned_start_from'])),date("Y-m-d",strtotime($_GET['planned_start_to'])),intval($_GET['equipmentid']));
            }
            
        }
        else if(empty(date("Y-m-d",strtotime($_GET['planned_start_from']))) && empty(date("Y-m-d",strtotime($_GET['actual_start_from']))))
        {
            if($_GET['wfstageid']=='0'){
                
                $results = DB::query("select t1.routine_test_wf_current_stage,t1.unit_id,t1.equipment_id,t4.equipment_code,t1.routine_test_wf_id, routine_test_wf_planned_start_date,t1.actual_wf_start_datetime,t1.actual_wf_end_datetime, wf_stage_description
from tbl_routine_test_wf_tracking_details t1, tbl_routine_test_schedules t2, workflow_stages t3, equipments t4
where t1.routine_test_wf_id=t2.routine_test_wf_id and t1.routine_test_wf_current_stage=t3.wf_stage and t1.equipment_id=t4.equipment_id
and t3.wf_type='RT' and t1.unit_id=%d and t1.equipment_id=%d" ,intval($_GET['unitid']),intval($_GET['equipmentid']));
            }
            else {
                $results = DB::query("select t1.routine_test_wf_current_stage,t1.unit_id,t1.equipment_id,t4.equipment_code,t1.routine_test_wf_id, routine_test_wf_planned_start_date,t1.actual_wf_start_datetime,t1.actual_wf_end_datetime, wf_stage_description
from tbl_routine_test_wf_tracking_details t1, tbl_routine_test_schedules t2, workflow_stages t3, equipments t4
where t1.routine_test_wf_id=t2.routine_test_wf_id and t1.routine_test_wf_current_stage=t3.wf_stage and t1.equipment_id=t4.equipment_id
and t3.wf_type='RT' and t1.unit_id=%d and t1.routine_test_wf_current_stage=%s and t1.equipment_id=%d" ,intval($_GET['unitid']),$_GET['wfstageid'],intval($_GET['equipmentid']));
            }
            
            
        }
    }
    
    
   
    
    
    
    
    
}
else 
{
    
    if($_GET['wfstageid']=='6'){
        $results = DB::query("select 0 routine_test_wf_current_stage, t1.unit_id, t1.equip_id as equipment_id,t2.equipment_code, t1.routine_test_wf_id,routine_test_wf_planned_start_date,'' actual_wf_start_datetime,'' actual_wf_end_datetime,'Workflow not yet initiated.' wf_stage_description
        from tbl_routine_test_schedules t1, equipments t2
        where t1.equip_id=t2.equipment_id and t1.unit_id=%d and routine_test_wf_id not in (select routine_test_wf_id from tbl_routine_test_wf_tracking_details)",intval($_GET['unitid']));
        
    }
    
    if($_GET['wfstageid']<>'6'){
        
        if(!empty(date("Y-m-d",strtotime($_GET['planned_start_from']))) && empty(date("Y-m-d",strtotime($_GET['actual_start_from']))))
        {
            
            
            if($_GET['wfstageid']=='0'){
                $results = DB::query("select t1.routine_test_wf_current_stage,t1.unit_id,t1.equipment_id,t4.equipment_code,t1.routine_test_wf_id, routine_test_wf_planned_start_date,t1.actual_wf_start_datetime,t1.actual_wf_end_datetime, wf_stage_description
from tbl_routine_test_wf_tracking_details t1, tbl_routine_test_schedules t2, workflow_stages t3, equipments t4
where t1.routine_test_wf_id=t2.routine_test_wf_id and t1.routine_test_wf_current_stage=t3.wf_stage and t1.equipment_id=t4.equipment_id
and t3.wf_type='RT' and t1.unit_id=%d and routine_test_wf_planned_start_date BETWEEN %? AND %?" ,intval($_GET['unitid']),date("Y-m-d",strtotime($_GET['planned_start_from'])),date("Y-m-d",strtotime($_GET['planned_start_to'])));
            }
            else {
                $results = DB::query("select t1.routine_test_wf_current_stage,t1.unit_id,t1.equipment_id,t4.equipment_code,t1.routine_test_wf_id, routine_test_wf_planned_start_date,t1.actual_wf_start_datetime,t1.actual_wf_end_datetime, wf_stage_description
from tbl_routine_test_wf_tracking_details t1, tbl_routine_test_schedules t2, workflow_stages t3, equipments t4
where t1.routine_test_wf_id=t2.routine_test_wf_id and t1.routine_test_wf_current_stage=t3.wf_stage and t1.equipment_id=t4.equipment_id
and t3.wf_type='RT' and t1.unit_id=%d and t1.routine_test_wf_current_stage=%s and routine_test_wf_planned_start_date BETWEEN %? AND %?" ,intval($_GET['unitid']),$_GET['wfstageid'],date("Y-m-d",strtotime($_GET['planned_start_from'])),date("Y-m-d",strtotime($_GET['planned_start_to'])));
            }
            
            
        }
        else if(empty(date("Y-m-d",strtotime($_GET['planned_start_from']))) && !empty(date("Y-m-d",strtotime($_GET['actual_start_from']))))
        {
            if($_GET['wfstageid']=='0'){
                $results = DB::query("select t1.routine_test_wf_current_stage,t1.unit_id,t1.equipment_id,t4.equipment_code,t1.routine_test_wf_id, routine_test_wf_planned_start_date,t1.actual_wf_start_datetime,t1.actual_wf_end_datetime, wf_stage_description
from tbl_routine_test_wf_tracking_details t1, tbl_routine_test_schedules t2, workflow_stages t3, equipments t4
where t1.routine_test_wf_id=t2.routine_test_wf_id and t1.routine_test_wf_current_stage=t3.wf_stage and t1.equipment_id=t4.equipment_id
and t3.wf_type='RT' and t1.unit_id=%d and date(t1.actual_wf_start_datetime) BETWEEN %? AND %?" ,intval($_GET['unitid']),date("Y-m-d",strtotime($_GET['actual_start_from'])),date("Y-m-d",strtotime($_GET['actual_start_to'])));
            }
            else {
                $results = DB::query("select t1.routine_test_wf_current_stage,t1.unit_id,t1.equipment_id,t4.equipment_code,t1.routine_test_wf_id, routine_test_wf_planned_start_date,t1.actual_wf_start_datetime,t1.actual_wf_end_datetime, wf_stage_description
from tbl_routine_test_wf_tracking_details t1, tbl_routine_test_schedules t2, workflow_stages t3, equipments t4
where t1.routine_test_wf_id=t2.routine_test_wf_id and t1.routine_test_wf_current_stage=t3.wf_stage and t1.equipment_id=t4.equipment_id
and t3.wf_type='RT' and t1.unit_id=%d and t1.routine_test_wf_current_stage=%s and date(t1.actual_wf_start_datetime) BETWEEN %? AND %?" ,intval($_GET['unitid']),$_GET['wfstageid'],date("Y-m-d",strtotime($_GET['actual_start_from'])),date("Y-m-d",strtotime($_GET['actual_start_to'])));
            }
            
        }
        else if(!empty(date("Y-m-d",strtotime($_GET['planned_start_from']))) && !empty(date("Y-m-d",strtotime($_GET['actual_start_from']))))
        {
            if($_GET['wfstageid']=='0'){
                $results = DB::query("select t1.routine_test_wf_current_stage,t1.unit_id,t1.equipment_id,t4.equipment_code,t1.routine_test_wf_id, routine_test_wf_planned_start_date,t1.actual_wf_start_datetime,t1.actual_wf_end_datetime, wf_stage_description
from tbl_routine_test_wf_tracking_details t1, tbl_routine_test_schedules t2, workflow_stages t3, equipments t4
where t1.routine_test_wf_id=t2.routine_test_wf_id and t1.routine_test_wf_current_stage=t3.wf_stage and t1.equipment_id=t4.equipment_id
and t3.wf_type='RT' and t1.unit_id=%d and (date(t1.actual_wf_start_datetime) BETWEEN %? AND %?) and (routine_test_wf_planned_start_date BETWEEN %? AND %?) " ,intval($_GET['unitid']),date("Y-m-d",strtotime($_GET['actual_start_from'])),date("Y-m-d",strtotime($_GET['actual_start_to'])),date("Y-m-d",strtotime($_GET['planned_start_from'])),date("Y-m-d",strtotime($_GET['planned_start_to'])));
            }
            else {
                $results = DB::query("select t1.routine_test_wf_current_stage,t1.unit_id,t1.equipment_id,t4.equipment_code,t1.routine_test_wf_id, routine_test_wf_planned_start_date,t1.actual_wf_start_datetime,t1.actual_wf_end_datetime, wf_stage_description
from tbl_routine_test_wf_tracking_details t1, tbl_routine_test_schedules t2, workflow_stages t3, equipments t4
where t1.routine_test_wf_id=t2.routine_test_wf_id and t1.routine_test_wf_current_stage=t3.wf_stage and t1.equipment_id=t4.equipment_id
and t3.wf_type='RT' and t1.unit_id=%d and t1.routine_test_wf_current_stage=%s and (date(t1.actual_wf_start_datetime) BETWEEN %? AND %?) and (routine_test_wf_planned_start_date BETWEEN %? AND %?) " ,intval($_GET['unitid']),$_GET['wfstageid'],date("Y-m-d",strtotime($_GET['actual_start_from'])),date("Y-m-d",strtotime($_GET['actual_start_to'])),date("Y-m-d",strtotime($_GET['planned_start_from'])),date("Y-m-d",strtotime($_GET['planned_start_to'])));
            }
            
        }
        else if(empty(date("Y-m-d",strtotime($_GET['planned_start_from']))) && empty(date("Y-m-d",strtotime($_GET['actual_start_from']))))
        {
            if($_GET['wfstageid']=='0'){
                
                $results = DB::query("select t1.routine_test_wf_current_stage,t1.unit_id,t1.equipment_id,t4.equipment_code,t1.routine_test_wf_id, routine_test_wf_planned_start_date,t1.actual_wf_start_datetime,t1.actual_wf_end_datetime, wf_stage_description
from tbl_routine_test_wf_tracking_details t1, tbl_routine_test_schedules t2, workflow_stages t3, equipments t4
where t1.routine_test_wf_id=t2.routine_test_wf_id and t1.routine_test_wf_current_stage=t3.wf_stage and t1.equipment_id=t4.equipment_id
and t3.wf_type='RT' and t1.unit_id=%d " ,intval($_GET['unitid']));
                
            }
            else {
                $results = DB::query("select t1.routine_test_wf_current_stage,t1.unit_id,t1.equipment_id,t4.equipment_code,t1.routine_test_wf_id, routine_test_wf_planned_start_date,t1.actual_wf_start_datetime,t1.actual_wf_end_datetime, wf_stage_description
from tbl_routine_test_wf_tracking_details t1, tbl_routine_test_schedules t2, workflow_stages t3, equipments t4
where t1.routine_test_wf_id=t2.routine_test_wf_id and t1.routine_test_wf_current_stage=t3.wf_stage and t1.equipment_id=t4.equipment_id
and t3.wf_type='RT' and t1.unit_id=%d and t1.routine_test_wf_current_stage=%s" ,intval($_GET['unitid']),$_GET['wfstageid']);
                
            }
            
           
            
            
        }
    }
    
    
       
}


echo "<table id='datagrid-report' class='table table-bordered'>
<thead>
<tr>
<th> # </th>


<th> Equipment Code</th>
<th> Routine Test Workflow ID </th>


<th> WF Stage</th>
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
        
        echo "<td>".$row["routine_test_wf_id"]." </td>";
        
  //      echo "<td>".$row["val_wf_planned_start_date"]." </td>";
  //      echo "<td>".$row["actual_wf_start_datetime"]." </td>";
 //       echo "<td>".$row["actual_wf_end_datetime"]." </td>";
        
        echo "<td>".$row["wf_stage_description"]." </td>";
        
        echo "<td><a href='#' data-toggle='modal' data-target='#viewProtocolModal' data-load-url='viewtestdetails_modal.php?equipment_id=".$row["equipment_id"]."&val_wf_id=".$row["routine_test_wf_id"]."' class='btn btn-gradient-primary btn-sm btn-rounded' role='button' aria-pressed='true'>View Tests</a>";
       
        
        
        
        echo "</tr>";
       $count=$count+1;   
    }
 
    
    
    
    
}

echo " </tbody>
                    </table>";

