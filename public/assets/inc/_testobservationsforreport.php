<?php

// Load configuration first
require_once(__DIR__."/../../core/config/config.php");
// DB class already included by parent file
date_default_timezone_set("Asia/Kolkata");

$query="select t1.test_id,test_name from tbl_test_schedules_tracking t1, tests t2
where t1.test_id=t2.test_id
and val_wf_id='".$_GET['val_wf_id']."'";

$testdetails= DB::query($query);

echo "<div class='table-responsive'><table class='table table-bordered'>
                      <thead>
                        <tr>
                          <th> Test ID </th>
                          <th> Test Name </th>
                          <th> Observation </th>
                        
                        </tr>
                      </thead>
                      <tbody>
                    ";


foreach ($testdetails as $row) {
    
    echo "<tr>";
    
    echo "<td>".$row["test_id"]." </td>";
    echo "<td>".$row["test_name"]." </td>";
    echo "<td><select class='form-control' name='testid-".$row["test_id"]."'>
      <option>Pass</option>
      
    </select></td>";
   
    echo "</tr>";
   
}

echo "  </tbody>
                    </table></div>";