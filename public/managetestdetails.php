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

// Validate required parameters
if (isset($_GET['m']) && $_GET['m'] != 'a' && (!isset($_GET['test_id']) || !is_numeric($_GET['test_id']))) {
    header('HTTP/1.1 400 Bad Request');
    header('Location: ' . BASE_URL . 'error.php?msg=invalid_parameters');
    exit();
}

// Initialize CSRF token if not set
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once 'core/config/db.class.php';

if (isset($_GET['m']) && $_GET['m'] != 'a') {
    try {
        $test_details = DB::queryFirstRow(
            "SELECT test_name, test_description, test_purpose, test_performed_by, test_status, dependent_tests, paper_on_glass_enabled 
             FROM tests WHERE test_id = %d", 
            intval($_GET['test_id'])
        );
        
        if (!$test_details) {
            header('HTTP/1.1 404 Not Found');
            header('Location: ' . BASE_URL . 'error.php?msg=test_not_found');
            exit();
        }
    } catch (Exception $e) {
        error_log("Error fetching test details: " . $e->getMessage());
        header('HTTP/1.1 500 Internal Server Error');
        header('Location: ' . BASE_URL . 'error.php?msg=database_error');
        exit();
    }
}



?>
<!DOCTYPE html>
<html lang="en">
  <head>
  <?php include_once "assets/inc/_header.php";?>  
   <script> 
      $(document).ready(function(){
      
      // Function to submit test data after successful authentication
      function submitTestData(mode) {
        // Get CSRF token
        var csrfToken = $("input[name='csrf_token']").val();
        
        $('#pleasewaitmodal').modal('show');
        
        // Prepare dependent tests value
        let dependentTestsValue = $("#dependent_tests").val();
        let dependentTestsString = '';
        if (dependentTestsValue && dependentTestsValue.length > 0) {
          dependentTestsString = dependentTestsValue.join(',');
        } else {
          dependentTestsString = 'NA';
        }

        // Prepare data object based on mode
        let data = {
          csrf_token: csrfToken,
          test_name: $("#test_name").val(),
          test_description: $("#test_description").val(),
          test_purpose: $("#test_purpose").val(),
          test_performed_by: $("#test_performed_by").val(),
          test_status: $("#test_status").val(),
          dependent_tests: dependentTestsString,
          paper_on_glass_enabled: $("#paper_on_glass_enabled").val(),
          mode: mode
        };
        
        // Add test_id for modify mode
        if (mode === 'modify') {
          data.test_id = $("#test_id").val();
        }
        
        // Send AJAX request
        $.ajax({
          url: "core/data/save/savetestdetails.php",
          type: "GET",
          data: data,
          success: function(data, status) {
            $('#pleasewaitmodal').modal('hide');
            
            // Try to parse JSON first
            try {
              var response = JSON.parse(data);
              
              // Update CSRF token if provided
              if (response.csrf_token) {
                $("input[name='csrf_token']").val(response.csrf_token);
              }
              
              if (response.status === "success") {
                Swal.fire({
                  icon: 'success',
                  title: 'Success',
                  text: "The test record is successfully " + (mode === 'add' ? "added" : "modified") + "!"
                }).then((result) => {
                  window.location = "searchtests.php";
                });
              } else {
                Swal.fire({
                  icon: 'error',
                  title: 'Oops...',
                  text: 'Something went wrong.'
                }).then((result) => {
                  window.location = "searchtests.php";
                });
              }
            } catch (e) {
              // Legacy handling
              if (data === "success") {
                Swal.fire({
                  icon: 'success',
                  title: 'Success',
                  text: "The test record is successfully " + (mode === 'add' ? "added" : "modified") + "!"
                }).then((result) => {
                  window.location = "searchtests.php";
                });
              } else {
                Swal.fire({
                  icon: 'error',
                  title: 'Oops...',
                  text: 'Something went wrong.'
                }).then((result) => {
                  window.location = "searchtests.php";
                });
              }
            }
          },
          error: function(xhr, status, error) {
            $('#pleasewaitmodal').modal('hide');
            Swal.fire({
              icon: 'error',
              title: 'Connection Error',
              text: 'Could not connect to the server. Please try again.'
            });
          }
        });
      }
      
      // Add Test button click handler
      $("#add_test").click(function(e) { 
        e.preventDefault();
        
        var form = document.getElementById('formtestvalidation'); 
        
        if (form.checkValidity() === false) {  
          form.classList.add('was-validated');  
        } else {
          form.classList.add('was-validated');
          
          // Set success callback for e-signature modal
          setSuccessCallback(function() {
            submitTestData('add');
          });
          
          // Show e-signature modal
          $('#enterPasswordRemark').modal('show');
        }
      });

      // Modify Test button click handler
      $("#modify_test").click(function(e) { 
        e.preventDefault();
        
        var form = document.getElementById('formtestvalidation'); 
        
        if (form.checkValidity() === false) {  
          form.classList.add('was-validated');  
        } else {
          form.classList.add('was-validated');
          
          // Set success callback for e-signature modal
          setSuccessCallback(function() {
            submitTestData('modify');
          });
          
          // Show e-signature modal
          $('#enterPasswordRemark').modal('show');
        }
      });

      // Template Upload Functionality
      if ($('#template-drop-zone').length > 0) {
        const dropZone = document.getElementById('template-drop-zone');
        const fileInput = document.getElementById('template-file');
        const uploadForm = document.getElementById('template-upload-form');
        
        let clickInProgress = false;
        
        // Click handler with prevention of double triggers
        dropZone.addEventListener('click', (e) => {
          console.log('Drop zone clicked');
          // Don't prevent default for the drop zone click
          e.stopPropagation();
          
          if (clickInProgress) {
            console.log('Click already in progress, skipping');
            return;
          }
          clickInProgress = true;
          
          // Reset the flag after a short delay
          setTimeout(() => {
            clickInProgress = false;
          }, 500);
          
          // Trigger the file input click
          console.log('Triggering file input click');
          fileInput.click();
        });
        
        // Drag and drop handlers
        dropZone.addEventListener('dragover', (e) => {
          console.log('Dragover event');
          e.preventDefault();
          e.stopPropagation();
          dropZone.classList.add('dragover');
        });
        
        dropZone.addEventListener('dragleave', (e) => {
          console.log('Dragleave event');
          e.preventDefault();
          e.stopPropagation();
          dropZone.classList.remove('dragover');
        });
        
        dropZone.addEventListener('drop', (e) => {
          console.log('Drop event');
          e.preventDefault();
          e.stopPropagation();
          dropZone.classList.remove('dragover');
          
          const files = e.dataTransfer.files;
          console.log('Files dropped:', files.length);
          if (files.length > 0) {
            console.log('File type:', files[0].type, 'File name:', files[0].name);
            // Create a new DataTransfer object to assign files to the input
            const dt = new DataTransfer();
            dt.items.add(files[0]);
            fileInput.files = dt.files;
            
            // Trigger change event manually
            const event = new Event('change', { bubbles: true });
            fileInput.dispatchEvent(event);
          }
        });
        
        // File input change handler (prevent double events)
        let changeInProgress = false;
        fileInput.addEventListener('change', (e) => {
          console.log('File input change event');
          
          if (changeInProgress) {
            console.log('Change already in progress, skipping');
            return;
          }
          changeInProgress = true;
          
          if (e.target.files.length > 0) {
            console.log('File selected:', e.target.files[0].name);
            validateFile(e.target.files[0]);
          }
          
          // Reset flag after processing
          setTimeout(() => {
            changeInProgress = false;
          }, 100);
        });
        
        // File validation function
        function validateFile(file) {
          const maxSize = 10 * 1024 * 1024; // 10MB
          const allowedTypes = ['application/pdf'];
          
          if (!allowedTypes.includes(file.type)) {
            Swal.fire({
              icon: 'error',
              title: 'Invalid File Type',
              text: 'Please select a PDF file only.'
            });
            fileInput.value = '';
            return false;
          }
          
          if (file.size > maxSize) {
            Swal.fire({
              icon: 'error',
              title: 'File Too Large',
              text: 'File size must be less than 10MB.'
            });
            fileInput.value = '';
            return false;
          }
          
          // Update UI to show file selected
          dropZone.classList.add('template-file-selected');
          $('.drop-zone-text').text(`Selected: ${file.name}`);
          $('.drop-zone-subtext').text(`Size: ${(file.size / 1024 / 1024).toFixed(2)} MB`);
          
          return true;
        }
        
        // Form submission handler
        if (uploadForm) {
          uploadForm.addEventListener('submit', (e) => {
            e.preventDefault();
            
            if (!fileInput.files.length) {
              Swal.fire({
                icon: 'error',
                title: 'No File Selected',
                text: 'Please select a PDF template file.'
              });
              return;
            }
            
            // Store form data for later submission
            window.pendingUploadFormData = new FormData(uploadForm);
            
            // Set success callback for e-signature modal
            setSuccessCallback(function() {
              submitTemplateUpload();
            });
            
            // Show e-signature modal
            $('#enterPasswordRemark').modal('show');
          });
          
          // Function to submit template upload after authentication
          window.submitTemplateUpload = function() {
            console.log('submitTemplateUpload called');
            if (!window.pendingUploadFormData) {
              console.error('No pending upload data found');
              return;
            }
            
            console.log('Submitting template upload');
            
            // Show loading
            $('#pleasewaitmodal').modal('show');
            
            $.ajax({
              url: 'core/pdf/template_handler.php',
              type: 'POST',
              data: window.pendingUploadFormData,
              processData: false,
              contentType: false,
              success: function(response) {
                $('#pleasewaitmodal').modal('hide');
                
                try {
                  const result = typeof response === 'string' ? JSON.parse(response) : response;
                  
                  if (result.status === 'success') {
                    Swal.fire({
                      icon: 'success',
                      title: 'Template Uploaded',
                      text: result.message
                    }).then(() => {
                      location.reload();
                    });
                  } else {
                    Swal.fire({
                      icon: 'error',
                      title: 'Upload Failed',
                      text: result.message
                    });
                  }
                } catch (e) {
                  console.error('JSON Parse Error:', e);
                  console.error('Raw response:', response);
                  Swal.fire({
                    icon: 'error',
                    title: 'Upload Error',
                    text: 'Server response was not valid JSON. Check console for details.'
                  });
                }
                
                // Clear pending data
                window.pendingUploadFormData = null;
              },
              error: function(xhr, status, error) {
                $('#pleasewaitmodal').modal('hide');
                console.error('AJAX Error:', {xhr: xhr, status: status, error: error});
                Swal.fire({
                  icon: 'error',
                  title: 'Connection Error',
                  text: 'Could not connect to the server. Status: ' + status + ', Error: ' + error
                });
                
                // Clear pending data
                window.pendingUploadFormData = null;
              }
            });
          }
        } else {
          console.error('Upload form not found! Cannot attach event listener.');
        }
      }
      
      // Template activation handler
      $(document).on('click', '.activate-template', function(e) {
        e.preventDefault();
        console.log('Activate template button clicked');
        
        const templateId = $(this).data('template-id');
        console.log('Template ID:', templateId);
        
        Swal.fire({
          title: 'Activate Template',
          html: `
            <p>This will deactivate the current template and make this one active.</p>
            <div class="form-group mt-3">
              <label for="effective_from_date"><strong>Effective From Date:</strong></label>
              <input type="date" id="effective_from_date" class="form-control" 
                     min="${new Date().toISOString().split('T')[0]}" 
                     value="${new Date().toISOString().split('T')[0]}" required>
              <small class="text-muted">This template will be active from this date onwards</small>
            </div>
          `,
          icon: 'question',
          showCancelButton: true,
          confirmButtonText: 'Continue with Authentication',
          cancelButtonText: 'Cancel',
          preConfirm: () => {
            const effectiveFromDate = document.getElementById('effective_from_date').value;
            if (!effectiveFromDate) {
              Swal.showValidationMessage('Please select an effective from date');
              return false;
            }
            return effectiveFromDate;
          }
        }).then((result) => {
          if (result.isConfirmed) {
            // Store template activation data for later submission
            window.pendingActivationData = {
              templateId: templateId,
              effectiveFromDate: result.value
            };
            
            // Set success callback for e-signature modal
            setSuccessCallback(function() {
              submitTemplateActivation();
            });
            
            // Show e-signature modal
            $('#enterPasswordRemark').modal('show');
          }
        });
      });
      
      // Function to submit template activation after authentication
      window.submitTemplateActivation = function() {
        console.log('submitTemplateActivation called');
        if (!window.pendingActivationData) {
          console.error('No pending activation data found');
          return;
        }
        
        const data = window.pendingActivationData;
        console.log('Submitting activation for template:', data.templateId);
        
        $.ajax({
          url: 'core/pdf/template_handler.php',
          type: 'POST',
          data: {
            action: 'activate',
            template_id: data.templateId,
            effective_from_date: data.effectiveFromDate,
            csrf_token: $("input[name='csrf_token']").val()
          },
          success: function(response) {
            console.log('Activation response type:', typeof response);
            console.log('Activation raw response:', response);
            
            try {
              // Handle case where response is already parsed as object
              const result = typeof response === 'string' ? JSON.parse(response) : response;
              
              if (result.status === 'success') {
                Swal.fire({
                  icon: 'success',
                  title: 'Template Activated',
                  text: result.message
                }).then(() => {
                  location.reload();
                });
              } else {
                Swal.fire({
                  icon: 'error',
                  title: 'Activation Failed',
                  text: result.message || 'An unexpected error occurred'
                });
              }
            } catch (e) {
              console.error('Activation parsing error:', e);
              console.error('Raw response:', response);
              
              // Try to extract message from response if it's a string
              let errorMessage = 'An unexpected error occurred';
              if (typeof response === 'string') {
                try {
                  const parsed = JSON.parse(response);
                  errorMessage = parsed.message || errorMessage;
                } catch (parseError) {
                  // If still can't parse, use the string as is
                  errorMessage = response.substring(0, 200);
                }
              }
              
              Swal.fire({
                icon: 'error',
                title: 'Activation Error',
                text: errorMessage
              });
            }
            
            // Clear pending data
            window.pendingActivationData = null;
          },
          error: function() {
            Swal.fire({
              icon: 'error',
              title: 'Connection Error',
              text: 'Could not connect to the server. Please try again.'
            });
            
            // Clear pending data
            window.pendingActivationData = null;
          }
        });
      }
      
      // Template view modal handler
      $(document).on('click', 'a[data-target="#imagepdfviewerModal"]', function(e) {
        e.preventDefault();
        console.log('Template view link clicked');
        
        var $link = $(this);
        var src = $link.attr('href');
        var title = $link.attr('data-title') || 'Template';
        var allowDownload = $link.attr('data-allow-download') === 'true';
        var downloadUrl = $link.attr('data-download-url');
        
        console.log('Opening modal with:', {src, title, allowDownload, downloadUrl});
        
        // Store data for modal to access
        var modalData = {
          src: src,
          title: title,  
          allowDownload: allowDownload,
          downloadUrl: downloadUrl
        };
        $('#imagepdfviewerModal').data('modalData', modalData);
        
        // Trigger the modal
        $('#imagepdfviewerModal').modal('show');
      });
      
      // Template deactivation handler
      $(document).on('click', '.deactivate-template', function(e) {
        e.preventDefault();
        console.log('Deactivate template button clicked');
        
        const templateId = $(this).data('template-id');
        const $button = $(this);
        console.log('Template ID:', templateId);
        
        // Add visual feedback immediately
        $button.addClass('clicked');
        
        Swal.fire({
          title: 'Deactivate Template',
          html: `
            <p>This will make the template inactive. Users will no longer be able to download it as the active template.</p>
            <div class="form-group mt-3">
              <label><strong>Effective Till Date:</strong></label>
              <p class="form-control-static text-info"><strong>${new Date().toLocaleDateString()}</strong></p>
              <small class="text-muted">Template will be deactivated with current system date</small>
            </div>
          `,
          icon: 'warning',
          showCancelButton: true,
          confirmButtonText: 'Continue with Authentication',
          cancelButtonText: 'Cancel',
          confirmButtonColor: '#dc3545'
        }).then((result) => {
          if (result.isConfirmed) {
            // Store template deactivation data for later submission
            window.pendingDeactivationData = {
              templateId: templateId,
              button: $button
            };
            
            // Set success callback for e-signature modal
            setSuccessCallback(function() {
              submitTemplateDeactivation();
            });
            
            // Show e-signature modal
            $('#enterPasswordRemark').modal('show');
          } else {
            // Remove visual feedback if user cancels
            $button.removeClass('clicked');
          }
        });
      });
      
      // Function to submit template deactivation after authentication
      window.submitTemplateDeactivation = function() {
        console.log('submitTemplateDeactivation called');
        if (!window.pendingDeactivationData) {
          console.error('No pending deactivation data found');
          return;
        }
        
        const data = window.pendingDeactivationData;
        console.log('Submitting deactivation for template:', data.templateId);
        
        $.ajax({
          url: 'core/pdf/template_handler.php',
          type: 'POST',
          data: {
            action: 'deactivate',
            template_id: data.templateId,
            effective_till_date: 'current', // Backend will use current date
            csrf_token: $("input[name='csrf_token']").val()
          },
          success: function(response) {
            console.log('Deactivation response type:', typeof response);
            console.log('Deactivation raw response:', response);
            
            try {
              // Handle case where response is already parsed as object
              const result = typeof response === 'string' ? JSON.parse(response) : response;
              
              if (result.status === 'success') {
                Swal.fire({
                  icon: 'success',
                  title: 'Template Deactivated',
                  text: result.message
                }).then(() => {
                  location.reload();
                });
              } else {
                Swal.fire({
                  icon: 'error',
                  title: 'Deactivation Failed',
                  text: result.message || 'An unexpected error occurred'
                });
              }
            } catch (e) {
              console.error('Deactivation parsing error:', e);
              console.error('Raw response:', response);
              
              // Try to extract message from response if it's a string
              let errorMessage = 'An unexpected error occurred';
              if (typeof response === 'string') {
                try {
                  const parsed = JSON.parse(response);
                  errorMessage = parsed.message || errorMessage;
                } catch (parseError) {
                  // If still can't parse, use the string as is
                  errorMessage = response.substring(0, 200);
                }
              }
              
              Swal.fire({
                icon: 'error',
                title: 'Deactivation Error',
                text: errorMessage
              });
            }
            
            // Clear pending data and remove visual feedback
            if (data.button) {
              data.button.removeClass('clicked');
            }
            window.pendingDeactivationData = null;
          },
          error: function() {
            Swal.fire({
              icon: 'error',
              title: 'Connection Error',
              text: 'Could not connect to the server. Please try again.'
            });
            
            // Clear pending data and remove visual feedback
            if (data.button) {
              data.button.removeClass('clicked');
            }
            window.pendingDeactivationData = null;
          }
        });
      }

      // Dependent Tests validation to prevent circular dependencies
      $("#dependent_tests").change(function() {
        var currentTestId = $("#test_id").val();
        var selectedTests = $(this).val();
        
        if (currentTestId && selectedTests && selectedTests.includes(currentTestId)) {
          // Remove the current test from selection to prevent circular dependency
          var filteredTests = selectedTests.filter(function(testId) {
            return testId !== currentTestId;
          });
          $(this).val(filteredTests);
          
          // Show warning
          Swal.fire({
            icon: 'warning',
            title: 'Invalid Selection',
            text: 'A test cannot depend on itself. This selection has been removed.',
            timer: 3000,
            showConfirmButton: false
          });
        }
      });

      // Initialize dependent tests field behavior
      $("#dependent_tests").on('focus', function() {
        var naOption = $(this).find('option[value="NA"]');
        if (naOption.is(':selected')) {
          naOption.prop('selected', false);
        }
      });
      
      // If NA is selected, clear other selections
      $("#dependent_tests").on('change', function() {
        var selectedValues = $(this).val();
        if (selectedValues && selectedValues.includes('NA')) {
          // If NA is selected, clear all other selections
          $(this).val(['NA']);
        }
      });
      
      
    
});

    </script>
    
    <!-- Template Upload Styles -->
    <style>
    .drop-zone {
        border: 2px dashed #007bff;
        border-radius: 10px;
        padding: 50px;
        text-align: center;
        cursor: pointer;
        background-color: #f8f9fa;
        transition: all 0.3s ease;
        position: relative;
        min-height: 150px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .drop-zone:hover {
        border-color: #0056b3;
        background-color: #e3f2fd;
    }
    
    .drop-zone.dragover {
        border-color: #28a745;
        background-color: #d4edda;
        transform: scale(1.02);
    }
    
    .drop-zone-content {
        pointer-events: none;
    }
    
    .drop-zone-icon {
        font-size: 3rem;
        color: #007bff;
        margin-bottom: 15px;
        display: block;
    }
    
    .drop-zone-text {
        font-size: 1.1rem;
        color: #495057;
        margin-bottom: 5px;
    }
    
    .drop-zone-subtext {
        font-size: 0.9rem;
        color: #6c757d;
        margin: 0;
    }
    
    .file-input {
        position: absolute;
        top: -9999px;
        left: -9999px;
        width: 1px;
        height: 1px;
        opacity: 0;
    }
    
    .upload-progress {
        margin-top: 15px;
        display: none;
    }
    
    .border-left-success {
        border-left: 4px solid #28a745 !important;
    }
    
    .border-left-info {
        border-left: 4px solid #17a2b8 !important;
    }
    
    .border-left-warning {
        border-left: 4px solid #ffc107 !important;
    }
    
    .template-file-selected {
        border-color: #28a745;
        background-color: #d4edda;
    }
    
    /* Visual feedback for deactivation button */
    .deactivate-template.clicked {
        background-color: #dc3545 !important;
        border-color: #dc3545 !important;
        color: white !important;
    }
    </style>
  </head>
  <body>
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
							 Test Details
						</h3>
						<nav aria-label="breadcrumb">
							<ul class="breadcrumb">
								<li class="breadcrumb-item active" aria-current="page"><span><a class='btn btn-gradient-info btn-sm btn-rounded'
										href="searchtests.php"><< Back</a> </span>
								</li>
							</ul>
						</nav>
					</div>
					
					
					
		<div class="row">

	<div class="col-lg-12 grid-margin stretch-card">
                <div class="card">
                  <div class="card-body">
                    <h4 class="card-title">Test Details</h4>
                    <p class="card-description"> 
                    </p>
                    
                    
                    
                    
                    
                    
                    




								        <form id='formtestvalidation' class="needs-validation" novalidate>
								        <!-- Add CSRF token field -->
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                       
							<?php 
								        if(isset($_GET['m']) && $_GET['m']!='a')
								        {
								        
								        echo '<input type="hidden" id="test_id" name="test_id" value="'.$_GET['test_id'].'" />';
								    
								         }
								        
								        ?>
							
							
							<div class="form-row">
                    
                    <div class="form-group  col-md-4">
                        <label for="exampleSelectGender">Test Name</label>
                        <input type="text" class="form-control" value='<?php echo ((isset($_GET['m']) && $_GET['m']!='a')?  $test_details['test_name']:'');?>' name='test_name' id='test_name' required/>
                        <div class="invalid-feedback">  
                                            Please provide a valid test name.  
                                        </div>
                      </div>
                      
                       <div class="form-group  col-md-4">
                        <label for="exampleSelectGender">Performed By</label>
                        <select class="form-control" id="test_performed_by" name="test_performed_by" >
									
										
                       	<?php 
                       	
                       	echo "<option value='Internal' ". (isset($_GET['m']) && $_GET['m']!='a' && (trim($test_details['test_performed_by'])=='Internal')? "selected" : "") .">Internal</option>";
                       	echo "<option value='External' ". (isset($_GET['m']) && $_GET['m']!='a' && (trim($test_details['test_performed_by'])=='External')? "selected" : "") .">External</option>";
                      
                       	    
                       
                       	?>	
                        </select>
                      </div>
                      
                          <div class="form-group  col-md-4">
                        <label for="exampleSelectGender">Status</label>
                        <select class="form-control" id="test_status" name="test_status">
									
										
                       	<?php 
                       	echo "<option value='Active'".(isset($_GET['m']) && $_GET['m']!='a' && ($test_details['test_status']=='Active')? "selected" : "") .">Active</option>";
                       	echo "<option value='Inactive'".(isset($_GET['m']) && $_GET['m']!='a' && ($test_details['test_status']=='Inactive')? "selected" : "") .">Inactive</option>";
                 
                       	
                       	    
                       	    
                       
                       	?>	
                        </select> </div>
                      
                      </div>
                      
                      <div class="form-row">
                      
                    <div class="form-group  col-md-12">
                        <label for="exampleSelectGender">Test Description</label>
                        <input type="text" class="form-control" value='<?php echo ((isset($_GET['m']) && $_GET['m']!='a')?  $test_details['test_description']:'');?>' name='test_description' id='test_description' required/>
                     <div class="invalid-feedback">  
                                            Please provide a valid test description.  
                                        </div>
                     
                      </div>
                     </div>
                     
                     <div class="form-row">
                      
                    <div class="form-group  col-md-12">
                        <label for="exampleSelectGender">Test Purpose</label>
                        <input type="text" class="form-control" value='<?php echo ((isset($_GET['m']) && $_GET['m']!='a')?  $test_details['test_purpose']:'');?>' name='test_purpose' id='test_purpose' required/>
                      
                      <div class="invalid-feedback">  
                                            Please provide a valid test purpose.  
                                        </div>
                      
                      </div>
                     
                      
					</div>
					
					<div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="dependent_tests">Dependent Test(s)</label>
                            <select class="form-control" id="dependent_tests" name="dependent_tests" multiple style="height: 120px;">
                                <option value="NA" <?php echo ((isset($_GET['m']) && $_GET['m']!='a' && ($test_details['dependent_tests']=='NA' || empty($test_details['dependent_tests'])))? 'selected' : '');?>>NA</option>
                                <?php
                                // Get all available tests for dependent test selection
                                $all_tests = DB::query("SELECT test_id, test_name FROM tests WHERE test_status='Active' ORDER BY test_name");
                                
                                // Get current dependent tests if editing
                                $current_dependent_tests = array();
                                if(isset($_GET['m']) && $_GET['m']!='a' && !empty($test_details['dependent_tests']) && $test_details['dependent_tests'] != 'NA' && $test_details['dependent_tests'] !== null) {
                                    $current_dependent_tests = explode(',', $test_details['dependent_tests']);
                                    $current_dependent_tests = array_map('trim', $current_dependent_tests);
                                }
                                
                                foreach($all_tests as $test) {
                                    $selected = in_array($test['test_id'], $current_dependent_tests) ? 'selected' : '';
                                    echo "<option value='".$test['test_id']."' ".$selected.">".$test['test_name']."</option>";
                                }
                                ?>
                            </select>
                            <small class="form-text text-muted">Hold Ctrl/Cmd to select multiple tests</small>
                        </div>
                        
                        <div class="form-group col-md-6">
                            <label for="paper_on_glass_enabled">Paper-on-Glass Enabled</label>
                            <select class="form-control" id="paper_on_glass_enabled" name="paper_on_glass_enabled">
                                <option value="No" <?php echo ((isset($_GET['m']) && $_GET['m']!='a' && $test_details['paper_on_glass_enabled']=='No') || (!isset($_GET['m']) || $_GET['m']=='a') ? 'selected' : '');?>>No</option>
                                <option value="Yes" <?php echo ((isset($_GET['m']) && $_GET['m']!='a' && $test_details['paper_on_glass_enabled']=='Yes') ? 'selected' : '');?>>Yes</option>
                            </select>
                            <small class="form-text text-muted">Enable paper-on-glass functionality for this test</small>
                        </div>
                    </div>		
							
					
						
                       
                      
                 
				
									
					
					
					<div class="d-flex justify-content-center"> 
					
					
					 <?php
                  
                  if(isset($_GET['m']) && $_GET['m']=='m'){
                      ?>
                  <button  id="modify_test"	class='btn btn-gradient-primary mr-2'>Modify Test</button>    
                  <?php     
                  }
                  else if(isset($_GET['m']) && $_GET['m']=='a'){
                      ?>
                  <button  id="add_test"	class='btn btn-gradient-primary mr-2'>Add Test</button>    
                  <?php  
                  }
                  
                  
                  ?>
					
					
					</div> 
					
					 
                    
                    
                    
        </form>
                    
                                      </div>
                </div>
              </div>

<?php if(isset($_GET['m']) && $_GET['m']=='m'): ?>
<!-- Raw Data Template Management Card -->
<div class="col-lg-12 grid-margin stretch-card">
    <div class="card">
        <div class="card-body">
            <h4 class="card-title">
                <i class="mdi mdi-file-pdf-outline"></i> Raw Data Template Management
            </h4>
            <p class="card-description">
                Manage PDF templates for external vendor data submissions
            </p>
            
            <?php
            // Get current active template for this test
            $current_template = DB::queryFirstRow("
                SELECT rt.*, u.user_name as uploaded_by_name 
                FROM raw_data_templates rt 
                LEFT JOIN users u ON rt.created_by = u.user_id 
                WHERE rt.test_id = %d AND rt.is_active = 1
            ", intval($_GET['test_id']));
            ?>
            
            <!-- Current Active Template Display -->
            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="card border-left-success">
                        <div class="card-body">
                            <h6 class="card-title text-success">
                                <i class="mdi mdi-check-circle"></i> Active Template Status
                            </h6>
                            <?php if($current_template): ?>
                                <div class="row">
                                    <div class="col-md-8">
                                        <p class="mb-1"><strong>File:</strong> <?= basename($current_template['file_path']) ?></p>
                                        <p class="mb-1"><strong>Effective Date:</strong> <?= date('d.m.Y', strtotime($current_template['effective_date'])) ?></p>
                                        <p class="mb-0"><strong>Uploaded by:</strong> <?= $current_template['uploaded_by_name'] ?> on <?= date('d.m.Y H:i', strtotime($current_template['created_at'])) ?></p>
                                    </div>
                                    <div class="col-md-4 text-right">
                                        <a href="core/pdf/template_handler.php?action=view&id=<?= $current_template['id'] ?>" 
                                           class="btn btn-outline-primary btn-sm" 
                                           data-toggle="modal" 
                                           data-target="#imagepdfviewerModal"
                                           data-title="<?= basename($current_template['file_path']) ?>"
                                           data-allow-download="true"
                                           data-download-url="core/pdf/template_handler.php?action=download&id=<?= $current_template['id'] ?>">
                                            <i class="mdi mdi-download"></i> View Template
                                        </a>
                                    </div>
                                </div>
                            <?php else: ?>
                                <p class="text-muted mb-0">
                                    <i class="mdi mdi-alert-circle-outline"></i> No active template available for this test. Please upload a template below.
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Upload New Template Section -->
            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="card border-left-info">
                        <div class="card-body">
                            <h6 class="card-title text-info">
                                <i class="mdi mdi-cloud-upload"></i> Upload New Template
                            </h6>
                            <form id="template-upload-form" enctype="multipart/form-data">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                                <input type="hidden" name="test_id" value="<?= $_GET['test_id'] ?>">
                                <input type="hidden" name="action" value="upload">
                                
                                <div class="form-group">
                                    <label>Template File</label>
                                    <div class="file-upload-wrapper">
                                        <div id="template-drop-zone" class="drop-zone">
                                            <div class="drop-zone-content">
                                                <i class="mdi mdi-cloud-upload-outline drop-zone-icon"></i>
                                                <p class="drop-zone-text">Drag & drop PDF template here or click to browse</p>
                                                <p class="drop-zone-subtext">Maximum file size: 10MB | PDF files only</p>
                                            </div>
                                            <input type="file" id="template-file" name="template_file" accept=".pdf" class="file-input" required>
                                        </div>
                                        <div class="invalid-feedback">
                                            Please select a valid PDF template file.
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group col-md-6">
                                        <label for="effective_date">Effective Date</label>
                                        <input type="date" class="form-control" id="effective_date" name="effective_date" 
                                               value="<?= date('Y-m-d') ?>" required>
                                        <div class="invalid-feedback">
                                            Please provide a valid effective date.
                                        </div>
                                    </div>
                                    <div class="form-group col-md-6 d-flex align-items-end">
                                        <button type="submit" class="btn btn-gradient-info">
                                            <i class="mdi mdi-upload"></i> Upload Template
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Template History Section -->
            <div class="row">
                <div class="col-md-12">
                    <div class="card border-left-info">
                        <div class="card-body">
                            <h6 class="card-title text-info">
                                <i class="mdi mdi-history"></i> Template Version History
                            </h6>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>File Name</th>
                                            <th>Effective Date</th>
                                            <th>Effective Till Date</th>
                                            <th>Status</th>
                                            <th>Uploaded By</th>
                                            <th>Upload Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="template-history-tbody">
                                        <?php
                                        $template_history = DB::query("
                                            SELECT rt.*, u.user_name as uploaded_by_name 
                                            FROM raw_data_templates rt 
                                            LEFT JOIN users u ON rt.created_by = u.user_id 
                                            WHERE rt.test_id = %d 
                                            ORDER BY rt.created_at DESC
                                        ", intval($_GET['test_id']));
                                        
                                        if(empty($template_history)): ?>
                                            <tr>
                                                <td colspan="7" class="text-center text-muted py-4">
                                                    <i class="mdi mdi-information-outline"></i> No templates uploaded yet
                                                </td>
                                            </tr>
                                        <?php else:
                                            foreach($template_history as $template): ?>
                                            <tr>
                                                <td>
                                                    <i class="mdi mdi-file-pdf-outline text-danger mr-2"></i>
                                                    <?= basename($template['file_path']) ?>
                                                </td>
                                                <td><?= date('d.m.Y', strtotime($template['effective_date'])) ?></td>
                                                <td>
                                                    <?php if(!$template['is_active'] && $template['effective_till_date']): ?>
                                                        <?= date('d.m.Y', strtotime($template['effective_till_date'])) ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">—</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if($template['is_active']): ?>
                                                        <span class="badge badge-success">
                                                            <i class="mdi mdi-check"></i> Active
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge badge-secondary">Inactive</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= $template['uploaded_by_name'] ?></td>
                                                <td><?= date('d.m.Y H:i', strtotime($template['created_at'])) ?></td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <a href="core/pdf/template_handler.php?action=view&id=<?= $template['id'] ?>" 
                                                           class="btn btn-outline-primary btn-sm" 
                                                           data-toggle="modal" 
                                                           data-target="#imagepdfviewerModal"
                                                           data-title="<?= basename($template['file_path']) ?>"
                                                           data-allow-download="true"
                                                           data-download-url="core/pdf/template_handler.php?action=download&id=<?= $template['id'] ?>"
                                                           title="View Template">
                                                            <i class="mdi mdi-download"></i>
                                                        </a>
                                                        <?php if(!$template['is_active']): ?>
                                                            <button class="btn btn-outline-success btn-sm activate-template" 
                                                                    data-template-id="<?= $template['id'] ?>"
                                                                    title="Activate Template">
                                                                <i class="mdi mdi-check"></i>
                                                            </button>
                                                        <?php else: ?>
                                                            <button class="btn btn-outline-danger btn-sm deactivate-template" 
                                                                    data-template-id="<?= $template['id'] ?>"
                                                                    title="Deactivate Template">
                                                                <i class="mdi mdi-close"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach;
                                        endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>



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
 <?php include "assets/inc/_esignmodal.php"; ?>
 <?php include "assets/inc/_imagepdfviewermodal.php"; ?>
</body>
</html>