<?php

session_start();



// Load configuration first
require_once(__DIR__ . '/../../config/config.php');
// Validate session timeout
require_once('../../security/session_timeout_middleware.php');
validateActiveSession();
require_once '../../config/db.class.php';

date_default_timezone_set("Asia/Kolkata");



$query="select vendor_id, vendor_name, vendor_spoc_name,vendor_spoc_mobile, vendor_spoc_email 
from vendors where vendor_name like '%".$_GET['searchinput']."%'";




$user_details= DB::query($query);



echo "<table id='tbl-vendor-details' class='table table-bordered'>
                      <thead>
                        <tr>
                          <th> # </th>

                          <th> Vendor name</th>
                          <th> Vendor SPOC name</th>
                          <th> Vendor SPOC mobile</th>
                          <th> Vendor SPOC email</th>
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
        echo "<td>".$row['vendor_name']."</td>";

        echo "<td>".$row['vendor_spoc_name']."</td>";
        echo "<td>".$row['vendor_spoc_mobile']."</td>";
        echo "<td>".$row['vendor_spoc_email']."</td>";
        // Build search parameters for back navigation
        $search_params = http_build_query([
            'searchinput' => $_GET['searchinput'],
            'from_search' => '1'
        ]);

        echo "<td><a href='managevendordetails.php?vendor_id=".$row["vendor_id"]."&m=r&".$search_params."' class='btn btn-sm btn-gradient-info btn-icon-text' role='button' aria-pressed='true'>View</a>
&nbsp;&nbsp;<a href='managevendordetails.php?vendor_id=".$row["vendor_id"]."&m=m&".$search_params."' class='btn btn-sm btn-gradient-info btn-icon-text' role='button' aria-pressed='true'>Edit</a> </td>";
        echo "</tr>";
        $count++;
    }
    echo "  </tbody></table>";
}




