<?php
require_once('./core/config/config.php');

// Session is already started by config.php via session_init.php

// Validate session timeout
require_once('core/security/session_timeout_middleware.php');
validateActiveSession();

// Use centralized session validation
require_once('core/security/session_validation.php');
validateUserSession();

date_default_timezone_set("Asia/Kolkata");

// Security Configuration Constants
define('DEPT_ENGINEERING', 1);
define('DEPT_QA', 8);
define('DEPT_QC', 0);
define('DEPT_MICROBIOLOGY', 6);
define('DEPT_HSE', 7);

define('STAGE_NEW_TASK', '1');
define('STAGE_PENDING_APPROVAL', '2');
define('STAGE_UNIT_HEAD_APPROVAL', '3');
define('STAGE_QA_HEAD_APPROVAL', '4');
define('STAGE_COMPLETED', '5');
define('STAGE_REASSIGNED_A', '3A');
define('STAGE_REASSIGNED_B', '3B');
define('STAGE_REASSIGNED_4B', '4B');
define('STAGE_REASSIGNED_4A', '4A');

require_once("core/config/db.class.php");
require_once 'core/security/secure_query_wrapper.php';

// Additional security validation - validate user type
$userType = $_SESSION['logged_in_user'] ?? '';
if (!in_array($userType, ['employee', 'vendor'])) {
    session_destroy();
    header('Location: login.php?msg=invalid_user_type');
    exit();
}

// Generate CSRF token for file upload form
$csrf_token_for_upload = generateCSRFToken();

// Generate CSRF token if not already set
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Secure input validation for GET parameters
$test_val_wf_id = secure_get('test_val_wf_id', 'string');
$test_id = secure_get('test_id', 'int');  
$current_wf_stage = secure_get('current_wf_stage', 'string');

// Enhanced parameter validation with performance considerations
if (!$test_val_wf_id || !$test_id || !$current_wf_stage) {
    // Log security event and redirect
    error_log('Invalid parameters for task details: ' . json_encode([
        'user_id' => $_SESSION['user_id'],
        'test_val_wf_id' => $test_val_wf_id,
        'test_id' => $test_id,
        'current_wf_stage' => $current_wf_stage
    ]));
    header('Location: assignedcases.php?error=invalid_parameters');
    exit;
}

// Additional stage validation using constants for better performance
$validStages = [
    STAGE_NEW_TASK,
    STAGE_PENDING_APPROVAL, 
    STAGE_UNIT_HEAD_APPROVAL,
    STAGE_QA_HEAD_APPROVAL,
    STAGE_COMPLETED,
    STAGE_REASSIGNED_A,
    STAGE_REASSIGNED_B,
    STAGE_REASSIGNED_4B
];

if (!in_array($current_wf_stage, $validStages)) {
    error_log('Invalid workflow stage: ' . $current_wf_stage . ' for user: ' . $_SESSION['user_id']);
    header('Location: assignedcases.php?error=invalid_stage');
    exit;
}

// Initialize data variables with fallbacks
$audit_trails = [];
$result = ['test_name' => 'Error Loading Test', 'test_purpose' => 'Unable to load test details'];
$workflow_details = ['wf_stage_description' => 'Error Loading Stage'];
$test_conducted_date = null;
$equipment = 'Error Loading Equipment';

// Optimized database queries with performance improvements
try {
    // Handle different query logic for vendor vs employee users
    if (isVendor()) {
        // Vendor users: Join without unit_id constraint since vendors may access across units
        $mainDataQuery = "
            SELECT 
                t.test_name,
                t.test_purpose,
                t.test_performed_by,
                w.wf_stage_description,
                ts.test_conducted_date,
                e.equipment_code
            FROM tests t
            LEFT JOIN workflow_stages w ON w.wf_stage = %s
            LEFT JOIN tbl_test_schedules_tracking ts ON ts.test_wf_id = %s
            LEFT JOIN equipments e ON e.equipment_id = ts.equip_id
            WHERE t.test_id = %i
        ";
        $mainData = DB::queryFirstRow($mainDataQuery, $current_wf_stage, $test_val_wf_id, $test_id);
    } else {
        // Employee users: Include unit_id constraint for data segregation
        $mainDataQuery = "
            SELECT 
                t.test_name,
                t.test_purpose,
                t.test_performed_by,
                w.wf_stage_description,
                ts.test_conducted_date,
                e.equipment_code
            FROM tests t
            LEFT JOIN workflow_stages w ON w.wf_stage = %s
            LEFT JOIN tbl_test_schedules_tracking ts ON ts.test_wf_id = %s AND ts.unit_id = %i
            LEFT JOIN equipments e ON e.equipment_id = ts.equip_id
            WHERE t.test_id = %i
        ";
        $mainData = DB::queryFirstRow($mainDataQuery, $current_wf_stage, $test_val_wf_id, getUserUnitId(), $test_id);
    }
    
    if ($mainData) {
        $result = [
            'test_name' => $mainData['test_name'] ?? 'Unknown Test',
            'test_purpose' => $mainData['test_purpose'] ?? 'No purpose specified'
        ];
        $workflow_details = [
            'wf_stage_description' => $mainData['wf_stage_description'] ?? 'Unknown Stage'
        ];
        $test_conducted_date = $mainData['test_conducted_date'];
        $equipment = $mainData['equipment_code'] ?? 'Unknown Equipment';
    }
    
} catch (Exception $e) {
    // Log error and use fallback values already set above
    error_log("Database error in updatetaskdetails.php (main_data): " . $e->getMessage());
}

try {
    // Separate optimized query for audit trails (cannot be easily combined)
    $audit_trails = DB::query("
        SELECT 
            t1.time_stamp as 'wf-assignedtimestamp', 
            t2.wf_stage_description as 'wf-stages'
        FROM audit_trail t1
        INNER JOIN workflow_stages t2 ON t1.wf_stage = t2.wf_stage
        WHERE t1.test_wf_id = %s 
          AND t2.wf_type = 'External Test'
        ORDER BY t1.time_stamp ASC
    ", $test_val_wf_id);

} catch (Exception $e) {
    // Log error and use fallback empty array
    error_log("Database error in updatetaskdetails.php (audit_trails): " . $e->getMessage());
    $audit_trails = [];
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
  <?php include_once "assets/inc/_header.php"; ?>
  
  <!-- Security Headers -->
  <meta name="csrf-token" content="<?php echo $_SESSION['csrf_token']; ?>">
  <meta http-equiv="X-Content-Type-Options" content="nosniff">
  <meta http-equiv="X-Frame-Options" content="DENY">
  <meta http-equiv="X-XSS-Protection" content="1; mode=block">
  
  <!-- Performance Optimization -->
  <link rel="dns-prefetch" href="//cdn.jsdelivr.net">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <script>
    $(document).ready(function() {

// Enhanced modal show event to log file views
$('#imagepdfviewerModal').on('show.bs.modal', function (e) {
  var src = $(e.relatedTarget).attr('href');
  var uploadId = $(e.relatedTarget).data('upload-id');
  var fileType = $(e.relatedTarget).data('file-type');
  var testWfId = $(e.relatedTarget).data('test-wf-id') || $('#test_wf_id').val();
  
  // Generate a unique view ID for this modal open event
  var viewId = Date.now().toString();
  
  // Only log the view if this is a file download link and is triggered from modal
  if (uploadId && fileType && e.relatedTarget) {
    // Log the file view
    $.ajax({
      url: 'core/debug/log_file_view.php',
      type: 'POST',
      data: {
        upload_id: uploadId,
        file_type: fileType,
        file_path: src,
        test_val_wf_id: testWfId,
        view_id: viewId
      },
      success: function(response) {
        console.log('File view logged from modal');
        
        // Track document views for all employee users
        if (typeof trackDocumentView === 'function') {
          trackDocumentView(uploadId, fileType);
        }
      },
      error: function(xhr, status, error) {
        console.error('Error logging file view:', error);
      }
    });
  }
  
  if(src.indexOf('.pdf')==-1) {
    $(this).find('.modal-body > iframe').attr('src', '');
    $(this).find('.modal-body > iframe').attr('hidden', true);
    $(this).find('.modal-body > img.image_modal').attr('src', src);
    $(this).find('.modal-body > img.image_modal').attr('hidden', false);
  } else {
    $(this).find('.modal-body > iframe').attr('src', src);
    $(this).find('.modal-body > iframe').attr('hidden', false);
    $(this).find('.modal-body > img.image_modal').attr('src', '');
    $(this).find('.modal-body > img.image_modal').attr('hidden', true);
  }
});

// Function to track document views for all employee users
function trackDocumentView(uploadId, fileType) {
  // Use different storage keys for different contexts to maintain compatibility
  let storageKey;
  if (current_wf_stage === '<?php echo STAGE_REASSIGNED_A; ?>' && department_id === '<?php echo DEPT_QA; ?>') {
    // Keep existing QA-specific key for backward compatibility
    storageKey = 'qaDocumentViews_3A_' + test_val_wf_id;
  } else {
    // Use general key for all other users/stages
    storageKey = 'documentViews_' + current_wf_stage + '_' + department_id + '_' + test_val_wf_id;
  }
  
  const uniqueId = uploadId + '_' + fileType;
  
  let viewedDocuments = [];
  const existingData = sessionStorage.getItem(storageKey);
  if (existingData) {
    try {
      viewedDocuments = JSON.parse(existingData);
    } catch (e) {
      console.error('Error loading document views:', e);
    }
  }
  
  if (!viewedDocuments.includes(uniqueId)) {
    viewedDocuments.push(uniqueId);
    sessionStorage.setItem(storageKey, JSON.stringify(viewedDocuments));
    
    // Update visual indicator
    const link = $('[data-upload-id="' + uploadId + '"][data-file-type="' + fileType + '"]');
    link.css('color', 'green');
    link.css('font-weight', 'bold');
    link.attr('title', 'Document reviewed');
    
    console.log('Document view tracked:', uniqueId);
  }
}

// Function to check if all documents have been viewed for QA at stage 3A
function allQADocumentsViewed() {
  if (current_wf_stage !== '3A' || department_id !== '8') {
    return true; // Not applicable
  }
  
  const storageKey = 'qaDocumentViews_3A_' + test_val_wf_id;
  let viewedDocuments = [];
  
  const existingData = sessionStorage.getItem(storageKey);
  if (existingData) {
    try {
      viewedDocuments = JSON.parse(existingData);
    } catch (e) {
      console.error('Error loading QA document views:', e);
    }
  }
  
  // Get all download links in the uploaded files table
  const downloadLinks = $('#targetDocLayer .file-download-link');
  
  if (downloadLinks.length === 0) {
    return true; // No documents to view
  }
  
  // Check if all documents have been viewed
  let allViewed = true;
  downloadLinks.each(function() {
    const uploadId = $(this).data('upload-id');
    const fileType = $(this).data('file-type');
    const uniqueId = uploadId + '_' + fileType;
    
    if (!viewedDocuments.includes(uniqueId)) {
      allViewed = false;
      return false; // Break the loop
    }
  });
  
  return allViewed;
}

// Function to restore viewed document states from sessionStorage on page load
function restoreViewedDocumentStates() {
  console.log('Restoring viewed document states...');
  
  // Get storage keys for different contexts
  let storageKeys = [];
  
  // QA-specific key for stage 3A
  if (current_wf_stage === '<?php echo STAGE_REASSIGNED_A; ?>' && department_id === '<?php echo DEPT_QA; ?>') {
    storageKeys.push('qaDocumentViews_3A_' + test_val_wf_id);
  }
  
  // General key for all users/stages
  storageKeys.push('documentViews_' + current_wf_stage + '_' + department_id + '_' + test_val_wf_id);
  
  // Process each storage key
  storageKeys.forEach(function(storageKey) {
    const existingData = sessionStorage.getItem(storageKey);
    if (existingData) {
      try {
        const viewedDocuments = JSON.parse(existingData);
        
        viewedDocuments.forEach(function(uniqueId) {
          const parts = uniqueId.split('_');
          const uploadId = parts[0];
          const fileType = parts.slice(1).join('_'); // Handle file types with underscores
          
          const link = $('[data-upload-id="' + uploadId + '"][data-file-type="' + fileType + '"]');
          if (link.length > 0) {
            link.addClass('viewed');
            link.attr('title', 'Document reviewed');
          }
        });
        
        console.log('Restored', viewedDocuments.length, 'document views from', storageKey);
      } catch (e) {
        console.error('Error restoring viewed document states from', storageKey, ':', e);
      }
    }
  });
}

      // Function to convert date format
      function convertDateFormat(dateString) {
        var dateParts = dateString.split('.');
        return dateParts[2] + '-' + dateParts[1] + '-' + dateParts[0];
      }

      // Function to format upload error messages into readable bullet points
      function formatUploadErrorMessage(errorMessage) {
        if (!errorMessage) return 'Files could not be uploaded.';
        
        // Check if it's a compound error message with multiple file errors
        if (errorMessage.includes('Error uploading upload file')) {
          let formattedMessage = '';
          let errors = [];
          
          // Split by comma and process each error
          let parts = errorMessage.split(',');
          for (let i = 0; i < parts.length; i++) {
            let part = parts[i].trim();
            if (part.includes('Error uploading upload file')) {
              let fileType = '';
              let errorText = '';
              
              // Extract file type and error
              if (part.includes('raw data:')) {
                fileType = 'Raw Data';
                errorText = part.split('raw data:')[1].trim();
              } else if (part.includes('master:')) {
                fileType = 'Master Certificate';
                errorText = part.split('master:')[1].trim();
              } else if (part.includes('certificate:')) {
                fileType = 'Test Certificate';
                errorText = part.split('certificate:')[1].trim();
              } else {
                // Generic file error
                let match = part.match(/Error uploading upload file ([^:]+):\s*(.+)/);
                if (match) {
                  fileType = match[1].charAt(0).toUpperCase() + match[1].slice(1);
                  errorText = match[2].trim();
                }
              }
              
              if (fileType && errorText) {
                errors.push('• ' + fileType + ': ' + errorText);
              }
            }
          }
          
          if (errors.length > 0) {
            return errors.join('<br>');
          }
        }
        
        // For single error messages or unrecognized format
        return errorMessage;
      }

      $("#test_conducted_date").datepicker({
        dateFormat: 'dd.mm.yy',
        changeMonth: true,
        beforeShow: function(input, inst) {
          // Disable manual input by preventing focus on the input field
          setTimeout(function() {
            $(input).prop('readonly', true);
          }, 0);
        }
      });

      is_doc_uploaded = "no";
      is_remark_added = "no";

      test_id = "<?php echo htmlspecialchars($test_id, ENT_QUOTES, 'UTF-8'); ?>";
      val_wf_id = "<?php echo htmlspecialchars(secure_get('val_wf_id', 'string'), ENT_QUOTES, 'UTF-8'); ?>";
      test_val_wf_id = "<?php echo htmlspecialchars($test_val_wf_id, ENT_QUOTES, 'UTF-8'); ?>";
      current_wf_stage = "<?php echo htmlspecialchars($current_wf_stage, ENT_QUOTES, 'UTF-8'); ?>";
      logged_in_user = "<?php echo htmlspecialchars($_SESSION['logged_in_user'], ENT_QUOTES, 'UTF-8'); ?>";
      department_id = "<?php echo htmlspecialchars(!empty($_SESSION['department_id']) ? $_SESSION['department_id'] : '', ENT_QUOTES, 'UTF-8'); ?>";
      read_mode = "<?php echo htmlspecialchars((!empty(secure_get('mode', 'string')) ? 'yes' : 'no'), ENT_QUOTES, 'UTF-8'); ?>";



      $(document).on('click', '.navlink-approve', async function(e) {


        e.preventDefault();

        if (read_mode == 'yes') {
          alert("No action allowed");
        } else

        {

          const result = await Swal.fire({
            title: 'Are you sure?',
            text: 'Do you want to approve the files? This action cannot be undone.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes',
            cancelButtonText: 'No'
          });


          if (result.isConfirmed) {

            $.post("core/data/update/updateuploadstatus.php", {
                up_id: $(this).attr('data-upload-id'),
                test_val_wf_id: $('#test_wf_id').val(),
                action: 'approve',
                wf_stage: current_wf_stage,
                csrf_token: $('meta[name="csrf-token"]').attr('content')


              },
              async function(data, status) {


                $.ajax({
                  url: "core/data/get/getuploadedfiles.php", // Your PHP script to generate the content
                  method: "GET",
                  data: {
                    test_val_wf_id: $('#test_wf_id').val()
                    // Add more parameters as needed
                  },
                  success: function(data) {
                    $("#targetDocLayer").html(data);
                    
                    // Restore viewed document states after table update
                    setTimeout(function() {
                      restoreViewedDocumentStates();
                    }, 100);
                  },
                  error: function() {
                    alert("Failed to reload section.");
                  }
                });
                const result = await Swal.fire({
                  icon: 'success', // 'error' is the icon for an error message
                  title: 'Success',
                  text: 'The files were successfully approved.'
                });
              });
          }
        }
      });


      $(document).on('click', '.navlink-reject', async function(e) {
        e.preventDefault();
        //alert('clicked');
        if (read_mode == 'yes') {
          alert("No action allowed");
        } else

        {
          const result = await Swal.fire({
            title: 'Are you sure?',
            text: 'Do you want to reject the files? This action cannot be undone.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes',
            cancelButtonText: 'No'
          });


          if (result.isConfirmed) {
            $.post("core/data/update/updateuploadstatus.php", {
                up_id: $(this).attr('data-upload-id'),
                test_val_wf_id: $('#test_wf_id').val(),
                action: 'reject',
                csrf_token: $('meta[name="csrf-token"]').attr('content')
              },
              async function(data, status) {

                //$("#targetDocLayer").html(data);
                //$("#targetDocLayer").load('core/getuploadedfiles.php?test_val_wf_id='+$('#test_wf_id').val());
                $.ajax({
                  url: "core/data/get/getuploadedfiles.php", // Your PHP script to generate the content
                  method: "GET",
                  data: {
                    test_val_wf_id: $('#test_wf_id').val()
                    // Add more parameters as needed
                  },
                  success: function(data) {
                    $("#targetDocLayer").html(data);
                    
                    // Restore viewed document states after table update
                    setTimeout(function() {
                      restoreViewedDocumentStates();
                    }, 100);
                  },
                  error: function() {
                    alert("Failed to reload section.");
                  }
                });
                const result = await Swal.fire({
                  icon: 'success', // 'error' is the icon for an error message
                  title: 'Success',
                  text: 'The files were successfully rejected.'
                });
               

              });


          }









        }
      });










      $("#vendorsubmitassign").click(function() {

        // alert(current_wf_stage);      
        url = "core/data/update/updatewfstage.php?test_conducted_date=" + convertDateFormat($('#test_conducted_date').val()) + "&val_wf_id=" + val_wf_id + "&test_val_wf_id=" + test_val_wf_id + "&current_wf_stage=" + current_wf_stage + "&action=assign";

        if ($(".navlink-approve")[0]) {
          Swal.fire({
            icon: 'error',
            title: 'Oops...',
            text: 'You have one or more files to be approved.'
          });

        } else if ($('#test_conducted_date').val() == '') {

          Swal.fire({
            icon: 'error',
            title: 'Oops...',
            text: 'Kindly input the Test Conducted Date.'
          });
        } else {

          
          $('#enterPasswordRemark').modal('show');
        }

      });



      $("#vendorsubmitreassign").click(function() {
        //alert(current_wf_stage);
        if (current_wf_stage == '<?php echo STAGE_REASSIGNED_B; ?>') {
          url = "core/data/update/updatewfstage.php?test_conducted_date=" + convertDateFormat($('#test_conducted_date').val()) + "&val_wf_id=" + val_wf_id + "&test_val_wf_id=" + test_val_wf_id + "&current_wf_stage=" + current_wf_stage + "&action=assign_back_engg_vendor";
        } else if (current_wf_stage == '<?php echo STAGE_REASSIGNED_4B; ?>') {

          url = "core/data/update/updatewfstage.php?test_conducted_date=" + convertDateFormat($('#test_conducted_date').val()) + "&val_wf_id=" + val_wf_id + "&test_val_wf_id=" + test_val_wf_id + "&current_wf_stage=" + current_wf_stage + "&action=assign_back_qa_vendor";

        }

        $('#enterPasswordRemark').modal('show');
      });


      $("#enggsubmit").click(function() {

        url = "core/data/update/updatewfstage.php?test_conducted_date=" + convertDateFormat($('#test_conducted_date').val()) + "&test_type=internal&val_wf_id=" + val_wf_id + "&test_val_wf_id=" + test_val_wf_id + "&current_wf_stage=" +
          current_wf_stage + "&action=assign";


        if ($(".navlink-approve")[0]) {
          // alert('You have one or more files to be approved.');
          Swal.fire({
            icon: 'error',
            title: 'Oops...',
            text: 'You have one or more files to be approved.'
          });
        } else if ($('#test_conducted_date').val() == '') {

          Swal.fire({
            icon: 'error',
            title: 'Oops...',
            text: 'Kindly input the Test Conducted Date.'
          });

        } else {
          $('#enterPasswordRemark').modal('show');
        }


      });

      $("#enggapprove").click(function() {


        url = "core/data/update/updatewfstage.php?test_conducted_date=" + convertDateFormat($('#test_conducted_date').val()) + "&val_wf_id=" + val_wf_id + "&test_val_wf_id=" + test_val_wf_id + "&current_wf_stage=" + current_wf_stage + "&action=engg_approve";

        if ($(".navlink-approve")[0]) {
          Swal.fire({
            icon: 'error',
            title: 'Oops...',
            text: 'You have one or more files to be approved.'
          });

        } else if ($('#test_conducted_date').val() == '') {
          Swal.fire({
            icon: 'error',
            title: 'Oops...',
            text: 'Kindly input the Test Conducted Date.'
          });

        } else {
          $('#enterPasswordRemark').modal('show');
        }


      });

      $("#enggreject").click(function() {


        url = "core/data/update/updatewfstage.php?test_conducted_date=" + convertDateFormat($('#test_conducted_date').val()) + "&val_wf_id=" + val_wf_id + "&test_val_wf_id=" + test_val_wf_id + "&current_wf_stage=" + current_wf_stage + "&action=engg_reject";

        if ($(".navlink-approve")[0]) {
          Swal.fire({
            icon: 'error',
            title: 'Oops...',
            text: 'You have one or more files to be approved.'
          });
        } else {
          $('#enterPasswordRemark').modal('show');
        }
      });

      $("#enggapproval1").click(function() {
        url = "core/data/update/updatewfstage.php?test_conducted_date=" + convertDateFormat($('#test_conducted_date').val()) + "&val_wf_id=" + val_wf_id + "&test_val_wf_id=" + test_val_wf_id + "&current_wf_stage=" + current_wf_stage + "&action=engg_approve_final";
        if ($(".navlink-approve")[0]) {
          Swal.fire({
            icon: 'error',
            title: 'Oops...',
            text: 'You have one or more files to be approved.'
          });
        } else {
          $('#enterPasswordRemark').modal('show');
        }
      });

      $("#qaapprove").click(function() {
        url = "core/data/update/updatewfstage.php?test_conducted_date=" + convertDateFormat($('#test_conducted_date').val()) + "&val_wf_id=" + val_wf_id + "&test_val_wf_id=" + test_val_wf_id + "&current_wf_stage=" + current_wf_stage + "&action=qa_approve";
        
        // Check if current_wf_stage is 3A and user is QA team
        if (current_wf_stage === '<?php echo STAGE_REASSIGNED_A; ?>' && department_id === '<?php echo DEPT_QA; ?>') {
          if (!allQADocumentsViewed()) {
            Swal.fire({
              icon: 'error',
              title: 'Action Required',
              text: 'You must review all documents by opening them in the modal viewer before approving.'
            });
            return;
          }
        }
        
        if ($(".navlink-approve")[0]) {
          Swal.fire({
            icon: 'error',
            title: 'Oops...',
            text: 'You have one or more files to be approved.'
          });
        } else {
          $('#enterPasswordRemark').modal('show');
        }
      });

      $("#qareject").click(function() {
        url = "core/data/update/updatewfstage.php?test_conducted_date=" + convertDateFormat($('#test_conducted_date').val()) + "&val_wf_id=" + val_wf_id + "&test_val_wf_id=" + test_val_wf_id + "&current_wf_stage=" + current_wf_stage + "&action=qa_reject";
        
        // Check if current_wf_stage is 3A and user is QA team  
        if (current_wf_stage === '<?php echo STAGE_REASSIGNED_A; ?>' && department_id === '<?php echo DEPT_QA; ?>') {
          if (!allQADocumentsViewed()) {
            Swal.fire({
              icon: 'error',
              title: 'Action Required',
              text: 'You must review all documents by opening them in the modal viewer before rejecting.'
            });
            return;
          }
        }
        
        if ($(".navlink-approve")[0]) {
          Swal.fire({
            icon: 'error',
            title: 'Oops...',
            text: 'You have one or more files to be approved.'
          });
        } else {

           // Set up success callback to refresh file display after QA rejection
           setSuccessCallback(function(response) {
            // Refresh the uploaded files section to show rejected status
            $.ajax({
              url: "core/getuploadedfiles.php",
              method: "GET", 
              data: {
                test_val_wf_id: test_val_wf_id
              },
              success: function(data) {
                $("#targetDocLayer").html(data);
                console.log('File display refreshed after QA rejection');
              },
              error: function() {
                console.error('Failed to refresh file display after QA rejection');
              }
            });
            
            // Show success message
            Swal.fire({
              icon: 'success',
              title: 'Task Rejected',
              text: 'The task and all associated files have been rejected successfully.'
            }).then(() => {
              // Proceed with normal workflow redirect
              if (typeof url !== 'undefined') {
                window.location.href = url;
              }
            });
          });
          




          $('#enterPasswordRemark').modal('show');
        }
      });
/*
      $("#formmodalvalidation").on('submit', (function(e) {
        e.preventDefault();
        if ($("#user_remark").val().length == 0 || $("#user_password").val().length == 0) {
          //alert("Heloooooooooooooooo");
        } else {
          //alert(url);
          adduserremark($("#user_remark").val(), $("#user_password").val());
        }
      }));

*/

      $('input[type="file"]').change(function(e) {
        var fileExtensions = ['pdf'];
        var fileName = e.target.files[0].name;
        fileExtension = fileName.replace(/^.*\./, '');

        if ($.inArray(fileExtension, fileExtensions) == -1) {
          Swal.fire({
            icon: 'error',
            title: 'Oops...',
            text: 'The file extension is not allowed. Allowed file extension(s): pdf.'
          });

          $(this).val('');
        }
      });

      $("#uploadDocForm").on('submit', (function(e) {
        e.preventDefault();

        var fileName1 = $("#upload_file_raw_data").val();
        var fileName2 = $("#upload_file_master").val();
        var fileName3 = $("#upload_file_certificate").val();
        var fileName4 = $("#upload_file_other").val();

        if ((!fileName1 && !fileName2 && !fileName3 && !fileName4) || (logged_in_user == 'vendor' && (!fileName1 || !fileName2 || !fileName3))) {

          if (logged_in_user == "employee") {

            Swal.fire({
              icon: 'error',
              title: 'Oops...',
              text: 'No file selected for uploading.'
            });

          } else {

            Swal.fire({
              icon: 'error',
              title: 'Oops...',
              text: 'Test Raw Data, Master Certificate and Test Certificate files must be uploaded. One or more file(s) are missing.'
            });

          }

        } else { // returns true if the string is not empty

          var str = $('#test_wf_id').val();

          var formData = new FormData(this);

          formData.append('test_wf_id', str);
          formData.append('test_id', test_id);
          formData.append('val_wf_id', val_wf_id);
          $("#prgDocsUpload").css('display', 'block');

          $("#btnUploadDocs").prop("value", "Please wait...");

          $.ajax({
            url: "core/validation/fileupload_revised.php",
            type: "POST",
            data: formData,
            contentType: false,
            cache: false,
            processData: false,
            success: function(data) {
              $("input[type=file]").val('');
              if (data.indexOf('Error') !== -1) {
                // Parse and format the error message
                let errorMessage = 'Files could not be uploaded.';
                
                try {
                  // Try to parse as JSON first
                  let response = JSON.parse(data);
                  if (response.error) {
                    errorMessage = formatUploadErrorMessage(response.error);
                  }
                } catch (e) {
                  // If it's not JSON, use the data directly but try to format it
                  if (data.includes('Error uploading upload file')) {
                    errorMessage = formatUploadErrorMessage(data);
                  } else {
                    errorMessage = data;
                  }
                }
                
                Swal.fire({
                  icon: 'error', // 'error' is the icon for an error message
                  title: 'Upload Failed!',
                  html: errorMessage,

                  customClass: {
                    content: 'text-left'
                  }
                });
              } else {
                Swal.fire({
                  icon: 'success', // 'error' is the icon for an error message
                  title: 'Success',
                  text: data
                });
              }
              //   alert(data);
              //   $("#uploadedfiles").html(data);
              is_doc_uploaded = "yes";


              $("#prgDocsUpload").css('display', 'none');

              $("#btnUploadDocs").prop("value", "Upload Documents");
              $("#btnUploadPhoto").removeAttr('disabled');
              $("#btnUploadDocs").removeAttr('disabled');
              $("#btnUploadCanSig").removeAttr('disabled');
              $("#btnUploadParSig").removeAttr('disabled');
              $("#completeProcess").removeAttr('disabled');

              $("#targetError").html("");

              //alert("data.indexOf('l 0 file')"+data.indexOf('l 0 file'));
              //alert(data);
              if (data.indexOf('Error') == -1 && data.indexOf('l 0 file') == -1) {
                // alert('inside if');

                $("#upDocs").show();

                //location.reload(true);
                $("input[type=file]").val('');
                $.ajax({
                  url: "core/data/get/getuploadedfiles.php", // Your PHP script to generate the content
                  method: "GET",
                  data: {
                    test_val_wf_id: $('#test_wf_id').val()
                    // Add more parameters as needed
                  },
                  success: function(data) {
                    $("#targetDocLayer").html(data);
                    
                    // Restore viewed document states after table update
                    setTimeout(function() {
                      restoreViewedDocumentStates();
                    }, 100);
                  },
                  error: function() {
                    alert("Failed to reload section.");
                  }
                });

              } else {

              }
            },
            error: function(data) {
              //alert(data);
              Swal.fire({
                icon: 'error',
                title: 'Oops...',
                text: 'Something went wrong. Files could not be uploaded.'
              });

              //alert("inside error");

              $("#prgDocsUpload").css('display', 'none');

              $("#btnUploadDocs").prop("value", "Upload Documents");
              $("#btnUploadPhoto").removeAttr('disabled');
              $("#btnUploadDocs").removeAttr('disabled');
              $("#btnUploadCanSig").removeAttr('disabled');
              $("#btnUploadParSig").removeAttr('disabled');
              $("#completeProcess").removeAttr('disabled');

            }
          });
        }
      }));

/*
function adduserremark(ur, up) {
  // Get the current token from the form
  var csrfToken = $("input[name='csrf_token']").val();
  
  // Show the loading spinner and disable buttons
  $("#prgmodaladd").css('display', 'block');
  $("#mdlbtnsubmit").prop("innerHTML", "Please wait...");
  $("#mdlbtnsubmit").attr('disabled', 'disabled');
  $("#mdlbtnclose").attr('disabled', 'disabled');
  $("#modalbtncross").attr('disabled', 'disabled');

  // Make the AJAX request
  $.ajax({
    url: "core/addremarks.php",
    type: "POST",
    data: {
      csrf_token: csrfToken,
      user_remark: ur,
      user_password: up,
      wf_id: val_wf_id,
      test_wf_id: test_val_wf_id
    },
    success: function(data) {
      console.log("Response received:", data);
      
      // First try to parse as JSON
      try {
        var response = JSON.parse(data);
        console.log("Parsed response:", response);
        
        // Check if we need to forcefully redirect (account locked case)
        if (response.forceRedirect && response.redirect) {
          console.log("Force redirect detected to:", response.redirect);
          
          // Immediately close the modal to prevent further interactions
          $('#enterPasswordRemark').modal('hide');
          
          Swal.fire({
            icon: 'error',
            title: 'Account Locked',
            text: "Your account has been locked due to too many failed attempts. Please contact the administrator.",
            allowOutsideClick: false,
            allowEscapeKey: false,
            allowEnterKey: false,
            showConfirmButton: true,
            confirmButtonText: 'OK'
          }).then(function() {
            // Force redirect to login page after the alert is closed
            console.log("Executing redirect to:", response.redirect);
            window.location.href = response.redirect;
            
            // As a fallback, use a timer in case the redirect doesn't work immediately
            setTimeout(function() {
              window.location.replace(response.redirect);
            }, 500);
          });
          return;
        }
        
        // Check for regular redirect
        if (response.redirect) {
          console.log("Regular redirect detected to:", response.redirect);
          
          // Immediately close the modal to prevent further interactions
          $('#enterPasswordRemark').modal('hide');
          
          // For non-forced redirects, still show an alert but with different text
          Swal.fire({
            icon: 'info',
            title: 'Attention',
            text: "You will be redirected. Please click OK to continue.",
            allowOutsideClick: false,
            showConfirmButton: true,
            confirmButtonText: 'OK'
          }).then(function() {
            window.location.href = response.redirect;
          });
          return;
        }
        
        // Update CSRF token if a new one was provided in the response
        if (response.csrf_token) {
          $("input[name='csrf_token']").val(response.csrf_token);
        }
        
        if (response.status === "success") {
          Swal.fire({
            icon: 'success',
            title: 'Success',
            text: "Data saved. The task has been successfully submitted."
          }).then((result) => {
            window.location.href = url;
          });
        } else {
          // Handle different error types
          var errorMessage = "An error occurred. Please try again.";
          
          if (response.message === "invalid_credentials") {
            errorMessage = "Please enter the correct password and proceed.";
            if (response.attempts_left) {
              errorMessage += " You have " + response.attempts_left + " attempts left.";
            }
          } else if (response.message === "account_locked") {
            errorMessage = "Your account has been locked due to too many failed attempts. Please contact the administrator.";
          } else if (response.message === "security_error") {
            errorMessage = "A security error occurred. Please try again or refresh the page.";
          }
          
          Swal.fire({
            icon: 'error',
            title: 'Oops...',
            text: errorMessage
          });
        }
      } catch (e) {
        // If not valid JSON, handle as plain text (backward compatibility)
        console.log("Non-JSON response, parsing error:", e);
        
        if (data === "success") {
          Swal.fire({
            icon: 'success',
            title: 'Success',
            text: "Data saved. The task has been successfully submitted."
          }).then((result) => {
            window.location.href = url;
          });
        } else {
          Swal.fire({
            icon: 'error',
            title: 'Oops...',
            text: 'Please enter the correct password and proceed.'
          });
        }
      }
      
      // Reset the UI
      $("#prgmodaladd").css('display', 'none');
      $("#mdlbtnsubmit").prop("innerHTML", "Proceed");
      $("#mdlbtnsubmit").removeAttr('disabled');
      $("#mdlbtnclose").removeAttr('disabled');
      
      // Clear the form fields
      $("#user_remark").val("");
      $("#user_password").val("");
    },
    error: function(xhr, status, error) {
      console.error("AJAX Error:", error);
      
      Swal.fire({
        icon: 'error',
        title: 'Connection Error',
        text: 'Could not connect to the server. Please try again.'
      });
      
      // Reset the UI
      $("#prgmodaladd").css('display', 'none');
      $("#mdlbtnsubmit").prop("innerHTML", "Proceed");
      $("#mdlbtnsubmit").removeAttr('disabled');
      $("#mdlbtnclose").removeAttr('disabled');
    }
  });
}
*/
      
      // Security: Disable the back button to prevent cache-based security issues
      window.history.pushState(null, "", window.location.href);        
      window.onpopstate = function() {
          window.history.pushState(null, "", window.location.href);
      };
      
      // Performance: Restore viewed document states on initial page load
      setTimeout(function() {
        restoreViewedDocumentStates();
      }, 1500); // Longer delay for initial page load to ensure all AJAX content is loaded
    });
  </script>

  <style>
    #prgDocsUpload {
      display: none;
    }

    #prgAddRemarks {
      display: none;
    }

    #prgmodaladd {
      display: none;
    }


    .text-left {
      text-align: left;
    }

    /* CSS for viewed download links */
    .file-download-link.viewed {
      color: #28a745 !important; /* Bootstrap success green */
      font-weight: bold !important;
      text-decoration: none !important;
    }
    
    /* Debug style to verify CSS is working */
    .file-download-link.viewed:hover {
      color: #218838 !important; /* Darker green on hover */
    }
  </style>









</head>

<body>

  <!-- Modal -->
<?php include_once "assets/inc/_imagepdfviewermodal.php"; ?>

<?php include_once "assets/inc/_esignmodal.php"; ?>
<!--
  <div class="modal fade" id="exampleModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="exampleModalLabel">Enter your password and remarks</h5>
          <button id="modalbtncross" type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="d-flex justify-content-center">
          <div id="prgmodaladd" class="spinner-grow text-primary" style="width: 3rem; height: 3rem;" role="status">
            <span class="sr-only">Loading...</span>
          </div>
        </div>
        <form id="formmodalvalidation" class="needs-validation" novalidate>
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
          <div class="modal-body">



            <div class="form-group">
              <label for="recipient-name" class="col-form-label">Account Password:</label>
              <input type="password" class="form-control" id="user_password" required>
              <div class="invalid-feedback">Enter the account password.</div>
            </div>
            <div class="form-group">
              <label for="message-text" class="col-form-label">Remarks:</label>
              <textarea class="form-control" id="user_remark" required></textarea>
              <div class="invalid-feedback">Enter the remarks.</div>
            </div>




          </div>
          <div class="modal-footer">
            <button id="mdlbtnclose" type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            <button id="mdlbtnsubmit" class="btn btn-primary" type="submit">Proceed</button>
          </div>
        </form>
      </div>
    </div>
  </div>
  -->





  <?php include_once "assets/inc/_pleasewaitmodal.php"; ?>
  <div class="container-scroller">
    <?php include "assets/inc/_navbar.php"; ?>
    <!-- partial -->
    <div class="container-fluid page-body-wrapper">
      <!-- partial:assets/inc/_sidebar.php -->
      <?php include "assets/inc/_sidebar.php"; ?>
      <!-- partial -->
      <div class="main-panel">
        <div class="content-wrapper">
          <?php include "assets/inc/_sessiontimeout.php"; ?>

          <div class="page-header">
            <h3 class="page-title">
              <span class="page-title-icon bg-gradient-primary text-white mr-2">
                <i class="mdi mdi-home"></i>
              </span> Task Details
            </h3>
            <nav aria-label="breadcrumb">
              <ul class="breadcrumb">
                <li class="breadcrumb-item active" aria-current="page"><span><a class='btn btn-gradient-info btn-sm btn-rounded' href="assignedcases.php">
                      << Back</a> </span>
                </li>
              </ul>
            </nav>
          </div>



          <div class="row">

            <div class="col-lg-12 grid-margin stretch-card">
              <div class="card">
                <div class="card-body">
                  <h4 class="card-title"><?php echo htmlspecialchars($result['test_name'], ENT_QUOTES, 'UTF-8'); ?></h4>
                  <p class="card-description"> <?php echo htmlspecialchars($result['test_purpose'], ENT_QUOTES, 'UTF-8'); ?>
                  </p>











                  <div class="table-responsive">
                    <table class="table table-bordered">

                      <tr>
                        <td>
                          <h6 class="text-muted">Validation Workflow ID</h6>
                        </td>
                        <td> <?php echo htmlspecialchars(secure_get('val_wf_id', 'string'), ENT_QUOTES, 'UTF-8'); ?> </td>

                        <td>
                          <h6 class="text-muted">Test Workflow ID</h6>
                        </td>
                        <td> <?php echo htmlspecialchars($test_val_wf_id, ENT_QUOTES, 'UTF-8'); ?> </td>
                      </tr>
                      <tr>
                        <td>
                          <h6 class="text-muted">Equipment Code</h6>
                        </td>
                        <td colspan="3"><?php echo htmlspecialchars($equipment, ENT_QUOTES, 'UTF-8'); ?></td>

                      </tr>


                      <tr>
                        <td>
                          <h6 class="text-muted">Workflow Status</h6>
                        </td>
                        <td colspan="3"><?php echo htmlspecialchars($workflow_details['wf_stage_description'], ENT_QUOTES, 'UTF-8'); ?></td>

                      </tr>

                      <tr>
                        <td>
                          <h6 class="text-muted">Audit Trail</h6>
                        </td>
                        <td colspan="3"><?php

                                        foreach ($audit_trails as $row) {
                                          echo "[ " . Date('d.m.Y H:i:s', strtotime($row['wf-assignedtimestamp'])) . " ] - " . $row['wf-stages'];
                                          echo "<br/>";
                                        }

                                        ?></td>

                      </tr>


                      <tr>

                        <td>
                          <h6 class="text-muted">Test Conducted Date </h6>
                        </td>
                        <td colspan="3">

                          <?php
                          if (!empty($test_conducted_date) || !empty(secure_get('mode', 'string'))) {

                            echo '<input type="text" id="test_conducted_date" name="test_conducted_date" class="form-control" value="' . date('d.m.Y', strtotime($test_conducted_date)) . '" disabled/></td>';
                          } else {

                            echo '<input type="text" class="form-control" id="test_conducted_date" name="test_conducted_date" Required></td>';
                          }

                          ?>




                      </tr>



                      <tr>

                        <td class="align-text-top" colspan="4">
                          <h6 class="text-muted ">Upload
                            Documents</h6>
                          <br />




                          <div class="d-flex justify-content-center">
                            <div id="prgDocsUpload" class="spinner-grow text-primary" style="width: 3rem; height: 3rem;" role="status" style="display:none;">
                              <span class="sr-only">Loading...</span>
                            </div>
                          </div>
                          <form id="uploadDocForm" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token_for_upload; ?>">
                            <input type="hidden" id="test_wf_id" name="test_wf_id" value="<?php echo htmlspecialchars($test_val_wf_id, ENT_QUOTES, 'UTF-8'); ?>" <?php echo (!empty(secure_get('mode', 'string'))) ? 'disabled' : ''; ?> /> 
                            <input type="hidden" id="val_wf_id" name="val_wf_id" value="<?php echo htmlspecialchars(secure_get('val_wf_id', 'string'), ENT_QUOTES, 'UTF-8'); ?>" />

                            <div class="text-center">
                              <table class="table table-bordered">

                                <tr>
                                  <td><label>Raw Data File</label>

                                  <td><input name="upload_file_raw_data" id="upload_file_raw_data" type="file" class="form-control-file" <?php echo (!empty(secure_get('mode', 'string'))) ? 'disabled' : ''; ?> /></td>

                                </tr>

                                <tr>
                                  <td><label>Master Certificate File</label>

                                  <td><input name="upload_file_master" id="upload_file_master" type="file" class="form-control-file" <?php echo (!empty(secure_get('mode', 'string'))) ? 'disabled' : ''; ?> /></td>

                                </tr>

                                <tr>
                                  <td><label>Certificate File</label>

                                  <td><input name="upload_file_certificate" id="upload_file_certificate" type="file" class="form-control-file" <?php echo (!empty(secure_get('mode', 'string'))) ? 'disabled' : ''; ?> /></td>

                                </tr>
                                <tr>
                                  <td><label>Other Documents</label>

                                  <td><input name="upload_file_other" id="upload_file_other" type="file" class="form-control-file" <?php echo (!empty(secure_get('mode', 'string'))) ? 'disabled' : ''; ?> /></td>

                                </tr>


                                <tr>

                                  <td colspan="2"><input id="btnUploadDocs" class="btn btn-success" type="submit" value="Upload Documents" <?php 
                                    $mode = secure_get('mode', 'string');
                                    echo (($mode && $mode == 'read') || 
                                          ($_SESSION['logged_in_user'] == "employee" && $_SESSION['department_id'] == 1 && $current_wf_stage != '1') || 
                                          ($_SESSION['logged_in_user'] == "employee" && $_SESSION['department_id'] == 8)) ? "style='display:none'" : ''; 
                                  ?> /></td>


                                </tr>


                              </table>







                              <br />
                            </div>



                          </form> <br />
                          <div id="targetDocError"></div>
                          <div id="targetDocLayer"><?php include("core/data/get/getuploadedfiles.php") ?></div>
                          <?php

                          echo "</td>";

                          ?>



                      </tr>








                      <tr>
                        <td>
                          <h6 class="text-muted">Remarks</h6>
                        </td>
                        <td colspan="3">

                          <div id="showappremarks"><?php include("core/data/get/getremarks.php") ?></div>

                        </td>

                      </tr>









                      <tr>
                        <td colspan="4">
                          <div class="d-flex justify-content-center"> <?php

                                                                      if ($_SESSION['logged_in_user'] == "vendor") // Logged in user is vendor

                                                                      {
                                                                        if ($current_wf_stage == STAGE_NEW_TASK) // Task assigned for the first time
                                                                        {
                                                                      ?>

                                <button id="vendorsubmitassign" class='upload-check-required btn btn-primary btn-small'>Submit</button>

                              <?php
                                                                        } else if ($current_wf_stage == STAGE_REASSIGNED_B or $current_wf_stage == STAGE_REASSIGNED_4B) // Task is re-assigned
                                                                        {
                              ?>
                                <button id="vendorsubmitreassign" class='upload-check-required btn btn-primary btn-small'>Submit</button>


                              <?php
                                                                        }
                                                                      } else if ($_SESSION['logged_in_user'] == "employee" and $_SESSION['department_id'] == 1 and empty(secure_get('mode', 'string'))) // Logged in user is from the engineering team
                                                                      {
                                                                        if ($current_wf_stage == STAGE_NEW_TASK) // Task assigned for the first time
                                                                        {
                                                                          //$text = "<script>document.writeln(document.getElementById('user_remark').innerHTML);</script>";
                                                                          //echo $text;
                              ?>
                                <button id="enggsubmit" class='upload-check-required btn btn-primary btn-small'>Submit</button>


                              <?php
                                                                        } else if ($current_wf_stage == STAGE_PENDING_APPROVAL) // Task is assigned
                                                                        {
                              ?>
                                <button id="enggapprove" class='upload-check-required btn btn-primary btn-small'>Approve</button>
                                &nbsp;&nbsp;
                                <button id="enggreject" class='upload-check-required btn btn-danger btn-small'>Reject</button>


                              <?php
                                                                        } else if ($current_wf_stage == STAGE_REASSIGNED_4B) // Task is re-assigned
                                                                        {
                              ?>
                                <button id="enggassign" class='upload-check-required btn btn-primary btn-small'>Assign</button>


                              <?php
                                                                        } else if ($current_wf_stage == STAGE_REASSIGNED_4A) // Task is re-assigned
                                                                        {
                              ?>
                                <button id="enggapproval1" class='upload-check-required btn btn-primary btn-small'>Submit for
                                  Approval I</button>
                              <?php
                                                                        }
                                                                      } else if ($_SESSION['logged_in_user'] == "employee" and $_SESSION['department_id'] == 8 and empty(secure_get('mode', 'string'))) // Logged in user is from the QA team
                                                                      {

                              ?>
                              <button id="qaapprove" class='upload-check-required btn btn-primary btn-small'>Approve</button>
                              &nbsp;&nbsp;
                              <button id="qareject" class='upload-check-required btn btn-danger btn-small'>Reject</button>
                            <?php
                                                                      }

                            ?>
                          </div>
                        </td>

                      </tr>
                    </table>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <!-- content-wrapper ends -->
        <!-- partial:assets/inc/_footer.php -->
        <?php include "assets/inc/_footercopyright.php"; ?>
        <!-- partial -->
      </div>
      <!-- main-panel ends -->
    </div>
    <!-- page-body-wrapper ends -->
  </div>
  <?php include "assets/inc/_footerjs.php"; ?>
</body>

</html>