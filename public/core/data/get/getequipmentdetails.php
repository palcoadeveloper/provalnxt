<?php

session_start();



// Load configuration first
require_once(__DIR__ . '/../../config/config.php');
// Validate session timeout
require_once(__DIR__ . '/../../security/session_timeout_middleware.php');
validateActiveSession();
require_once __DIR__ . '/../../config/db.class.php';

date_default_timezone_set("Asia/Kolkata");



$query="select equipment_id,equipment_code,t1.unit_id,t2.department_name,equipment_category 
from equipments t1, departments t2
where t1.department_id=t2.department_id and unit_id='".$_GET['unitid']."'
";


if ($_GET['dept_id']!='Select')
{
    $query=$query." and t2.department_id ='".$_GET['dept_id']."'";
}
if ($_GET['equipment_type']!='Select')

{
    if ($_GET['equipment_type']=='0')
    {
        $query=$query." and equipment_category ='AHU'";
        
    }
    else 
    {
        $query=$query." and equipment_category ='VU'";
    }
    
    
}
if(!empty($_GET['equipment_id']))
{
    $query=$query." and equipment_id = ".$_GET['equipment_id'];
}




$user_details= DB::query($query);



echo "<table id='tbl-equip-details' class='table table-bordered'>
                      <thead>
                        <tr>
                          <th> # </th>
                          <th> Equipment Code </th>
                          <th> Category </th>
                          <th> Unit</th>
                          <th> Department</th>
                          <th> Action</th>
                        </tr>
                      </thead>
                      <tbody>
                    ";


if(empty($user_details))
{
    echo "<tr><td colspan='5'>Nothing found.</td></tr>";
}
else 
{
    $count=1;
    foreach ($user_details as $row) {
        echo "<tr>";
        echo "<td>".$count."</td>";
        echo "<td>".$row['equipment_code']."</td>";
        echo "<td>".$row['equipment_category']."</td>";
        echo "<td>".$row['unit_id']."</td>";
        echo "<td>".$row['department_name']."</td>";

        echo "<td><a href='manageequipmentdetails.php?equip_id=".$row["equipment_id"]."&m=r' class='btn btn-sm btn-gradient-danger btn-icon-text' role='button' aria-pressed='true'>View</a>&nbsp;&nbsp;<a href='manageequipmentdetails.php?equip_id=".$row["equipment_id"]."&m=m' class='btn btn-sm btn-gradient-info btn-icon-text' role='button' aria-pressed='true'>Modify</a> </td>";
        echo "</tr>";
        $count++;
    }
    echo "  </tbody></table>";
}




