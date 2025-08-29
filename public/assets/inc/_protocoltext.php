            
          <?php
          
          
// Load configuration first
require_once(__DIR__."/../../core/config/config.php");
header('Content-Type: text/html; charset=utf-8');
          include_once (__DIR__."/../../core/config/db.class.php");
          date_default_timezone_set("Asia/Kolkata");
          

            // Execute the query and store the result in an associative array
$results = DB::query("SELECT DISTINCT etvm.test_id, v.vendor_name
                      FROM equipment_test_vendor_mapping etvm
                      JOIN vendors v ON etvm.vendor_id = v.vendor_id
                       where equipment_id=(select equip_id from tbl_val_schedules where val_wf_id='".$_GET['val_wf_id']."')
                      ORDER BY etvm.test_id");

// Create an associative array where test_id is the key
$testVendorMap = [];
foreach ($results as $row) {
    $testVendorMap[$row['test_id']] = $row['vendor_name'];
}

// Function to get vendor name by test_id
function getVendorByTestId($test_id, $testVendorMap) {
    if (array_key_exists($test_id, $testVendorMap)) {
        return $testVendorMap[$test_id];
    } else {
        return "Vendor not found for this test ID.";
    }
}







          
          $training_details=DB::query("SELECT user_name,department_name,'Trained' training_status, file_path
FROM tbl_training_details t1 left join users t2 on t1.user_id=t2.user_id
left join departments t3 on t1.department_id=t3.department_id
where record_status='Active' and val_wf_id=%s",$_GET['val_wf_id']);
          $equipment_details= DB::queryFirstRow("select * from equipments e, departments d where e.department_id=d.department_id and equipment_id=%i", $_GET['equipment_id']);
        
         $report_details=DB::queryFirstRow("select * from validation_reports where val_wf_id=%s",$_GET['val_wf_id']);
         
          $initiated_by=DB::queryFirstField("SELECT user_name FROM tbl_val_wf_tracking_details t1, users t2 where t1.wf_initiated_by_user_id=t2.user_id and val_wf_id=%s",$_GET['val_wf_id']);
          $wf_details=DB::queryFirstRow("select date(actual_wf_start_datetime) actual_wf_start_datetime,val_wf_current_stage,unit_id from tbl_val_wf_tracking_details where val_wf_id=%s",$_GET['val_wf_id']);
        
          
          $unit_name=DB::queryFirstField("select unit_name from units where unit_id=". $wf_details['unit_id']);
          
          
          $test1_query=DB::query("select test_wf_id,upload_id, upload_path_raw_data,upload_path_master_certificate,upload_path_test_certificate,upload_path_other_doc,uploaded_datetime,upload_remarks, upload_type,t2.user_name,t2.user_id from tbl_uploads t1, users t2
where t1.uploaded_by=t2.user_id and upload_action='Approved'
and test_id=1 and val_wf_id=%s order by uploaded_datetime", $_GET['val_wf_id']);
          $test1_output="";
          $count=0;
          
          if(!empty($test1_query)){
              $test1_output="Approved Certificate(s):<br/> <table class='table table-border'>";
              foreach ($test1_query as $row) {
                  $count++;
                  
                  
                  $test1_output=$test1_output."<tr>";
                  $test_output=$test1_output."<td>".$count."</td>";
                  
                 // $test1_output=$test1_output."<td>". ( (!empty($row['upload_path_raw_data'])) ? "<a target='_blank' href='".substr($row['upload_path_raw_data'],3)."'>Test Data</a>" : "-"  ) . "</td>";
                  
                 // $test1_output=$test1_output."<td>". ( (!empty($row['upload_path_master_certificate'])) ? "<a target='_blank' href='".substr($row['upload_path_master_certificate'],3)."'>Master Certificate</a>" : "-"  ) . "</td>";
                 // $test1_output=$test1_output."<td>". ( (!empty($row['upload_path_test_certificate'])) ? "<a target='_blank' href='".substr($row['upload_path_test_certificate'],3)."'>Test Certificate</a>" : "-"  ) . "</td>";
                 // $test1_output=$test1_output."<td>". ( (!empty($row['upload_path_other_doc'])) ? "<a target='_blank' href='".substr($row['upload_path_other_doc'],3)."'>Other Document</a>" : "-"  ) . "</td>";
                
$test1_output=$test1_output."<td>". ( (!empty($row['upload_path_raw_data'])) ? "<a href='javascript:void(0)' onclick='return viewDocument(\"".substr($row['upload_path_raw_data'],3)."\", \"Test Data\")'>Test Data</a>" : "-"  ) . "</td>";

$test1_output=$test1_output."<td>". ( (!empty($row['upload_path_master_certificate'])) ? "<a href='javascript:void(0)' onclick='return viewDocument(\"".substr($row['upload_path_master_certificate'],3)."\", \"Master Certificate\")'>Master Certificate</a>" : "-"  ) . "</td>";

$test1_output=$test1_output."<td>". ( (!empty($row['upload_path_test_certificate'])) ? "<a href='javascript:void(0)' onclick='return viewDocument(\"".substr($row['upload_path_test_certificate'],3)."\", \"Test Certificate\")'>Test Certificate</a>" : "-"  ) . "</td>";

$test1_output=$test1_output."<td>". ( (!empty($row['upload_path_other_doc'])) ? "<a href='javascript:void(0)' onclick='return viewDocument(\"".substr($row['upload_path_other_doc'],3)."\", \"Other Document\")'>Other Document</a>" : "-"  ) . "</td>";

              }
              $test1_output=$test1_output."</table>";
          }
          
          
          
          $test2_query=DB::query("select test_wf_id,upload_id, upload_path_raw_data,upload_path_master_certificate,upload_path_test_certificate,upload_path_other_doc,uploaded_datetime,upload_remarks, upload_type,t2.user_name,t2.user_id from tbl_uploads t1, users t2
where t1.uploaded_by=t2.user_id and upload_action='Approved'
and test_id=2 and val_wf_id=%s order by uploaded_datetime", $_GET['val_wf_id']);
          $test2_output="";
          $count=0;
          
          if(!empty($test2_query)){
              $test2_output="Approved Certificate(s):<br/> <table class='table table-border'>";
              foreach ($test2_query as $row) {
                  $count++;
                  
                  
                  $test2_output=$test2_output."<tr>";
                  $test2_output=$test2_output."<td>".$count."</td>";
                  
              //    $test2_output=$test2_output."<td>". ( (!empty($row['upload_path_raw_data'])) ? "<a target='_blank' href='".substr($row['upload_path_raw_data'],3)."'>Test Data</a>" : "-"  ) . "</td>";
              //    $test2_output=$test2_output."<td>". ( (!empty($row['upload_path_master_certificate'])) ? "<a target='_blank' href='".substr($row['upload_path_master_certificate'],3)."'>Master Certificate</a>" : "-"  ) . "</td>";
              //    $test2_output=$test2_output."<td>". ( (!empty($row['upload_path_test_certificate'])) ? "<a target='_blank' href='".substr($row['upload_path_test_certificate'],3)."'>Test Certificate</a>" : "-"  ) . "</td>";
              //    $test2_output=$test2_output."<td>". ( (!empty($row['upload_path_other_doc'])) ? "<a target='_blank' href='".substr($row['upload_path_other_doc'],3)."'>Other Document</a>" : "-"  ) . "</td>";
                $test2_output=$test2_output."<td>". ( (!empty($row['upload_path_raw_data'])) ? "<a href='javascript:void(0)' onclick='return viewDocument(\"".substr($row['upload_path_raw_data'],3)."\", \"Test Data\")'>Test Data</a>" : "-"  ) . "</td>";
                $test2_output=$test2_output."<td>". ( (!empty($row['upload_path_master_certificate'])) ? "<a href='javascript:void(0)' onclick='return viewDocument(\"".substr($row['upload_path_master_certificate'],3)."\", \"Master Certificate\")'>Master Certificate</a>" : "-"  ) . "</td>";
                $test2_output=$test2_output."<td>". ( (!empty($row['upload_path_test_certificate'])) ? "<a href='javascript:void(0)' onclick='return viewDocument(\"".substr($row['upload_path_test_certificate'],3)."\", \"Test Certificate\")'>Test Certificate</a>" : "-"  ) . "</td>";
                $test2_output=$test2_output."<td>". ( (!empty($row['upload_path_other_doc'])) ? "<a href='javascript:void(0)' onclick='return viewDocument(\"".substr($row['upload_path_other_doc'],3)."\", \"Other Document\")'>Other Document</a>" : "-"  ) . "</td>";
   
                  
              }
              $test2_output=$test2_output."</table>";
          }
          
          
          $test3_query=DB::query("select test_wf_id,upload_id, upload_path_raw_data,upload_path_master_certificate,upload_path_test_certificate,upload_path_other_doc,uploaded_datetime,upload_remarks, upload_type,t2.user_name,t2.user_id from tbl_uploads t1, users t2
where t1.uploaded_by=t2.user_id and upload_action='Approved'
and test_id=3 and val_wf_id=%s order by uploaded_datetime", $_GET['val_wf_id']);
          $test3_output="";
          $count=0;
          
          if(!empty($test3_query)){
              $test3_output="Approved Certificate(s):<br/> <table class='table table-border'>";
              foreach ($test3_query as $row) {
                  $count++;
                  
                  
                  $test3_output=$test3_output."<tr>";
                  $test3_output=$test3_output."<td>".$count."</td>";
                  
                 // $test3_output=$test3_output."<td>". ( (!empty($row['upload_path_raw_data'])) ? "<a target='_blank' href='".substr($row['upload_path_raw_data'],3)."'>Test Data</a>" : "-"  ) . "</td>";
                 // $test3_output=$test3_output."<td>". ( (!empty($row['upload_path_master_certificate'])) ? "<a target='_blank' href='".substr($row['upload_path_master_certificate'],3)."'>Master Certificate</a>" : "-"  ) . "</td>";
                 // $test3_output=$test3_output."<td>". ( (!empty($row['upload_path_test_certificate'])) ? "<a target='_blank' href='".substr($row['upload_path_test_certificate'],3)."'>Test Certificate</a>" : "-"  ) . "</td>";
                 // $test3_output=$test3_output."<td>". ( (!empty($row['upload_path_other_doc'])) ? "<a target='_blank' href='".substr($row['upload_path_other_doc'],3)."'>Other Document</a>" : "-"  ) . "</td>";
                    $test3_output=$test3_output."<td>". ( (!empty($row['upload_path_raw_data'])) ? "<a href='javascript:void(0)' onclick='return viewDocument(\"".substr($row['upload_path_raw_data'],3)."\", \"Test Data\")'>Test Data</a>" : "-"  ) . "</td>";
                    $test3_output=$test3_output."<td>". ( (!empty($row['upload_path_master_certificate'])) ? "<a href='javascript:void(0)' onclick='return viewDocument(\"".substr($row['upload_path_master_certificate'],3)."\", \"Master Certificate\")'>Master Certificate</a>" : "-"  ) . "</td>";
                    $test3_output=$test3_output."<td>". ( (!empty($row['upload_path_test_certificate'])) ? "<a href='javascript:void(0)' onclick='return viewDocument(\"".substr($row['upload_path_test_certificate'],3)."\", \"Test Certificate\")'>Test Certificate</a>" : "-"  ) . "</td>";
                    $test3_output=$test3_output."<td>". ( (!empty($row['upload_path_other_doc'])) ? "<a href='javascript:void(0)' onclick='return viewDocument(\"".substr($row['upload_path_other_doc'],3)."\", \"Other Document\")'>Other Document</a>" : "-"  ) . "</td>";
    
                  
              }
              $test3_output=$test3_output."</table>";
          }
          
          $test4_query=DB::query("select test_wf_id,upload_id, upload_path_raw_data,upload_path_master_certificate,upload_path_test_certificate,upload_path_other_doc,uploaded_datetime,upload_remarks, upload_type,t2.user_name,t2.user_id from tbl_uploads t1, users t2
where t1.uploaded_by=t2.user_id and upload_action='Approved'
and test_id=4 and val_wf_id=%s order by uploaded_datetime", $_GET['val_wf_id']);
          $test4_output="";
          $count=0;
          
          if(!empty($test4_query)){
              $test4_output="Approved Certificate(s):<br/> <table class='table table-border'>";
              foreach ($test4_query as $row) {
                  $count++;
                  
                  
                  $test4_output=$test4_output."<tr>";
                  $test4_output=$test4_output."<td>".$count."</td>";
                  
                //  $test4_output=$test4_output."<td>". ( (!empty($row['upload_path_raw_data'])) ? "<a target='_blank' href='".substr($row['upload_path_raw_data'],3)."'>Test Data</a>" : "-"  ) . "</td>";
                //  $test4_output=$test4_output."<td>". ( (!empty($row['upload_path_master_certificate'])) ? "<a target='_blank' href='".substr($row['upload_path_master_certificate'],3)."'>Master Certificate</a>" : "-"  ) . "</td>";
                //  $test4_output=$test4_output."<td>". ( (!empty($row['upload_path_test_certificate'])) ? "<a target='_blank' href='".substr($row['upload_path_test_certificate'],3)."'>Test Certificate</a>" : "-"  ) . "</td>";
                //  $test4_output=$test4_output."<td>". ( (!empty($row['upload_path_other_doc'])) ? "<a target='_blank' href='".substr($row['upload_path_other_doc'],3)."'>Other Document</a>" : "-"  ) . "</td>";
                $test4_output=$test4_output."<td>". ( (!empty($row['upload_path_raw_data'])) ? "<a href='javascript:void(0)' onclick='return viewDocument(\"".substr($row['upload_path_raw_data'],3)."\", \"Test Data\")'>Test Data</a>" : "-"  ) . "</td>";
                $test4_output=$test4_output."<td>". ( (!empty($row['upload_path_master_certificate'])) ? "<a href='javascript:void(0)' onclick='return viewDocument(\"".substr($row['upload_path_master_certificate'],3)."\", \"Master Certificate\")'>Master Certificate</a>" : "-"  ) . "</td>";
                $test4_output=$test4_output."<td>". ( (!empty($row['upload_path_test_certificate'])) ? "<a href='javascript:void(0)' onclick='return viewDocument(\"".substr($row['upload_path_test_certificate'],3)."\", \"Test Certificate\")'>Test Certificate</a>" : "-"  ) . "</td>";
                $test4_output=$test4_output."<td>". ( (!empty($row['upload_path_other_doc'])) ? "<a href='javascript:void(0)' onclick='return viewDocument(\"".substr($row['upload_path_other_doc'],3)."\", \"Other Document\")'>Other Document</a>" : "-"  ) . "</td>";
                     
                  
              }
              $test4_output=$test4_output."</table>";
          }
          
          
          $test6_query=DB::query("select test_wf_id,upload_id, upload_path_raw_data,upload_path_master_certificate,upload_path_test_certificate,upload_path_other_doc,uploaded_datetime,upload_remarks, upload_type,t2.user_name,t2.user_id from tbl_uploads t1, users t2
where t1.uploaded_by=t2.user_id and upload_action='Approved'
and test_id=6 and val_wf_id=%s order by uploaded_datetime", $_GET['val_wf_id']);
          $test6_output="";
          $count=0;
          
          if(!empty($test6_query)){
              $test6_output="Approved Certificate(s):<br/> <table class='table table-border'>";
              foreach ($test6_query as $row) {
                  $count++;
                  
                  
                  $test6_output=$test6_output."<tr>";
                  $test6_output=$test6_output."<td>".$count."</td>";
                  
                //  $test6_output=$test6_output."<td>". ( (!empty($row['upload_path_raw_data'])) ? "<a target='_blank' href='".substr($row['upload_path_raw_data'],3)."'>Test Data</a>" : "-"  ) . "</td>";
                //  $test6_output=$test6_output."<td>". ( (!empty($row['upload_path_master_certificate'])) ? "<a target='_blank' href='".substr($row['upload_path_master_certificate'],3)."'>Master Certificate</a>" : "-"  ) . "</td>";
                //  $test6_output=$test6_output."<td>". ( (!empty($row['upload_path_test_certificate'])) ? "<a target='_blank' href='".substr($row['upload_path_test_certificate'],3)."'>Test Certificate</a>" : "-"  ) . "</td>";
                //  $test6_output=$test6_output."<td>". ( (!empty($row['upload_path_other_doc'])) ? "<a target='_blank' href='".substr($row['upload_path_other_doc'],3)."'>Other Document</a>" : "-"  ) . "</td>";
                $test6_output=$test6_output."<td>". ( (!empty($row['upload_path_raw_data'])) ? "<a href='javascript:void(0)' onclick='return viewDocument(\"".substr($row['upload_path_raw_data'],3)."\", \"Test Data\")'>Test Data</a>" : "-"  ) . "</td>";
                $test6_output=$test6_output."<td>". ( (!empty($row['upload_path_master_certificate'])) ? "<a href='javascript:void(0)' onclick='return viewDocument(\"".substr($row['upload_path_master_certificate'],3)."\", \"Master Certificate\")'>Master Certificate</a>" : "-"  ) . "</td>";
                $test6_output=$test6_output."<td>". ( (!empty($row['upload_path_test_certificate'])) ? "<a href='javascript:void(0)' onclick='return viewDocument(\"".substr($row['upload_path_test_certificate'],3)."\", \"Test Certificate\")'>Test Certificate</a>" : "-"  ) . "</td>";
                $test6_output=$test6_output."<td>". ( (!empty($row['upload_path_other_doc'])) ? "<a href='javascript:void(0)' onclick='return viewDocument(\"".substr($row['upload_path_other_doc'],3)."\", \"Other Document\")'>Other Document</a>" : "-"  ) . "</td>";
                     
                  
              }
              $test6_output=$test6_output."</table>";
          }
          
          $test7_query=DB::query("select test_wf_id,upload_id, upload_path_raw_data,upload_path_master_certificate,upload_path_test_certificate,upload_path_other_doc,uploaded_datetime,upload_remarks, upload_type,t2.user_name,t2.user_id from tbl_uploads t1, users t2
where t1.uploaded_by=t2.user_id and upload_action='Approved'
and test_id=7 and val_wf_id=%s order by uploaded_datetime", $_GET['val_wf_id']);
          $test7_output="";
          $count=0;
          
          if(!empty($test7_query)){
              $test7_output="Approved Certificate(s):<br/> <table class='table table-border'>";
              foreach ($test7_query as $row) {
                  $count++;
                  
                  
                  $test7_output=$test7_output."<tr>";
                  $test7_output=$test7_output."<td>".$count."</td>";
                  
                //  $test7_output=$test7_output."<td>". ( (!empty($row['upload_path_raw_data'])) ? "<a target='_blank' href='".substr($row['upload_path_raw_data'],3)."'>Test Data</a>" : "-"  ) . "</td>";
                //  $test7_output=$test7_output."<td>". ( (!empty($row['upload_path_master_certificate'])) ? "<a target='_blank' href='".substr($row['upload_path_master_certificate'],3)."'>Master Certificate</a>" : "-"  ) . "</td>";
                //  $test7_output=$test7_output."<td>". ( (!empty($row['upload_path_test_certificate'])) ? "<a target='_blank' href='".substr($row['upload_path_test_certificate'],3)."'>Test Certificate</a>" : "-"  ) . "</td>";
                //  $test7_output=$test7_output."<td>". ( (!empty($row['upload_path_other_doc'])) ? "<a target='_blank' href='".substr($row['upload_path_other_doc'],3)."'>Other Document</a>" : "-"  ) . "</td>";
                 $test7_output=$test7_output."<td>". ( (!empty($row['upload_path_raw_data'])) ? "<a href='javascript:void(0)' onclick='return viewDocument(\"".substr($row['upload_path_raw_data'],3)."\", \"Test Data\")'>Test Data</a>" : "-"  ) . "</td>";
                $test7_output=$test7_output."<td>". ( (!empty($row['upload_path_master_certificate'])) ? "<a href='javascript:void(0)' onclick='return viewDocument(\"".substr($row['upload_path_master_certificate'],3)."\", \"Master Certificate\")'>Master Certificate</a>" : "-"  ) . "</td>";
                $test7_output=$test7_output."<td>". ( (!empty($row['upload_path_test_certificate'])) ? "<a href='javascript:void(0)' onclick='return viewDocument(\"".substr($row['upload_path_test_certificate'],3)."\", \"Test Certificate\")'>Test Certificate</a>" : "-"  ) . "</td>";
                $test7_output=$test7_output."<td>". ( (!empty($row['upload_path_other_doc'])) ? "<a href='javascript:void(0)' onclick='return viewDocument(\"".substr($row['upload_path_other_doc'],3)."\", \"Other Document\")'>Other Document</a>" : "-"  ) . "</td>";
                    
                  
              }
              $test7_output=$test7_output."</table>";
          }
          
          
          
          
          
          
          
          
          
          
          
          
          
          
          
          
          
          
          
          
          
          
          
          
          
          
          
          
          $test8_query=DB::query("select test_wf_id,upload_id, upload_path_raw_data,upload_path_master_certificate,upload_path_test_certificate,upload_path_other_doc,uploaded_datetime,upload_remarks, upload_type,t2.user_name,t2.user_id from tbl_uploads t1, users t2
where t1.uploaded_by=t2.user_id and upload_action='Approved'
and test_id=8 and val_wf_id=%s order by uploaded_datetime", $_GET['val_wf_id']);
          $test8_output="";
          $count=0;
          
          if(!empty($test8_query)){
              $test8_output="Approved Certificate(s):<br/> <table class='table table-border'>";
          foreach ($test8_query as $row) {
              $count++;
             
              
              $test8_output=$test8_output."<tr>";
              $test8_output=$test8_output."<td>".$count."</td>";
              
            //  $test8_output=$test8_output."<td>". ( (!empty($row['upload_path_raw_data'])) ? "<a target='_blank' href='".substr($row['upload_path_raw_data'],3)."'>Test Data</a>" : "-"  ) . "</td>";
            //  $test8_output=$test8_output."<td>". ( (!empty($row['upload_path_master_certificate'])) ? "<a target='_blank' href='".substr($row['upload_path_master_certificate'],3)."'>Master Certificate</a>" : "-"  ) . "</td>";
            //  $test8_output=$test8_output."<td>". ( (!empty($row['upload_path_test_certificate'])) ? "<a target='_blank' href='".substr($row['upload_path_test_certificate'],3)."'>Test Certificate</a>" : "-"  ) . "</td>";
            //  $test8_output=$test8_output."<td>". ( (!empty($row['upload_path_other_doc'])) ? "<a target='_blank' href='".substr($row['upload_path_other_doc'],3)."'>Other Document</a>" : "-"  ) . "</td>";
                 $test8_output=$test8_output."<td>". ( (!empty($row['upload_path_raw_data'])) ? "<a href='javascript:void(0)' onclick='return viewDocument(\"".substr($row['upload_path_raw_data'],3)."\", \"Test Data\")'>Test Data</a>" : "-"  ) . "</td>";
                $test8_output=$test8_output."<td>". ( (!empty($row['upload_path_master_certificate'])) ? "<a href='javascript:void(0)' onclick='return viewDocument(\"".substr($row['upload_path_master_certificate'],3)."\", \"Master Certificate\")'>Master Certificate</a>" : "-"  ) . "</td>";
                $test8_output=$test8_output."<td>". ( (!empty($row['upload_path_test_certificate'])) ? "<a href='javascript:void(0)' onclick='return viewDocument(\"".substr($row['upload_path_test_certificate'],3)."\", \"Test Certificate\")'>Test Certificate</a>" : "-"  ) . "</td>";
                $test8_output=$test8_output."<td>". ( (!empty($row['upload_path_other_doc'])) ? "<a href='javascript:void(0)' onclick='return viewDocument(\"".substr($row['upload_path_other_doc'],3)."\", \"Other Document\")'>Other Document</a>" : "-"  ) . "</td>";
                
              
          }
          $test8_output=$test8_output."</table>";
          }
          
          
          
          
          $test9_query=DB::query("select test_wf_id,upload_id, upload_path_raw_data,upload_path_master_certificate,upload_path_test_certificate,upload_path_other_doc,uploaded_datetime,upload_remarks, upload_type,t2.user_name,t2.user_id from tbl_uploads t1, users t2
where t1.uploaded_by=t2.user_id and upload_action='Approved'
and test_id=9 and val_wf_id=%s order by uploaded_datetime", $_GET['val_wf_id']);
          $test9_output="";
          $count=0;
          
          if(!empty($test9_query)){
              $test9_output="Approved Certificate(s):<br/> <table class='table table-border'>";
              foreach ($test9_query as $row) {
                  $count++;
                  
                  
                  $test9_output=$test9_output."<tr>";
                  $test9_output=$test9_output."<td>".$count."</td>";
                  
                //  $test9_output=$test9_output."<td>". ( (!empty($row['upload_path_raw_data'])) ? "<a target='_blank' href='".substr($row['upload_path_raw_data'],3)."'>Test Data</a>" : "-"  ) . "</td>";
                //  $test9_output=$test9_output."<td>". ( (!empty($row['upload_path_master_certificate'])) ? "<a target='_blank' href='".substr($row['upload_path_master_certificate'],3)."'>Master Certificate</a>" : "-"  ) . "</td>";
                //  $test9_output=$test9_output."<td>". ( (!empty($row['upload_path_test_certificate'])) ? "<a target='_blank' href='".substr($row['upload_path_test_certificate'],3)."'>Test Certificate</a>" : "-"  ) . "</td>";
                //  $test9_output=$test9_output."<td>". ( (!empty($row['upload_path_other_doc'])) ? "<a target='_blank' href='".substr($row['upload_path_other_doc'],3)."'>Other Document</a>" : "-"  ) . "</td>";
                 $test9_output=$test9_output."<td>". ( (!empty($row['upload_path_raw_data'])) ? "<a href='javascript:void(0)' onclick='return viewDocument(\"".substr($row['upload_path_raw_data'],3)."\", \"Test Data\")'>Test Data</a>" : "-"  ) . "</td>";
                $test9_output=$test9_output."<td>". ( (!empty($row['upload_path_master_certificate'])) ? "<a href='javascript:void(0)' onclick='return viewDocument(\"".substr($row['upload_path_master_certificate'],3)."\", \"Master Certificate\")'>Master Certificate</a>" : "-"  ) . "</td>";
                $test9_output=$test9_output."<td>". ( (!empty($row['upload_path_test_certificate'])) ? "<a href='javascript:void(0)' onclick='return viewDocument(\"".substr($row['upload_path_test_certificate'],3)."\", \"Test Certificate\")'>Test Certificate</a>" : "-"  ) . "</td>";
                $test9_output=$test9_output."<td>". ( (!empty($row['upload_path_other_doc'])) ? "<a href='javascript:void(0)' onclick='return viewDocument(\"".substr($row['upload_path_other_doc'],3)."\", \"Other Document\")'>Other Document</a>" : "-"  ) . "</td>";
                    
                  
              }
              $test9_output=$test9_output."</table>";
          }
          
          
          
          
          
          $test10_query=DB::query("select test_wf_id,upload_id, upload_path_raw_data,upload_path_master_certificate,upload_path_test_certificate,upload_path_other_doc,uploaded_datetime,upload_remarks, upload_type,t2.user_name,t2.user_id from tbl_uploads t1, users t2
where t1.uploaded_by=t2.user_id and upload_action='Approved'
and test_id=10 and val_wf_id=%s order by uploaded_datetime", $_GET['val_wf_id']);
          $test10_output="";
          $count=0;
          
          if(!empty($test10_query)){
              $test10_output="Approved Certificate(s):<br/> <table class='table table-border'>";
              foreach ($test10_query as $row) {
                  $count++;
                  
                  
                  $test10_output=$test10_output."<tr>";
                  $test10_output=$test10_output."<td>".$count."</td>";
                  
                 // $test10_output=$test10_output."<td>". ( (!empty($row['upload_path_raw_data'])) ? "<a target='_blank' href='".substr($row['upload_path_raw_data'],3)."'>Test Data</a>" : "-"  ) . "</td>";
                 // $test10_output=$test10_output."<td>". ( (!empty($row['upload_path_master_certificate'])) ? "<a target='_blank' href='".substr($row['upload_path_master_certificate'],3)."'>Master Certificate</a>" : "-"  ) . "</td>";
                 // $test10_output=$test10_output."<td>". ( (!empty($row['upload_path_test_certificate'])) ? "<a target='_blank' href='".substr($row['upload_path_test_certificate'],3)."'>Test Certificate</a>" : "-"  ) . "</td>";
                 // $test10_output=$test10_output."<td>". ( (!empty($row['upload_path_other_doc'])) ? "<a target='_blank' href='".substr($row['upload_path_other_doc'],3)."'>Other Document</a>" : "-"  ) . "</td>";
                 $test10_output=$test10_output."<td>". ( (!empty($row['upload_path_raw_data'])) ? "<a href='javascript:void(0)' onclick='return viewDocument(\"".substr($row['upload_path_raw_data'],3)."\", \"Test Data\")'>Test Data</a>" : "-"  ) . "</td>";
                $test10_output=$test10_output."<td>". ( (!empty($row['upload_path_master_certificate'])) ? "<a href='javascript:void(0)' onclick='return viewDocument(\"".substr($row['upload_path_master_certificate'],3)."\", \"Master Certificate\")'>Master Certificate</a>" : "-"  ) . "</td>";
                $test10_output=$test10_output."<td>". ( (!empty($row['upload_path_test_certificate'])) ? "<a href='javascript:void(0)' onclick='return viewDocument(\"".substr($row['upload_path_test_certificate'],3)."\", \"Test Certificate\")'>Test Certificate</a>" : "-"  ) . "</td>";
                $test10_output=$test10_output."<td>". ( (!empty($row['upload_path_other_doc'])) ? "<a href='javascript:void(0)' onclick='return viewDocument(\"".substr($row['upload_path_other_doc'],3)."\", \"Other Document\")'>Other Document</a>" : "-"  ) . "</td>";
                    
                  
              }
              $test10_output=$test10_output."</table>";
          }
          
          
          
          
          $test11_query=DB::query("select test_wf_id,upload_id, upload_path_raw_data,upload_path_master_certificate,upload_path_test_certificate,upload_path_other_doc,uploaded_datetime,upload_remarks, upload_type,t2.user_name,t2.user_id from tbl_uploads t1, users t2
where t1.uploaded_by=t2.user_id and upload_action='Approved'
and test_id=11 and val_wf_id=%s order by uploaded_datetime", $_GET['val_wf_id']);
          $test11_output="";
          $count=0;
          
          if(!empty($test11_query)){
              $test11_output="Approved Certificate(s):<br/> <table class='table table-border'>";
              foreach ($test11_query as $row) {
                  $count++;
                  
                  
                  $test11_output=$test11_output."<tr>";
                  $test11_output=$test11_output."<td>".$count."</td>";
                  
                //  $test11_output=$test11_output."<td>". ( (!empty($row['upload_path_raw_data'])) ? "<a target='_blank' href='".substr($row['upload_path_raw_data'],3)."'>Test Data</a>" : "-"  ) . "</td>";
                //  $test11_output=$test11_output."<td>". ( (!empty($row['upload_path_master_certificate'])) ? "<a target='_blank' href='".substr($row['upload_path_master_certificate'],3)."'>Master Certificate</a>" : "-"  ) . "</td>";
                //  $test11_output=$test11_output."<td>". ( (!empty($row['upload_path_test_certificate'])) ? "<a target='_blank' href='".substr($row['upload_path_test_certificate'],3)."'>Test Certificate</a>" : "-"  ) . "</td>";
                //  $test11_output=$test11_output."<td>". ( (!empty($row['upload_path_other_doc'])) ? "<a target='_blank' href='".substr($row['upload_path_other_doc'],3)."'>Other Document</a>" : "-"  ) . "</td>";
                 $test11_output=$test11_output."<td>". ( (!empty($row['upload_path_raw_data'])) ? "<a href='javascript:void(0)' onclick='return viewDocument(\"".substr($row['upload_path_raw_data'],3)."\", \"Test Data\")'>Test Data</a>" : "-"  ) . "</td>";
                $test11_output=$test11_output."<td>". ( (!empty($row['upload_path_master_certificate'])) ? "<a href='javascript:void(0)' onclick='return viewDocument(\"".substr($row['upload_path_master_certificate'],3)."\", \"Master Certificate\")'>Master Certificate</a>" : "-"  ) . "</td>";
                $test11_output=$test11_output."<td>". ( (!empty($row['upload_path_test_certificate'])) ? "<a href='javascript:void(0)' onclick='return viewDocument(\"".substr($row['upload_path_test_certificate'],3)."\", \"Test Certificate\")'>Test Certificate</a>" : "-"  ) . "</td>";
                $test11_output=$test11_output."<td>". ( (!empty($row['upload_path_other_doc'])) ? "<a href='javascript:void(0)' onclick='return viewDocument(\"".substr($row['upload_path_other_doc'],3)."\", \"Other Document\")'>Other Document</a>" : "-"  ) . "</td>";
                    
                  
              }
              $test11_output=$test11_output."</table>";
          }
          
          
          
          
          $test12_query=DB::query("select test_wf_id,upload_id, upload_path_raw_data,upload_path_master_certificate,upload_path_test_certificate,upload_path_other_doc,uploaded_datetime,upload_remarks, upload_type,t2.user_name,t2.user_id from tbl_uploads t1, users t2
where t1.uploaded_by=t2.user_id and upload_action='Approved'
and test_id=12 and val_wf_id=%s order by uploaded_datetime", $_GET['val_wf_id']);
          $test12_output="";
          $count=0;
          
          if(!empty($test12_query)){
              $test12_output="Approved Certificate(s):<br/> <table class='table table-border'>";
              foreach ($test12_query as $row) {
                  $count++;
                  
                  
                  $test12_output=$test12_output."<tr>";
                  $test12_output=$test12_output."<td>".$count."</td>";
                  
               //   $test12_output=$test12_output."<td>". ( (!empty($row['upload_path_raw_data'])) ? "<a target='_blank' href='".substr($row['upload_path_raw_data'],3)."'>Test Data</a>" : "-"  ) . "</td>";
               //   $test12_output=$test12_output."<td>". ( (!empty($row['upload_path_master_certificate'])) ? "<a target='_blank' href='".substr($row['upload_path_master_certificate'],3)."'>Master Certificate</a>" : "-"  ) . "</td>";
               //   $test12_output=$test12_output."<td>". ( (!empty($row['upload_path_test_certificate'])) ? "<a target='_blank' href='".substr($row['upload_path_test_certificate'],3)."'>Test Certificate</a>" : "-"  ) . "</td>";
               //   $test12_output=$test12_output."<td>". ( (!empty($row['upload_path_other_doc'])) ? "<a target='_blank' href='".substr($row['upload_path_other_doc'],3)."'>Other Document</a>" : "-"  ) . "</td>";
                 $test12_output=$test12_output."<td>". ( (!empty($row['upload_path_raw_data'])) ? "<a href='javascript:void(0)' onclick='return viewDocument(\"".substr($row['upload_path_raw_data'],3)."\", \"Test Data\")'>Test Data</a>" : "-"  ) . "</td>";
                $test12_output=$test12_output."<td>". ( (!empty($row['upload_path_master_certificate'])) ? "<a href='javascript:void(0)' onclick='return viewDocument(\"".substr($row['upload_path_master_certificate'],3)."\", \"Master Certificate\")'>Master Certificate</a>" : "-"  ) . "</td>";
                $test12_output=$test12_output."<td>". ( (!empty($row['upload_path_test_certificate'])) ? "<a href='javascript:void(0)' onclick='return viewDocument(\"".substr($row['upload_path_test_certificate'],3)."\", \"Test Certificate\")'>Test Certificate</a>" : "-"  ) . "</td>";
                $test12_output=$test12_output."<td>". ( (!empty($row['upload_path_other_doc'])) ? "<a href='javascript:void(0)' onclick='return viewDocument(\"".substr($row['upload_path_other_doc'],3)."\", \"Other Document\")'>Other Document</a>" : "-"  ) . "</td>";
                    
                  
              }
              $test12_output=$test12_output."</table>";
          }
          
          
          
          
          $test13_query=DB::query("select test_wf_id,upload_id, upload_path_raw_data,upload_path_master_certificate,upload_path_test_certificate,upload_path_other_doc,uploaded_datetime,upload_remarks, upload_type,t2.user_name,t2.user_id from tbl_uploads t1, users t2
where t1.uploaded_by=t2.user_id and upload_action='Approved'
and test_id=13 and val_wf_id=%s order by uploaded_datetime", $_GET['val_wf_id']);
          $test13_output="";
          $count=0;
          
          if(!empty($test13_query)){
              $test13_output="Approved Certificate(s):<br/> <table class='table table-border'>";
              foreach ($test13_query as $row) {
                  $count++;
                  
                  
                  $test13_output=$test13_output."<tr>";
                  $test13_output=$test13_output."<td>".$count."</td>";
                  
                //  $test13_output=$test13_output."<td>". ( (!empty($row['upload_path_raw_data'])) ? "<a target='_blank' href='".substr($row['upload_path_raw_data'],3)."'>Test Data</a>" : "-"  ) . "</td>";
                //  $test13_output=$test13_output."<td>". ( (!empty($row['upload_path_master_certificate'])) ? "<a target='_blank' href='".substr($row['upload_path_master_certificate'],3)."'>Master Certificate</a>" : "-"  ) . "</td>";
                //  $test13_output=$test13_output."<td>". ( (!empty($row['upload_path_test_certificate'])) ? "<a target='_blank' href='".substr($row['upload_path_test_certificate'],3)."'>Test Certificate</a>" : "-"  ) . "</td>";
                //  $test13_output=$test13_output."<td>". ( (!empty($row['upload_path_other_doc'])) ? "<a target='_blank' href='".substr($row['upload_path_other_doc'],3)."'>Other Document</a>" : "-"  ) . "</td>";
                 $test13_output=$test13_output."<td>". ( (!empty($row['upload_path_raw_data'])) ? "<a href='javascript:void(0)' onclick='return viewDocument(\"".substr($row['upload_path_raw_data'],3)."\", \"Test Data\")'>Test Data</a>" : "-"  ) . "</td>";
                $test13_output=$test13_output."<td>". ( (!empty($row['upload_path_master_certificate'])) ? "<a href='javascript:void(0)' onclick='return viewDocument(\"".substr($row['upload_path_master_certificate'],3)."\", \"Master Certificate\")'>Master Certificate</a>" : "-"  ) . "</td>";
                $test13_output=$test13_output."<td>". ( (!empty($row['upload_path_test_certificate'])) ? "<a href='javascript:void(0)' onclick='return viewDocument(\"".substr($row['upload_path_test_certificate'],3)."\", \"Test Certificate\")'>Test Certificate</a>" : "-"  ) . "</td>";
                $test13_output=$test13_output."<td>". ( (!empty($row['upload_path_other_doc'])) ? "<a href='javascript:void(0)' onclick='return viewDocument(\"".substr($row['upload_path_other_doc'],3)."\", \"Other Document\")'>Other Document</a>" : "-"  ) . "</td>";
                    
                  
              }
              $test13_output=$test13_output."</table>";
          }
          
          
          
          
          $test14_query=DB::query("select test_wf_id,upload_id, upload_path_raw_data,upload_path_master_certificate,upload_path_test_certificate,upload_path_other_doc,uploaded_datetime,upload_remarks, upload_type,t2.user_name,t2.user_id from tbl_uploads t1, users t2
where t1.uploaded_by=t2.user_id and upload_action='Approved'
and test_id=14 and val_wf_id=%s order by uploaded_datetime", $_GET['val_wf_id']);
          $test14_output="";
          $count=0;
          
          if(!empty($test14_query)){
              $test14_output="Approved Certificate(s):<br/> <table class='table table-border'>";
              foreach ($test14_query as $row) {
                  $count++;
                  
                  
                  $test14_output=$test14_output."<tr>";
                  $test14_output=$test14_output."<td>".$count."</td>";
                  
               //   $test14_output=$test14_output."<td>". ( (!empty($row['upload_path_raw_data'])) ? "<a target='_blank' href='".substr($row['upload_path_raw_data'],3)."'>Test Data</a>" : "-"  ) . "</td>";
               //   $test14_output=$test14_output."<td>". ( (!empty($row['upload_path_master_certificate'])) ? "<a target='_blank' href='".substr($row['upload_path_master_certificate'],3)."'>Master Certificate</a>" : "-"  ) . "</td>";
               //   $test14_output=$test14_output."<td>". ( (!empty($row['upload_path_test_certificate'])) ? "<a target='_blank' href='".substr($row['upload_path_test_certificate'],3)."'>Test Certificate</a>" : "-"  ) . "</td>";
               //   $test14_output=$test14_output."<td>". ( (!empty($row['upload_path_other_doc'])) ? "<a target='_blank' href='".substr($row['upload_path_other_doc'],3)."'>Other Document</a>" : "-"  ) . "</td>";
                  $test14_output=$test14_output."<td>". ( (!empty($row['upload_path_raw_data'])) ? "<a href='javascript:void(0)' onclick='return viewDocument(\"".substr($row['upload_path_raw_data'],3)."\", \"Test Data\")'>Test Data</a>" : "-"  ) . "</td>";
                $test14_output=$test14_output."<td>". ( (!empty($row['upload_path_master_certificate'])) ? "<a href='javascript:void(0)' onclick='return viewDocument(\"".substr($row['upload_path_master_certificate'],3)."\", \"Master Certificate\")'>Master Certificate</a>" : "-"  ) . "</td>";
                $test14_output=$test14_output."<td>". ( (!empty($row['upload_path_test_certificate'])) ? "<a href='javascript:void(0)' onclick='return viewDocument(\"".substr($row['upload_path_test_certificate'],3)."\", \"Test Certificate\")'>Test Certificate</a>" : "-"  ) . "</td>";
                $test14_output=$test14_output."<td>". ( (!empty($row['upload_path_other_doc'])) ? "<a href='javascript:void(0)' onclick='return viewDocument(\"".substr($row['upload_path_other_doc'],3)."\", \"Other Document\")'>Other Document</a>" : "-"  ) . "</td>";
                   
                  
              }
              $test14_output=$test14_output."</table>";
          }
          
          $test15_query=DB::query("select test_wf_id,upload_id, upload_path_raw_data,upload_path_master_certificate,upload_path_test_certificate,upload_path_other_doc,uploaded_datetime,upload_remarks, upload_type,t2.user_name,t2.user_id from tbl_uploads t1, users t2
where t1.uploaded_by=t2.user_id and upload_action='Approved'
and test_id=15 and val_wf_id=%s order by uploaded_datetime", $_GET['val_wf_id']);
          $test15_output="";
          $count=0;
          
          if(!empty($test15_query)){
              $test15_output="Approved Certificate(s):<br/> <table class='table table-border'>";
              foreach ($test15_query as $row) {
                  $count++;
                  
                  
                  $test15_output=$test15_output."<tr>";
                  $test15_output=$test15_output."<td>".$count."</td>";
                  
                //  $test15_output=$test15_output."<td>". ( (!empty($row['upload_path_raw_data'])) ? "<a target='_blank' href='".substr($row['upload_path_raw_data'],3)."'>Test Data</a>" : "-"  ) . "</td>";
                //  $test15_output=$test15_output."<td>". ( (!empty($row['upload_path_master_certificate'])) ? "<a target='_blank' href='".substr($row['upload_path_master_certificate'],3)."'>Master Certificate</a>" : "-"  ) . "</td>";
                //  $test15_output=$test15_output."<td>". ( (!empty($row['upload_path_test_certificate'])) ? "<a target='_blank' href='".substr($row['upload_path_test_certificate'],3)."'>Test Certificate</a>" : "-"  ) . "</td>";
                //  $test15_output=$test15_output."<td>". ( (!empty($row['upload_path_other_doc'])) ? "<a target='_blank' href='".substr($row['upload_path_other_doc'],3)."'>Other Document</a>" : "-"  ) . "</td>";
                 $test15_output=$test15_output."<td>". ( (!empty($row['upload_path_raw_data'])) ? "<a href='javascript:void(0)' onclick='return viewDocument(\"".substr($row['upload_path_raw_data'],3)."\", \"Test Data\")'>Test Data</a>" : "-"  ) . "</td>";
                $test15_output=$test15_output."<td>". ( (!empty($row['upload_path_master_certificate'])) ? "<a href='javascript:void(0)' onclick='return viewDocument(\"".substr($row['upload_path_master_certificate'],3)."\", \"Master Certificate\")'>Master Certificate</a>" : "-"  ) . "</td>";
                $test15_output=$test15_output."<td>". ( (!empty($row['upload_path_test_certificate'])) ? "<a href='javascript:void(0)' onclick='return viewDocument(\"".substr($row['upload_path_test_certificate'],3)."\", \"Test Certificate\")'>Test Certificate</a>" : "-"  ) . "</td>";
                $test15_output=$test15_output."<td>". ( (!empty($row['upload_path_other_doc'])) ? "<a href='javascript:void(0)' onclick='return viewDocument(\"".substr($row['upload_path_other_doc'],3)."\", \"Other Document\")'>Other Document</a>" : "-"  ) . "</td>";
                    
                  
              }
              $test15_output=$test15_output."</table>";
          }
          
          $test16_query=DB::query("select test_wf_id,upload_id, upload_path_raw_data,upload_path_master_certificate,upload_path_test_certificate,upload_path_other_doc,uploaded_datetime,upload_remarks, upload_type,t2.user_name,t2.user_id from tbl_uploads t1, users t2
where t1.uploaded_by=t2.user_id and upload_action='Approved'
and test_id=16 and val_wf_id=%s order by uploaded_datetime", $_GET['val_wf_id']);
          $test16_output="";
          $count=0;
          
          if(!empty($test16_query)){
              $test16_output="Approved Certificate(s):<br/> <table class='table table-border'>";
              foreach ($test16_query as $row) {
                  $count++;
                  
                  
                  $test16_output=$test16_output."<tr>";
                  $test16_output=$test16_output."<td>".$count."</td>";
                  
                //  $test16_output=$test16_output."<td>". ( (!empty($row['upload_path_raw_data'])) ? "<a target='_blank' href='".substr($row['upload_path_raw_data'],3)."'>Test Data</a>" : "-"  ) . "</td>";
                //  $test16_output=$test16_output."<td>". ( (!empty($row['upload_path_master_certificate'])) ? "<a target='_blank' href='".substr($row['upload_path_master_certificate'],3)."'>Master Certificate</a>" : "-"  ) . "</td>";
                //  $test16_output=$test16_output."<td>". ( (!empty($row['upload_path_test_certificate'])) ? "<a target='_blank' href='".substr($row['upload_path_test_certificate'],3)."'>Test Certificate</a>" : "-"  ) . "</td>";
                //  $test16_output=$test16_output."<td>". ( (!empty($row['upload_path_other_doc'])) ? "<a target='_blank' href='".substr($row['upload_path_other_doc'],3)."'>Other Document</a>" : "-"  ) . "</td>";
                 $test16_output=$test16_output."<td>". ( (!empty($row['upload_path_raw_data'])) ? "<a href='javascript:void(0)' onclick='return viewDocument(\"".substr($row['upload_path_raw_data'],3)."\", \"Test Data\")'>Test Data</a>" : "-"  ) . "</td>";
                $test16_output=$test16_output."<td>". ( (!empty($row['upload_path_master_certificate'])) ? "<a href='javascript:void(0)' onclick='return viewDocument(\"".substr($row['upload_path_master_certificate'],3)."\", \"Master Certificate\")'>Master Certificate</a>" : "-"  ) . "</td>";
                $test16_output=$test16_output."<td>". ( (!empty($row['upload_path_test_certificate'])) ? "<a href='javascript:void(0)' onclick='return viewDocument(\"".substr($row['upload_path_test_certificate'],3)."\", \"Test Certificate\")'>Test Certificate</a>" : "-"  ) . "</td>";
                $test16_output=$test16_output."<td>". ( (!empty($row['upload_path_other_doc'])) ? "<a href='javascript:void(0)' onclick='return viewDocument(\"".substr($row['upload_path_other_doc'],3)."\", \"Other Document\")'>Other Document</a>" : "-"  ) . "</td>";
                    
                  
              }
              $test16_output=$test16_output."</table>";
          }
          
          
         
          
          
         ?> 
          
          
          
<table width="100%" cellpadding="7" cellspacing="0" border="0">
    <tr>
        <td colspan="3" Style="text-align:right;"><img src='assets/images/logo.png' width="120" height="50"/><br/>Goa&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</td>
        
    </tr>  
           
 </table>         
<br/>

<table width="100%" cellpadding="7" cellspacing="0" border="1">
    <tr>
        <td colspan="3" Style="text-align:center;"><b>PERIODIC PERFORMANCE VERIFICATION </b></td>
        
    </tr>
    <tr>
        <td width="28%">Workflow ID: <?php echo $_GET['val_wf_id'];?></td>
        <td Style="text-align:center;"  >HEATING VENTILATION AND AIR CONDITIONING (HVAC) SYSTEM

</td>
     
    </tr>
    
</table>
<br/>
<table width="100%" cellpadding="7" cellspacing="0" border="0">
        <tr>
        	<td colspan="2"><b>Unit:</b> <?php echo str_replace('Unit ','',$unit_name);?>
        
        </tr>
        <tr>
            <td>
                <b>AHU/VU No.:</b>&nbsp;&nbsp; <?php echo $equipment_details['equipment_code']?>
            </td>
            
            <td style="text-align:right">
                <b>Date of Start:</b> <?php echo isset($wf_details)? date_format(date_create($wf_details['actual_wf_start_datetime']),"d.m.Y"):"";?>
            </td>
            
        </tr>
        
</table>
        <br/>
        
<p><b>1.0&nbsp;&nbsp;&nbsp;Objective:</b></p>

<p align="justify" style=" margin-bottom: 0cm">
To establish that the HVAC system is performing as it is supposed to perform by:</p>

<p align="justify" style=" margin-bottom: 0cm">1.1&nbsp;&nbsp;Ensuring that the required temperature, relative humidity and pressure gradient is within the
limit of acceptance criteria (if applicable).
 </p>
<p align="justify" style=" margin-bottom: 0cm">1.2&nbsp;&nbsp;Ensuring that the quality of air with respect to non-viable (particulate matter count) is within the limit of acceptance criteria (if applicable). </p>
<p align="justify" style=" margin-bottom: 0cm">1.3&nbsp;&nbsp;Ensuring that the total number of air changes, Velocity and Installed filter leakages are within the limit of acceptance criteria (if applicable). </p>
<p align="justify" style=" margin-bottom: 0cm">1.4&nbsp;&nbsp;Ensure that the airflow direction and visualization is as per requirement (if applicable). </p>

<br/>

<p><b>2.0&nbsp;&nbsp;&nbsp;Justification for selection of system:</b></p>

<p align="justify">
<?php echo (isset($report_details))? $report_details['justification']: "" ?>








<p><b>3.0&nbsp;&nbsp;&nbsp;Scope:</b></p>

<p align="justify">
Applicable to all AHU System which is installed to control room conditions.
</p>


<p><b>4.0&nbsp;&nbsp;&nbsp;Site of study:</b></p>

<p align="justify">
<table width="100%" cellpadding="7" cellspacing="0" border="1">

<tr>
    <td>Site and location Name</td>
    <td>Cipla Unit <?php echo str_replace('Unit ','',$unit_name);?>, Goa</td>
</tr>

<tr>
    <td>Department Name</td>
    <td><?php echo $equipment_details['department_name']?></td>
</tr>

<tr>
    <td>HVAC Scope</td>
    <td><?php echo $equipment_details['area_served']?></td>
</tr>



</table>
</p>


<p><b>5.0&nbsp;&nbsp;&nbsp;Performance verification Team And Responsibility As Per Performance verification Documents:</b></p>

<p align="justify">
Representative From:
</p>

<table width="100%" cellpadding="7" cellspacing="0" border="1">
    <!-- User Department -->
<tr>
    <td>User Department</td>
    <td>&nbsp;<br/><?php 
        $result = DB::queryFirstField("
            SELECT t2.user_name 
            FROM tbl_report_approvers t1
            JOIN users t2 ON t1.level1_approver_user = t2.user_id
            JOIN tbl_val_wf_approval_tracking_details t3 
                ON t1.val_wf_id = t3.val_wf_id AND t1.iteration_id = t3.iteration_id
            WHERE t1.val_wf_id = %s
            AND t3.iteration_status = 'Active'",
            $_GET['val_wf_id']);
        
        echo isset($result) ? $result : ($wf_details['val_wf_current_stage']=='2'||$wf_details['val_wf_current_stage']=='3'||$wf_details['val_wf_current_stage']=='4'||$wf_details['val_wf_current_stage']=='5' ? "No Approval Required" : "___________");
    ?></td>
</tr>

<!-- Engineering -->
<tr>
    <td>Engineering</td>
    <td>&nbsp;<br/><?php 
        $result = DB::queryFirstField("
            SELECT t2.user_name 
            FROM tbl_report_approvers t1
            JOIN users t2 ON t1.level1_approver_engg = t2.user_id
            JOIN tbl_val_wf_approval_tracking_details t3 
                ON t1.val_wf_id = t3.val_wf_id AND t1.iteration_id = t3.iteration_id
            WHERE t1.val_wf_id = %s
            AND t3.iteration_status = 'Active'",
            $_GET['val_wf_id']);
        
        echo isset($result) ? $result : ($wf_details['val_wf_current_stage']=='2'||$wf_details['val_wf_current_stage']=='3'||$wf_details['val_wf_current_stage']=='4'||$wf_details['val_wf_current_stage']=='5' ? "No Approval Required" : "___________");
    ?></td>
</tr>

<!-- EHS -->
<tr>
    <td>EHS</td>
    <td>&nbsp;<br/><?php 
        $result = DB::queryFirstField("
            SELECT t2.user_name 
            FROM tbl_report_approvers t1
            JOIN users t2 ON t1.level1_approver_hse = t2.user_id
            JOIN tbl_val_wf_approval_tracking_details t3 
                ON t1.val_wf_id = t3.val_wf_id AND t1.iteration_id = t3.iteration_id
            WHERE t1.val_wf_id = %s
            AND t3.iteration_status = 'Active'",
            $_GET['val_wf_id']);
        
        echo isset($result) ? $result : ($wf_details['val_wf_current_stage']=='2'||$wf_details['val_wf_current_stage']=='3'||$wf_details['val_wf_current_stage']=='4'||$wf_details['val_wf_current_stage']=='5' ? "No Approval Required" : "___________");
    ?></td>
</tr>

<!-- Quality Control -->
<tr>
    <td>Quality Control</td>
    <td>&nbsp;<br/><?php 
        $result = DB::queryFirstField("
            SELECT t2.user_name 
            FROM tbl_report_approvers t1
            JOIN users t2 ON t1.level1_approver_qc = t2.user_id
            JOIN tbl_val_wf_approval_tracking_details t3 
                ON t1.val_wf_id = t3.val_wf_id AND t1.iteration_id = t3.iteration_id
            WHERE t1.val_wf_id = %s
            AND t3.iteration_status = 'Active'",
            $_GET['val_wf_id']);
        
        echo isset($result) ? $result : ($wf_details['val_wf_current_stage']=='2'||$wf_details['val_wf_current_stage']=='3'||$wf_details['val_wf_current_stage']=='4'||$wf_details['val_wf_current_stage']=='5' ? "No Approval Required" : "___________");
    ?></td>
</tr>

<!-- Quality Assurance -->
<tr>
    <td>Quality Assurance</td>
    <td>&nbsp;<br/><?php 
        $result = DB::queryFirstField("
            SELECT t2.user_name 
            FROM tbl_report_approvers t1
            JOIN users t2 ON t1.level1_approver_qa = t2.user_id
            JOIN tbl_val_wf_approval_tracking_details t3 
                ON t1.val_wf_id = t3.val_wf_id AND t1.iteration_id = t3.iteration_id
            WHERE t1.val_wf_id = %s
            AND t3.iteration_status = 'Active'",
            $_GET['val_wf_id']);
        
        echo isset($result) ? $result : ($wf_details['val_wf_current_stage']=='2'||$wf_details['val_wf_current_stage']=='3'||$wf_details['val_wf_current_stage']=='4'||$wf_details['val_wf_current_stage']=='5' ? "No Approval Required" : "___________");
    ?></td>
</tr>

</table>

<br/>

<p><b>6.0&nbsp;&nbsp;&nbsp;Description of the system to be verified:</b></p>

<p align="justify" style="margin-bottom: 0cm">
To establish that the HVAC system is performing as it is supposed to perform by:</p>

<p align="justify" style=" margin-bottom: 0cm">1.&nbsp;&nbsp;Prior to initiation of test, intimation to the respective department, suitable operation of HVAC unit and operation of supply and exhaust unit, if applicable etc.
 </p>
<p align="justify" style=" margin-bottom: 0cm">2.&nbsp;&nbsp;Specify ‘NA’ wherever not applicable / if other than given   specification mention separately. </p>
<p align="justify" style=" margin-bottom: 0cm">3.&nbsp;&nbsp;The acceptance criteria for non-viable particle count should be considered as per ISO/EU/WHO/USFDA guideline.
</p>
<br/>

<center>
	<table width="100%" cellpadding="7" cellspacing="0" border="1">
		<col width="128*">
		<col width="10*">
		<col width="38*">
		<col width="79*">
		<tr>
			<td width="50%" height="11" >
				<p>Area</p>
			</td>
			<td width="4%" valign="top" >
				<p>:</p>
			</td>
			<td colspan="2" width="46%" valign="top" >
				<p><?php echo $equipment_details['area_served']?></p>
			</td>
		</tr>
		
<tr>
			<td width="50%" height="11" >
				<p>Equipment No.</p>
			</td>
			<td width="4%" valign="top" >
				<p>:</p>
			</td>
			<td colspan="2" width="46%" valign="top" >
				<p><?php echo $equipment_details['equipment_code']?></p>
			</td>
		</tr>

<tr>
			<td width="50%" height="11" >
				<p>Type of AHU recorded in</p>
			</td>
			<td width="4%" valign="top" >
				<p>:</p>
			</td>
			<td colspan="2" width="46%" valign="top" >
				<p><?php echo $equipment_details['equipment_type']?>
</p>
			</td>
		</tr>


<?php 

$prev_val_wf_id=DB::queryFirstField("select val_wf_id from  tbl_val_wf_tracking_details where unit_id=".intval($wf_details['unit_id'])."
and equipment_id=".intval($equipment_details['equipment_id'])." and val_wf_current_stage=5 and val_wf_id !='".$_GET['val_wf_id']."' order by actual_wf_start_datetime desc
    Limit 1");
if(!empty($prev_val_wf_id))
{
    $principal_test_unit=DB::queryFirstField("select primary_test_id from units where unit_id=".intval($wf_details['unit_id']));
    
    $prev_val_completed_on=DB::queryFirstField("select test_conducted_date from tbl_test_schedules_tracking where val_wf_id ='".$prev_val_wf_id."' and test_id=".intval($principal_test_unit));
    
    
}
else 
{
    $prev_val_completed_on='';
}

?>

<tr>
			<td width="50%" height="11" >
				<p>Previous verification/Qualification done on</p>
			</td>
			<td width="4%" valign="top" >
				<p>:</p>
			</td>
			<td colspan="2" width="46%" valign="top" >
				<p><?php echo (empty($prev_val_completed_on))?'NA':date_format(date_create($prev_val_completed_on),"d.m.Y") ?></p>
			</td>
		</tr>


               
		
                
		
                
		
                <tr>
			<td width="50%" height="11" >
				<p>Design Capacity of AHU in CFM.</p>
			</td>
			<td width="4%" valign="top" >
				<p>:</p>
			</td>
			<td colspan="2" width="46%" valign="top" >
				<p><?php echo empty($equipment_details['design_cfm'])?"NA":$equipment_details['design_cfm'];?></p>
			</td>
		</tr>
		
               
   		<tr>
			<td rowspan="2" width="50%" height="4" >
				<p>Classification
				of Area catered by HVAC system and particle count occupancy state
				at which class is achieved.</p>
			</td>
			<td rowspan="2" width="4%" valign="top" >
				<p>:</p>
			</td>
			<td width="23%" valign="top" >
				<p>At Rest</p>
			</td>
			<td width="23%" valign="top" >
				<p>In Operation</p>
			</td>
		</tr>
		<tr>
			<td width="23%" >
				<p><?php echo empty($equipment_details['area_classification'])?"NA":$equipment_details['area_classification'];?></p>

		
			</td>
			<td width="23%" valign="top">
				<p><?php echo empty($equipment_details['area_classification_in_operation'])?"NA":$equipment_details['area_classification_in_operation'];?></p>


			</td>
		</tr>
		
	</table>
</center>





<center>
	<table width="100%" cellpadding="7" cellspacing="0" border="1">
		<col width="128*">
		<col width="10*">
		<col width="38*">
		<col width="79*">
		
                <tr>
                    <td colspan="4" height="11"><b>Filtration</b></td>
                    
                </tr>
                <tr>
			<td width="50%" height="11" >
				<p>Fresh air filter (if applicable)</p>
			</td>
			<td width="4%" valign="top" >
				<p>:</p>
			</td>
			<td colspan="2" width="46%" valign="top" >
				<p><?php echo empty($equipment_details['filteration_fresh_air'])?"NA":$equipment_details['filteration_fresh_air']; ?></p>
			</td>
		</tr>
		
                <tr>
			<td width="50%" height="11">
				<p>Intermediate</p>
			</td>
			<td width="4%" valign="top" >
				<p>:</p>
			</td>
			<td colspan="2" width="46%" valign="top" >
				<p><?php echo empty($equipment_details['filteration_intermediate'])?"NA":$equipment_details['filteration_intermediate']; ?></p>
			</td>
		</tr>

           <tr>
           <td width="50%" height="11"> <p>Pre filter (if applicable)</p>
           </td>
           <td width="4%" valign="top" >
				<p>:</p>
			</td>
			<td colspan="2" width="46%" valign="top" >
				<p><?php echo empty($equipment_details['filteration_pre_filter'])?"NA":$equipment_details['filteration_pre_filter']; ?></p>
			</td>
		</tr>
		
                <tr>
			<td width="50%" height="11" >
				<p>Fine filter (Supply)</p>
			</td>
			<td width="4%" valign="top" >
				<p>:</p>
			</td>
			<td colspan="2" width="46%" valign="top" >
				<p><?php echo empty($equipment_details['filteration_final_filter_plenum'])?"NA":$equipment_details['filteration_final_filter_plenum']; ?> </p>
			</td>
		</tr>
		
                <tr>
			<td width="50%" height="11" >
				<p>Exhaust Pre filter (if applicable)</p>
			</td>
			<td width="4%" valign="top" >
				<p>:</p>
			</td>
			<td colspan="2" width="46%" valign="top" >
				<p><?php echo empty($equipment_details['filteration_exhaust_pre_filter'])?"NA":$equipment_details['filteration_exhaust_pre_filter'];?></p>
			</td>
		</tr>
		
                <tr>
			<td width="50%" height="11" >
				<p>Exhaust final Filter (if applicable)</p>
			</td>
			<td width="4%" valign="top" >
				<p>:</p>
			</td>
			<td colspan="2" width="46%" valign="top" >
				<p><?php echo empty($equipment_details['filteration_exhaust_final_filter'])?"NA":$equipment_details['filteration_exhaust_final_filter']; ?> </p>
			</td>
		</tr>
		
                <tr>
			<td width="50%" height="11" >
				<p>Terminal filter (If applicable)</p>
			</td>
			<td width="4%" valign="top" >
				<p>:</p>
			</td>
			<td colspan="2" width="46%" valign="top" >
				<p><?php echo empty($equipment_details['filteration_terminal_filter'])?"NA":$equipment_details['filteration_terminal_filter']; ?></p>
			</td>
		</tr>
		
                <tr>
			<td width="50%" height="11" >
				<p>Bag in Bag out filter (If applicable)</p>
			</td>
			<td width="4%" valign="top" >
				<p>:</p>
			</td>
			<td colspan="2" width="46%" valign="top" >
				<p><?php echo empty($equipment_details['filteration_bibo_filter'])?"NA":$equipment_details['filteration_bibo_filter'];?></p>
			</td>
		</tr>
		
        <tr>
			<td width="50%" height="11" >
				<p>Relief filter</p>
			</td>
			<td width="4%" valign="top" >
				<p>:</p>
			</td>
			<td colspan="2" width="46%" valign="top" >
				<p><?php echo empty($equipment_details['filteration_relief_filter'])?"NA":$equipment_details['filteration_relief_filter']; ?> </p>
			</td>
		</tr>
		
        <tr>
			<td width="50%" height="11" >
				<p>Filter on Return riser </p>
			</td>
			<td width="4%" valign="top" >
				<p>:</p>
			</td>
			<td colspan="2" width="46%" valign="top" >
				<p><?php echo empty($equipment_details['filteration_terminal_filter_on_riser'])?"NA":$equipment_details['filteration_terminal_filter_on_riser']; ?></p>
			</td>
		</tr>
		
		 <tr>
			<td width="50%" height="11" >
				<p>Terminal Filter on Riser </p>
			</td>
			<td width="4%" valign="top" >
				<p>:</p>
			</td>
			<td colspan="2" width="46%" valign="top" >
				<p><?php echo empty($equipment_details['filteration_terminal_filter_on_riser'])?"NA":$equipment_details['filteration_terminal_filter_on_riser'];?></p>
			</td>
		</tr>
		
		 <tr>
			<td width="50%" height="11" >
				<p>Reactivation Filter</p>
			</td>
			<td width="4%" valign="top" >
				<p>:</p>
			</td>
			<td colspan="2" width="46%" valign="top" >
				<p><?php echo empty($equipment_details['filteration_reativation_filter'])?"NA":$equipment_details['filteration_reativation_filter'];?></p>
			</td>
		</tr>
		
	</table>
</center>
<br/>
<p><b>7.0&nbsp;&nbsp;&nbsp;Standard Operating Procedures (SOPs) and Microbiological methods (MMs) to be followed:</b></p>

<p align="justify">








<table width="100%" cellpadding="7" cellspacing="0" border="1">


<tr>
    <th>Sr. No.</th>
    <th>SOP/Document Name</th>
    <th>SOP/Document No. Including Version No.</th>
    <th>Data Entered by Sign & Date</th>
</tr>

<tr>
<td>1.</td>
<td>SOP for operating the HVAC System</td>
<td><?php echo (empty($report_details)||empty($report_details['sop1_doc_number'])) ? "NA": $report_details['sop1_doc_number']?></td>
<td><?php echo (empty($report_details)||empty($report_details['sop1_doc_number']))? "NA": $initiated_by."<br>".date('d.m.Y H:i:s',strtotime($report_details['sop1_entered_date']))?></td>
</tr>

<tr>
<td>2.</td>
<td>SOP for recording pressure difference with respect to adjacent area / atmosphere.</td>
<td><?php echo (empty($report_details)||empty($report_details['sop2_doc_number']))? "NA": $report_details['sop2_doc_number']?></td>
<td><?php echo (empty($report_details)||empty($report_details['sop2_doc_number']))? "NA":  $initiated_by."<br>".date('d.m.Y H:i:s',strtotime($report_details['sop2_entered_date']))?></td>
</tr>

<tr>
<td>3.</td>
<td>SOP for Air velocity measurement and calculation of number of air changes</td>	
<td><?php echo (empty($report_details)||empty($report_details['sop3_doc_number']))? "NA": $report_details['sop3_doc_number']?></td>
<td><?php echo (empty($report_details)||empty($report_details['sop3_doc_number']))? "NA":  $initiated_by."<br>".date('d.m.Y H:i:s',strtotime($report_details['sop3_entered_date']))?></td>
</tr>

<tr>
<td>4.</td>
<td>SOP for checking installed filter system leakages</td>
<td><?php echo (empty($report_details)||empty($report_details['sop4_doc_number']))? "NA": $report_details['sop4_doc_number']?></td>
<td><?php echo (empty($report_details)||empty($report_details['sop4_doc_number']))? "NA":  $initiated_by."<br>".date('d.m.Y H:i:s',strtotime($report_details['sop1_entered_date']))?></td>
</tr>

<tr>
<td>5.</td>
<td>SOP for checking of particulate matter count.</td>
<td><?php echo (empty($report_details)||empty($report_details['sop5_doc_number']))? "NA": $report_details['sop5_doc_number']?></td>
<td><?php echo (empty($report_details)||empty($report_details['sop5_doc_number']))? "NA":  $initiated_by."<br>".date('d.m.Y H:i:s',strtotime($report_details['sop5_entered_date']))?></td>
</tr>

<tr>
<td>6.</td>
<td>SOP for airflow direction test and
visualization.
</td>
<td><?php echo (empty($report_details)||empty($report_details['sop6_doc_number']))? "NA": $report_details['sop6_doc_number']?></td>
<td><?php echo (empty($report_details)||empty($report_details['sop6_doc_number']))? "NA":  $initiated_by."<br>".date('d.m.Y H:i:s',strtotime($report_details['sop6_entered_date']))?></td>
</tr>


<tr>
<td>7.</td>
<td>SOP for BMS start stop operation. (if applicable)</td>
<td><?php echo (empty($report_details)||empty($report_details['sop7_doc_number']))? "NA": $report_details['sop7_doc_number']?></td>
<td><?php echo (empty($report_details)||empty($report_details['sop7_doc_number']))? "NA":  $initiated_by."<br>".date('d.m.Y H:i:s',strtotime($report_details['sop7_entered_date']))?></td>
</tr>

<tr>
<td>8.</td>
<td>SOP for Duct leakage Measurement. </td>
<td><?php echo (empty($report_details)||empty($report_details['sop8_doc_number']))? "NA": $report_details['sop8_doc_number']?></td>
<td><?php echo (empty($report_details)||empty($report_details['sop8_doc_number']))? "NA":  $initiated_by."<br>".date('d.m.Y H:i:s',strtotime($report_details['sop8_entered_date']))?></td>
</tr>

<tr>
<td>9.</td>
<td>SOP for area recovery / clean up period study. 

</td>
<td><?php echo (empty($report_details)||empty($report_details['sop9_doc_number']))? "NA": $report_details['sop9_doc_number']?></td>
<td><?php echo (empty($report_details)||empty($report_details['sop9_doc_number']))? "NA":  $initiated_by."<br>".date('d.m.Y H:i:s',strtotime($report_details['sop9_entered_date']))?></td>
</tr>

<tr>
<td>10.</td>
<td>SOP for containment leakage test. </td>
<td><?php echo (empty($report_details)||empty($report_details['sop10_doc_number']))? "NA": $report_details['sop10_doc_number']?></td>
<td><?php echo (empty($report_details)||empty($report_details['sop10_doc_number']))? "NA":  $initiated_by."<br>".date('d.m.Y H:i:s',strtotime($report_details['sop10_entered_date']))?></td>
</tr> 

<tr>
<td>11.</td>
<td>SOP scrubber / Point exhaust CFM</td>
<td><?php echo (empty($report_details)||empty($report_details['sop11_doc_number']))? "NA": $report_details['sop11_doc_number']?></td>
<td><?php echo (empty($report_details)||empty($report_details['sop11_doc_number']))? "NA":  $initiated_by."<br>".date('d.m.Y H:i:s',strtotime($report_details['sop11_entered_date']))?></td>
</tr>

<tr>
<td>12.</td>
<td>Microbiological method (MM) for environmental monitoring </td>
<td><?php echo (empty($report_details)||empty($report_details['sop12_doc_number']))? "NA": $report_details['sop12_doc_number']?></td>
<td><?php echo (empty($report_details)||empty($report_details['sop12_doc_number']))? "NA":  $initiated_by."<br>".date('d.m.Y H:i:s',strtotime($report_details['sop12_entered_date']))?></td>
</tr>

<tr>
<td>13.</td>
<td>Additional SOP Details</td>
<td><?php echo (empty($report_details)||empty($report_details['sop13_doc_number']))? "NA": $report_details['sop13_doc_number']?></td>
<td><?php echo (empty($report_details)||empty($report_details['sop13_doc_number']))? "NA":  $initiated_by."<br>".date('d.m.Y H:i:s',strtotime($report_details['sop13_entered_date']))?></td>
</tr>









</table>


 </p>


<br/>

<p><b>8.0&nbsp;&nbsp;&nbsp;Controls</b></p>




<p>8.1&nbsp;&nbsp;&nbsp;Ensure the calibration details of instrument used for performance</p>
<p>8.2&nbsp;&nbsp;&nbsp;Training should be available for concern person.</p>

<p>
<table class='table table-bordered' cellpadding="7" cellspacing="0" border="1">
<tr><th>Name</th><th>Department</th><th>Training Status</th></tr>
<?php 
    if(empty($training_details))
    {
        echo "<tr><td colspan='3'>NA</td></tr>";
    }
    else
    {
    foreach($training_details as $row)
    {
        echo "<tr>";
        echo "<td>".$row['user_name'] ."</td>";
        echo "<td>".(empty($row['department_name'])?"External Agency":$row['department_name']) ."</td>";
        
        // Check if file_path exists and is not empty
        if (!empty($row['file_path'])) {
            // Create a hyperlink that opens the file using the same viewDocument approach
            $fileUrl = BASE_URL . $row['file_path'];
echo "<td><a href='javascript:void(0)' onclick='return viewDocument(\"{$fileUrl}\", \"Training Certificate\")'>{$row['training_status']}</a></td>";
        } else {
            // If no file path, just show the training status without a link
            echo "<td>".$row['training_status']."</td>";
        }
        
        echo "</tr>";
    }
    }
    
?>


</table>



</p>

<p>8.3&nbsp;&nbsp;&nbsp;Ensure that all required precautions should be taken as per operation SOP.</p>
<p>8.4&nbsp;&nbsp;&nbsp;Gowning procedure used by personnel should be as per area requirement.</p>
<br/>

<p><b>9.0&nbsp;&nbsp;&nbsp;Verification Procedure:</b></p>



 <br/>
<table width="100%" cellpadding="7" cellspacing="0" border="1">









<tr>
<td rowspan="3">1.</td>
<td>Test</td>
<td>Number of air changes per hour in the area.</td>
</tr>


<tr>

<td>Acceptance Criteria</td>
<td><table class='table table-bordered'><tr><th>Sr No.</th><th>Area</th><th>Air change per hours</th></tr><tr><td>1</td><td>ISO Class 8</td><td>More than 10</td></tr><tr><td>2</td><td>ISO Class 7 </td><td>More than 20</td></tr><tr><td>3</td><td>ISO Class 5</td><td>More than 30</td></tr><tr><td>4</td><td>Controlled Not Classified (CNC)</td><td>More than 06</td></tr></table></td>


</tr>

<tr>

<td>Observations</td>
<td><?php echo (empty($report_details) || empty($report_details['test1_observation']))? "Not applicable":  $report_details['test1_observation']?> <br/> <?php echo  $test1_output?>
<?php echo !empty($report_details['test1_observation'])?"<br>Test Performed by External Vendor: ".getVendorByTestId(1, $testVendorMap):''; ?>







</td>


</tr>


<tr>
<td rowspan="3">2.</td>
<td>Test</td>
<td>Fresh air quantity in CFM</td>
</tr>


<tr>

<td>Acceptance Criteria</td>
<td>Should not be less than 10% of area CFM</td>

</tr>

<tr>

<td>Observations</td>
<td><?php echo (empty($report_details) || empty($report_details['test2_observation']))? "Not applicable":  $report_details['test2_observation']?> <br/> <?php echo  $test2_output?> 
<?php echo !empty($report_details['test2_observation'])?"<br>Test Performed by External Vendor: ".getVendorByTestId(2, $testVendorMap):''; ?></td>

</tr>


<tr>
<td rowspan="3">3.</td>
<td>Test</td>
<td>Return air CFM at diffuser / riser / riser filter in the area (if applicable)</td>
</tr>


<tr>

<td>Acceptance Criteria</td>
<td>To be checked at actual for monitoring purpose only when all exhaust systems are 'ON'.</td>


</tr>

<tr>

<td>Observations</td>
<td><?php echo (empty($report_details) || empty($report_details['test3_observation']))? "Not applicable":  $report_details['test3_observation']?> <br/> <?php echo  $test3_output?> 
<?php echo !empty($report_details['test3_observation'])?"<br>Test Performed by External Vendor: ".getVendorByTestId(3, $testVendorMap):''; ?></td>

</tr>

<tr>
<td rowspan="3">4.</td>
<td>Test</td>
<td>Relief air CFM through relief air filter of HVAC (if applicable)</td>
</tr>


<tr>

<td>Acceptance Criteria</td>
<td>Should be NMT 30%</td>


</tr>

<tr>

<td>Observations</td>
<td><?php echo (empty($report_details) || empty($report_details['test4_observation']))? "Not applicable":  $report_details['test4_observation']?> <br/> <?php echo $test4_output?> 
<?php echo !empty($report_details['test4_observation'])?"<br>Test Performed by External Vendor: ".getVendorByTestId(4, $testVendorMap):''; ?></td>

</tr>



<tr>
<td rowspan="3">5.</td>
<td>Test</td>
<td>Filter Integrity Testing to be done for the Installed filters in the HVAC System. </td>
</tr>


<tr>

<td>Acceptance Criteria</td>
<td>Any leakage should not be more than 0.01% of upstream
challenge aerosol concentration.
</td>

</tr>

<tr>

<td>Observations</td>
<td><?php echo (empty($report_details) || empty($report_details['test6_observation']))? "Not applicable":  $report_details['test6_observation']?> <br/> <?php echo  $test6_output?> 
<?php echo !empty($report_details['test6_observation'])?"<br>Test Performed by External Vendor: ".getVendorByTestId(6, $testVendorMap):''; ?></td>
</tr>


<tr>
<td rowspan="3">6.</td>
<td>Test</td>
<td>Check dust collector / scrubber / point exhaust CFM (If applicable).</td>
</tr>


<tr>

<td>Acceptance Criteria</td>
<td>To be checked at actual</td>


</tr>

<tr>

<td>Observations</td>
<td><?php echo (empty($report_details) || empty($report_details['test7_observation']))? "Not applicable":  $report_details['test7_observation']?> <br/> <?php echo $test7_output?>
<?php echo !empty($report_details['test7_observation'])?"<br>Test Performed by External Vendor: ".getVendorByTestId(7, $testVendorMap):''; ?></td>

</tr>


<tr>
<td rowspan="3">7.</td>
<td>Test</td>
<td>Comprehensive Temperature test and Relative humidity (%) in the area. In case of BMS, corresponding trends to be attached as Annexure.</td>
</tr>


<tr>

<td>Acceptance Criteria</td>
<td>Limit should meet the environmental condition of
corresponding unit level SOP.
</td>

</tr>

<tr>

<td>Observations</td>

<td><?php echo (empty($report_details) || empty($report_details['test8_observation'] ))? "Not applicable":  $report_details['test8_observation']?> <br/> <?php echo  $test8_output?> </td>

</tr>


<tr>
<td rowspan="3">8.</td>
<td>Test</td>
<td>Air Differential pressure in the area with respect to adjacent area/ atmosphere (if applicable).</td>
</tr>


<tr>

<td>Acceptance Criteria</td>
<td>Air differential pressure in the area with respect to adjacent area/atmosphere should be within limit and for actual readings refer attached annexure , if applicable.</td>

</tr>

<tr>

<td>Observations</td>

<td><?php echo (empty($report_details) || empty($report_details['test9_observation']))? "Not applicable":  $report_details['test9_observation']?> <br/> <?php echo  $test9_output?></td>

</tr>


<tr>
<td rowspan="3">9.</td>
<td>Test</td>
<td>Airflow direction test and visualization</td>
</tr>


<tr>

<td>Acceptance Criteria</td>
<td>The smoke should be diffused uniformly at supply
grill/diffusers to room and pass through return
grill/diffusers/riser. The smoke should pass from positive
area to negative area.
</td>

</tr>

<tr>

<td>Observations</td>

<td><?php echo (empty($report_details) || empty($report_details['test10_observation']))? "Not applicable":  $report_details['test10_observation']?> <br/> <?php echo  $test10_output?> </td>

</tr>

<tr>
<td rowspan="3">10.</td>
<td>Test</td>
<td>Particulate matter count ("At rest" condition)</td>
</tr>


<tr>

<td>Acceptance Criteria</td>
<td><p>No. of particles/m3</p>
<p>Maximum concentration limits</p>
<p>As per EC/WHO/ISO guideline</p>





<table class='table table-bordered' cellpadding="7" cellspacing="0" border="1">
<tr><th>Grade / ISO Class</th><th>0.5μ</th><th>5μ</th></tr>
<tr><td>ISO Class 5 / Grade A</td><td>3520</td><td>20</td></tr>
<tr><td>ISO Class 5 / Grade B</td><td>3520 </td><td>29</td></tr>
<tr><td>ISO Class 7/ Grade C</td><td>3,52,000</td><td>2900 (As per EC/WHO guideline)<br/> 2930 (As per ISO guideline)</td></tr>
<tr><td>ISO Class 8 / Grade D</td><td>35,20,000</td><td>29000 (As per EC/WHO guideline)<br/> 29300 (As per ISO guideline)</td></tr></table>
</td>

</tr>

<tr>

<td>Observations</td>

<td><?php echo (empty($report_details) || empty($report_details['test11_observation']))? "Not applicable":  $report_details['test11_observation']?> <br/> <?php echo  $test11_output?> 
<?php echo !empty($report_details['test11_observation'])?"<br>Test Performed by External Vendor: ".getVendorByTestId(11, $testVendorMap):''; ?></td>
</tr>

<tr>
<td rowspan="3">11.</td>
<td>Test</td>
<td>Particulate matter count ("In operation" condition)</td>
</tr>


<tr>

<td>Acceptance Criteria</td>
<td><p>No. of particles/m3</p>
<p>Maximum concentration limits</p>


<table class='table table-bordered' cellpadding="7" cellspacing="0" border="1">
<tr><th colspan="3">As per USFDA guideline</th><th colspan="4">As per EC/WHO/ISO guideline</th></tr>
<tr><th>Area Class</th><th>ISO Class</th><th>0.5μ</th><th>Grade</th><th>ISO Class</th><th>0.5μ</th><th>5μ </th></tr>
<tr><td>100</td><td>5</td><td>3520</td><td>A</td><td>5</td><td>3520</td><td>20 </td></tr>
<tr><td>10000</td><td>7</td><td>352000</td><td>B</td><td>7</td><td>352000</td><td>2900 </td></tr>
<tr><td>100000</td><td>8</td><td>3520000</td><td>C</td><td>8</td><td>3520000</td><td>29000 </td></tr>
<tr><td>NA</td><td>NA</td><td>NA</td><td>D</td><td>NA</td><td>Not defined</td><td>Not defined</td></tr>
</table>
</td>

</tr>

<tr>

<td>Observations</td>
<td><?php echo (empty($report_details) || empty($report_details['test12_observation']))? "Not applicable":  $report_details['test12_observation']?> <br/> <?php echo $test12_output?>
<?php echo !empty($report_details['test12_observation'])?"<br>Test Performed by External Vendor: ".getVendorByTestId(12, $testVendorMap):''; ?></td>

</tr>

<tr>
<td rowspan="3">12.</td>
<td>Test</td>
<td>Containment leakage test (if applicable)</td>
</tr>


<tr>

<td>Acceptance Criteria</td>
<td>As per respective SOP</td>

</tr>

<tr>

<td>Observations</td>
<td><?php echo (empty($report_details) || empty($report_details['test13_observation']))? "Not applicable":  $report_details['test13_observation']?> <br/> <?php echo  $test13_output?> 
<?php echo !empty($report_details['test13_observation'])?"<br>Test Performed by External Vendor: ".getVendorByTestId(13, $testVendorMap):''; ?></td>

</tr>

<tr>
<td rowspan="3">13.</td>
<td>Test</td>
<td>Area recovery / clean-up period study.</td>
</tr>


<tr>

<td>Acceptance Criteria</td>
<td>As per respective SOP</td>

</tr>

<tr>

<td>Observations</td>
<td><?php echo (empty($report_details) || empty($report_details['test14_observation']))? "Not applicable":  $report_details['test14_observation']?> <br/> <?php echo  $test14_output?> 
<?php echo !empty($report_details['test14_observation'])?"<br>Test Performed by External Vendor: ".getVendorByTestId(14, $testVendorMap):''; ?></td>

</tr>

<tr>
<td rowspan="3">14.</td>
<td>Test</td>
<td>Microbial count by settle plate exposure and Air Sampling (If applicable)</td>
</tr>


<tr>

<td>Acceptance Criteria</td>
<td>Should not be more than the limit specified in Microbiological methods (MM) for monitoring environmental control</td>

</tr>

<tr>

<td>Observations</td>
<td><?php echo (empty($report_details) || empty($report_details['test15_observation']))? "Not applicable":  $report_details['test15_observation']?> <br/> <?php echo  $test15_output?></td>


</tr>

<tr>
<td rowspan="3">15.</td>
<td>Equipment Maintenance Details </td>
<td>All the Planned Preventive Maintenance & Filter Cleaning Activity records to be Reviewed since previous Periodic Performance Verification.  </td>
</tr>


<tr>

<td>Acceptance Criteria</td>
<td>All the PPM & Filter Cleaning Activities to be performed & Recorded as per their respective applicable SOP’s. </td>

</tr>

<tr>

<td>Observations</td>
<td><?php echo (empty($report_details) || empty($report_details['test16_observation']))? "Not applicable":  "Reviewed and Found Satisfactory" ?> <br/><?php echo  $test16_output?></td>


</tr>




</table>

 
 

<p align="justify">
        <b>Note:</b> Microbiological Monitoring is carried out separately as per schedule, Reports or trends to be attached Since last Periodic Performance verification date, (if applicable). 

</p>
<br/>


<p><b>10.0&nbsp;&nbsp;&nbsp;Frequency:</b></p>

<p align="justify">
For Classified area, performance verification study shall be performed once in a year or whenever the changes are incorporated in the area, Equipment or HVAC system. 

</p>
        <p align="justify">
For Non-Classified areas/General areas, performance verification study shall be performed every Two years or whenever the changes are incorporated in the area, Equipment or HVAC system. 

</p>
<br/>


<p><b>11.0&nbsp;&nbsp;&nbsp;Deviation/Out of specifications (If any):</b></p>

<p align="justify">
<?php echo (empty($report_details['deviation']))? "NA":  $report_details['deviation']?>
</p>
<br/>

<p><b>12.0&nbsp;&nbsp;&nbsp;Review of deviation, change request, and CAPA since last verification:</b></p>

<p align="justify">
<?php echo (empty($report_details['deviation_review']))? "NA": $report_details['deviation_review']?>
</p>
<br/>


<p><b>13.0&nbsp;&nbsp;&nbsp;Summary of performance verification:</b></p>

<p align="justify">
<?php echo (empty($report_details['summary']))? "NA":  $report_details['summary']?>
</p>



<p><b>14.0&nbsp;&nbsp;&nbsp;Recommendation:</b></p>

<p align="justify">
<?php echo (empty($report_details['recommendationn']))? "NA":  $report_details['recommendationn']?>
</p>




        <p><b>15.0&nbsp;&nbsp;&nbsp;Team Approval :</b></p>
        
<p align="justify">

<table width="100%" cellpadding="7" cellspacing="0" border="1">
<tr>
    <th>
        Department
    </th>
    <th>
        Approval Remark 
    </th>
    <th>
        Approved By 
    </th>
    <th>
        Approved On
    </th>
</tr>

<?php 
// Get approval details from the active iteration
$result1 = DB::queryFirstRow("
    SELECT t2.user_name, t1.level1_user_dept_approval_datetime as app_date, level1_user_dept_approval_remarks as remarks 
    FROM tbl_val_wf_approval_tracking_details t1 
    JOIN users t2 ON t1.level1_user_dept_approval_by = t2.user_id 
    WHERE t1.val_wf_id = %s AND t1.iteration_status = 'Active'", 
    $_GET['val_wf_id']);

$result2 = DB::queryFirstRow("
    SELECT t2.user_name, t1.level1_eng_approval_datetime as app_date, level1_eng_approval_remarks as remarks 
    FROM tbl_val_wf_approval_tracking_details t1 
    JOIN users t2 ON t1.level1_eng_approval_by = t2.user_id 
    WHERE t1.val_wf_id = %s AND t1.iteration_status = 'Active'", 
    $_GET['val_wf_id']);

$result3 = DB::queryFirstRow("
    SELECT t2.user_name, t1.level1_hse_approval_datetime as app_date, level1_hse_approval_remarks as remarks 
    FROM tbl_val_wf_approval_tracking_details t1 
    JOIN users t2 ON t1.level1_hse_approval_by = t2.user_id 
    WHERE t1.val_wf_id = %s AND t1.iteration_status = 'Active'", 
    $_GET['val_wf_id']);

$result4 = DB::queryFirstRow("
    SELECT t2.user_name, t1.level1_qc_approval_datetime as app_date, level1_qc_approval_remarks as remarks 
    FROM tbl_val_wf_approval_tracking_details t1 
    JOIN users t2 ON t1.level1_qc_approval_by = t2.user_id 
    WHERE t1.val_wf_id = %s AND t1.iteration_status = 'Active'", 
    $_GET['val_wf_id']);

$result5 = DB::queryFirstRow("
    SELECT t2.user_name, t1.level1_qa_approval_datetime as app_date, level1_qa_approval_remarks as remarks 
    FROM tbl_val_wf_approval_tracking_details t1 
    JOIN users t2 ON t1.level1_qa_approval_by = t2.user_id 
    WHERE t1.val_wf_id = %s AND t1.iteration_status = 'Active'", 
    $_GET['val_wf_id']);
?>

<tr>
    <td>Engineering</td>
    <td><?php echo isset($result2) && isset($result2['remarks']) ? $result2['remarks'] : "NA"; ?></td>
    <td>
        <?php echo isset($result2) && isset($result2['user_name']) ? $result2['user_name'] : ($wf_details['val_wf_current_stage']=='2'||$wf_details['val_wf_current_stage']=='3'||$wf_details['val_wf_current_stage']=='4'||$wf_details['val_wf_current_stage']=='5' ? "No Approval Required" : "___________"); ?>
    </td>
    <td><?php echo isset($result2) && isset($result2['app_date']) ? date("d.m.Y H:i:s", strtotime($result2['app_date'])) : "NA"; ?> </td>
</tr>

<tr>
    <td>User</td>
    <td><?php echo isset($result1) && isset($result1['remarks']) ? $result1['remarks'] : "NA"; ?></td>
    <td>
        <?php echo isset($result1) && isset($result1['user_name']) ? $result1['user_name'] : ($wf_details['val_wf_current_stage']=='2'||$wf_details['val_wf_current_stage']=='3'||$wf_details['val_wf_current_stage']=='4'||$wf_details['val_wf_current_stage']=='5' ? "No Approval Required" : "___________"); ?>
    </td>
    <td><?php echo isset($result1) && isset($result1['app_date']) ? date("d.m.Y H:i:s", strtotime($result1['app_date'])) : "NA"; ?></td>
</tr>

<tr>
    <td>EHS</td>
    <td><?php echo isset($result3) && isset($result3['remarks']) ? $result3['remarks'] : "NA"; ?></td>
    <td>
        <?php echo isset($result3) && isset($result3['user_name']) ? $result3['user_name'] : ($wf_details['val_wf_current_stage']=='2'||$wf_details['val_wf_current_stage']=='3'||$wf_details['val_wf_current_stage']=='4'||$wf_details['val_wf_current_stage']=='5' ? "No Approval Required" : "___________"); ?>
    </td>
    <td><?php echo isset($result3) && isset($result3['app_date']) ? date("d.m.Y H:i:s", strtotime($result3['app_date'])) : "NA"; ?></td>
</tr>

<tr>
    <td>Quality Control</td>
    <td><?php echo isset($result4) && isset($result4['remarks']) ? $result4['remarks'] : "NA"; ?></td>
    <td>
        <?php echo isset($result4) && isset($result4['user_name']) ? $result4['user_name'] : ($wf_details['val_wf_current_stage']=='2'||$wf_details['val_wf_current_stage']=='3'||$wf_details['val_wf_current_stage']=='4'||$wf_details['val_wf_current_stage']=='5' ? "No Approval Required" : "___________"); ?>
    </td>
    <td><?php echo isset($result4) && isset($result4['app_date']) ? date("d.m.Y H:i:s", strtotime($result4['app_date'])) : "NA"; ?></td>
</tr>

<tr>
    <td>Quality Assurance</td>
    <td><?php echo isset($result5) && isset($result5['remarks']) ? $result5['remarks'] : "NA"; ?></td>
    <td>
        <?php echo isset($result5) && isset($result5['user_name']) ? $result5['user_name'] : ($wf_details['val_wf_current_stage']=='2'||$wf_details['val_wf_current_stage']=='3'||$wf_details['val_wf_current_stage']=='4'||$wf_details['val_wf_current_stage']=='5' ? "No Approval Required" : "___________"); ?>
    </td>
    <td><?php echo isset($result5) && isset($result5['app_date']) ? date("d.m.Y H:i:s", strtotime($result5['app_date'])) : "NA"; ?></td>
</tr>
</table>





</p>



        <p><b>16.0&nbsp;&nbsp;&nbsp;Review (Inclusive of follow up action, if any):</b></p>
        <?php 
    $unitheadaslevel2 = DB::queryFirstRow("
        SELECT t1.level2_unit_head_approval_remarks, t2.user_name, t1.level2_unit_head_approval_datetime as app_date 
        FROM tbl_val_wf_approval_tracking_details t1
        JOIN users t2 ON t1.level2_unit_head_approval_by = t2.user_id 
        WHERE t1.val_wf_id = %s AND t1.iteration_status = 'Active'", 
        $_GET['val_wf_id']);
    
    if($unitheadaslevel2 && isset($unitheadaslevel2['level2_unit_head_approval_remarks']))
    {
        echo $unitheadaslevel2['level2_unit_head_approval_remarks'];
    }
    else
    {
        echo "NA".'<br/>';
    }
?>
         
        <p><br/><b>17.0&nbsp;&nbsp;&nbsp;Approved By :</b></p>
       
<p align="justify">
<table>
        <tr>
        <td>

            <?php 
    // Make sure $unitheadaslevel2 is fetched from the query with 'Active' status
    // If this code is separate from the previous block, you'll need to run the query again
    if (!isset($unitheadaslevel2)) {
        $unitheadaslevel2 = DB::queryFirstRow("
            SELECT t1.level2_unit_head_approval_remarks, t2.user_name, t1.level2_unit_head_approval_datetime as app_date 
            FROM tbl_val_wf_approval_tracking_details t1
            JOIN users t2 ON t1.level2_unit_head_approval_by = t2.user_id 
            WHERE t1.val_wf_id = %s AND t1.iteration_status = 'Active'", 
            $_GET['val_wf_id']);
    }
    
    if($unitheadaslevel2 && isset($unitheadaslevel2['user_name']))
    {
        echo $unitheadaslevel2['user_name'];
    }
    else
    {
        echo "NA";
    }
?>

         </td>
            <td>&nbsp;<br/> </td>
            
            
   <td>&nbsp;</td>
         <td>&nbsp;</td>
         
        </tr>
        <tr>
<td>
<?php
if($unitheadaslevel2 && isset($unitheadaslevel2['user_name']))
        {
            echo ' <br/>Unit Head<br/> Date:'.date('d.m.Y H:i:s',strtotime($unitheadaslevel2['app_date']));
        }
        else
        {
            echo "NA";
        }

        ?>
</td>

         </td>
            <td>&nbsp;</td>
            
            
   <td>&nbsp;</td>
         <td>&nbsp;</td>
         
         
        </tr>
       
        
        </table>
</p>






<p><b>18.0&nbsp;&nbsp;&nbsp;Approved By:</b></p>
<?php 
    $resultheadqa = DB::queryFirstRow("
        SELECT t1.level3_head_qa_approval_remarks, t2.user_name, t1.level3_head_qa_approval_datetime as app_date 
        FROM tbl_val_wf_approval_tracking_details t1
        JOIN users t2 ON t1.level3_head_qa_approval_by = t2.user_id 
        WHERE t1.val_wf_id = %s AND t1.iteration_status = 'Active'", 
        $_GET['val_wf_id']);
?>

<p align="justify">
<table>
    <tr>
        <td>&nbsp;<?php echo isset($resultheadqa) && isset($resultheadqa['level3_head_qa_approval_remarks']) ? $resultheadqa['level3_head_qa_approval_remarks'] : "___________"; ?><br/> <br/> 
        <?php echo isset($resultheadqa) && isset($resultheadqa['user_name']) ? $resultheadqa['user_name'] : "___________"; ?> </td>
        <td>&nbsp;</td>
        <td>&nbsp;</td>
        <td>&nbsp;</td>
    </tr>
    
    <tr>
        <td><br/>Head Unit Quality Assurance<br/> Date: <?php echo isset($resultheadqa) && isset($resultheadqa['app_date']) ? $resultheadqa['app_date'] : "___________"; ?> </td>
        <td>&nbsp;</td>
        <td>&nbsp;</td>
        <td>&nbsp;</td>
    </tr>
</table>
</p>
        



        <p><b>19.0&nbsp;&nbsp;&nbsp;Abbreviations:</b></p>
        
<p align="justify">
<center>
<table width="80%" cellspacing="0" cellpadding="1" border="0">
    <col width="128*">
		<col width="10*">
		<col width="38*">
		<col width="79*">
        <tr>
            <td width="15%">AHU </td>
            <td width="4%">:</td>
            <td colspan="2" width="81%">Air Handling Unit </td>
        </tr>
        
        <tr>
            <td>ACPH </td>
            <td>:</td>
            <td colspan="2">Air Changes per Hour </td>
        </tr>
        
        <tr>
            <td>BMS </td>
            <td>:</td>
            <td colspan="2">Building Management system </td>
        </tr>
        
        <tr>
            <td>CD </td>
            <td>:</td>
            <td colspan="2">Compact Disc </td>
        </tr>
        
        <tr>
            <td>CFM </td>
            <td>:</td>
            <td colspan="2">Cubic Feet per Minute </td>
        </tr>
        
        <tr>
            <td>Dept. </td>
            <td>:</td>
            <td colspan="2">Department </td>
        </tr>
        
        <tr>
            <td>EC </td>
            <td>:</td>
            <td colspan="2">European Commission </td>
        </tr>
        
        <tr>
            <td>EU </td>
            <td>:</td>
            <td colspan="2">Eurovent </td>
        </tr>
        
        <tr>
            <td>GMP </td>
            <td>:</td>
            <td colspan="2">Good Manufacturing Practice </td>
        </tr>
        
        <tr>
            <td>EHS </td>
            <td>:</td>
            <td colspan="2">Environment Health and Safety </td>
        </tr>
        
        <tr>
            <td>HVAC </td>
            <td>:</td>
            <td colspan="2">Heating  and Air Conditioning System </td>
        </tr>
        
        <tr>
            <td>ISO </td>
            <td>:</td>
            <td colspan="2">International Organization for Standardization </td>
        </tr>
        
        <tr>
            <td>m3 </td>
            <td>:</td>
            <td colspan="2">Cubic meter </td>
        </tr>
        
        <tr>
            <td>mm </td>
            <td>:</td>
            <td colspan="2">Millimetre </td>
        </tr>
        
        <tr>
            <td>NA </td>
            <td>:</td>
            <td colspan="2">Not Applicable </td>
        </tr>
        
        <tr>
            <td>No.	 </td>
            <td>:</td>
            <td colspan="2">Number </td>
        </tr>
        
        <tr>
            <td>OSD </td>
            <td>:</td>
            <td colspan="2">Oral Solid Dosage </td>
        </tr>
        
        <tr>
            <td>SOP </td>
            <td>:</td>
            <td colspan="2">Standard Operating Procedure </td>
        </tr>
        
        <tr>
            <td>VFD </td>
            <td>:</td>
            <td colspan="2">Variable Frequency Drive </td>
        </tr>
        
        <tr>
            <td>WHO </td>
            <td>:</td>
            <td colspan="2">World Health Organization </td>
        </tr>
        
        <tr>
            <td>% </td>
            <td>:</td>
            <td colspan="2">Percentage </td>
        </tr>
        
        <tr>
            <td>mu </td>
            <td>:</td>
            <td colspan="2">Micron </td>
        </tr>
        
        
        </table>
        </center>
</p>


          <p><b>20.0&nbsp;&nbsp;&nbsp;References:</b></p>
          
<p align="justify">
<center>
<table width="80%" cellspacing="0" cellpadding="1" border="0">
    <col width="128*">
		<col width="10*">
		<col width="38*">
		<col width="79*">
        <tr>
            <td width="15%">ISO 14644 </td>
            <td width="4%">:</td>
            <td colspan="2" width="81%">Clean rooms and associated controlled environments.</td>
        </tr>
        
        <tr>
            <td>Part - 1  </td>
            <td>:</td>
            <td colspan="2">Classification of air cleanliness by particle concentration </td>
        </tr>
        
        <tr>
            <td>Part - 2 </td>
            <td>:</td>
            <td colspan="2">Monitoring to provide evidence of cleanroom performance related to air cleanliness by particle concentration</td>
        </tr>
        
        <tr>
            <td>Part - 3  </td>
            <td>:</td>
            <td colspan="2">Metrology and test methods.</td>
        </tr>
        
        <tr>
            <td>Part - 4 </td>
            <td>:</td>
            <td colspan="2">Design, Construction and Start up.</td>
        </tr>
         <tr>
         
            <td colspan="4">WHO Technical Report Series No.961, 2011 </td>
        </tr>
        <tr>
        
            <td colspan="4">EC (Brussels, March 2009 </td>
        </tr>
        <tr>
        
            <td colspan="4">"SCHEDULE - M - THE GAZETTE OF INDIA." 2006 </td>
        </tr>
        <tr>
            <td>1035-G-0045</td>
            <td>:</td>
            <td colspan="2">Temperature and relative humidity distribution study </td>
        </tr>
        
        
        </table>
        </center>
        </p>

<!-- Add this at the bottom of _protocoltext.php -->
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
</script>