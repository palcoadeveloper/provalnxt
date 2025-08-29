<?php

// Load configuration first
require_once(__DIR__."/../../core/config/config.php");
// DB class already included by parent file
date_default_timezone_set("Asia/Kolkata");
echo "<table class='table table-bordered'>
                      <thead>
                        <tr>
                          <th> User Department </th>
                          <th> Select Approver </th>
                          
    
                        </tr>
                      </thead>
                      <tbody>
                    ";


$engg_team= DB::query("select user_id,user_name from users where unit_id=".$_SESSION['unit_id']." and department_id=1 and user_status='Active'");
 
echo "<tr>";

echo "<td>Engineering Team </td>";
echo "<td><select class='form-control' name='engg_team'>";
echo "<option value='0'>Approval Not Required</option>";
foreach ($engg_team as $row) {
    
    
    
    
    echo "<option value='".$row["user_id"]."'>".$row["user_name"]."</option>";
    
    
    
    
}
echo "</select></td>";

echo "</tr>";


$hse_team= DB::query("select user_id,user_name from users where unit_id=".$_SESSION['unit_id']." and department_id=7 and user_status='Active'");

echo "<tr>";

echo "<td>HSE Team </td>";
echo "<td><select class='form-control' name='hse_team'>";
echo "<option value='0'>Approval Not Required</option>";
foreach ($hse_team as $row) {
    
  
    
   
      echo "<option value='".$row["user_id"]."'>".$row["user_name"]."</option>";
 
          
   
    
}
echo "</select></td>";

echo "</tr>";



$qc_team= DB::query("select user_id,user_name from users where unit_id=".$_SESSION['unit_id']." and (department_id=0 or department_id=6) and user_status='Active'");

echo "<tr>";

echo "<td>QC Team </td>";
echo "<td><select class='form-control' name='qc_team'>";
echo "<option value='0'>Approval Not Required</option>";
foreach ($qc_team as $row) {
    echo "<option value='".$row["user_id"]."'>".$row["user_name"]."</option>";
}
echo "</select></td>";

echo "</tr>";



$qa_team= DB::query("select user_id,user_name from users where unit_id=".$_SESSION['unit_id']." and department_id=8 and user_status='Active'");

echo "<tr>";

echo "<td>QA Team </td>";
echo "<td><select class='form-control' name='qa_team'>";
echo "<option value='0'>Approval Not Required</option>";
foreach ($qa_team as $row) {   
    echo "<option value='".$row["user_id"]."'>".$row["user_name"]."</option>";
}
echo "</select></td>";

echo "</tr>";

$equipment_id=DB::queryFirstField("select equipment_id from tbl_val_wf_tracking_details where val_wf_id=%s",$_GET['val_wf_id']);
$department_id=DB::queryFirstField("select department_id from equipments where equipment_id=%i",$equipment_id);

if($department_id==0 || $department_id==1 || $department_id==6 || $department_id==7 || $department_id==8)
{
  echo "<tr>";
  
  echo "<td>User Team </td>";
  echo "<td><select class='form-control' name='user_team'>";
  echo "<option value='0'>Approval Not Required</option>";
  echo "</select></td>";
  echo "</tr>";

}
else{
  $user_team= DB::query("select user_id,user_name from users where department_id=%i and unit_id=".$_SESSION['unit_id']." and user_status='Active'",$department_id);

  echo "<tr>";
  
  echo "<td>User Team </td>";
  echo "<td><select class='form-control' name='user_team'>";
  echo "<option value='0'>Approval Not Required</option>";
  foreach ($user_team as $row) {

      echo "<option value='".$row["user_id"]."'>".$row["user_name"]."</option>";
  }
  echo "</select></td>";
  
  echo "</tr>";
}














echo "</tbody></table>";