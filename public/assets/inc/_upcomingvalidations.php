<?php
// Start the session
//session_start();


// Load configuration first
require_once(__DIR__."/../../core/config/config.php");
include_once (__DIR__."/../../core/config/db.class.php");
date_default_timezone_set("Asia/Kolkata");

//include $_SERVER['DOCUMENT_ROOT']."/public/core/config/db.class.php";
$query="select val_wf_id,t1.unit_id,equipment_code,t1.equip_id, equipment_category,val_wf_planned_start_date 
from tbl_val_schedules t1,equipments t2
where t1.equip_id=t2.equipment_id and val_wf_id not in (select val_wf_id from tbl_val_wf_tracking_details) and t1.val_wf_status='Active' 
and t1.unit_id= ".$_SESSION['unit_id']." and equipment_status='Active' order by val_wf_planned_start_date";
$results = DB::query($query);

echo "<div class='table-responsive'><table id='datagrid-upcoming' class='table table-sm table-bordered dataTable no-footer text-center'>
<thead>
<tr>
<th> # </th>
<th> Validation Workflow ID </th>
<th> Unit ID </th>
<th> Equipment Code</th>
<th> Equipment Category</th>
<th> Planned Start Date</th>

<th> Action</th>
</tr>
</thead><tbody>";

if(empty($results))
{
   // echo "<tr><td colspan='7'>Nothing is pending</td></tr>";
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
       
        echo "<td><a class='btn btn-primary' id='startvalidation' data-toggle='modal' data-whatever='".$row["val_wf_id"]."' data-planneddate='".$row['val_wf_planned_start_date']."' data-target='#startValidationModal' data-href='core/workflow/beginvalidation.php?u=".$row["unit_id"]."&e=".$row["equip_id"]."&d=".$row["val_wf_planned_start_date"]."&w=".$row["val_wf_id"]."&l=".$_SESSION['user_id']."' class='btn btn-primary btn-small' role='button' aria-pressed='true'>Start</a> </td>";
        //echo "<td><a class='btn btn-primary' href='core/workflow/beginvalidation.php?u=".$row["unit_id"]."&e=".$row["equip_id"]."&d=".$row["val_wf_planned_start_date"]."&w=".$row["val_wf_id"]."&l=".$_SESSION['user_id']."' class='btn btn-primary btn-small' role='button' aria-pressed='true'>Start</a> </td>";
        
        echo "</tr>";
       $count=$count+1;   
    }
 
    
    
    
    
}

echo " </tbody>
                    </table></div>";

