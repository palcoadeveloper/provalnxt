<?php 




// Load configuration first
require_once(__DIR__."/../../core/config/config.php");
include_once (__DIR__."/../../core/config/db.class.php");
date_default_timezone_set("Asia/Kolkata");
$query=" select val_wf_id,t.unit_id, equipment_code, equipment_category, actual_wf_start_datetime from tbl_val_wf_tracking_details t, equipments e where
 t.equipment_id=e.equipment_id and t.val_wf_current_stage='4' and
t.unit_id=".$_SESSION['unit_id'];


$countcompletedtasks = DB::query($query);




echo "<div class='table-responsive'><table id='datagrid-level3approval' class='table table-sm table-bordered dataTable no-footer text-center'>
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
  //  echo "<tr><td colspan='7'>Nothing is pending</td></tr>";
}
else
{
    $count=1;
    foreach ($countcompletedtasks as $row) {

       $latest_iteration_details=DB::queryFirstRow("SELECT val_wf_approval_trcking_id,iteration_id 
                            FROM tbl_val_wf_approval_tracking_details
                            WHERE val_wf_id = %s AND iteration_status='Active'
                            AND iteration_id = (
                                SELECT MAX(iteration_id)
                                FROM tbl_val_wf_approval_tracking_details
                                WHERE iteration_status='Active' and val_wf_id = %s
                            )
                            LIMIT 1;", $row['val_wf_id'],$row['val_wf_id']);
        $val_approval_trk_id=$latest_iteration_details['val_wf_approval_trcking_id'];
        $iteration_id=$latest_iteration_details['iteration_id'];
        
        echo "<tr>";
        echo "<td>".$count."</td>";
        echo "<td>".$row["val_wf_id"]." </td>";
        echo "<td>".$row["unit_id"]." </td>";
        echo "<td>".$row["equipment_code"]." </td>";
        echo "<td>".$row["equipment_category"]." </td>";
        echo "<td>".date("d.m.Y H:i:s", strtotime($row["actual_wf_start_datetime"]))." </td>";
        
        echo "<td><a href='pendingforlevel3approval.php?approval_stage=3&val_wf_tracking_id=".$val_approval_trk_id."&val_wf_id=".$row["val_wf_id"]."' class='btn btn-primary btn-small' role='button' aria-pressed='true'>Manage</a> </td>";
        echo "</tr>";
        $count=$count+1;
    }
    
    
    
    
    
}

echo "  </tbody>
                    </table></div>";
