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

// Database queries (keeping existing queries)
try {
    // Query 1: Audit trails
    $audit_trails = DB::query("SELECT t1.time_stamp as 'wf-assignedtimestamp', t2.wf_stage_description as 'wf-stages'
                               FROM val_test_audit_trails t1 
                               INNER JOIN workflow_stages t2 ON t1.wf_stage = t2.wf_stage 
                               WHERE t1.test_val_wf_id = %s 
                               ORDER BY t1.time_stamp DESC", 
                               $test_val_wf_id);

    // Query 2: Current stage workflow details
    $workflow_details = DB::queryFirstRow("SELECT wf_stage_description FROM workflow_stages WHERE wf_stage=%s", $current_wf_stage);

    // Additional queries for test details, employee info, etc. (keeping existing structure)
    $test_details = DB::queryFirstRow("SELECT * FROM tests WHERE test_id = %i", $test_id);
    $employee_details = DB::queryFirstRow("SELECT * FROM users WHERE employee_id = %s", $_SESSION['employee_id']);
    
} catch (Exception $e) {
    SecurityUtils::logSecurityEvent('database_error', 'Database query failed in task details', [
        'error' => $e->getMessage(),
        'test_val_wf_id' => $test_val_wf_id
    ]);
    die("Database error occurred. Please try again later.");
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>Task Details - ProVal</title>
  <?php include_once "assets/inc/_header.php"; ?>
  
  <!-- CSRF Token for AJAX requests -->
  <meta name="csrf-token" content="<?php echo $_SESSION['csrf_token']; ?>">
  
  <style>
    /* CSS for viewed document highlighting */
    .file-download-link.viewed {
      color: #28a745 !important;
      font-weight: bold;
    }
    .file-download-link.viewed::after {
      content: " ✓";
      color: #28a745;
    }
  </style>
  
  <script>
// Simple document highlighting functionality
var test_val_wf_id = "<?php echo htmlspecialchars($test_val_wf_id, ENT_QUOTES, 'UTF-8'); ?>";
var current_wf_stage = "<?php echo htmlspecialchars($current_wf_stage, ENT_QUOTES, 'UTF-8'); ?>";
var logged_in_user = "<?php echo htmlspecialchars($_SESSION['logged_in_user'], ENT_QUOTES, 'UTF-8'); ?>";
var read_mode = "<?php echo htmlspecialchars((!empty(secure_get('mode', 'string')) ? 'yes' : 'no'), ENT_QUOTES, 'UTF-8'); ?>";

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

  // Simple button handlers without session validation complexity
  $("#vendorsubmitassign").click(function() {
    var url = "core/data/update/updatewfstage.php?test_conducted_date=" + convertDateFormat($('#test_conducted_date').val()) + "&val_wf_id=" + val_wf_id + "&test_val_wf_id=" + test_val_wf_id + "&current_wf_stage=" + current_wf_stage + "&action=assign";

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
        text: 'Please select the test conducted date.'
      });
    } else {
      window.open(url);
    }
  });

  // Other button handlers - simplified without session validation
  $("#vendorsubmitreassign").click(function() {
    // Implementation here
  });

  $("#enggsubmit").click(function() {
    // Implementation here  
  });

  // File upload handling - simplified
  $("#formDocsUpload").submit(function(e) {
    e.preventDefault();
    var formData = new FormData(this);
    
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
          Swal.fire({
            icon: 'error',
            title: 'Upload Failed!',
            text: 'Files could not be uploaded.'
          });
        } else {
          Swal.fire({
            icon: 'success',
            title: 'Success',
            text: data
          });
          
          is_doc_uploaded = "yes";
          $("#prgDocsUpload").css('display', 'none');
          $("#btnUploadDocs").prop("value", "Upload Documents");
          $("#btnUploadPhoto").removeAttr('disabled');
          $("#btnUploadDocs").removeAttr('disabled');
          $("#btnUploadCanSig").removeAttr('disabled');
          $("#btnUploadParSig").removeAttr('disabled');
          $("#completeProcess").removeAttr('disabled');
          
          // Refresh uploaded files display
          $.ajax({
            url: "core/data/get/getuploadedfiles.php",
            method: "GET",
            data: { test_val_wf_id: test_val_wf_id },
            success: function(data) {
              $("#targetDocLayer").html(data);
              setTimeout(function() {
                restoreViewedDocumentStates();
              }, 100);
            }
          });
        }
      },
      error: function() {
        Swal.fire({
          icon: 'error',
          title: 'Oops...',
          text: 'Something went wrong. Files could not be uploaded.'
        });
        $("#prgDocsUpload").css('display', 'none');
        $("#btnUploadDocs").prop("value", "Upload Documents");
      }
    });
  });
});

// Utility functions
function convertDateFormat(dateStr) {
  if (!dateStr) return '';
  var parts = dateStr.split('-');
  return parts[2] + '-' + parts[1] + '-' + parts[0];
}

  </script>
</head>

<body>
  <!-- Page content here -->
  <div class="container">
    <h2>Task Details</h2>
    
    <!-- File upload section -->
    <div id="targetDocLayer">
      <!-- This will be populated by AJAX -->
    </div>
    
    <!-- Form for document upload -->
    <form id="formDocsUpload" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?php echo $csrf_token_for_upload; ?>">
      <input type="hidden" name="test_val_wf_id" value="<?php echo $test_val_wf_id; ?>">
      <input type="file" name="files[]" multiple>
      <input type="submit" id="btnUploadDocs" value="Upload Documents">
    </form>
    
    <!-- Progress indicator -->
    <div id="prgDocsUpload" style="display:none;">
      Uploading files, please wait...
    </div>
    
    <!-- Action buttons -->
    <button id="vendorsubmitassign">Submit Assignment</button>
    <button id="vendorsubmitreassign">Reassign</button>
    <button id="enggsubmit">Engineering Submit</button>
    
    <!-- Test conducted date -->
    <input type="date" id="test_conducted_date">
    
    <!-- Hidden field for test workflow ID -->
    <input type="hidden" id="test_wf_id" value="<?php echo $test_val_wf_id; ?>">
  </div>

  <!-- Include PDF modal -->
  <?php include_once "assets/inc/_imagepdfviewermodal.php"; ?>
  
</body>
</html>