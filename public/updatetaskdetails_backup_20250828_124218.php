<?php
require_once('./core/config/config.php');

// Session is already started by config.php via session_init.php

// Check for proper authentication
if (!isset($_SESSION['logged_in_user']) || !isset($_SESSION['user_name'])) {
    session_destroy();
    header('Location: ' . BASE_URL . 'login.php?msg=session_required');
    exit();
}

// Validate session timeout
require_once('core/security/session_timeout_middleware.php');
validateActiveSession();

date_default_timezone_set("Asia/Kolkata");


include_once 'core/config/db.class.php';
require_once 'core/security/secure_query_wrapper.php';

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

// Validate required parameters
if (!$test_val_wf_id || !$test_id || !$current_wf_stage) {
    SecurityUtils::logSecurityEvent('invalid_parameters', 'Missing required parameters for task details', [
        'test_val_wf_id' => $test_val_wf_id,
        'test_id' => $test_id,
        'current_wf_stage' => $current_wf_stage
    ]);
    header('Location: assignedcases.php?error=invalid_parameters');
    exit;
}

// Secure parameterized database queries with error handling
try {
    // Query 1: Audit trails (removed unit_id condition as column doesn't exist)
    $audit_trails = DB::query("SELECT t1.time_stamp as 'wf-assignedtimestamp', t2.wf_stage_description as 'wf-stages'
    FROM audit_trail t1, workflow_stages t2
    WHERE t1.wf_stage=t2.wf_stage AND test_wf_id=%s AND t2.wf_type='External Test'
    ORDER BY t1.time_stamp ASC", $test_val_wf_id);

} catch (Exception $e) {
    require_once('core/error/error_logger.php');
    logDatabaseError("Database error in updatetaskdetails.php (audit_trails): " . $e->getMessage(), [
        'operation_name' => 'audit_trails_query',
        'test_val_wf_id' => $test_val_wf_id,
        'unit_id' => $_SESSION['unit_id']
    ]);
    die("Database Error: Failed to load audit trail data - " . $e->getMessage());
}

try {
    // Query 2: Test details
    $result = DB::queryFirstRow("SELECT test_name, test_purpose, test_performed_by FROM tests WHERE test_id=%i", $test_id);

} catch (Exception $e) {
    require_once('core/error/error_logger.php');
    logDatabaseError("Database error in updatetaskdetails.php (test_details): " . $e->getMessage(), [
        'operation_name' => 'test_details_query',
        'test_id' => $test_id
    ]);
    die("Database Error: Failed to load test details - " . $e->getMessage());
}

try {
    // Query 3: Workflow details
    $workflow_details = DB::queryFirstRow("SELECT wf_stage_description FROM workflow_stages WHERE wf_stage=%s", $current_wf_stage);

} catch (Exception $e) {
    require_once('core/error/error_logger.php');
    logDatabaseError("Database error in updatetaskdetails.php (workflow_details): " . $e->getMessage(), [
        'operation_name' => 'workflow_details_query',
        'current_wf_stage' => $current_wf_stage
    ]);
    die("Database Error: Failed to load workflow details - " . $e->getMessage());
}

try {
    // Query 4: Test conducted date
    $test_conducted_date = DB::queryFirstField("SELECT test_conducted_date FROM tbl_test_schedules_tracking WHERE test_wf_id=%s AND unit_id=%i", $test_val_wf_id, $_SESSION['unit_id']);

} catch (Exception $e) {
    require_once('core/error/error_logger.php');
    logDatabaseError("Database error in updatetaskdetails.php (test_conducted_date): " . $e->getMessage(), [
        'operation_name' => 'test_conducted_date_query',
        'test_val_wf_id' => $test_val_wf_id,
        'unit_id' => $_SESSION['unit_id']
    ]);
    die("Database Error: Failed to load test conducted date - " . $e->getMessage());
}

try {
    // Query 5: Equipment details
    $equipment = DB::queryFirstField("SELECT equipment_code FROM equipments WHERE equipment_id IN 
    (SELECT equip_id FROM tbl_test_schedules_tracking WHERE test_wf_id=%s AND unit_id=%i)", $test_val_wf_id, $_SESSION['unit_id']);
    
} catch (Exception $e) {
    require_once('core/error/error_logger.php');
    logDatabaseError("Database error in updatetaskdetails.php (equipment): " . $e->getMessage(), [
        'operation_name' => 'equipment_query',
        'test_val_wf_id' => $test_val_wf_id,
        'unit_id' => $_SESSION['unit_id']
    ]);
    die("Database Error: Failed to load equipment details - " . $e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
  <?php include_once "assets/inc/_header.php"; ?>
  
  <!-- CSRF Token for AJAX requests -->
  <meta name="csrf-token" content="<?php echo $_SESSION['csrf_token']; ?>">
  <script>

// Simple document highlighting functionality
var test_val_wf_id = "<?php echo htmlspecialchars($test_val_wf_id, ENT_QUOTES, 'UTF-8'); ?>";
var current_wf_stage = "<?php echo htmlspecialchars($current_wf_stage, ENT_QUOTES, 'UTF-8'); ?>";
var logged_in_user = "<?php echo htmlspecialchars($_SESSION['logged_in_user'], ENT_QUOTES, 'UTF-8'); ?>";
var read_mode = "<?php echo htmlspecialchars((!empty(secure_get('mode', 'string')) ? 'yes' : 'no'), ENT_QUOTES, 'UTF-8'); ?>";

// Simple document highlighting functionality
function trackDocumentView(uploadId, fileType) {
  if (!uploadId || !fileType) return;
  
  // Simple storage key for this page
  var storageKey = 'viewed_docs_' + test_val_wf_id;
  var viewedDocs = JSON.parse(sessionStorage.getItem(storageKey) || '[]');
  var docId = uploadId + '_' + fileType;
  
  if (viewedDocs.indexOf(docId) === -1) {
    viewedDocs.push(docId);
    sessionStorage.setItem(storageKey, JSON.stringify(viewedDocs));
  }
  
  // Highlight the link
  $('[data-upload-id="' + uploadId + '"][data-file-type="' + fileType + '"]').addClass('viewed');
}

function restoreViewedDocumentStates() {
  var storageKey = 'viewed_docs_' + test_val_wf_id;
  var viewedDocs = JSON.parse(sessionStorage.getItem(storageKey) || '[]');
  
  viewedDocs.forEach(function(docId) {
    var parts = docId.split('_');
    var uploadId = parts[0];
    var fileType = parts.slice(1).join('_');
    $('[data-upload-id="' + uploadId + '"][data-file-type="' + fileType + '"]').addClass('viewed');
  });
}

$(document).ready(function() {
  // Initialize variables
  var is_doc_uploaded = "no";
  var is_remark_added = "no";
  var test_id = "<?php echo htmlspecialchars($test_id, ENT_QUOTES, 'UTF-8'); ?>";
  var val_wf_id = "<?php echo htmlspecialchars(secure_get('val_wf_id', 'string'), ENT_QUOTES, 'UTF-8'); ?>";
  var employee_id = "<?php echo htmlspecialchars($_SESSION['employee_id'], ENT_QUOTES, 'UTF-8'); ?>";
  var department_id = "<?php echo htmlspecialchars(!empty($_SESSION['department_id']) ? $_SESSION['department_id'] : '', ENT_QUOTES, 'UTF-8'); ?>";
  
  // Restore viewed document states on page load
  restoreViewedDocumentStates();

  $("#vendorsubmitassign").click(function() {
    try {
      viewedDocuments = JSON.parse(existingData);
    } catch (e) {
      console.error('Error loading document views:', e);
    }
  }
  
  if (!viewedDocuments.includes(uniqueId)) {
    viewedDocuments.push(uniqueId);
    sessionStorage.setItem(storageKey, JSON.stringify(viewedDocuments));
    
    console.log('Document view tracked:', uniqueId, 'with storage key:', storageKey);
    
    // Update visual indicator - delay slightly to ensure DOM is ready
    setTimeout(function() {
      // Try multiple selectors to find the link
      let link = $('[data-upload-id="' + uploadId + '"][data-file-type="' + fileType + '"]');
      
      console.log('Primary selector result:', link.length, 'with selector:', '[data-upload-id="' + uploadId + '"][data-file-type="' + fileType + '"]');
      
      // If primary selector doesn't work, try alternatives
      if (link.length === 0) {
        // Try with class selector as backup
        link = $('.file-download-link[data-upload-id="' + uploadId + '"]').filter('[data-file-type="' + fileType + '"]');
        console.log('Alternative selector result:', link.length);
      }
      
      if (link.length > 0) {
        link.addClass('viewed');
        link.attr('title', 'Document reviewed');
        console.log('Link highlighted successfully for:', uniqueId);
        console.log('Added class "viewed" to element:', link[0]);
      } else {
        console.warn('No link found to highlight for:', uniqueId);
        // Debug: Show all available links
        console.log('Available file-download-link elements:');
        $('.file-download-link').each(function(index) {
          console.log('Link', index, '- uploadId:', $(this).data('upload-id'), 'fileType:', $(this).data('file-type'));
        });
      }
    }, 200); // Increased delay for better reliability
  } else {
    console.log('Document already viewed:', uniqueId);
  }
}

// Global function to restore viewed document states from sessionStorage on page load
function restoreViewedDocumentStates() {
  console.log('restoreViewedDocumentStates called');
  console.log('DocumentTracker config:', DocumentTracker);
  
  // Get storage keys for current user/stage context
  let storageKey;
  if (DocumentTracker.current_wf_stage === '3A' && DocumentTracker.department_id === '8') {
    // Use QA-specific key
    storageKey = 'qaDocumentViews_3A_' + DocumentTracker.test_val_wf_id;
  } else {
    // Use general key for all other users/stages
    storageKey = 'documentViews_' + DocumentTracker.current_wf_stage + '_' + DocumentTracker.department_id + '_' + DocumentTracker.test_val_wf_id;
  }
  
  console.log('Restoring viewed document states from:', storageKey);
  
  // Get viewed documents from sessionStorage
  const existingData = sessionStorage.getItem(storageKey);
  if (existingData) {
    try {
      const viewedDocuments = JSON.parse(existingData);
      
      if (viewedDocuments.length === 0) {
        console.log('No viewed documents found to restore');
        return;
      }
      
      console.log('Found viewed documents to restore:', viewedDocuments);
      
      // Apply green highlighting to all previously viewed documents
      let restoredCount = 0;
      viewedDocuments.forEach(function(uniqueId) {
        const parts = uniqueId.split('_');
        const uploadId = parts[0];
        const fileType = parts.slice(1).join('_'); // Handle file types with underscores
        
        let link = $('[data-upload-id="' + uploadId + '"][data-file-type="' + fileType + '"]');
        
        // Try alternative selector if primary doesn't work
        if (link.length === 0) {
          link = $('.file-download-link[data-upload-id="' + uploadId + '"]').filter('[data-file-type="' + fileType + '"]');
        }
        
        if (link.length) {
          link.addClass('viewed');
          link.attr('title', 'Document reviewed');
          restoredCount++;
          console.log('Restored viewed state for:', uniqueId);
        } else {
          console.warn('Could not find link to restore for:', uniqueId);
        }
      });
      
      console.log('Restored viewed state for', restoredCount, 'out of', viewedDocuments.length, 'documents');
    } catch (e) {
      console.error('Error restoring viewed document states:', e);
    }
  } else {
    console.log('No viewed document data found in sessionStorage');
  }
}

console.log('DEBUG: Checkpoint 2 - Before document.ready');

    $(document).ready(function() {

console.log('DEBUG: Checkpoint 3 - Inside document.ready');

// Session validation function for critical actions
function validateSessionBeforeAction(callback) {
  return new Promise((resolve, reject) => {
    $.ajax({
      url: 'core/security/validate_session_ajax.php',
      type: 'POST',
      data: {
        csrf_token: $('meta[name="csrf-token"]').attr('content')
      },
      dataType: 'json',
      success: function(response) {
        if (response.session_valid) {
          resolve(response);
          if (callback) callback(true);
        } else {
          // Session expired - redirect to login
          Swal.fire({
            icon: 'warning',
            title: 'Session Expired',
            text: response.message || 'Your session has expired. Please log in again.',
            confirmButtonText: 'Login'
          }).then(() => {
            if (response.redirect_url) {
              window.location.href = response.redirect_url;
            } else {
              window.location.href = 'login.php';
            }
          });
          reject(response);
          if (callback) callback(false);
        }
      },
      error: function(xhr, status, error) {
        console.error('Session validation failed:', error);
        
        // Handle server errors gracefully
        Swal.fire({
          icon: 'error',
          title: 'Connection Error',
          text: 'Unable to verify your session. Please try again or refresh the page.',
          confirmButtonText: 'OK'
        });
        
        reject({ session_valid: false, message: 'Session validation failed' });
        if (callback) callback(false);
      }
    });
  });
}



// Function to check if all documents have been viewed for QA at stage 3A
function allQADocumentsViewed() {
  if (DocumentTracker.current_wf_stage !== '3A' || DocumentTracker.department_id !== '8') {
    return true; // Not applicable
  }
  
  const storageKey = 'qaDocumentViews_3A_' + DocumentTracker.test_val_wf_id;
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

      var is_doc_uploaded = "no";
      var is_remark_added = "no";

      var test_id = "<?php echo htmlspecialchars($test_id, ENT_QUOTES, 'UTF-8'); ?>";
      // Initialize global variables (keeping for backward compatibility)
      var val_wf_id = "<?php echo htmlspecialchars(secure_get('val_wf_id', 'string'), ENT_QUOTES, 'UTF-8'); ?>";
      var test_val_wf_id = "<?php echo htmlspecialchars($test_val_wf_id, ENT_QUOTES, 'UTF-8'); ?>";
      var current_wf_stage = "<?php echo htmlspecialchars($current_wf_stage, ENT_QUOTES, 'UTF-8'); ?>";
      var logged_in_user = "<?php echo htmlspecialchars($_SESSION['logged_in_user'], ENT_QUOTES, 'UTF-8'); ?>";
      var department_id = "<?php echo htmlspecialchars(!empty($_SESSION['department_id']) ? $_SESSION['department_id'] : '', ENT_QUOTES, 'UTF-8'); ?>";
      var employee_id = "<?php echo htmlspecialchars($_SESSION['employee_id'], ENT_QUOTES, 'UTF-8'); ?>";
      var read_mode = "<?php echo htmlspecialchars((!empty(secure_get('mode', 'string')) ? 'yes' : 'no'), ENT_QUOTES, 'UTF-8'); ?>";

      // Initialize global DocumentTracker object
      try {
        DocumentTracker.test_val_wf_id = test_val_wf_id || '';
        DocumentTracker.current_wf_stage = current_wf_stage || '';
        DocumentTracker.department_id = department_id || '';
        DocumentTracker.logged_in_user = logged_in_user || '';
        
        console.log('DocumentTracker initialized:', DocumentTracker);
      } catch (e) {
        console.error('Error initializing DocumentTracker:', e);
        console.log('Variables:', {
          test_val_wf_id: typeof test_val_wf_id !== 'undefined' ? test_val_wf_id : 'undefined',
          current_wf_stage: typeof current_wf_stage !== 'undefined' ? current_wf_stage : 'undefined', 
          department_id: typeof department_id !== 'undefined' ? department_id : 'undefined',
          logged_in_user: typeof logged_in_user !== 'undefined' ? logged_in_user : 'undefined'
        });
      }

      $(document).on('click', '.navlink-approve', async function(e) {
    e.preventDefault();

    if (read_mode == 'yes') {
        alert("No action allowed");
        return;
    }

    // Validate session before proceeding with approve action
    try {
        await validateSessionBeforeAction();
    } catch (error) {
        console.log('Session validation failed for approve action');
        return; // Stop execution if session is invalid
    }

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
        async function(postData, postStatus) {
            $.ajax({
                url: "core/data/get/getuploadedfiles.php",
                method: "GET",
                data: {
                    test_val_wf_id: $('#test_wf_id').val()
                },
                success: function(ajaxData) {
                    $("#targetDocLayer").html(ajaxData);
                    
                    // Restore viewed document states after table update
                    setTimeout(function() {
                        restoreViewedDocumentStates();
                    }, 100);
                },
                error: function() {
                    alert("Failed to reload section.");
                }
            });
            
            const swalResult = await Swal.fire({
                icon: 'success',
                title: 'Success',
                text: 'The files were successfully approved.'
            });
        });
    }
});


      $(document).on('click', '.navlink-reject', async function(e) {
        e.preventDefault();
        
        if (read_mode == 'yes') {
          alert("No action allowed");
          return;
        }

        // Validate session before proceeding with reject action
        try {
          await validateSessionBeforeAction();
        } catch (error) {
          console.log('Session validation failed for reject action');
          return; // Stop execution if session is invalid
        }

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
                //$("#targetDocLayer").load('core/data/get/getuploadedfiles.php?test_val_wf_id='+$('#test_wf_id').val());
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
      });










      $("#vendorsubmitassign").click(async function() {
        // Validate session before proceeding with vendor submit action
        try {
          await validateSessionBeforeAction();
        } catch (error) {
          console.log('Session validation failed for vendor submit action');
          return; // Stop execution if session is invalid
        }

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
        if (current_wf_stage == '3B') {
          url = "core/data/update/updatewfstage.php?test_conducted_date=" + convertDateFormat($('#test_conducted_date').val()) + "&val_wf_id=" + val_wf_id + "&test_val_wf_id=" + test_val_wf_id + "&current_wf_stage=" + current_wf_stage + "&action=assign_back_engg_vendor";
        } else if (current_wf_stage == '4B') {

          url = "core/data/update/updatewfstage.php?test_conducted_date=" + convertDateFormat($('#test_conducted_date').val()) + "&val_wf_id=" + val_wf_id + "&test_val_wf_id=" + test_val_wf_id + "&current_wf_stage=" + current_wf_stage + "&action=assign_back_qa_vendor";

        }

        $('#enterPasswordRemark').modal('show');
      });


      $("#enggsubmit").click(async function() {
        // Validate session before proceeding with engineering submit action
        try {
          await validateSessionBeforeAction();
        } catch (error) {
          console.log('Session validation failed for engineering submit action');
          return; // Stop execution if session is invalid
        }

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

      $("#enggapprove").click(async function() {
        // Validate session before proceeding with engineering approve action
        try {
          await validateSessionBeforeAction();
        } catch (error) {
          console.log('Session validation failed for engineering approve action');
          return; // Stop execution if session is invalid
        }

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

      $("#enggreject").click(async function() {
        // Validate session before proceeding with engineering reject action
        try {
          await validateSessionBeforeAction();
        } catch (error) {
          console.log('Session validation failed for engineering reject action');
          return; // Stop execution if session is invalid
        }

        url = "core/data/update/updatewfstage.php?test_conducted_date=" + convertDateFormat($('#test_conducted_date').val()) + "&val_wf_id=" + val_wf_id + "&test_val_wf_id=" + test_val_wf_id + "&current_wf_stage=" + current_wf_stage + "&action=engg_reject";

        if ($(".navlink-approve")[0]) {
          Swal.fire({
            icon: 'error',
            title: 'Oops...',
            text: 'You have one or more files to be approved.'
          });
        }
        $('#enterPasswordRemark').modal('show');
      });

      $("#enggapproval1").click(async function() {
        // Validate session before proceeding with engineering approval final action
        try {
          await validateSessionBeforeAction();
        } catch (error) {
          console.log('Session validation failed for engineering approval final action');
          return; // Stop execution if session is invalid
        }

        url = "core/data/update/updatewfstage.php?test_conducted_date=" + convertDateFormat($('#test_conducted_date').val()) + "&val_wf_id=" + val_wf_id + "&test_val_wf_id=" + test_val_wf_id + "&current_wf_stage=" + current_wf_stage + "&action=engg_approve_final";
        if ($(".navlink-approve")[0]) {
          Swal.fire({
            icon: 'error',
            title: 'Oops...',
            text: 'You have one or more files to be approved.'
          });
        }
        $('#enterPasswordRemark').modal('show');
      });

      $("#qaapprove").click(async function() {
        // Validate session before proceeding with QA approve action
        try {
          await validateSessionBeforeAction();
        } catch (error) {
          console.log('Session validation failed for QA approve action');
          return; // Stop execution if session is invalid
        }

        url = "core/data/update/updatewfstage.php?test_conducted_date=" + convertDateFormat($('#test_conducted_date').val()) + "&val_wf_id=" + val_wf_id + "&test_val_wf_id=" + test_val_wf_id + "&current_wf_stage=" + current_wf_stage + "&action=qa_approve";
        
        // Check if current_wf_stage is 3A and user is QA team
        if (current_wf_stage === '3A' && department_id === '8') {
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

      $("#qareject").click(async function() {
        // Validate session before proceeding with QA reject action
        try {
          await validateSessionBeforeAction();
        } catch (error) {
          console.log('Session validation failed for QA reject action');
          return; // Stop execution if session is invalid
        }

        url = "core/data/update/updatewfstage.php?test_conducted_date=" + convertDateFormat($('#test_conducted_date').val()) + "&val_wf_id=" + val_wf_id + "&test_val_wf_id=" + test_val_wf_id + "&current_wf_stage=" + current_wf_stage + "&action=qa_reject";
        
        // Check if current_wf_stage is 3A and user is QA team  
        if (current_wf_stage === '3A' && department_id === '8') {
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
              url: "core/data/get/getuploadedfiles.php",
              method: "GET", 
              data: {
                test_val_wf_id: test_val_wf_id
              },
              success: function(data) {
                $("#targetDocLayer").html(data);
                console.log('File display refreshed after QA rejection');
                
                // Restore viewed document states after table update
                setTimeout(function() {
                  restoreViewedDocumentStates();
                }, 100);
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

      $("#uploadDocForm").on('submit', async function(e) {
        e.preventDefault();

        // Validate session before proceeding with file upload
        try {
          await validateSessionBeforeAction();
        } catch (error) {
          console.log('Session validation failed for file upload');
          return; // Stop execution if session is invalid
        }

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
                // No additional files to process
              }
                }
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
    url: "core/validation/addremarks.php",
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
      // Restore viewed document states on initial page load
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
                                                                        if ($current_wf_stage == '1') // Task assigned for the first time
                                                                        {
                                                                      ?>

                                <button id="vendorsubmitassign" class='upload-check-required btn btn-primary btn-small'>Submit</button>

                              <?php
                                                                        } else if ($current_wf_stage == '3B' or $current_wf_stage == '4B') // Task is re-assigned
                                                                        {
                              ?>
                                <button id="vendorsubmitreassign" class='upload-check-required btn btn-primary btn-small'>Submit</button>


                              <?php
                                                                        }
                                                                      } else if ($_SESSION['logged_in_user'] == "employee" and $_SESSION['department_id'] == 1 and empty(secure_get('mode', 'string'))) // Logged in user is from the engineering team
                                                                      {
                                                                        if ($current_wf_stage == '1') // Task assigned for the first time
                                                                        {
                                                                          //$text = "<script>document.writeln(document.getElementById('user_remark').innerHTML);</script>";
                                                                          //echo $text;
                              ?>
                                <button id="enggsubmit" class='upload-check-required btn btn-primary btn-small'>Submit</button>


                              <?php
                                                                        } else if ($current_wf_stage == '2') // Task is assigned
                                                                        {
                              ?>
                                <button id="enggapprove" class='upload-check-required btn btn-primary btn-small'>Approve</button>
                                &nbsp;&nbsp;
                                <button id="enggreject" class='upload-check-required btn btn-danger btn-small'>Reject</button>


                              <?php
                                                                        } else if ($current_wf_stage == '4B') // Task is re-assigned
                                                                        {
                              ?>
                                <button id="enggassign" class='upload-check-required btn btn-primary btn-small'>Assign</button>


                              <?php
                                                                        } else if ($current_wf_stage == '4A') // Task is re-assigned
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