<?php
require_once('./core/config/config.php');

// Session is already started by config.php via session_init.php

// Optimized session validation
require_once('core/security/optimized_session_validation.php');
OptimizedSessionValidation::validateOnce();

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
define('STAGE_OFFLINE_PROVISIONAL', '1PRV');
define('STAGE_OFFLINE_REJECTED', '1RRV');
define('STAGE_OFFLINE_REJECTED_ENGG', '3BPRV');
define('STAGE_REASSIGNED_4BPRV', '4BPRV');
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
$val_wf_id = secure_get('val_wf_id', 'string');
$test_id = secure_get('test_id', 'int');  
$current_wf_stage = secure_get('current_wf_stage', 'string');

// Enhanced parameter validation with performance considerations
// NOTE: Parameters are also validated client-side via URL parsing for offline actions
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
    STAGE_REASSIGNED_4B,
    STAGE_OFFLINE_PROVISIONAL,
    STAGE_OFFLINE_REJECTED,
    STAGE_OFFLINE_REJECTED_ENGG,
    STAGE_REASSIGNED_4BPRV
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

// Initialize variables to avoid undefined variable issues
$show_same_user_error = false;

// Optimized database queries with performance improvements
try {
    // Handle different query logic for vendor vs employee users
    if (isVendor()) {
        // Vendor users: Join without unit_id constraint since vendors may access across units
        $mainDataQuery = "
            SELECT 
                t.test_name,
                t.test_purpose,
                t.test_performed_by as test_type_performed_by,
                ts.test_performed_by,
                t.paper_on_glass_enabled,
                w.wf_stage_description,
                ts.test_conducted_date,
                ts.data_entry_mode,
                ts.test_wf_current_stage,
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
                t.test_performed_by as test_type_performed_by,
                ts.test_performed_by,
                t.paper_on_glass_enabled,
                w.wf_stage_description,
                ts.test_conducted_date,
                ts.data_entry_mode,
                ts.test_wf_current_stage,
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
            'test_purpose' => $mainData['test_purpose'] ?? 'No purpose specified',
            'paper_on_glass_enabled' => $mainData['paper_on_glass_enabled'] ?? 'No',
            'data_entry_mode' => $mainData['data_entry_mode'] ?? null,
            'test_wf_current_stage' => $mainData['test_wf_current_stage'] ?? null,
            'test_id' => $test_id
        ];
        $workflow_details = [
            'wf_stage_description' => $mainData['wf_stage_description'] ?? 'Unknown Stage'
        ];
        $test_conducted_date = $mainData['test_conducted_date'];
        $equipment = $mainData['equipment_code'] ?? 'Unknown Equipment';
        
        // Validation: Check if same user is trying to access their own offline test
        if ($mainData['test_wf_current_stage'] === '1PRV' && 
            $mainData['test_performed_by'] == $_SESSION['user_id']) {
            $show_same_user_error = true;
            // Debug logging
            error_log("Same user offline test access detected: Stage=" . $mainData['test_wf_current_stage'] . 
                     ", Performed by=" . $mainData['test_performed_by'] . 
                     ", Current user=" . $_SESSION['user_id']);
        }
    } else {
        // Debug logging when no main data
        error_log("No main data found for test_wf_id: " . $test_val_wf_id . ", test_id: " . $test_id);
    }
    
} catch (Exception $e) {
    // Log error and use fallback values already set above
    error_log("Database error in updatetaskdetails.php (main_data): " . $e->getMessage());
    $show_same_user_error = false; // Initialize error flag for exception case
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

// Check test finalisation status for conditional UI logic
$hide_upload_and_submit = false;
try {
    // Check if paper-on-glass is enabled AND data entry mode is online OR offline AND test is not finalised
    if (($result['paper_on_glass_enabled'] ?? 'No') == 'Yes' && 
        (($result['data_entry_mode'] ?? '') == 'online' || ($result['data_entry_mode'] ?? '') == 'offline')) {
        $finalisation_check = DB::queryFirstRow("
            SELECT test_finalised_on, test_finalised_by 
            FROM tbl_test_finalisation_details 
            WHERE test_wf_id = %s AND status = 'Active'
        ", $test_val_wf_id);
        
        // Hide upload documents and submit if test is not finalised yet (only for online mode with paper-on-glass)
        //if (!$finalisation_check || empty($finalisation_check['test_finalised_on']) || empty($finalisation_check['test_finalised_by'])) 
        if (!$finalisation_check )
        {
            $hide_upload_and_submit = true;
        }
    }
} catch (Exception $e) {
    error_log("Database error in updatetaskdetails.php (finalisation_check): " . $e->getMessage());
}

// Prepare finalization data for JavaScript
$finalization_js_data = 'null';
if (isset($finalisation_check) && $finalisation_check && 
    !empty($finalisation_check['test_finalised_on']) && 
    !empty($finalisation_check['test_finalised_by'])) {
    
    // Get user name for finalization details
    try {
        $finalised_by_user = DB::queryFirstRow("
            SELECT user_name 
            FROM users 
            WHERE user_id = %i
        ", $finalisation_check['test_finalised_by']);
        
        $finalization_js_data = json_encode([
            'is_finalized' => true,
            'finalized_on' => date('d/m/Y H:i', strtotime($finalisation_check['test_finalised_on'])),
            'finalized_by' => $finalised_by_user['user_name'] ?? 'Unknown User',
            'data_entry_mode' => $result['data_entry_mode'] ?? 'online'
        ]);
    } catch (Exception $e) {
        error_log("Error fetching finalization user details: " . $e->getMessage());
        $finalization_js_data = json_encode([
            'is_finalized' => true,
            'finalized_on' => date('d/m/Y H:i', strtotime($finalisation_check['test_finalised_on'])),
            'finalized_by' => 'Unknown User',
            'data_entry_mode' => $result['data_entry_mode'] ?? 'online'
        ]);
    }
} else {
    $finalization_js_data = json_encode([
        'is_finalized' => false,
        'data_entry_mode' => $result['data_entry_mode'] ?? 'online'
    ]);
}

// Prepare user role information for JavaScript
$user_role_data = json_encode([
    'department_id' => $_SESSION['department_id'] ?? null,
    'logged_in_user' => $_SESSION['logged_in_user'] ?? null,
    'is_engineering_or_qa' => ($_SESSION['department_id'] == 1 || $_SESSION['department_id'] == 8)
]);

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
  <script type="text/javascript">
    // Global variables for user role and test finalization status
    window.testFinalizationStatus = <?php echo $finalization_js_data; ?>;
    window.userRoleData = <?php echo $user_role_data; ?>;
  </script>
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

      // Initialize datepicker only if the field is not disabled
      if (!$("#test_conducted_date").prop('disabled')) {
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
      }

      is_doc_uploaded = "no";
      is_remark_added = "no";

      test_id = "<?php echo htmlspecialchars($test_id, ENT_QUOTES, 'UTF-8'); ?>";
      val_wf_id = "<?php echo htmlspecialchars(secure_get('val_wf_id', 'string'), ENT_QUOTES, 'UTF-8'); ?>";
      test_val_wf_id = "<?php echo htmlspecialchars($test_val_wf_id, ENT_QUOTES, 'UTF-8'); ?>";
      current_wf_stage = "<?php echo htmlspecialchars($current_wf_stage, ENT_QUOTES, 'UTF-8'); ?>";
      logged_in_user = "<?php echo htmlspecialchars($_SESSION['logged_in_user'], ENT_QUOTES, 'UTF-8'); ?>";
      department_id = "<?php echo htmlspecialchars(!empty($_SESSION['department_id']) ? $_SESSION['department_id'] : '', ENT_QUOTES, 'UTF-8'); ?>";
      read_mode = "<?php echo htmlspecialchars((!empty(secure_get('mode', 'string')) ? 'yes' : 'no'), ENT_QUOTES, 'UTF-8'); ?>";

      // Check for same user offline test access error on page load
      console.log('Debug: show_same_user_error = <?php echo isset($show_same_user_error) ? ($show_same_user_error ? "true" : "false") : "undefined"; ?>');
      <?php if (isset($show_same_user_error) && $show_same_user_error): ?>
      console.log('Debug: Showing same user error popup');
      Swal.fire({
        icon: 'error',
        title: 'Access Denied',
        text: 'The test data of this test was entered by you in an offline mode which needs to be reviewed by any other team member. You cannot take any action on this test.',
        confirmButtonText: 'OK',
        allowOutsideClick: false,
        allowEscapeKey: false
      }).then((result) => {
        if (result.isConfirmed) {
          window.location.href = 'assignedcases.php';
        }
      });
      
      // Disable all interface elements except SweetAlert buttons
      $('input, button:not(.swal2-confirm):not(.swal2-cancel), select, textarea').prop('disabled', true);
      $('a:not(.swal2-confirm):not(.swal2-cancel)').addClass('disabled').attr('style', 'pointer-events: none; color: #ccc;');
      <?php endif; ?>

      // PDF Regeneration Spinner Functions
      function showPDFRegenerationSpinner() {
        // Create spinner overlay if it doesn't exist
        if ($('#pdf-regeneration-spinner').length === 0) {
          const spinnerHTML = `
            <div id="pdf-regeneration-spinner" style="
              position: fixed;
              top: 0;
              left: 0;
              width: 100%;
              height: 100%;
              background: rgba(0, 0, 0, 0.7);
              z-index: 9999;
              display: flex;
              justify-content: center;
              align-items: center;
              flex-direction: column;
            ">
              <div class="spinner-border text-primary" style="width: 3rem; height: 3rem; margin-bottom: 1rem;" role="status">
                <span class="sr-only">Loading...</span>
              </div>
              <div style="color: white; font-size: 18px; font-weight: 500;">
                Regenerating PDFs with witness details...
              </div>
              <div style="color: #ccc; font-size: 14px; margin-top: 0.5rem;">
                Please wait, this may take a few moments
              </div>
            </div>
          `;
          $('body').append(spinnerHTML);
        }
        $('#pdf-regeneration-spinner').fadeIn(200);
        // Disable scrolling
        $('body').css('overflow', 'hidden');
      }

      function hidePDFRegenerationSpinner() {
        $('#pdf-regeneration-spinner').fadeOut(200, function() {
          $(this).remove();
        });
        // Re-enable scrolling
        $('body').css('overflow', '');
      }

      // QA Approval Spinner Functions
      function showQAApprovalSpinner() {
        // Create spinner overlay if it doesn't exist
        if ($('#qa-approval-spinner').length === 0) {
          const spinnerHTML = `
            <div id="qa-approval-spinner" style="
              position: fixed;
              top: 0;
              left: 0;
              width: 100%;
              height: 100%;
              background: rgba(0, 0, 0, 0.7);
              z-index: 9999;
              display: flex;
              justify-content: center;
              align-items: center;
              flex-direction: column;
            ">
              <div class="spinner-border text-success" style="width: 3rem; height: 3rem; margin-bottom: 1rem;" role="status">
                <span class="sr-only">Loading...</span>
              </div>
              <div style="color: white; font-size: 18px; font-weight: 500;">
                Regenerating certificates with approval details...
              </div>
              <div style="color: #ccc; font-size: 14px; margin-top: 0.5rem;">
                Please wait, this may take a few moments
              </div>
            </div>
          `;
          $('body').append(spinnerHTML);
        }
        $('#qa-approval-spinner').fadeIn(200);
        // Disable scrolling
        $('body').css('overflow', 'hidden');
      }

      function hideQAApprovalSpinner() {
        $('#qa-approval-spinner').fadeOut(200, function() {
          $(this).remove();
        });
        // Re-enable scrolling
        $('body').css('overflow', '');
      }

      $(document).on('click', '.navlink-approve', async function(e) {
        e.preventDefault();

        if (read_mode == 'yes') {
          alert("No action allowed");
        } else {

          const result = await Swal.fire({
            title: 'Are you sure?',
            text: 'Do you want to approve the files? This action cannot be undone.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes',
            cancelButtonText: 'No'
          });

          if (result.isConfirmed) {
            const uploadId = $(this).attr('data-upload-id');
            const testWfId = $('#test_wf_id').val();
            const csrfToken = $('meta[name="csrf-token"]').attr('content');
            
            // Show spinner for PDF regeneration
            showPDFRegenerationSpinner();
            
            try {
              // Step 1: Try PDF regeneration first (only if conditions are met)
              const pdfResponse = await $.post("core/data/save/regenerate_pdfs_for_approval.php", {
                test_wf_id: testWfId,
                upload_id: uploadId,
                csrf_token: csrfToken
              });
              
              let pdfSuccess = false;
              let pdfMessage = '';
              
              if (pdfResponse.success) {
                pdfSuccess = true;
                pdfMessage = ' PDFs regenerated with witness details.';
                console.log('PDF regeneration successful:', pdfResponse.message);
              } else {
                console.log('PDF regeneration not performed:', pdfResponse.error);
                // If PDF regeneration fails due to conditions not met, continue with approval
                if (pdfResponse.error.includes('conditions not met')) {
                  pdfMessage = ' (PDF regeneration skipped - conditions not met)';
                } else if (pdfResponse.error.includes('PDF regeneration not applicable')) {
                  pdfMessage = ' (PDF regeneration skipped - no test documents to regenerate)';
                } else {
                  throw new Error(pdfResponse.error);
                }
              }
              
              // Step 2: Proceed with normal approval process
              const approvalResponse = await $.post("core/data/update/updateuploadstatus.php", {
                up_id: uploadId,
                test_val_wf_id: testWfId,
                action: 'approve',
                wf_stage: current_wf_stage,
                csrf_token: csrfToken
              });
              
              // Step 3: Refresh the file display
              await $.ajax({
                url: "core/data/get/getuploadedfiles.php",
                method: "GET",
                data: {
                  test_val_wf_id: testWfId
                },
                success: function(data) {
                  $("#targetDocLayer").html(data);
                  
                  // Restore viewed document states after table update
                  setTimeout(function() {
                    restoreViewedDocumentStates();
                  }, 100);
                },
                error: function() {
                  console.error("Failed to reload uploaded files section.");
                }
              });
              
              // Hide spinner
              hidePDFRegenerationSpinner();
              
              // Step 4: Show success message
              const successMessage = pdfSuccess ? 
                'Files approved successfully and PDFs regenerated with witness details.' :
                'Files approved successfully.' + pdfMessage;
                
              await Swal.fire({
                icon: 'success',
                title: 'Success',
                text: successMessage
              });
              
            } catch (error) {
              // Hide spinner on error
              hidePDFRegenerationSpinner();
              
              console.error('Approval process error:', error);
              
              await Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Failed to complete approval process: ' + (error.message || error)
              });
            }
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
        console.log('Submit button clicked - checking ACPH validation');
        
        // Check ACPH validation first if functions are available
        if (typeof window.validateACPHDataComplete === 'function') {
          console.log('ACPH validation function found, calling it...');
          const validationResult = window.validateACPHDataComplete();
          console.log('ACPH validation result:', validationResult);
          
          if (!validationResult.isComplete && !validationResult.validationSkipped) {
            console.log('ACPH validation failed, showing error');
            window.showACPHValidationError(validationResult);
            return false;
          } else {
            console.log('ACPH validation passed or skipped');
          }
        } else {
          console.log('ACPH validation function not found');
        }

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
          // Add offline mode file validation before showing password modal
          validateOfflineModeFiles().then(function(validationResult) {
            if (!validationResult.isValid) {
              Swal.fire({
                icon: 'error',
                title: 'Missing Required Files',
                text: validationResult.message,
                confirmButtonText: 'OK'
              });
              return;
            }
            
            // If validation passes, configure and show the password modal
            configureRemarksModal(
              'assign', // action
              'core/data/update/updatewfstage.php', // endpoint
              {
                test_conducted_date: convertDateFormat($('#test_conducted_date').val()),
                val_wf_id: val_wf_id,
                test_val_wf_id: test_val_wf_id,
                current_wf_stage: current_wf_stage,
                action: 'assign'
              },
              function(response) {
                // Success callback
                Swal.fire({
                  icon: 'success',
                  title: 'Success',
                  text: response.message || 'Test submitted successfully'
                }).then(() => {
                  window.location.href = 'assignedcases.php';
                });
              }
            );
            
            $('#enterPasswordRemark').modal('show');
          }).catch(function(error) {
            console.error('Validation failed:', error);
            Swal.fire({
              icon: 'error',
              title: 'Validation Error',
              text: 'Unable to validate file uploads. Please try again.',
              confirmButtonText: 'OK'
            });
          });
        }

      });



      $("#vendorsubmitreassign").click(function() {
        // Check ACPH validation first if functions are available
        if (typeof window.validateACPHDataComplete === 'function') {
          const validationResult = window.validateACPHDataComplete();
          if (!validationResult.isComplete && !validationResult.validationSkipped) {
            window.showACPHValidationError(validationResult);
            return false;
          }
        }
        
        // Configure modal for the appropriate stage
        if (current_wf_stage == '<?php echo STAGE_REASSIGNED_B; ?>') {
          configureRemarksModal(
            'assign_back_engg_vendor', // action
            'core/data/update/updatewfstage.php', // endpoint
            {
              test_conducted_date: convertDateFormat($('#test_conducted_date').val()),
              val_wf_id: val_wf_id,
              test_val_wf_id: test_val_wf_id,
              current_wf_stage: current_wf_stage,
              action: 'assign_back_engg_vendor'
            },
            function(response) {
              // Success callback
              Swal.fire({
                icon: 'success',
                title: 'Success',
                text: response.message || 'Test resubmitted successfully'
              }).then(() => {
                window.location.href = 'assignedcases.php';
              });
            }
          );
        } else if (current_wf_stage == '<?php echo STAGE_REASSIGNED_4B; ?>') {
          configureRemarksModal(
            'assign_back_qa_vendor', // action
            'core/data/update/updatewfstage.php', // endpoint
            {
              test_conducted_date: convertDateFormat($('#test_conducted_date').val()),
              val_wf_id: val_wf_id,
              test_val_wf_id: test_val_wf_id,
              current_wf_stage: current_wf_stage,
              action: 'assign_back_qa_vendor'
            },
            function(response) {
              // Success callback
              Swal.fire({
                icon: 'success',
                title: 'Success',
                text: response.message || 'Test resubmitted successfully'
              }).then(() => {
                window.location.href = 'assignedcases.php';
              });
            }
          );
        }

        $('#enterPasswordRemark').modal('show');
      });


      $("#enggsubmit").click(function() {
        // Check ACPH validation first if functions are available
        if (typeof window.validateACPHDataComplete === 'function') {
          const validationResult = window.validateACPHDataComplete();
          if (!validationResult.isComplete && !validationResult.validationSkipped) {
            window.showACPHValidationError(validationResult);
            return false;
          }
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
          // Configure the remarks modal for internal test submission
          configureRemarksModal(
            'assign', // action
            'core/data/update/updatewfstage.php', // endpoint
            {
              test_conducted_date: convertDateFormat($('#test_conducted_date').val()),
              test_type: 'internal',
              val_wf_id: val_wf_id,
              test_val_wf_id: test_val_wf_id,
              current_wf_stage: current_wf_stage,
              action: 'assign'
            },
            function(response) {
              // Success callback
              Swal.fire({
                icon: 'success',
                title: 'Success',
                text: response.message || 'Internal test submitted successfully'
              }).then(() => {
                window.location.href = 'assignedcases.php';
              });
            }
          );
          
          $('#enterPasswordRemark').modal('show');
        }


      });

      $("#enggapprove").click(function() {
        // Check ACPH validation first if functions are available
        if (typeof window.validateACPHDataComplete === 'function') {
          const validationResult = window.validateACPHDataComplete();
          if (!validationResult.isComplete && !validationResult.validationSkipped) {
            window.showACPHValidationError(validationResult);
            return false;
          }
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
          // Configure the remarks modal for engineering approval
          configureRemarksModal(
            'engg_approve', // action
            'core/data/update/updatewfstage.php', // endpoint
            {
              test_conducted_date: convertDateFormat($('#test_conducted_date').val()),
              val_wf_id: val_wf_id,
              test_val_wf_id: test_val_wf_id,
              current_wf_stage: current_wf_stage,
              action: 'engg_approve'
            },
            function(response) {
              // Custom success callback for engineering approval
              Swal.fire({
                icon: 'success',
                title: 'Success',
                text: response.message || 'Test approved successfully'
              }).then(() => {
                window.location.href = 'assignedcases.php';
              });
            }
          );
          
          $('#enterPasswordRemark').modal('show');
        }


      });

      $("#enggreject").click(function() {
        // Check ACPH validation first if functions are available
        if (typeof window.validateACPHDataComplete === 'function') {
          const validationResult = window.validateACPHDataComplete();
          if (!validationResult.isComplete && !validationResult.validationSkipped) {
            window.showACPHValidationError(validationResult);
            return false;
          }
        }

        url = "core/data/update/updatewfstage.php?test_conducted_date=" + convertDateFormat($('#test_conducted_date').val()) + "&val_wf_id=" + val_wf_id + "&test_val_wf_id=" + test_val_wf_id + "&current_wf_stage=" + current_wf_stage + "&action=engg_reject";

        if ($(".navlink-approve")[0]) {
          Swal.fire({
            icon: 'error',
            title: 'Oops...',
            text: 'You have one or more files to be approved.'
          });
        } else {
          // Configure the remarks modal for engineering rejection
          configureRemarksModal(
            'engg_reject', // action
            'core/data/update/updatewfstage.php', // endpoint
            {
              test_conducted_date: convertDateFormat($('#test_conducted_date').val()),
              val_wf_id: val_wf_id,
              test_val_wf_id: test_val_wf_id,
              current_wf_stage: current_wf_stage,
              action: 'engg_reject'
            },
            function(response) {
              // Custom success callback for engineering rejection
              Swal.fire({
                icon: 'success',
                title: 'Success',
                text: response.message || 'Test rejected successfully'
              }).then(() => {
                window.location.href = 'assignedcases.php';
              });
            }
          );
          
          $('#enterPasswordRemark').modal('show');
        }
      });

      $("#enggapproval1").click(function() {
        // Check ACPH validation first if functions are available
        if (typeof window.validateACPHDataComplete === 'function') {
          const validationResult = window.validateACPHDataComplete();
          if (!validationResult.isComplete && !validationResult.validationSkipped) {
            window.showACPHValidationError(validationResult);
            return false;
          }
        }
        
        if ($(".navlink-approve")[0]) {
          Swal.fire({
            icon: 'error',
            title: 'Oops...',
            text: 'You have one or more files to be approved.'
          });
        } else {
          // Configure the remarks modal for final engineering approval
          configureRemarksModal(
            'engg_approve_final', // action
            'core/data/update/updatewfstage.php', // endpoint
            {
              test_conducted_date: convertDateFormat($('#test_conducted_date').val()),
              val_wf_id: val_wf_id,
              test_val_wf_id: test_val_wf_id,
              current_wf_stage: current_wf_stage,
              action: 'engg_approve_final'
            },
            function(response) {
              // Success callback
              Swal.fire({
                icon: 'success',
                title: 'Success',
                text: response.message || 'Test approved successfully'
              }).then(() => {
                window.location.href = 'assignedcases.php';
              });
            }
          );
          
          $('#enterPasswordRemark').modal('show');
        }
      });

      $("#qaapprove").click(async function() {
        // Check ACPH validation first if functions are available
        if (typeof window.validateACPHDataComplete === 'function') {
          const validationResult = window.validateACPHDataComplete();
          if (!validationResult.isComplete && !validationResult.validationSkipped) {
            window.showACPHValidationError(validationResult);
            return false;
          }
        }
        
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
          
          // Check if we need PDF regeneration for stage 3A + paper on glass + online mode
          if (current_wf_stage === '<?php echo STAGE_REASSIGNED_A; ?>' && department_id === '<?php echo DEPT_QA; ?>') {
            
            // Show confirmation dialog first
            const result = await Swal.fire({
              title: 'QA Approval Confirmation',
              text: 'Do you want to approve this test? Certificate files will be regenerated with approval details.',
              icon: 'question',
              showCancelButton: true,
              confirmButtonText: 'Yes, Approve',
              cancelButtonText: 'Cancel'
            });

            if (result.isConfirmed) {
              const csrfToken = $('meta[name="csrf-token"]').attr('content');
              
              // Show spinner for PDF regeneration
              showQAApprovalSpinner();
              
              try {
                // Step 1: Regenerate PDFs with QA approval details (only for stage 3A)
                const pdfResponse = await $.post("core/data/save/regenerate_qa_approval_pdfs.php", {
                  test_wf_id: test_val_wf_id,
                  csrf_token: csrfToken
                });
                
                let pdfMessage = '';
                
                if (pdfResponse.success) {
                  pdfMessage = ' Certificates regenerated with approval details.';
                  console.log('QA PDF regeneration successful:', pdfResponse.message);
                } else {
                  // If PDF regeneration fails, still continue with approval but log the error
                  console.log('QA PDF regeneration failed:', pdfResponse.error);
                  pdfMessage = ' (Certificate regeneration failed - approval will proceed)';
                }
                
                // Hide spinner
                hideQAApprovalSpinner();
                
                // Step 2: Proceed with normal approval workflow via modal
                $('#enterPasswordRemark').modal('show');
                
              } catch (error) {
                // Hide spinner on error
                hideQAApprovalSpinner();
                
                console.error('QA PDF regeneration error:', error);
                
                await Swal.fire({
                  icon: 'warning',
                  title: 'PDF Regeneration Failed',
                  text: 'Certificate regeneration failed, but you can still proceed with approval. Continue?',
                  showCancelButton: true,
                  confirmButtonText: 'Continue Approval',
                  cancelButtonText: 'Cancel'
                }).then((continueResult) => {
                  if (continueResult.isConfirmed) {
                    $('#enterPasswordRemark').modal('show');
                  }
                });
              }
            }
            
          } else {
            // For non-3A stages or other conditions, proceed normally
            $('#enterPasswordRemark').modal('show');
          }
        }
      });

      $("#qareject").click(function() {
        // Check ACPH validation first if functions are available
        if (typeof window.validateACPHDataComplete === 'function') {
          const validationResult = window.validateACPHDataComplete();
          if (!validationResult.isComplete && !validationResult.validationSkipped) {
            window.showACPHValidationError(validationResult);
            return false;
          }
        }
        
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
      
      // Test Data Entry - Instrument Management Functionality
      if ($('#instrument_search').length > 0) {
        // Define workflow ID variables from PHP
        const test_val_wf_id = '<?php echo htmlspecialchars($test_val_wf_id, ENT_QUOTES); ?>';
        const val_wf_id = '<?php echo htmlspecialchars($val_wf_id ?? '', ENT_QUOTES); ?>';
        
        let selectedInstrument = null;
        let searchTimeout = null;
        
        // Load existing instruments on page load
        loadTestInstruments();
        
        // Check if offline mode is already selected and disable buttons
        if ($('#mode_offline').is(':checked')) {
          $('#mode_online').prop('disabled', true);
          $('#mode_offline').prop('disabled', true);
          $('.mode-option').addClass('disabled-mode');
        }
        
        // Instrument search functionality
        $('#instrument_search').on('input', function() {
          const searchTerm = $(this).val().trim();
          
          // Clear previous timeout
          if (searchTimeout) {
            clearTimeout(searchTimeout);
          }
          
          // Handle clear button visibility
          if (searchTerm.length > 0) {
            $('#clear_selection_btn').show();
          } else {
            $('#clear_selection_btn').hide();
            selectedInstrument = null;
          }
          
          // Hide dropdown if less than 2 characters
          if (searchTerm.length < 2) {
            $('#instrument_dropdown').hide();
            $('#add_instrument_btn').prop('disabled', true);
            selectedInstrument = null;
            return;
          }
          
          // Reset selection when user types (not selecting from dropdown)
          selectedInstrument = null;
          $('#add_instrument_btn').prop('disabled', true);
          
          // Set new timeout for search
          searchTimeout = setTimeout(function() {
            searchInstruments(searchTerm);
          }, 300);
        });
        
        // Hide dropdown when clicking outside
        $(document).on('click', function(event) {
          if (!$(event.target).closest('#instrument_search, #instrument_dropdown').length) {
            $('#instrument_dropdown').hide();
          }
        });
        
        // Handle instrument selection from dropdown
        $(document).on('click', '.instrument-option', function(e) {
          e.preventDefault();
          
          // Check if instrument is disabled (expired calibration)
          if ($(this).data('disabled') === 'true') {
            // Show error message for expired instruments
            Swal.fire({
              icon: 'error',
              title: 'Cannot Add Instrument',
              text: 'This instrument cannot be added because its calibration has expired.',
              timer: 3000,
              showConfirmButton: false
            });
            return;
          }
          
          const instrumentData = $(this).data();
          selectedInstrument = instrumentData;
          
          // Format: "Air Capture Hood - INs234 - s222"
          const formattedText = `${instrumentData.type} - ${instrumentData.code} - ${instrumentData.serial || instrumentData.name}`;
          $('#instrument_search').val(formattedText);
          $('#instrument_dropdown').hide();
          $('#add_instrument_btn').prop('disabled', false);
          $('#clear_selection_btn').show();
        });
        
        // Clear selection button click
        $('#clear_selection_btn').click(function() {
          $('#instrument_search').val('');
          selectedInstrument = null;
          $('#add_instrument_btn').prop('disabled', true);
          $('#clear_selection_btn').hide();
          $('#instrument_dropdown').hide();
        });
        
        // Add instrument button click
        $('#add_instrument_btn').click(function() {
          if (selectedInstrument) {
            addInstrumentToTest(selectedInstrument.id);
          }
        });
        
        // Data Entry Mode change handling
        $('input[name="data_entry_mode"]').on('change', function() {
          // Prevent changes if buttons are disabled
          if ($(this).is(':disabled')) {
            return false;
          }
          
          const selectedMode = $(this).val();
          
          // If user selects offline mode, show confirmation dialog
          if (selectedMode === 'offline') {
            Swal.fire({
              icon: 'warning',
              title: 'Switch to Offline Mode?',
              html: `
                <div class="text-left">
                  <p><strong>Important:</strong> This action cannot be undone for this test.</p>
                  <p>In offline mode, you will need to:</p>
                  <ul style="text-align: left; padding-left: 20px;">
                    <li>Record all test data on paper first</li>
                    <li>Upload the handwritten data sheets to the system</li>
                    <li>Enter data digitally based on your paper records</li>
                  </ul>
                  <p><strong>Are you sure you want to proceed?</strong></p>
                </div>
              `,
              showCancelButton: true,
              confirmButtonText: 'Yes, Switch to Offline Mode',
              cancelButtonText: 'Cancel',
              confirmButtonColor: '#d33',
              cancelButtonColor: '#3085d6'
            }).then((result) => {
              if (result.isConfirmed) {
                // User confirmed - save offline mode to database
                saveDataEntryMode('offline');
              } else {
                // User cancelled - revert to online mode
                $('#mode_online').prop('checked', true);
                $('#mode_offline').prop('checked', false);
              }
            });
          } else if (selectedMode === 'online') {
            // Save online mode to database (no confirmation needed)
            saveDataEntryMode('online');
          }
        });
        
        // Remove instrument functionality
        $(document).on('click', '.remove-instrument-btn', function() {
          const mappingId = $(this).data('mapping-id');
          const instrumentName = $(this).data('instrument-name');
          
          Swal.fire({
            title: 'Remove Instrument?',
            text: `Are you sure you want to remove "${instrumentName}" from this test?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, Remove',
            cancelButtonText: 'Cancel'
          }).then((result) => {
            if (result.isConfirmed) {
              removeInstrumentFromTest(mappingId);
            }
          });
        });
      }
      
      // Function to save data entry mode to database
      function saveDataEntryMode(mode) {
        $.ajax({
          url: 'core/data/update/savedataentrymode.php',
          type: 'POST',
          data: {
            test_val_wf_id: test_val_wf_id,
            val_wf_id: val_wf_id,
            data_entry_mode: mode
          },
          success: function(response) {
            if (response.status === 'success') {
              if (mode === 'offline') {
                // Disable both radio buttons to prevent further changes
                $('#mode_online').prop('disabled', true);
                $('#mode_offline').prop('disabled', true);
                
                // Add visual indication that buttons are disabled
                $('.mode-option').addClass('disabled-mode');
                
                // Show confirmation for offline mode
                Swal.fire({
                  icon: 'info',
                  title: 'Offline Mode Enabled',
                  text: 'Test data must now be recorded on paper first and uploaded to the system. This selection cannot be changed.',
                  timer: 4000,
                  showConfirmButton: false
                });
              }
            } else {
              // Show error message
              Swal.fire({
                icon: 'error',
                title: 'Error',
                text: response.message || 'Failed to save data entry mode'
              });
              
              // Revert the radio button on error
              if (mode === 'offline') {
                $('#mode_online').prop('checked', true);
                $('#mode_offline').prop('checked', false);
              } else {
                $('#mode_offline').prop('checked', true);
                $('#mode_online').prop('checked', false);
              }
            }
          },
          error: function(xhr, status, error) {
                
            Swal.fire({
              icon: 'error',
              title: 'Error',
              text: 'Failed to save data entry mode. Please try again.'
            });
            
            // Revert the radio button on error
            if (mode === 'offline') {
              $('#mode_online').prop('checked', true);
              $('#mode_offline').prop('checked', false);
            } else {
              $('#mode_offline').prop('checked', true);
              $('#mode_online').prop('checked', false);
            }
          }
        });
      }
      
      // Function to validate offline mode file uploads
      function validateOfflineModeFiles() {
        // Check if in offline mode
        const currentMode = window.testFinalizationStatus && window.testFinalizationStatus.data_entry_mode 
            ? window.testFinalizationStatus.data_entry_mode : 'online';
        
        if (currentMode !== 'offline') {
            return Promise.resolve({ isValid: true, message: 'Not offline mode - validation skipped' });
        }
        
        // Make AJAX call to check uploaded files
        return $.ajax({
          url: 'core/data/get/validateofflinefiles.php',
          type: 'GET',
          data: {
            test_val_wf_id: test_val_wf_id
          },
          dataType: 'json'
        }).then(function(response) {
          return {
            isValid: response.isValid,
            message: response.message,
            missing_files: response.missing_files || []
          };
        }).catch(function(xhr, status, error) {
          console.error('Validation error:', error);
          return {
            isValid: false,
            message: 'Failed to validate files. Please try again.'
          };
        });
      }
      
      // Function to search instruments
      function searchInstruments(searchTerm) {
        
        $.ajax({
          url: 'core/data/get/searchinstruments_autocomplete.php',
          type: 'GET',
          data: {
            q: searchTerm,
            test_val_wf_id: test_val_wf_id
          },
          beforeSend: function() {
            $('#instrument_dropdown').html('<div class="dropdown-item-text text-info">Searching...</div>').show();
          },
          success: function(response) {
            
            // Handle both JSON object and string responses
            let data = response;
            if (typeof response === 'string') {
              try {
                data = JSON.parse(response);
              } catch (e) {
                console.error('Failed to parse response as JSON:', e);
                $('#instrument_dropdown').html('<div class="dropdown-item-text text-danger">Invalid response format</div>').show();
                return;
              }
            }
            
            if (data.instruments && data.instruments.length > 0) {
              let dropdownHtml = '';
              data.instruments.forEach(function(instrument) {
                // Check if instrument calibration is expired
                const isExpired = instrument.calibration_status === 'Expired';
                const badgeClass = instrument.calibration_status === 'Valid' ? 'success' : 
                                 instrument.calibration_status === 'Expired' ? 'danger' : 'warning';
                const itemClass = isExpired ? 'dropdown-item instrument-option disabled' : 'dropdown-item instrument-option';
                const disabledAttr = isExpired ? 'data-disabled="true"' : '';
                
                dropdownHtml += `
                  <a href="#" class="${itemClass}" 
                     data-id="${instrument.id}"
                     data-code="${instrument.code}"
                     data-name="${instrument.name}"
                     data-type="${instrument.type}"
                     data-serial="${instrument.serial_number || ''}"
                     data-calibration-status="${instrument.calibration_status}"
                     ${disabledAttr}
                     ${isExpired ? 'style="opacity: 0.6; cursor: not-allowed;"' : ''}>
                    <div class="d-flex justify-content-between">
                      <div>
                        <strong>${instrument.name}</strong> (${instrument.code})
                        <br><small class="text-muted">${instrument.type} - ${instrument.serial_number}</small>
                        ${isExpired ? '<br><small class="text-danger"><i class="mdi mdi-alert-circle"></i> Calibration Expired - Cannot be added</small>' : ''}
                      </div>
                      <div class="text-right">
                        <span class="badge badge-${badgeClass}">${instrument.calibration_status}</span>
                      </div>
                    </div>
                  </a>
                `;
              });
              $('#instrument_dropdown').html(dropdownHtml).show();
            } else if (data.error) {
              $('#instrument_dropdown').html(`<div class="dropdown-item-text text-danger">Error: ${data.error}</div>`).show();
            } else {
              $('#instrument_dropdown').html('<div class="dropdown-item-text text-muted">No instruments found</div>').show();
            }
          },
          error: function(xhr, status, error) {
            console.error('Search AJAX error:', {xhr: xhr, status: status, error: error});
            console.error('Response text:', xhr.responseText);
            
            let errorMsg = 'Search failed. Please try again.';
            if (xhr.responseText) {
              try {
                let errorData = JSON.parse(xhr.responseText);
                if (errorData.error) {
                  errorMsg = errorData.error;
                }
              } catch (e) {
                // If not JSON, show first 100 chars of response
                errorMsg = `Error: ${xhr.responseText.substring(0, 100)}`;
              }
            }
            
            $('#instrument_dropdown').html(`<div class="dropdown-item-text text-danger">${errorMsg}</div>`).show();
          }
        });
      }
      
      // Function to reload all test-specific data entry sections after instrument changes
      function reloadTestDataEntrySections(action) {
        console.log('[RELOAD] Starting reload of test data entry sections after instrument ' + action);
        
        // Debug: Check what functions are available
        console.log('[DEBUG] Checking available functions:');
        console.log('  - loadACPHFiltersAndData:', typeof loadACPHFiltersAndData);
        console.log('  - loadInstrumentsForDropdowns:', typeof loadInstrumentsForDropdowns);
        console.log('  - loadInstrumentsForTemperatureDropdowns:', typeof loadInstrumentsForTemperatureDropdowns);
        console.log('  - loadInstrumentsForAirflowDropdowns:', typeof loadInstrumentsForAirflowDropdowns);
        
        // 1. ACPH Test - Reload filters and instrument dropdowns directly
        if (typeof loadACPHFiltersAndData === 'function') {
          console.log('[ACPH] Calling loadACPHFiltersAndData()');
          loadACPHFiltersAndData();
          console.log('[SUCCESS] Completed ACPH filter sections reload');
        } else {
          console.log('[WARNING] loadACPHFiltersAndData function not available');
        }
        
        // 1.1. ACPH Test - Direct instrument dropdown reload (more reliable)
        if (typeof loadInstrumentsForDropdowns === 'function') {
          console.log('[ACPH] Calling loadInstrumentsForDropdowns() directly');
          loadInstrumentsForDropdowns();
          console.log('[SUCCESS] Completed direct ACPH instrument dropdowns reload');
        } else {
          console.log('[WARNING] loadInstrumentsForDropdowns function not available - attempting manual reload');
          
          // Manual ACPH instrument reload if function not available
          try {
            console.log('[MANUAL] Attempting manual ACPH instrument dropdowns reload...');
            
            $.ajax({
              url: 'core/data/get/gettestinstruments.php',
              type: 'GET',
              data: {
                test_val_wf_id: test_val_wf_id,
                format: 'dropdown'
              },
              success: function(response) {
                console.log('[AJAX] Manual instrument data received:', response);
                try {
                  const data = typeof response === 'string' ? JSON.parse(response) : response;
                  
                  if (data.instruments && Array.isArray(data.instruments)) {
                    console.log('[MANUAL] Manual update of ACPH instrument dropdowns with ' + data.instruments.length + ' instruments');
                    
                    // Create instrument options
                    const instrumentOptions = data.instruments.map(function(instrument) {
                      let statusClass = '';
                      if (instrument.calibration_status === 'Expired') {
                        statusClass = ' (EXPIRED)';
                      } else if (instrument.calibration_status === 'Due Soon') {
                        statusClass = ' (Due Soon)';
                      }
                      return `<option value="${instrument.id}" data-status="${instrument.calibration_status}">${instrument.display_name}${statusClass}</option>`;
                    }).join('');
                    
                    // Update Global Instrument Selection dropdown
                    const $globalSelect = $('#global_instrument_select');
                    if ($globalSelect.length > 0) {
                      const currentGlobalValue = $globalSelect.val();
                      $globalSelect.html('<option value="">Select Instrument for ALL Readings...</option><option value="manual">Manual Entry</option>' + instrumentOptions);
                      if (currentGlobalValue) {
                        $globalSelect.val(currentGlobalValue);
                        if ($globalSelect.val() !== currentGlobalValue) {
                          console.warn('[WARNING] Previously selected global instrument ' + currentGlobalValue + ' no longer available');
                          $globalSelect.val('');
                        }
                      }
                      console.log('[SUCCESS] Updated global instrument dropdown');
                    } else {
                      console.log('[WARNING] Global instrument select not found');
                    }
                    
                    // Update all filter-level instrument dropdowns
                    const $filterSelects = $('.filter-instrument-select');
                    if ($filterSelects.length > 0) {
                      $filterSelects.each(function() {
                        const $select = $(this);
                        const currentValue = $select.val();
                        $select.html('<option value="">Select instrument...</option><option value="manual">Manual Entry</option>' + instrumentOptions);
                        if (currentValue) {
                          $select.val(currentValue);
                          if ($select.val() !== currentValue) {
                            console.warn('[WARNING] Previously selected filter instrument ' + currentValue + ' no longer available');
                            $select.val('');
                          }
                        }
                      });
                      console.log('[SUCCESS] Updated ' + $filterSelects.length + ' filter instrument dropdowns');
                    } else {
                      console.log('[WARNING] No filter instrument selects found');
                    }
                    
                    // Update all reading instrument dropdowns
                    const $readingSelects = $('.reading-instrument-select');
                    if ($readingSelects.length > 0) {
                      $readingSelects.each(function() {
                        const $select = $(this);
                        const currentValue = $select.val();
                        // Clear existing instrument options but preserve base options
                        $select.find('option').not('[value=""], [value="manual"]').remove();
                        // Add instrument options after "Manual Entry"
                        const $manualOption = $select.find('option[value="manual"]');
                        if ($manualOption.length > 0) {
                          $manualOption.after(instrumentOptions);
                        } else {
                          $select.append(instrumentOptions);
                        }
                        // Restore selection
                        if (currentValue) {
                          $select.val(currentValue);
                          if ($select.val() !== currentValue) {
                            console.warn('[WARNING] Previously selected reading instrument ' + currentValue + ' no longer available');
                            $select.val('');
                          }
                        }
                      });
                      console.log('[SUCCESS] Updated ' + $readingSelects.length + ' reading instrument dropdowns');
                    } else {
                      console.log('[WARNING] No reading instrument selects found');
                    }
                    
                  } else {
                    console.error('[ERROR] Invalid instrument data received');
                  }
                } catch (e) {
                  console.error('[ERROR] Failed to parse instrument data:', e);
                }
              },
              error: function(xhr, status, error) {
                console.error('[ERROR] Failed to load instruments manually:', error);
              }
            });
          } catch (e) {
            console.error('[ERROR] Error during manual instrument reload:', e);
          }
        }
        
        // 2. Temperature Test - Reload instrument dropdowns
        if (typeof loadInstrumentsForTemperatureDropdowns === 'function') {
          console.log('[TEMP] Calling loadInstrumentsForTemperatureDropdowns()');
          loadInstrumentsForTemperatureDropdowns();
          console.log('[SUCCESS] Completed Temperature test instrument dropdowns reload');
        }
        
        // 3. Airflow Test - Reload instrument dropdowns
        if (typeof loadInstrumentsForAirflowDropdowns === 'function') {
          console.log('[AIRFLOW] Calling loadInstrumentsForAirflowDropdowns()');
          loadInstrumentsForAirflowDropdowns();
          console.log('[SUCCESS] Completed Airflow test instrument dropdowns reload');
        }
        
        // 4. Generic reload for any test-specific sections with instrument dropdowns
        try {
          const $genericSelects = $('.test-specific-instrument-select');
          if ($genericSelects.length > 0) {
            console.log('[GENERIC] Generic reload for ' + $genericSelects.length + ' test-specific instrument selects');
            
            // Reload all instrument dropdowns in test-specific sections
            $genericSelects.each(function() {
              const $select = $(this);
              const currentValue = $select.val(); // Preserve current selection
              
              // Reload instrument options via AJAX
              $.ajax({
                url: 'core/data/get/gettestinstruments.php',
                type: 'GET',
                data: {
                  test_val_wf_id: test_val_wf_id,
                  format: 'dropdown'
                },
                success: function(response) {
                  try {
                    const data = typeof response === 'string' ? JSON.parse(response) : response;
                    
                    if (data.instruments && Array.isArray(data.instruments)) {
                      // Update dropdown options
                      const instrumentOptions = data.instruments.map(function(instrument) {
                        let statusClass = '';
                        if (instrument.calibration_status === 'Expired') {
                          statusClass = ' (EXPIRED)';
                        } else if (instrument.calibration_status === 'Due Soon') {
                          statusClass = ' (Due Soon)';
                        }
                        
                        return `<option value="${instrument.id}" data-status="${instrument.calibration_status}">${instrument.display_name}${statusClass}</option>`;
                      }).join('');
                      
                      // Clear existing instrument options but preserve base options
                      $select.find('option').not('[value=""], [value="manual"]').remove();
                      
                      // Find manual entry option and add after it
                      const $manualOption = $select.find('option[value="manual"]');
                      if ($manualOption.length > 0) {
                        $manualOption.after(instrumentOptions);
                      } else {
                        // If no manual entry option, just append to end
                        $select.append(instrumentOptions);
                      }
                      
                      // Restore previous selection if still valid
                      if (currentValue) {
                        $select.val(currentValue);
                        if ($select.val() !== currentValue) {
                          console.warn('[WARNING] Previously selected generic instrument ' + currentValue + ' no longer available');
                          $select.val(''); // Clear invalid selection
                        }
                      }
                    }
                  } catch (e) {
                    console.error('[ERROR] Failed to parse instruments response for generic dropdown reload:', e);
                  }
                },
                error: function(xhr, status, error) {
                  console.error('[ERROR] Failed to reload instruments for generic dropdown:', error);
                }
              });
            });
            
            console.log('[SUCCESS] Initiated generic test-specific instrument dropdowns reload');
          } else {
            console.log('[INFO] No generic test-specific instrument selects found');
          }
          
        } catch (e) {
          console.error('[ERROR] Error during generic instrument dropdown reload:', e);
        }
        
        // 5. Trigger custom event for any custom test sections to listen for
        $(document).trigger('testInstrumentsUpdated', {
          action: action,
          test_val_wf_id: test_val_wf_id
        });
        console.log('[EVENT] Triggered testInstrumentsUpdated event');
        
        console.log('[COMPLETE] Completed test data entry sections reload after instrument ' + action);
      }
      
      // Function to add instrument to test
      function addInstrumentToTest(instrumentId) {
        $.ajax({
          url: 'core/data/save/addtestinstrument_simple.php',
          type: 'POST',
          data: {
            instrument_id: instrumentId,
            test_val_wf_id: test_val_wf_id,
            csrf_token: $('meta[name="csrf-token"]').attr('content')
          },
          success: function(response) {
            if (response.status === 'success') {
              // Update CSRF token
              if (response.csrf_token) {
                $('meta[name="csrf-token"]').attr('content', response.csrf_token);
              }
              
              // Clear search field and hide clear button
              $('#instrument_search').val('');
              $('#add_instrument_btn').prop('disabled', true);
              $('#clear_selection_btn').hide();
              selectedInstrument = null;
              
              // Reload instruments table
              loadTestInstruments();
              
              // Reload all test-specific data entry sections
              reloadTestDataEntrySections('add');
              console.log('Reloaded all test data entry sections after adding new instrument');
              
              // Show success message
              Swal.fire({
                icon: 'success',
                title: 'Success',
                text: response.message,
                timer: 2000,
                showConfirmButton: false
              });
            } else {
              Swal.fire({
                icon: 'error',
                title: 'Error',
                text: response.message
              });
            }
          },
          error: function(xhr, status, error) {
            
            Swal.fire({
              icon: 'error',
              title: 'Error',
              text: 'Failed to add instrument. Please check console for details.'
            });
          }
        });
      }
      
      // Function to remove instrument from test
      function removeInstrumentFromTest(mappingId) {
        $.ajax({
          url: 'core/data/update/removetestinstrument_simple.php',
          type: 'POST',
          data: {
            mapping_id: mappingId,
            test_val_wf_id: test_val_wf_id,
            csrf_token: $('meta[name="csrf-token"]').attr('content')
          },
          success: function(response) {
            if (response.status === 'success') {
              // Update CSRF token
              if (response.csrf_token) {
                $('meta[name="csrf-token"]').attr('content', response.csrf_token);
              }
              
              // Reload instruments table
              loadTestInstruments();
              
              // Reload all test-specific data entry sections
              reloadTestDataEntrySections('remove');
              console.log('Reloaded all test data entry sections after removing instrument');
              
              // Show success message
              Swal.fire({
                icon: 'success',
                title: 'Success',
                text: response.message,
                timer: 2000,
                showConfirmButton: false
              });
            } else {
              Swal.fire({
                icon: 'error',
                title: 'Error',
                text: response.message
              });
            }
          },
          error: function(xhr, status, error) {
            let errorMessage = 'Failed to remove instrument. Please check console for details.';
            let errorTitle = 'Error';
            
            // Handle specific error types for better user experience
            if (xhr.status === 400 && xhr.responseJSON) {
              const response = xhr.responseJSON;
              if (response.error_type === 'instrument_in_use') {
                errorTitle = 'Instrument Currently In Use';
                errorMessage = response.message;
                
                // Show detailed error with additional guidance
                Swal.fire({
                  icon: 'warning',
                  title: errorTitle,
                  html: `
                    <div style="text-align: left; margin: 10px 0;">
                      <p><strong>Issue:</strong> ${response.message}</p>
                      <br>
                      <p><strong>To resolve this:</strong></p>
                      <ol style="text-align: left; margin-left: 20px;">
                        <li>Go to the ACPH test data entry section</li>
                        <li>Update the affected filter sections to use different instruments</li>
                        <li>Save the filter data changes</li>
                        <li>Then try removing this instrument again</li>
                      </ol>
                      <br>
                      <p><em>Affected sections: ${response.usage_count} filter(s)</em></p>
                    </div>
                  `,
                  width: 600,
                  confirmButtonText: 'Got it',
                  confirmButtonColor: '#007bff'
                });
                return; // Exit early to avoid the generic error dialog
              }
            }
            
            // Generic error handling
            Swal.fire({
              icon: 'error',
              title: errorTitle,
              text: errorMessage
            });
          }
        });
      }
      
      // Function to load test instruments
      function loadTestInstruments() {
        
        $.ajax({
          url: 'core/data/get/gettestinstruments.php',
          type: 'GET',
          data: {
            test_val_wf_id: test_val_wf_id
          },
          success: function(response) {
            
            // Handle both JSON object and string responses
            let data = response;
            if (typeof response === 'string') {
              try {
                data = JSON.parse(response);
              } catch (e) {
                console.error('Failed to parse instruments response as JSON:', e);
                $('#test_instruments_tbody').html(`
                  <tr>
                    <td colspan="8" class="text-center text-danger">Invalid response format. Please refresh the page.</td>
                  </tr>
                `);
                return;
              }
            }
            
            if (data.error) {
              $('#test_instruments_tbody').html(`
                <tr>
                  <td colspan="8" class="text-center text-danger">Error: ${data.error}</td>
                </tr>
              `);
              return;
            }
            
            if (data.instruments && data.instruments.length > 0) {
              let tableHtml = '';
              data.instruments.forEach(function(instrument) {
                const actionsColumn = read_mode === 'yes' ? '' : `
                  <td>
                    <button type="button" class="btn btn-sm btn-danger remove-instrument-btn" 
                            data-mapping-id="${instrument.mapping_id}"
                            data-instrument-name="${instrument.instrument_name}">
                      <i class="mdi mdi-delete"></i> Remove
                    </button>
                  </td>
                `;
                
                tableHtml += `
                  <tr>
                    <td>${instrument.instrument_type}</td>
                    <td>${instrument.instrument_name}</td>
                    <td>${instrument.serial_number}</td>
                    <td>${instrument.instrument_code}</td>
                    <td><span class="badge ${instrument.status_class}">${instrument.calibration_status}</span></td>
                    <td>${instrument.added_date}</td>
                    <td>${instrument.added_by_name}</td>
                    ${actionsColumn}
                  </tr>
                `;
              });
              $('#test_instruments_tbody').html(tableHtml);
              $('#no_instruments_row').hide();
            } else {
              $('#test_instruments_tbody').html(`
                <tr id="no_instruments_row">
                  <td colspan="8" class="text-center text-muted">No instruments added yet</td>
                </tr>
              `);
            }
          },
          error: function(xhr, status, error) {
            console.error('Load instruments AJAX error:', {xhr: xhr, status: status, error: error});
            console.error('Response text:', xhr.responseText);
            
            let errorMsg = 'Failed to load instruments. Please refresh the page.';
            if (xhr.responseText) {
              try {
                let errorData = JSON.parse(xhr.responseText);
                if (errorData.error) {
                  errorMsg = errorData.error;
                }
              } catch (e) {
                // If not JSON, show first 100 chars of response
                errorMsg = `Error: ${xhr.responseText.substring(0, 100)}`;
              }
            }
            
            $('#test_instruments_tbody').html(`
              <tr>
                <td colspan="8" class="text-center text-danger">${errorMsg}</td>
              </tr>
            `);
          }
        });
      }
      
      // Function to show submit buttons after test data finalization
      function showSubmitButtonsAfterFinalization() {
        console.log('Showing submit buttons after test finalization...');
        
        // Show all submit buttons that were hidden due to paper-on-glass + online mode
        $('#vendorsubmitassign, #vendorsubmitreassign, #enggsubmit').each(function() {
          const $button = $(this);
          if ($button.length > 0) {
            // Show the button and its parent elements if they exist
            $button.show();
            $button.closest('.btn-group, .button-container, .text-center').show();
            
            // Also show any wrapper divs or table cells that might be hidden
            $button.parents().filter(':hidden').show();
          }
        });
        
        // Handle 1RRV stage - show Submit to Checker button after finalization
        const urlParams = new URLSearchParams(window.location.search);
        const currentStage = urlParams.get('current_wf_stage');
        
        if (currentStage === '1RRV') {
          // Find the warning message and replace it with the Submit to Checker button
          const warningAlert = $('.alert-warning').filter(':contains("Test data must be finalized")');
          if (warningAlert.length > 0) {
            warningAlert.replaceWith(`
              <button id="resubmit_to_checker" class='btn btn-primary btn-small'>Submit to Checker</button>
            `);
            
            // Re-bind the click event for the new button with upload validation
            $("#resubmit_to_checker").off('click').on('click', function() {
              const testWfId = urlParams.get('test_val_wf_id') || '';

              if (!testWfId) {
                Swal.fire({
                  icon: 'error',
                  title: 'Error',
                  text: 'Missing test workflow ID'
                });
                return;
              }

              // Show loading state
              const $button = $(this);
              const originalText = $button.text();
              $button.prop('disabled', true).text('Validating...');

              // First, validate uploads before opening modal
              $.ajax({
                url: 'core/data/get/validate_uploads.php',
                type: 'POST',
                dataType: 'json',
                data: {
                  test_wf_id: testWfId,
                  csrf_token: $("input[name='csrf_token']").val()
                },
                success: function(response) {
                  // Restore button state
                  $button.prop('disabled', false).text(originalText);

                  if (response.status === 'success') {
                    // Upload validation passed - proceed with modal
                    configureRemarksModal(
                      'resubmit', // action
                      'core/data/update/resubmit_offline_test.php', // endpoint
                      {
                        test_wf_id: testWfId,
                        val_wf_id: urlParams.get('val_wf_id') || '',
                        test_id: urlParams.get('test_id') || ''
                      },
                      function(response) {
                        // Custom success callback for resubmission
                        Swal.fire({
                          icon: 'success',
                          title: 'Success',
                          text: response.message || 'Test resubmitted successfully'
                        }).then(() => {
                          window.location.reload();
                        });
                      }
                    );

                    // Show the Add Remarks modal
                    $('#enterPasswordRemark').modal('show');
                  } else {
                    // Upload validation failed - show error
                    Swal.fire({
                      icon: 'error',
                      title: 'Upload Required',
                      text: response.message || 'Upload validation failed'
                    });
                  }
                },
                error: function(xhr, status, error) {
                  // Restore button state
                  $button.prop('disabled', false).text(originalText);

                  console.error('Upload validation error:', error);
                  Swal.fire({
                    icon: 'error',
                    title: 'Validation Error',
                    text: 'Failed to validate uploads. Please try again.'
                  });
                }
              });
            });
            
            console.log('Submit to Checker button shown after finalization for 1RRV stage');
          }
        }
        
        console.log('Submit buttons shown after finalization');
      }
      
      // Function to check if we should show submit buttons based on finalization status
      function checkAndShowSubmitButtons() {
        // Check if test data has been finalized
        if (window.testFinalizationStatus && window.testFinalizationStatus.is_finalized) {
          showSubmitButtonsAfterFinalization();
        }
      }
      
      // Global function to handle test finalization completion
      // This can be called from any included file (like _testdataentry_acph.php)
      window.onTestFinalizationComplete = function() {
        console.log('Test finalization completed - updating UI...');
        
        // Update global finalization status
        if (window.testFinalizationStatus) {
          window.testFinalizationStatus.is_finalized = true;
        }
        
        // Show submit buttons if conditions are met
        showSubmitButtonsAfterFinalization();
      };
      
      // Listen for custom testDataFinalized event from included files
      $(document).on('testDataFinalized', function(event, data) {
        console.log('Received testDataFinalized event with data:', data);
        
        // Update global finalization status
        if (window.testFinalizationStatus) {
          window.testFinalizationStatus.is_finalized = true;
          window.testFinalizationStatus.finalized_at = data.finalized_at;
        }
        
        // Show submit buttons
        showSubmitButtonsAfterFinalization();
        
        console.log('Processed testDataFinalized event and updated submit button visibility');
      });
      
      // Offline test review handlers
      $("#offline_approve").click(function() {
        url = "core/data/update/offline_test_review.php";
        offline_action = "approve";
        
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
      
      $("#offline_reject").click(function() {
        url = "core/data/update/offline_test_review.php";
        offline_action = "reject";
        
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
      
      $("#resubmit_to_checker").click(function() {
        // Configure the remarks modal for resubmission with upload validation
        const urlParams = new URLSearchParams(window.location.search);
        const testWfId = urlParams.get('test_val_wf_id') || '';

        if (!testWfId) {
          Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Missing test workflow ID'
          });
          return;
        }

        // Show loading state
        const $button = $(this);
        const originalText = $button.text();
        $button.prop('disabled', true).text('Validating...');

        // First, validate uploads before opening modal
        $.ajax({
          url: 'core/data/get/validate_uploads.php',
          type: 'POST',
          dataType: 'json',
          data: {
            test_wf_id: testWfId,
            csrf_token: $("input[name='csrf_token']").val()
          },
          success: function(response) {
            // Restore button state
            $button.prop('disabled', false).text(originalText);

            if (response.status === 'success') {
              // Upload validation passed - proceed with modal
              configureRemarksModal(
                'resubmit', // action
                'core/data/update/resubmit_offline_test.php', // endpoint
                {
                  test_wf_id: testWfId,
                  val_wf_id: urlParams.get('val_wf_id') || '',
                  test_id: urlParams.get('test_id') || ''
                },
                function(response) {
                  // Custom success callback for resubmission
                  Swal.fire({
                    icon: 'success',
                    title: 'Success',
                    text: response.message || 'Test resubmitted successfully'
                  }).then(() => {
                    window.location.reload();
                  });
                }
              );

              // Show the Add Remarks modal
              $('#enterPasswordRemark').modal('show');
            } else {
              // Upload validation failed - show error
              Swal.fire({
                icon: 'error',
                title: 'Upload Required',
                text: response.message || 'Upload validation failed'
              });
            }
          },
          error: function(xhr, status, error) {
            // Restore button state
            $button.prop('disabled', false).text(originalText);

            console.error('Upload validation error:', error);
            Swal.fire({
              icon: 'error',
              title: 'Validation Error',
              text: 'Failed to validate uploads. Please try again.'
            });
          }
        });
      });
      
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
    
    /* Test Data Entry - Data Entry Mode Styles */
    .data-entry-mode-card {
      padding: 0;
    }
    
    .data-entry-mode-card .card-body {
      padding: 0.25rem;
    }
    
    .mode-toggle-container {
      margin-bottom: 1rem;
    }
    
    .mode-option {
      position: relative;
    }
    
    .mode-option input[type="radio"] {
      display: none;
    }
    
    .mode-label {
      display: flex;
      align-items: center;
      padding: 0.875rem;
      border: 2px solid #dee2e6;
      border-radius: 0.35rem;
      background-color: #fff;
      cursor: pointer;
      transition: all 0.3s ease;
      margin-bottom: 0;
    }
    
    .mode-label:hover {
      border-color: #b6d7ff;
      background-color: #f8fbff;
      text-decoration: none;
      color: inherit;
    }
    
    .mode-option input[type="radio"]:checked + .mode-label {
      border-color: #007bff;
      background-color: #e3f2fd;
      box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
    }
    
    .mode-icon {
      font-size: 1.4rem;
      margin-right: 0.7rem;
      color: #6c757d;
      transition: color 0.3s ease;
    }
    
    .mode-option input[type="radio"]:checked + .mode-label .mode-icon {
      color: #007bff;
    }
    
    .mode-text h6 {
      margin-bottom: 0.15rem;
      font-weight: 600;
      font-size: 0.9rem;
    }
    
    .mode-text small {
      font-size: 0.7rem;
    }
    
    /* Disabled mode styles */
    .disabled-mode .mode-label {
      opacity: 0.6;
      cursor: not-allowed !important;
      background-color: #f8f9fa;
      border-color: #dee2e6;
    }
    
    .disabled-mode .mode-label:hover {
      border-color: #dee2e6 !important;
      transform: none !important;
    }
    
    .disabled-mode input[type="radio"]:checked + .mode-label {
      background-color: #e9ecef !important;
      border-color: #adb5bd !important;
    }

    /* Test Data Entry - Instrument Search Styles */
    .input-group {
      display: flex;
      flex-wrap: nowrap;
      align-items: stretch;
      width: 100%;
    }
    
    .input-group-append {
      margin-left: -1px;
      display: flex;
    }
    
    .input-group-append .btn {
      border-top-left-radius: 0;
      border-bottom-left-radius: 0;
    }
    
    #instrument_search {
      border-top-right-radius: 0;
      border-bottom-right-radius: 0;
    }
    
    #instrument_dropdown {
      position: absolute;
      z-index: 1000;
      border: 1px solid #dee2e6;
      border-top: none;
      box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
      top: 100%;
      left: 0;
      right: 0;
      background-color: #fff;
    }
    
    .instrument-option {
      padding: 0.75rem 1rem;
      border-bottom: 1px solid #f8f9fa;
      display: block;
      width: 100%;
      clear: both;
      font-weight: 400;
      color: #212529;
      text-align: inherit;
      white-space: nowrap;
      background-color: transparent;
      border: 0;
    }
    
    .instrument-option:hover {
      background-color: #f8f9fa;
      text-decoration: none;
      color: #212529;
    }
    
    .instrument-option:last-child {
      border-bottom: none;
    }
    
    .position-relative {
      position: relative;
    }
    
    #test_instruments_table {
      font-size: 0.875rem;
    }
    
    #test_instruments_table th {
      background-color: #f8f9fa;
      border-top: 1px solid #dee2e6;
    }
    
    .badge-success {
      background-color: #28a745;
    }
    
    .badge-warning {
      background-color: #ffc107;
      color: #212529;
    }
    
    .badge-danger {
      background-color: #dc3545;
    }
    
    .badge-secondary {
      background-color: #6c757d;
    }
    
    /* Responsive adjustments */
    @media (max-width: 768px) {
      .mode-label {
        padding: 0.7rem;
      }
      
      .mode-icon {
        font-size: 1.05rem;
        margin-right: 0.5rem;
      }
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

                      <!-- Remarks section shown here when upload/submit are visible -->
                     
                      <tr>
                        <td>
                          <h6 class="text-muted">Approver Remarks</h6>
                        </td>
                        <td colspan="3">

                          <div id="showappremarks"><?php include("core/data/get/getremarks.php") ?></div>

                        </td>

                      </tr>
                      


                      

                      <tr>

                        <td>
                          <h6 class="text-muted">Test Conducted Date </h6>
                        </td>
                        <td colspan="3">

                          <?php
                          // Get the test workflow current stage
                          $test_wf_current_stage = $result['test_wf_current_stage'] ?? null;
                          $is_read_mode = !empty(secure_get('mode', 'string'));
                          
                          // Enable editing if test_wf_current_stage is '3B' and not in read mode
                          $enable_editing_for_3b = ($test_wf_current_stage === '3B' && !$is_read_mode);
                          
                          if ((!empty($test_conducted_date) || $is_read_mode) && !$enable_editing_for_3b) {
                            // Show as disabled field with existing date
                            $display_date = !empty($test_conducted_date) ? date('d.m.Y', strtotime($test_conducted_date)) : '';
                            echo '<input type="text" id="test_conducted_date" name="test_conducted_date" class="form-control" value="' . htmlspecialchars($display_date, ENT_QUOTES, 'UTF-8') . '" disabled/></td>';
                          } else {
                            // Show as editable field - either no date exists OR stage is 3B
                            $display_date = !empty($test_conducted_date) ? date('d.m.Y', strtotime($test_conducted_date)) : '';
                            echo '<input type="text" class="form-control" id="test_conducted_date" name="test_conducted_date" value="' . htmlspecialchars($display_date, ENT_QUOTES, 'UTF-8') . '" Required></td>';
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







                      <?php if (($result['paper_on_glass_enabled'] ?? 'No') == 'Yes') { ?>
                      <tr>
                      <td colspan="4" style="text-align: left;">
  <h6 class="text-muted mb-1">Test Data Entry</h6>
  
  <!-- Test Finalization Status for JavaScript -->
  <script type="text/javascript">
    // Global variable for test finalization status
    window.testFinalizationStatus = <?php echo $finalization_js_data; ?>;
  </script>
  
  <!-- Common Sections (Data Entry Mode + Instruments Details) -->
  <?php include 'assets/inc/_testdataentry_common.php'; ?>
  
  <!-- Test-Specific Sections (manually coded per test) -->
  <?php include 'assets/inc/_testdataentry_specific.php'; ?>
</td>




                      
                      </tr>
                      <?php } ?>

                      








                      









                      <tr>
                        <td colspan="4">
                          <div class="d-flex justify-content-center"> <?php

                                                                      if ($_SESSION['logged_in_user'] == "vendor") // Logged in user is vendor

                                                                      {
                                                                        if ($current_wf_stage == STAGE_NEW_TASK) // Task assigned for the first time
                                                                        {
                                                                      ?>

                                <!-- Always render submit button but conditionally hide it -->
                                <button id="vendorsubmitassign" class='upload-check-required btn btn-primary btn-small' <?php echo $hide_upload_and_submit ? 'style="display: none;" data-finalization-hidden="true"' : ''; ?>>Submit Test Details</button>

                              <?php
                                                                        } else if ($current_wf_stage == STAGE_REASSIGNED_B or $current_wf_stage == STAGE_REASSIGNED_4B) // Task is re-assigned
                                                                        {
                              ?>
                                <!-- Always render submit button but conditionally hide it -->
                                <button id="vendorsubmitreassign" class='upload-check-required btn btn-primary btn-small' <?php echo $hide_upload_and_submit ? 'style="display: none;" data-finalization-hidden="true"' : ''; ?>>Submit Test Details</button>


                              <?php
                                                                        } else if (($current_wf_stage == STAGE_OFFLINE_PROVISIONAL || $current_wf_stage == STAGE_OFFLINE_REJECTED_ENGG || $current_wf_stage == STAGE_REASSIGNED_4BPRV) && !$show_same_user_error) // Offline test awaiting checker review
                                                                        {
                              ?>
                                <!-- Approve/Reject buttons for offline test review -->
                                <button id="offline_approve" class='btn btn-success btn-small'>Approve</button>
                                &nbsp;&nbsp;
                                <button id="offline_reject" class='btn btn-danger btn-small'>Reject</button>

                              <?php
                                                                        } else if ($current_wf_stage == STAGE_OFFLINE_REJECTED) // Offline test rejected, ready for resubmission
                                                                        {
                                                                          // Check if test data has been finalized before showing Submit to Checker button
                                                                          $finalization_check = DB::queryFirstRow(
                                                                            "SELECT test_finalised_by FROM tbl_test_finalisation_details 
                                                                            WHERE test_wf_id = %s AND status = 'Active'",
                                                                            $test_val_wf_id
                                                                          );
                                                                          
                                                                          if ($finalization_check) {
                              ?>
                                <!-- Submit to Checker button for rejected offline test (only shown if finalized) -->
                                <button id="resubmit_to_checker" class='btn btn-primary btn-small'>Submit to Checker</button>

                              <?php
                                                                          } else {
                              ?>
                                <!-- Message when test data is not finalized yet -->
                                <div class="alert alert-warning py-2 mb-2">
                                  <i class="mdi mdi-information"></i>
                                  <small>Test data must be finalized before it can be submitted to checker.</small>
                                </div>

                              <?php
                                                                          }
                                                                        }
                                                                      } else if ($_SESSION['logged_in_user'] == "employee" and $_SESSION['department_id'] == 1 and empty(secure_get('mode', 'string'))) // Logged in user is from the engineering team
                                                                      {
                                                                        if ($current_wf_stage == STAGE_NEW_TASK) // Task assigned for the first time
                                                                        {
                                                                          //$text = "<script>document.writeln(document.getElementById('user_remark').innerHTML);</script>";
                                                                          //echo $text;
                              ?>
                                <!-- Always render submit button but conditionally hide it -->
                                <button id="enggsubmit" class='upload-check-required btn btn-primary btn-small' <?php echo $hide_upload_and_submit ? 'style="display: none;" data-finalization-hidden="true"' : ''; ?>>Submit</button>


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