<?php 

// Load configuration first
require_once(__DIR__ . '/../../config/config.php');
if(!isset($_SESSION))
{
    session_start();

// Validate session timeout
require_once('../../security/session_timeout_middleware.php');
validateActiveSession();
} 

// Include DB class with protection against duplicate includes
if (!class_exists('DB')) {
    require_once '../../config/db.class.php';
}

date_default_timezone_set("Asia/Kolkata");


													$training_details=DB::query("SELECT id, user_name,department_name,file_path 
FROM tbl_training_details t1 left join departments t2 on t1.department_id=t2.department_id
left join users t3 on t1.user_id=t3.user_id where record_status='Active' and val_wf_id=%s", $_GET['val_wf_id']);


$output= "<table class='table table-bordered'><tr><th>Emplyee Name</th><th>Department Name</th><th>Training PDF File</th><th>Action</th></tr>";
if(empty($training_details))
{
    $output=$output. "<tr><td colspan='4'>Nothing to display.</td></tr>";
   
}
else {
    
  
    foreach ($training_details as $row) {
       
       
        $output=$output."<tr>";
        
        $output=$output."<td>". $row['user_name'] . "</td>";
        $output=$output."<td>".(is_null($row['department_name'])?"External Agency":$row['department_name']). "</td>";

        // Clean up file path - remove ./../../ if it exists at the beginning
        $clean_file_path = $row['file_path'];
        if (strpos($clean_file_path, './../../') === 0) {
            $clean_file_path = substr($clean_file_path, 8); // Remove './../../'
        }
        
        $output=$output."<td>". ( (!empty($row['file_path'])) ? "<a download href='".BASE_URL.$clean_file_path."'  data-toggle='modal' data-target='#imagepdfviewerModal'>Download</a>" : "-"  ) . "</td>";
        
   
        
            
                $output=$output."<td><a href='#' class='navlink-delete' data-record-id='".$row['id']."' style='color: red;'>Remove</a><br/></td>";
           
        
        $output=$output."</tr>";
    
        
        
    }
    
    
    
    
}
$output=$output."</table>";
echo $output;


													



?>