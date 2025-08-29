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



$query=' select t1.unit_id,equipment_code,routine_test_sch_id,routine_test_wf_id,DATE_FORMAT(routine_test_wf_planned_start_date, "%d.%m.%Y") planned_date, case 
when routine_test_wf_id in (select routine_test_wf_id from tbl_routine_test_wf_tracking_details) then "Routine Test Started"
else "Routine Test Not Started"
end routine_test_status, routine_test_wf_status,user_name,test_name
 from tbl_routine_test_schedules t1 
 left join equipments t2 on t1.equip_id=t2.equipment_id  
 left join users t3 on t1.requested_by_user_id=t3.user_id
 left join tests t4 on t1.test_id=t4.test_id
 where t1.unit_id='.intval($_GET['unitid'])." and is_adhoc='Y'";

// Ad-hoc routine tests are not filtered by year as they are immediate requests
// Year filter removed to show all ad-hoc tests regardless of planned year

$results=DB::Query($query);

echo "<div class='table-responsive'><table id='datagrid-report' class='table table-sm'>";
echo "<thead>
<tr>
<th> # </th>

<th> Equipment ID </th>
<th> Test Name </th>
<th>Workflow ID </th>
<th> Planned Date</th>    
    
<th> Routine Test Initiated?</th>
<th> Routine Test Status </th>
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
    echo "<td>".$row['test_name']."</td>";
    echo "<td>".$row['routine_test_wf_id']."</td>";
    echo "<td>".$row['planned_date']."</td>";
    echo "<td>".$row['routine_test_status']."</td>";
    echo "<td>".$row['routine_test_wf_status']."</td>";
    echo "<td>".$row['user_name']."</td>";
    echo "<td>";
    if($row['routine_test_status']=='Routine Test Started')
    {
        echo "<a class='btn btn-warning btn-small' role='button' aria-pressed='true' disabled>No Action Allowed</a>";

    }
    else
    {
        if($row['routine_test_wf_status']=='Active')
        {
        echo "<button name='btnadhocinactive' class='btn btn-danger btn-small' data-wf-id='".$row['routine_test_wf_id']."' role='button' aria-pressed='true'>Mark Inactive</button>";
        }
        else{
        echo "<button name='btnadhocactive' class='btn btn-success btn-small' data-wf-id='".$row['routine_test_wf_id']."' role='button' aria-pressed='true'>Mark Active</button>";
        }
    }
    
   
    
    echo " </td>";
   
  
    
    echo "</tr>";
    
}

echo "</table></div>";
}


?>