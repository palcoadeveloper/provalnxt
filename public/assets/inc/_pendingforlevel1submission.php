<?php

// Load configuration first
require_once(__DIR__."/../../core/config/config.php");
include_once (__DIR__."/../../core/config/db.class.php");
date_default_timezone_set("Asia/Kolkata");
$query=" select val_wf_id,t.unit_id, equipment_code, equipment_category, actual_wf_start_datetime from tbl_val_wf_tracking_details t, equipments e where
 t.equipment_id=e.equipment_id
 and
 
 val_wf_id in (
 
 select distinct val_wf_id from tbl_test_schedules_tracking t where
 (select count(test_wf_id) from tbl_test_schedules_tracking where test_wf_current_stage='5' and val_wf_id=t.val_wf_id)=
 (select count(test_wf_id) from tbl_test_schedules_tracking where val_wf_id=t.val_wf_id)) and t.val_wf_current_stage='1' and t.unit_id=".$_SESSION['unit_id'];
$countcompletedtasks = DB::query($query);



echo "<div class='table-responsive'><table id='datagrid-level1submission' class='table table-sm table-bordered dataTable no-footer text-center'>
                      <thead>
                        <tr>
                          <th> # </th>
                          <th> Validation Workflow ID </th>
                          <th> Unit ID </th>
                          <th> Equipment Code</th>
                          <th> Equipment Category</th>
                          <th> Actual Start Date</th>
                          <th> Action</th>
                        </tr>
                      </thead>
                      <tbody>
                    ";
if(empty($countcompletedtasks))
{
   // echo "<tr><td colspan='7'>Nothing is pending</td></tr>";
}
else
{
    $count=1;
    foreach ($countcompletedtasks as $row) {
        
        echo "<tr>";
        echo "<td>".$count."</td>";
        echo "<td>".$row["val_wf_id"]." </td>";
        echo "<td>".$row["unit_id"]." </td>";
        echo "<td>".$row["equipment_code"]." </td>";
        echo "<td>".$row["equipment_category"]." </td>";
        echo "<td>".date("d.m.Y",strtotime($row["actual_wf_start_datetime"]))." </td>";
        
        echo "<td><a href='pendingforlevel1submission.php?approval_stage=0&val_wf_id=".$row["val_wf_id"]."' class='btn btn-primary btn-small' role='button' aria-pressed='true'>Manage</a> </td>";
        echo "</tr>";
        $count=$count+1;
    }
    
    
    
    
    
}

echo "  </tbody>
                    </table></div>";
