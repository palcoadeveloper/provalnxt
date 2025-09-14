 <?php
          

          
// Load configuration first
require_once(__DIR__."/../../core/config/config.php");
include_once (__DIR__."/../../core/config/db.class.php");
      
          date_default_timezone_set("Asia/Kolkata");
          
          
          
          
          $query=DB::query("select t1.test_id,t2.test_description,t1.test_wf_id, t2.test_performed_by, t1.test_wf_current_stage, t3.wf_stage_description,t1.test_conducted_date
          from tbl_test_schedules_tracking t1, tests t2, workflow_stages t3
          where t1.test_id=t2.test_id and (t1.test_wf_current_stage=t3.wf_stage and wf_type='External Test')
          and val_wf_id=%s and unit_id=%d", $_GET['val_wf_id'],intval($_SESSION['unit_id']));

          
          
          
          echo '<div class="col-lg-12 grid-margin stretch-card">
    <div class="card">
    <div class="card-body"><h4 class="card-title">Tests Performed</h4>';
          echo "<div class='table-responsive'><table class='table table-border'>";
          echo "<thead>
<tr>
<th> # </th>


<th> Test</th>


<th> Test Conducted Date </th>
<th> WF Stage</th>


</tr>
</thead>";
          
          $count=0;
          foreach ($query as $row) {
              
              $count++;
              
              echo "<tr>";
              
              echo "<td>".$count."</td>";
            //  $url="updatetaskdetails.php?test_id=".$row['test_id']."&val_wf_id=".$_GET['val_wf_id']."&test_val_wf_id=".
            //      $row['test_wf_id']."&current_wf_stage=".$row['test_wf_current_stage']."&mode=read";
            //      echo "<td> <a target='_blank' href='".$url."'>".$row['test_description']."</a></td>";

                $url="viewtestwindow.php?test_id=".$row['test_id']."&val_wf_id=".$_GET['val_wf_id']."&test_val_wf_id=".
    $row['test_wf_id']."&current_wf_stage=".$row['test_wf_current_stage']."&mode=read";
echo "<td> <a href='javascript:void(0)' onclick='openTestWindow(\"" . $url . "\", \"" . htmlspecialchars($row['test_description'], ENT_QUOTES) . "\")'>" . $row['test_description'] . "</a></td>";

              echo "<td>".(!empty($row['test_conducted_date']) ? date_format(date_create($row['test_conducted_date']),"d.m.Y") : "Not Conducted")."</td>";
              echo "<td>".$row['wf_stage_description']."</td>";
              
              echo "</tr>";
              
          }
          
          echo "</table></div>";
          echo  "
                  </div>
                </div>
              </div>";
          
          echo '<div class="col-lg-12 grid-margin stretch-card">
    <div class="card">
    <div class="card-body"><h4 class="card-title">Uploaded Certificates</h4>';
          
          $query2=DB::query("select test_wf_id, upload_path_raw_data,upload_path_master_certificate,upload_path_test_certificate,upload_path_other_doc,t2.user_name, upload_action,test_description
from tbl_uploads t1, users t2, tests t3
where t1.uploaded_by=t2.user_id and t1.test_id=t3.test_id and t1.val_wf_id=%s", $_GET['val_wf_id']);
          
          // Function to normalize file paths
          function normalizeUploadPath($path) {
              if (empty($path)) return '';
              
              // Remove leading ../../ or ../ prefixes
              if (strpos($path, '../../') === 0) {
                  return substr($path, 6); // Remove ../../
              } else if (strpos($path, '../') === 0) {
                  return substr($path, 3); // Remove ../
              }
              
              // If path doesn't start with uploads/, add it
              if (strpos($path, 'uploads/') !== 0) {
                  return 'uploads/' . $path;
              }
              
              return $path;
          }
          
          
          echo "<table class='table table-border'>";
          echo "<thead>
<tr>
<th> # </th>
              
<th> Test</th>              
<th> Test Files</th>

<th> Upload Action </th>
              
              
</tr>
</thead>";
          $count=0;
          
          if(!empty($query2)){
             
              foreach ($query2 as $row) {
                  $count++;
                  
                  
                  echo "<tr>";
                  
                  echo "<td>".$count."</td>";
                  
                  echo "<td>". $row['test_description']. "</td>";
                 // echo "<td>". ( (!empty($row['upload_path_raw_data'])) ? "<a target='_blank' href='".substr($row['upload_path_raw_data'],3)."'>Test Raw Data</a>" : ""  ) ;
                 // echo ( (!empty($row['upload_path_master_certificate'])) ? "<br/><br/><a target='_blank' href='".substr($row['upload_path_master_certificate'],3)."'>Master Certificate</a>" : ""  ) ;
                 // echo ( (!empty($row['upload_path_test_certificate'])) ? "<br/><br/><a target='_blank' href='".substr($row['upload_path_test_certificate'],3)."'>Test Certificate</a>" : ""  ) ;
                 // echo ( (!empty($row['upload_path_other_doc'])) ? "<br/><br/><a target='_blank' href='".substr($row['upload_path_other_doc'],3)."'>Other Document</a>" : ""  ) . "</td>";
                  
                  echo "<td>". ( (!empty($row['upload_path_raw_data'])) ? "<a href='javascript:void(0)' onclick='return viewDocument(\"".htmlspecialchars(normalizeUploadPath($row['upload_path_raw_data']), ENT_QUOTES)."\", \"Test Raw Data\")' title='Original: ".htmlspecialchars($row['upload_path_raw_data'], ENT_QUOTES)."'>Test Raw Data</a>" : ""  ) ;
echo ( (!empty($row['upload_path_master_certificate'])) ? "<br/><br/><a href='javascript:void(0)' onclick='return viewDocument(\"".htmlspecialchars(normalizeUploadPath($row['upload_path_master_certificate']), ENT_QUOTES)."\", \"Master Certificate\")' title='Original: ".htmlspecialchars($row['upload_path_master_certificate'], ENT_QUOTES)."'>Master Certificate</a>" : ""  ) ;
echo ( (!empty($row['upload_path_test_certificate'])) ? "<br/><br/><a href='javascript:void(0)' onclick='return viewDocument(\"".htmlspecialchars(normalizeUploadPath($row['upload_path_test_certificate']), ENT_QUOTES)."\", \"Test Certificate\")' title='Original: ".htmlspecialchars($row['upload_path_test_certificate'], ENT_QUOTES)."'>Test Certificate</a>" : ""  ) ;
echo ( (!empty($row['upload_path_other_doc'])) ? "<br/><br/><a href='javascript:void(0)' onclick='return viewDocument(\"".htmlspecialchars(normalizeUploadPath($row['upload_path_other_doc']), ENT_QUOTES)."\", \"Other Document\")' title='Original: ".htmlspecialchars($row['upload_path_other_doc'], ENT_QUOTES)."'>Other Document</a>" : ""  ) . "</td>";
                  
                  
                  echo "<td>". $row['upload_action'] . "</td>";
                  echo "</tr>";
                  
                  
              }
             echo "</table>";
          }
          else 
          {
              echo "</table>";
          }
          
          echo  "
                  </div>
                </div>
              </div>";
     
    
          
          echo '<div class="col-lg-12 grid-margin stretch-card">
    <div class="card">
    <div class="card-body"><h4 class="card-title">Additional Uploaded Documents</h4>';
          
          $query3=DB::query("select test_wf_id, upload_path_raw_data,upload_path_master_certificate,upload_path_test_certificate,upload_path_other_doc,t2.user_name, upload_action
from tbl_uploads t1, users t2
where t1.uploaded_by=t2.user_id and  test_wf_id='' and t1.val_wf_id=%s", $_GET['val_wf_id']);
          
          
          echo "<table class='table table-border'>";
          echo "<thead>
<tr>
<th> # </th>
              

<th> Document</th>
              
<th> Upload Action </th>
              
              
</tr>
</thead>";
          $count=0;
          
          if(!empty($query3)){
              
              foreach ($query3 as $row) {
                  $count++;
                  
                  
                  echo "<tr>";
                  
                  echo "<td>".$count."</td>";
                  
                
                  echo "<td>". ( (!empty($row['upload_path_raw_data'])) ? "<a href='javascript:void(0)' onclick='return viewDocument(\"".substr($row['upload_path_raw_data'],3)."\", \"Additional Raw Data\")'>Additional Raw Data</a>" : ""  ) ;
                  echo ( (!empty($row['upload_path_master_certificate'])) ? "<br/><br/><a href='javascript:void(0)' onclick='return viewDocument(\"".substr($row['upload_path_master_certificate'],3)."\", \"Additional Master Certificate\")'>Additional Master Certificate</a>" : ""  ) ;
                  echo ( (!empty($row['upload_path_test_certificate'])) ? "<br/><br/><a href='javascript:void(0)' onclick='return viewDocument(\"".substr($row['upload_path_test_certificate'],3)."\", \"Additional Certificate\")'>Additional Certificate</a>" : ""  ) ;
                  echo ( (!empty($row['upload_path_other_doc'])) ? "<br/><br/><a href='javascript:void(0)' onclick='return viewDocument(\"".substr($row['upload_path_other_doc'],3)."\", \"Other Document\")'>Other Document</a>" : ""  ) . "</td>";
                  echo "<td>". $row['upload_action'] . "</td>";
                  echo "</tr>";
                  
                  
              }
              echo "</table>";
          }
          else {
              echo "</table>";
              
          }
          
          echo  "
                  </div>
                </div>
              </div>";
          
   ?>       

<script>
// Function to open document in a new window without using modal
function viewDocument(url, title) {
  // Create a new window with specific parameters
  var newWindow = window.open('', 'documentViewer', 'width=800,height=600,resizable=yes,scrollbars=yes');
  
  // Write content directly to the new window
  newWindow.document.write('<html><head><title>' + title + '</title>');
  newWindow.document.write('<style>body { margin: 0; padding: 0; }</style>');
  newWindow.document.write('</head><body>');
  
  // Determine file type based on extension
  var fileExtension = url.split('.').pop().toLowerCase();
  
  if (fileExtension === 'pdf') {
    // For PDFs, embed them using the browser's PDF viewer
    newWindow.document.write('<iframe src="' + url + '" style="width:100%; height:100vh; border:none;"></iframe>');
  } else if (['jpg', 'jpeg', 'png', 'gif'].includes(fileExtension)) {
    // For images, display them directly
    newWindow.document.write('<img src="' + url + '" style="max-width:100%; max-height:100vh; display:block; margin:0 auto;">');
  } else {
    // For other file types, try to display or offer download
    newWindow.document.write('<div style="padding:20px; text-align:center;">');
    newWindow.document.write('<h2>' + title + '</h2>');
    newWindow.document.write('<p>Opening file: ' + url + '</p>');
    newWindow.document.write('<p>If the file doesn\'t open automatically, <a href="' + url + '" download>click here</a> to download it.</p>');
    newWindow.document.write('</div>');
    
    // Redirect to the file after a brief delay
    newWindow.document.write('<script>setTimeout(function() { window.location.href = "' + url + '"; }, 1000);<\/script>');
  }
  
  newWindow.document.write('</body></html>');
  newWindow.document.close();
  
  return false;
}

// Function to open test window in a popup
function openTestWindow(url, title) {
  var testWindow = window.open(url, 'testViewer', 'width=1200,height=800,resizable=yes,scrollbars=yes,location=no,menubar=no,toolbar=no,status=no');
  
  // Focus the new window
  if (testWindow) {
    testWindow.focus();
  }
  
  return false;
}
</script>          
          
         