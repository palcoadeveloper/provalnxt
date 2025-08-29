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



$query="select user_id,employee_id,user_type,vendor_id,user_name,user_email from users ";

if($_GET['usertype'] == 'IE')
{
   
    if($_GET['searchinput']=='')
{
    //$query=$query." where unit_id=".$_GET['unitid'];
    $query=$query." where unit_id=".$_GET['unitid']." or unit_id is null and user_type='employee'";
    
}
else
{
    
    if ($_GET['searchcriteria']=='0')
    {
        if($_GET['unitid']=='select')
        {
            $query=$query." where user_name like '%".$_GET['searchinput']."%' and user_type='employee'";
        }
        else {
            $query=$query." where user_name like '%".$_GET['searchinput']."%' and unit_id=".$_GET['unitid']." and user_type='employee'";
        }
        
    }
    else  {
        if($_GET['unitid']=='select')
        {
            $query=$query." where employee_id like '%".$_GET['searchinput']."%' and user_type='employee'";
        }
        else {
            $query=$query." where employee_id like '%".$_GET['searchinput']."%' and unit_id=".$_GET['unitid']." and user_type='employee'";
        }
    }
    
    
}



}
else if($_GET['usertype'] == 'VE')
{
   

    if($_GET['searchinput']=='' && $_GET['vendorid']=='select')
{
    //$query=$query." where unit_id=".$_GET['unitid'];
    $query=$query." where user_type='vendor'";
    
}
else
{
    
    if ($_GET['searchcriteria']=='0')
    {
        
        if($_GET['vendorid']=='select')
        {
            $query=$query." where user_name like '%".$_GET['searchinput']."%' and user_type='vendor'";
        }
        else {
            $query=$query." where user_name like '%".$_GET['searchinput']."%' and vendor_id=".$_GET['vendorid']." and user_type='vendor'";
        }
        
           
       
        
    }
    else  {

        if($_GET['vendorid']=='select')
        {
            $query=$query." where employee_id like '%".$_GET['searchinput']."%' and user_type='vendor'";
        }
        else {
            $query=$query." where employee_id like '%".$_GET['searchinput']."%' and vendor_id=".$_GET['vendorid']." and user_type='vendor'";
        }
        
            
       
    }
    
    
}



}







$user_details= DB::query($query);


















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




