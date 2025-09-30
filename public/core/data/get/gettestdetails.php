<?php

session_start();



// Load configuration first
require_once(__DIR__ . '/../../config/config.php');
// Validate session timeout
require_once(__DIR__ . '/../../security/session_timeout_middleware.php');
validateActiveSession();
require_once __DIR__ . '/../../config/db.class.php';

date_default_timezone_set("Asia/Kolkata");



$query="select test_id, test_name, test_purpose, test_performed_by,test_status 
from tests";

if($_GET['test_performed_by']!='Select')
{
    
    $query=$query." where test_performed_by='".(($_GET['test_performed_by']=="0")?"Internal":"External")."'";
}
if ($_GET['test_status']!='Select') {
    if(strpos($query, "where") !== false)
    {
        $query=$query." and test_status='".(($_GET['test_status']=="0")?"Active":"Inactive")."'";
    }
    else 
    {
        $query=$query." where test_status='".(($_GET['test_status']=="0")?"Active":"Inactive")."'";
    }
}


$test_details= DB::query($query);



echo "<table id='tbl-test-details' class='table table-bordered'>
                      <thead>
                        <tr>
                          <th> # </th>
                          
                          <th> Test name</th>

                          <th> Test performed by</th>
                            <th> Test status</th>
                          <th> Action</th>
                        </tr>
                      </thead>
                      <tbody>
                    ";


if(empty($test_details))
{
    echo "<tr><td colspan='5'>Nothing found.</td></tr>";
}
else 
{
    $count=1;
    foreach ($test_details as $row) {
        echo "<tr>";
        echo "<td>".$count."</td>";
        echo "<td>".$row['test_name']."</td>";

        echo "<td>".$row['test_performed_by']."</td>";
        echo "<td>".$row['test_status']."</td>";
       
        // Build search parameters for back navigation
        $search_params = http_build_query([
            'test_performed_by' => $_GET['test_performed_by'] ?? '',
            'test_status' => $_GET['test_status'] ?? '',
            'from_search' => '1'
        ]);

        echo "<td><a href='managetestdetails.php?test_id=".$row["test_id"]."&m=r&" . $search_params . "' class='btn btn-sm btn-gradient-danger btn-icon-text' role='button' aria-pressed='true'>View</a>&nbsp;&nbsp;
<a href='managetestdetails.php?test_id=".$row["test_id"]."&m=m&" . $search_params . "' class='btn btn-sm btn-gradient-info btn-icon-text' role='button' aria-pressed='true'>Modify</a> </td>";
        echo "</tr>";
        $count++;
    }
    echo "  </tbody></table>";
}




