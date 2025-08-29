<?php

// Load configuration first
require_once(__DIR__."/../../core/config/config.php");
include_once (__DIR__."/../../core/config/db.class.php");
date_default_timezone_set("Asia/Kolkata");

$query="select val_wf_id,t1.unit_id,equipment_code,t1.equip_id, equipment_category,val_wf_planned_start_date 
from tbl_val_schedules t1,equipments t2
where t1.equip_id=t2.equipment_id and val_wf_id in (select val_wf_id from tbl_val_wf_tracking_details where val_wf_current_stage!=99) and t1.val_wf_status='Active' 
and t1.unit_id=".$_SESSION['unit_id']."

and val_wf_id NOT IN
(
select val_wf_id from tbl_val_wf_tracking_details t, equipments e where
 t.equipment_id=e.equipment_id
 and
 
 val_wf_id in (
 
 select distinct val_wf_id from tbl_test_schedules_tracking t where
 (select count(test_wf_id) from tbl_test_schedules_tracking where test_wf_current_stage='5' and val_wf_id=t.val_wf_id)=
 (select count(test_wf_id) from tbl_test_schedules_tracking where val_wf_id=t.val_wf_id)) and t.unit_id=".$_SESSION['unit_id'].")";
$results = DB::query($query);

echo "<div class='table-responsive'><table id='datagrid-inprogress' class='table table-sm table-bordered dataTable no-footer text-center'>
<thead>
<tr>
<th> # </th>
<th> Validation Workflow ID </th>
<th> Unit ID </th>
<th> Equipment Code</th>
<th> Equipment Category</th>
<th> Planned Start Date</th>
<th> Validation Report </th>

</tr>
</thead><tbody>";

if(empty($results))
{
    //echo "<tr><td colspan='7'>Nothing is pending</td></tr>";
}
else
{
    $count=1;
    foreach ($results as $row) {
        
        echo "<tr>";
        echo "<td>".$count."</td>";
        echo "<td>".$row["val_wf_id"]." </td>";
        echo "<td>".$row["unit_id"]." </td>";
        echo "<td>".$row["equipment_code"]." </td>";
        echo "<td>".$row["equipment_category"]." </td>";
        echo "<td>".date_format(date_create($row["val_wf_planned_start_date"]),"d.m.Y")." </td>";
        echo "<td><a href='#' data-toggle='modal' data-target='#viewProtocolModal' data-load-url='viewprotocol_modal.php?equipment_id=".$row["equip_id"]."&val_wf_id=".$row["val_wf_id"]."' class='btn btn-primary btn-small' role='button' aria-pressed='true'>View</a> </td>";
        echo "</tr>";
       $count=$count+1;   
    }
 
    
    
    
    
}

echo " </tbody>
                    </table></div>";