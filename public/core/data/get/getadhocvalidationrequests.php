<?php

session_start();



// Load configuration first
require_once(__DIR__ . '/../../config/config.php');
// Validate session timeout
require_once('../../security/session_timeout_middleware.php');
validateActiveSession();
require_once '../../config/db.class.php';

date_default_timezone_set("Asia/Kolkata");

//Show All PHP Errors
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);



$query=' select t1.unit_id,equipment_code,val_sch_id,val_wf_id,DATE_FORMAT(val_wf_planned_start_date, "%d.%m.%Y") planned_date, case 
when val_wf_id in (select val_wf_id from tbl_val_wf_tracking_details) then "Validation Started"
else "Validation Not Started"
end validation_status, val_wf_status,user_name
 from tbl_val_schedules t1 left join equipments t2 on t1.equip_id=t2.equipment_id  left join users t3 on t1.requested_by_user_id=t3.user_id where t1.unit_id='.intval($_GET['unitid'])." and is_adhoc='Y'";

if($_GET['valyear']!="")
{
    $query=$query." AND year(val_wf_planned_start_date)='".$_GET['valyear']."'";
}

$results=DB::Query($query);

echo "<div class='table-responsive'><table id='datagrid-report' class='table table-sm'>";
echo "<thead>
<tr>
<th> # </th>

<th> Equipment ID </th>
<th>Workflow ID </th>
<th> Planned Date</th>    
    
<th> Validation Initiated?</th>
<th> Validation Status </th>
<th> Requested By </th>
<th> Manage </th>    
    
</tr>
</thead>";

if(empty($results))
{
    //echo "<tr><td colspan='5'>No records</td></tr>";
}
else
{
    
$count=0;
foreach ($results as $row) {
    
    $count++;
    
    echo "<tr>";
    
    echo "<td>".$count."</td>";
   
    echo "<td>".$row['equipment_code']."</td>";
    echo "<td>".$row['val_wf_id']."</td>";
    echo "<td>".$row['planned_date']."</td>";
    echo "<td>".$row['validation_status']."</td>";
    echo "<td>".$row['val_wf_status']."</td>";
    echo "<td>".$row['user_name']."</td>";
    echo "<td>";
    if($row['validation_status']=='Validation Started')
    {
        echo "<a class='btn btn-warning btn-small' role='button' aria-pressed='true' disabled>No Action Allowed</a>";

    }
    else
    {
        if($row['val_wf_status']=='Active')
        {
        echo "<button  name='btnmarkinactive' class='btn btn-danger btn-small' data-wf-id='".$row['val_wf_id']."' role='button' aria-pressed='true'>Mark Inactive</button>";
        }
        else{
            echo "<button name='btnmarkactive' class='btn btn-success btn-small' data-wf-id='".$row['val_wf_id']."' role='button' aria-pressed='true'>Mark Active</button>";
        
        }
    }
    
   
    
    echo " </td>";
   
  
    
    echo "</tr>";
    
}

echo "</table></div>";
}


?>