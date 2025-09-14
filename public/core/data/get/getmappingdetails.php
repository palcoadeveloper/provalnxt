<?php

session_start();



// Load configuration first
require_once(__DIR__ . '/../../config/config.php');
// Validate session timeout
require_once('../../security/session_timeout_middleware.php');
validateActiveSession();
require_once '../../config/db.class.php';

date_default_timezone_set("Asia/Kolkata");



$query="select t1.mapping_id,t1.equipment_id,equipment_code,t1.test_id,test_name,t2.test_performed_by,t1.test_type,t1.vendor_id,mapping_status 
from equipment_test_vendor_mapping t1, tests t2, equipments t3
where t1.test_id=t2.test_id and t1.equipment_id=t3.equipment_id and t1.equipment_id='".$_GET['equipment_id']."'
";




//echo $query;


$user_details= DB::query($query);



echo "<table id='tbl-mapping-details' class='table table-bordered'>
                      <thead>
                        <tr>
                          <th> # </th>
                          <th> Equipment code </th>
                          <th> Test </th>
                          <th> Test performed by</th>
                          <th> Test type</th>
                          <th> Action</th>
                        </tr>
                      </thead>
                      <tbody>
                    ";


if(empty($user_details))
{
    echo "<tr><td colspan='6'>Nothing found.</td></tr>";
}
else 
{
    $count=1;
    foreach ($user_details as $row) {
        echo "<tr>";
        echo "<td>".$count."</td>";
        echo "<td>".$row['equipment_code']."</td>";
        echo "<td>".$row['test_name']."</td>";
        echo "<td>".$row['test_performed_by']."</td>";
        echo "<td>".$row['test_type']."</td>";

        echo "<td><a href='managemappingdetails.php?mapping_id=".$row["mapping_id"]."&unit_id=".$_GET['unitid']."&equip_id=".$row["equipment_id"]."&test_id=".$row['test_id']."&m=r' class='btn btn-sm btn-gradient-danger btn-icon-text' role='button' aria-pressed='true'>View</a>&nbsp;&nbsp;
<a href='managemappingdetails.php?mapping_id=".$row["mapping_id"]."&unit_id=".$_GET['unitid']."&equip_id=".$row["equipment_id"]."&test_id=".$row['test_id']."&m=m' class='btn btn-sm btn-gradient-info btn-icon-text' role='button' aria-pressed='true'>Modify</a> </td>";
        echo "</tr>";
        $count++;
    }
    echo "  </tbody></table>";
}




