<?php
require_once(__DIR__ . '/../../config/config.php');

// Session is already started by config.php via session_init.php
// Validate session timeout
require_once(__DIR__ . '/../../security/session_timeout_middleware.php');
validateActiveSession();
require_once(__DIR__ . '/../../config/db.class.php');

if(!isset($_SESSION['user_name']))
{
   header('Location:'.BASE_URL .'login.php');
   exit;
}


date_default_timezone_set("Asia/Kolkata");

// Basic input validation
if (!isset($_GET['schtype']) || !isset($_GET['unitid']) || !isset($_GET['schyear'])) {
    echo "<div class='alert alert-danger'>Missing required parameters</div>";
    exit;
}

// Sanitize inputs
$schtype = htmlspecialchars($_GET['schtype'], ENT_QUOTES, 'UTF-8');
$unitid = intval($_GET['unitid']);
$schyear = intval($_GET['schyear']);

echo "<table id='datagrid' class='table table-sm'>";
echo "<thead>
<tr>
<th> # </th>
<th> Unit </th>
<th> Schedule year </th>


<th> Schedule generation timestamp</th>
<th> Schedule status </th>
<th> View schedule </th>    
    
</tr>
</thead>";


if($schtype=='val' || $schtype=='rt')
{
    // ADD VALIDATION HERE - before any database queries
    if(empty($unitid) || $unitid <= 0) {
        echo "<tr><td colspan='6'>Please select a valid unit</td></tr></table>";
        exit;
    }
    try {
        if($schtype=='val')
        {
        $results =DB::query("select * from tbl_val_wf_schedule_requests where schedule_year=%d and unit_id=%d", $schyear, $unitid);
        }
        else if($schtype=='rt')
        {
            $results =DB::query("select * from tbl_routine_test_wf_schedule_requests where schedule_year=%d and unit_id=%d", $schyear, $unitid);
            
        }
    } catch (Exception $e) {
        require_once(__DIR__ . '/../../error/error_logger.php');
        logDatabaseError("Database error in getschedule.php: " . $e->getMessage(), [
            'operation_name' => 'search_schedule_validation',
            'unit_id' => $unitid,
            'val_wf_id' => null,
            'equip_id' => null
        ]);
        echo "<tr><td colspan='6'>Database error occurred. Please check the error logs.</td></tr></table>";
        exit;
    }
    if(empty($results))
{
    //echo "<tr><td colspan='5'>No records</td></tr>";
}
else
{
    
$count=0;

foreach ($results as $row) {
    
    $count++;
    try {
        $unit_name=DB::queryFirstField("SELECT unit_name FROM units where unit_id=%i and unit_status='Active'", $row['unit_id']);
    } catch (Exception $e) {
        require_once(__DIR__ . '/../../error/error_logger.php');
        logDatabaseError("Error fetching unit name for unit_id {$row['unit_id']}: " . $e->getMessage(), [
            'operation_name' => 'search_schedule_unit_name_query',
            'unit_id' => $row['unit_id'],
            'val_wf_id' => null,
            'equip_id' => null
        ]);
        $unit_name = "Unknown Unit";
    }
    echo "<tr>";
    
    echo "<td>".$count."</td>";
    echo "<td>".$unit_name."</td>";
    echo "<td>".$row['schedule_year']."</td>";
    echo "<td>".date("d.m.Y H:i:s", strtotime($row['schedule_generation_datetime']))."</td>";
   
    $status="";
    if($row['schedule_request_status']==1){$status='Pending for Engg Approval';}
    else if($row['schedule_request_status']==2){$status='Pending for QA Approval';}
    else if($row['schedule_request_status']==3){$status='Approved';}
    
    
    echo "<td>".$status."</td>";
    
    echo "<td>";
    if($schtype=='val')
    {
        $pdfPath = 'uploads/schedule-report-'.$unitid.'-'.$row['schedule_id'].'.pdf';
        $pdfUrl = 'core/pdf/view_pdf_with_footer.php?pdf_path='.$pdfPath;
        $pdfTitle = 'Schedule Report - '.$unit_name.' ('.$row['schedule_year'].')';
        echo "<a href='$pdfUrl' data-toggle='modal' data-target='#imagepdfviewerModal' data-title='$pdfTitle' class='btn btn-sm btn-gradient-primary btn-icon-text' role='button'>View</a>";
    }
    else 
    {
        $pdfPath = 'uploads/rt-schedule-report-'.$unitid.'-'.$row['schedule_id'].'.pdf';
        $pdfUrl = 'core/pdf/view_pdf_with_footer.php?pdf_path='.$pdfPath;
        $pdfTitle = 'Routine Test Schedule - '.$unit_name.' ('.$row['schedule_year'].')';
        echo "<a href='$pdfUrl' data-toggle='modal' data-target='#imagepdfviewerModal' data-title='$pdfTitle' class='btn btn-sm btn-gradient-primary btn-icon-text' role='button'>View</a>";
    }
    
    
    echo " </td>";
   
  
    
    echo "</tr>";
    
}

echo "</table>";
}
}
else if($schtype=='paval' )
{
     // ADD VALIDATION HERE - before cURL or include calls
     if(empty($unitid) || $unitid <= 0) {
        echo "<tr><td colspan='6'>Please select a valid unit</td></tr></table>";
        exit;
    }

  // Instead of cURL, include the file directly
  $_GET['unit_id'] = $unitid;
  $_GET['sch_year'] = $schyear;
  $_GET['user_name'] = $_SESSION['user_name'];
  $_GET['user_id'] = $_SESSION['user_id'];

  // Capture output and handle errors
  ob_start();
  try {
      $reportFile = __DIR__ . '/../generateplannedvsactualrpt.php';
      if (!file_exists($reportFile)) {
          throw new Exception("Report generation file not found: $reportFile");
      }
      include($reportFile);
      $output = ob_get_clean();
      error_log("Generated planned vs actual report for unit {$unitid}, year {$schyear}");
  } catch (Exception $e) {
      ob_end_clean();
      error_log("Error generating planned vs actual report: " . $e->getMessage());
      $output = '';
  }

/*
     // create a new cURL resource
    $ch = curl_init();
    
    // set URL and other appropriate options

    curl_setopt($ch, CURLOPT_URL, BASE_URL."generateplannedvsactualrpt.php?unit_id=".$_GET['unitid']."&sch_year=".$_GET['schyear']."&user_name=".urlencode($_SESSION['user_name'])."&user_id=".urlencode($_SESSION['user_id']));
    curl_setopt($ch, CURLOPT_HEADER, 0);
    
    // grab URL and pass it to the browser
    $output=curl_exec($ch);
//    echo $output;
    // close cURL resource, and free up system resources
    curl_close($ch);
    */
     try {
         $unit_name=DB::queryFirstField("SELECT unit_name FROM units where unit_id=%i and unit_status='Active'", $unitid);
     } catch (Exception $e) {
         error_log("Error fetching unit name for unit_id {$unitid}: " . $e->getMessage());
         $unit_name = "Unknown Unit";
     }
    echo "<tr>";
    
    echo "<td>"."1"."</td>";
    echo "<td>".$unit_name."</td>";
    echo "<td>".$schyear."</td>";
    echo "<td>".date('d.m.Y')."</td>";
     echo "<td>".'-'."</td>";

 // Generate PDF dynamically
 $pdfUrl = 'generateplannedvsactualrpt.php?unit_id='.$unitid.'&sch_year='.$schyear.'&user_name='.urlencode($_SESSION['user_name']).'&user_id='.urlencode($_SESSION['user_id']);
 $pdfTitle = 'Planned vs Actual Report - '.$unit_name.' ('.$schyear.')';
 echo "<td><a href='$pdfUrl' data-toggle='modal' data-target='#imagepdfviewerModal' data-title='$pdfTitle' class='btn btn-sm btn-gradient-primary btn-icon-text' role='button'>View</a>";

  echo " </td>";
   
  
    
    echo "</tr>";
    


echo "</table>";
}

else if($schtype=='part' )
{
     // ADD VALIDATION HERE - before cURL or include calls
     if(empty($unitid) || $unitid <= 0) {
        echo "<tr><td colspan='6'>Please select a valid unit</td></tr></table>";
        exit;
    }

    // Instead of cURL, include the file directly
  $_GET['unit_id'] = $unitid;
  $_GET['sch_year'] = $schyear;
  $_GET['user_name'] = $_SESSION['user_name'];
  $_GET['user_id'] = $_SESSION['user_id'];

  // Capture output and handle errors
  ob_start();
  try {
      $reportFile = __DIR__ . '/../generateplannedvsactualrtrpt.php';
      if (!file_exists($reportFile)) {
          throw new Exception("RT Report generation file not found: $reportFile");
      }
      include($reportFile);
      $output = ob_get_clean();
      error_log("Generated planned vs actual RT report for unit {$unitid}, year {$schyear}");
  } catch (Exception $e) {
      ob_end_clean();
      error_log("Error generating planned vs actual RT report: " . $e->getMessage());
      $output = '';
  }
  /*     // create a new cURL resource
    $ch = curl_init();
    
  // set URL and other appropriate options
 
   // curl_setopt($ch, CURLOPT_URL,BASE_URL."generateplannedvsactualrtrpt.php?unit_id=".$_GET['unitid']."&sch_year=".$_GET['schyear']."&user_name=".urlencode($_SESSION['user_name']));
    
    
   curl_setopt($ch, CURLOPT_URL, "http://localhost/proval4dep/public/generateplannedvsactualrtrpt.php?unit_id=".$_GET['unitid']."&sch_year=".$_GET['schyear']."&user_name=".urlencode($_SESSION['user_name'])."&user_id=".urlencode($_SESSION['user_id']));
   curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);  // Return response instead of outputting
   curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);  // Follow redirects
   curl_setopt($ch, CURLOPT_TIMEOUT, 30);           // 30 second timeout
   curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; PDF Generator)');
   curl_setopt($ch, CURLOPT_HEADER, 0);

    
    // grab URL and pass it to the browser
    $output=curl_exec($ch);
//    echo $output;

if(curl_errno($ch)){
    echo "Error: ".curl_error($ch);
}
 // Check HTTP response code
 $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
 if($httpCode !== 200) {
     error_log("HTTP Error: " . $httpCode);
 }

    // close cURL resource, and free up system resources
    curl_close($ch);

    */
     try {
         $unit_name=DB::queryFirstField("SELECT unit_name FROM units where unit_id=%i and unit_status='Active'", $unitid);
     } catch (Exception $e) {
         error_log("Error fetching unit name for unit_id {$unitid}: " . $e->getMessage());
         $unit_name = "Unknown Unit";
     }
    echo "<tr>";
    
    echo "<td>"."1"."</td>";
    echo "<td>".$unit_name."</td>";
    echo "<td>".$schyear."</td>";
    echo "<td>".date('d.m.Y')."</td>";
     echo "<td>".'-'."</td>";

 // Generate PDF dynamically
 $pdfUrl = 'generateplannedvsactualrtrpt.php?unit_id='.$unitid.'&sch_year='.$schyear.'&user_name='.urlencode($_SESSION['user_name']).'&user_id='.urlencode($_SESSION['user_id']);
 $pdfTitle = 'Planned vs Actual RT Report - '.$unit_name.' ('.$schyear.')';
 echo "<td><a href='$pdfUrl' data-toggle='modal' data-target='#imagepdfviewerModal' data-title='$pdfTitle' class='btn btn-sm btn-gradient-primary btn-icon-text' role='button'>View</a>";

  echo " </td>";
   
  
    
    echo "</tr>";
    


echo "</table>";
}





