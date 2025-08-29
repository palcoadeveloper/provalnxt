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



$query="select routine_test_request_id,test_id,unit_name,equipment_code,DATE_FORMAT(test_planned_start_date, '%d.%m.%Y') planned_date,
CASE 
when test_frequency='Q' then 'Quarterly'
when test_frequency='H' then 'Half-Yearly'
when test_frequency='Y' then 'Yearly'
when test_frequency='2Y' then 'Bi-yearly'
when test_frequency='ADHOC' then 'Ad-hoc'
end frequency,
case
when routine_test_status='1' then 'Active'
when routine_test_status='0' then 'Inactive'
end request_status,user_name
 from tbl_routine_tests_requests t1 left join equipments t2 on t1.equipment_id=t2.equipment_id  
 left join users t3 on t1.routine_test_requested_by=t3.user_id 
 left join units t4 on t1.unit_id=t4.unit_id
 where t1.unit_id=".intval($_GET['unitid']);

// Add frequency filter if provided
if(isset($_GET['frequencyFilter']) && !empty($_GET['frequencyFilter'])) {
    $query .= " AND t1.test_frequency='".$_GET['frequencyFilter']."'";
}



$results=DB::Query($query);

echo "<div class='table-responsive'><table id='datagrid-report' class='table table-sm'>";
echo "<thead>
<tr>
<th> # </th>
<th>Unit</th>
<th> Equipment Code</th>
<th> Test </th>
<th> Last Performed  Date</th>    
    
<th> Test Frequency</th>
<th> Request Status </th>
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
    echo "<td>".$row['unit_name']."</td>";
    echo "<td>".$row['equipment_code']."</td>";
   echo "<td>".$row['test_id']."</td>";
   
    echo "<td>".$row['planned_date']."</td>";
    // Add visual badge for ad-hoc tests
    if($row['frequency'] == 'Ad-hoc') {
        echo "<td><span class='badge badge-warning'>".$row['frequency']."</span></td>";
    } else {
        echo "<td>".$row['frequency']."</td>";
    }
    echo "<td>".$row['request_status']."</td>";
    echo "<td>".$row['user_name']."</td>";
    echo "<td>";
   
        if($row['request_status']=='Active')
        {
        echo "<button  name='btnmarkinactive' class='btn btn-danger btn-small' data-request-id='".$row['routine_test_request_id']."' role='button' aria-pressed='true'>Mark Inactive</button>";
        }
        else{
            echo "<button name='btnmarkactive' class='btn btn-success btn-small' data-request-id='".$row['routine_test_request_id']."' role='button' aria-pressed='true'>Mark Active</button>";
        
        }
    
    
   
    
    echo " </td>";
   
  
    
    echo "</tr>";
    
}

echo "</table></div>";
}


?>