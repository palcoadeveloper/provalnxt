<?php

session_start();

// Load configuration first
require_once(__DIR__ . '/../../config/config.php');

// Validate session timeout
require_once(__DIR__ . '/../../security/session_timeout_middleware.php');
validateActiveSession();
require_once __DIR__ . '/../../config/db.class.php';

date_default_timezone_set("Asia/Kolkata");

//Show All PHP Errors
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);



// Build secure parameterized query
$base_query = "SELECT user_id, employee_id, user_type, vendor_id, user_name, user_email FROM users";
$user_details = [];

if ($_GET['usertype'] == 'IE') {
    // Employee search
    if (empty($_GET['searchinput'])) {
        // No search input - show all employees for unit
        if ($_GET['unitid'] != 'select') {
            $user_details = DB::query($base_query . " WHERE (unit_id = %i OR unit_id IS NULL) AND user_type = %s", 
                                     intval($_GET['unitid']), 'employee');
        } else {
            $user_details = DB::query($base_query . " WHERE user_type = %s", 'employee');
        }
    } else {
        // Search with input
        $search_input = '%' . $_GET['searchinput'] . '%';
        
        if ($_GET['searchcriteria'] == '0') {
            // Search by user name
            if ($_GET['unitid'] == 'select') {
                $user_details = DB::query($base_query . " WHERE user_name LIKE %s AND user_type = %s", 
                                         $search_input, 'employee');
            } else {
                $user_details = DB::query($base_query . " WHERE user_name LIKE %s AND unit_id = %i AND user_type = %s", 
                                         $search_input, intval($_GET['unitid']), 'employee');
            }
        } else {
            // Search by employee ID
            if ($_GET['unitid'] == 'select') {
                $user_details = DB::query($base_query . " WHERE employee_id LIKE %s AND user_type = %s", 
                                         $search_input, 'employee');
            } else {
                $user_details = DB::query($base_query . " WHERE employee_id LIKE %s AND unit_id = %i AND user_type = %s", 
                                         $search_input, intval($_GET['unitid']), 'employee');
            }
        }
    }
} else if ($_GET['usertype'] == 'VE') {
    // Vendor employee search
    if (empty($_GET['searchinput']) && $_GET['vendorid'] == 'select') {
        // No filters - show all vendor employees
        $user_details = DB::query($base_query . " WHERE user_type = %s", 'vendor');
    } else {
        // Search with filters
        $search_input = empty($_GET['searchinput']) ? '' : '%' . $_GET['searchinput'] . '%';
        
        if ($_GET['searchcriteria'] == '0') {
            // Search by user name
            if ($_GET['vendorid'] == 'select') {
                if (!empty($search_input)) {
                    $user_details = DB::query($base_query . " WHERE user_name LIKE %s AND user_type = %s", 
                                             $search_input, 'vendor');
                } else {
                    $user_details = DB::query($base_query . " WHERE user_type = %s", 'vendor');
                }
            } else {
                if (!empty($search_input)) {
                    $user_details = DB::query($base_query . " WHERE user_name LIKE %s AND vendor_id = %i AND user_type = %s", 
                                             $search_input, intval($_GET['vendorid']), 'vendor');
                } else {
                    $user_details = DB::query($base_query . " WHERE vendor_id = %i AND user_type = %s", 
                                             intval($_GET['vendorid']), 'vendor');
                }
            }
        } else {
            // Search by employee ID
            if ($_GET['vendorid'] == 'select') {
                if (!empty($search_input)) {
                    $user_details = DB::query($base_query . " WHERE employee_id LIKE %s AND user_type = %s", 
                                             $search_input, 'vendor');
                } else {
                    $user_details = DB::query($base_query . " WHERE user_type = %s", 'vendor');
                }
            } else {
                if (!empty($search_input)) {
                    $user_details = DB::query($base_query . " WHERE employee_id LIKE %s AND vendor_id = %i AND user_type = %s", 
                                             $search_input, intval($_GET['vendorid']), 'vendor');
                } else {
                    $user_details = DB::query($base_query . " WHERE vendor_id = %i AND user_type = %s", 
                                             intval($_GET['vendorid']), 'vendor');
                }
            }
        }
    }
}


















echo "<table id='tbl-user-details' class='table table-bordered'>
                      <thead>
                        <tr>
                          <th> # </th>
                          <th> Employee ID </th>
                          <th> User Name</th>
                          <th> Email</th>
                          <th> Action</th>
                        </tr>
                      </thead>
                      <tbody>
                    ";


if(empty($user_details))
{
  //  echo "<tr><td colspan='5'>Nothing found.</td></tr>";
}
else 
{
    $count=1;
    foreach ($user_details as $row) {
        echo "<tr>";
        echo "<td>".$count."</td>";
        echo "<td>".$row['employee_id']."</td>";

        echo "<td>".$row['user_name']."</td>";
        echo "<td>".$row['user_email']."</td>";
        echo "<td><a href='manageuserdetails.php?user_id=".$row["user_id"]."&m=m&u=".(($row['user_type']=='vendor')?"v":"c")."' class='btn btn-sm btn-gradient-danger btn-icon-text' role='button' aria-pressed='true'>Manage</a> </td>";
        echo "</tr>";
        $count++;
    }
    echo "  </tbody></table>";
}




