<?php
require_once('./core/config/config.php');



// Check for proper authentication
if (!isset($_SESSION['logged_in_user']) || !isset($_SESSION['user_name'])) {
    session_destroy();
    header('Location: ' . BASE_URL . 'login.php?msg=session_required');
    exit();
}

// Validate session timeout
require_once('core/security/session_timeout_middleware.php');
validateActiveSession();

// Generate CSRF token if not present
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
// Commenting out var_dump for production
// var_dump($_SESSION);

// Fix for issue with adduserremark function
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <?php include_once "assets/inc/_header.php"; ?>
  <meta name="csrf-token" content="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">

  <script>
    $(document).ready(function() {
      // Configuration variables
      var validationAdvanceLimit = <?php echo VALIDATION_ADVANCE_START_LIMIT_DAYS; ?>;

      // Initialize DataTables
      $('#datagrid-upcoming').DataTable({
        "pagingType": "numbers",
        "pageLength": 25,
        "lengthMenu": [[10, 25, 50, 100], [10, 25, 50, 100]],
        "language": {
            "lengthMenu": "Show _MENU_ entries"
        }
      });
      $('#datagrid-inprogress').DataTable({
        "pagingType": "numbers",
        "pageLength": 25,
        "lengthMenu": [[10, 25, 50, 100], [10, 25, 50, 100]],
        "language": {
            "lengthMenu": "Show _MENU_ entries"
        }
      });
      $('#datagrid-level1submission').DataTable({
        "pagingType": "numbers",
        "pageLength": 25,
        "lengthMenu": [[10, 25, 50, 100], [10, 25, 50, 100]],
        "language": {
            "lengthMenu": "Show _MENU_ entries"
        }
      });
      $('#datagrid-level1approval').DataTable({
        "pagingType": "numbers",
        "pageLength": 25,
        "lengthMenu": [[10, 25, 50, 100], [10, 25, 50, 100]],
        "language": {
            "lengthMenu": "Show _MENU_ entries"
        }
      });
      $('#datagrid-level2approval').DataTable({
        "pagingType": "numbers",
        "pageLength": 25,
        "lengthMenu": [[10, 25, 50, 100], [10, 25, 50, 100]],
        "language": {
            "lengthMenu": "Show _MENU_ entries"
        }
      });
      $('#datagrid-level3approval').DataTable({
        "pagingType": "numbers",
        "pageLength": 25,
        "lengthMenu": [[10, 25, 50, 100], [10, 25, 50, 100]],
        "language": {
            "lengthMenu": "Show _MENU_ entries"
        }
      });
      $('#datagrid-termination').DataTable({
        "pagingType": "numbers",
        "pageLength": 25,
        "lengthMenu": [[10, 25, 50, 100], [10, 25, 50, 100]],
        "language": {
            "lengthMenu": "Show _MENU_ entries"
        }
      });

      // Modal for viewing protocol
      $('#viewProtocolModal').on('show.bs.modal', function(e) {
        var loadurl = $(e.relatedTarget).data('load-url');
        $(this).find('.modal-body').load(loadurl);
      });

      // Form validation
      $("#formmodalbeginvalidation").on('submit', (function(e) {
        e.preventDefault();
      }));

      // Validation start
      $(".startvalidation").click(function() {
        console.log('Debug: Start validation button clicked');
        $("#confirmbeginvalidation").attr("href", this.href);
      });

      // Handle restricted start button clicks
      $(document).on('click', '.restricted-start-btn', function() {
        console.log('Debug: Restricted start button clicked');
        var advanceLimit = $(this).data('advance-limit');
        Swal.fire({
          icon: 'error',
          title: 'Validation Start Restricted',
          text: 'Validation cannot be started more than ' + advanceLimit + ' days before the planned start date.'
        });
      });

      // Initialize variables
      val_wf_id = "";
      needdeviationremarks = false;
      
      // Handle validation modal display
      $('#startValidationModal').on('show.bs.modal', function(event) {
        console.log('Debug: Modal show event triggered');
        var button = $(event.relatedTarget);
        var recipient = button.data('whatever');
        val_wf_id = recipient;
        var modal = $(this);

        var plannedDateObj = new Date(button.data('planneddate'));
        plannedDateObj.setHours(0, 0, 0, 0);
        plannedDate = plannedDateObj.getTime();

        var todayObj = new Date();
        todayObj.setHours(0, 0, 0, 0);
        today = todayObj.getTime();

        maxAdvanceDate = today + (validationAdvanceLimit * 24 * 60 * 60 * 1000);

        console.log('Debug: Planned Date:', new Date(plannedDate));
        console.log('Debug: Today:', new Date(today));
        console.log('Debug: Max Advance Date:', new Date(maxAdvanceDate));
        console.log('Debug: Validation Advance Limit:', validationAdvanceLimit);

        if (plannedDate > maxAdvanceDate) {
          // Prevent starting more than configured days in advance
          event.preventDefault();
          Swal.fire({
            icon: 'error',
            title: 'Validation Start Restricted',
            text: 'Validation cannot be started more than ' + validationAdvanceLimit + ' days before the planned start date.'
          });
          return;
        } else if (plannedDate < today) {
          needdeviationremarks = true;
          $("#deviationremark").prop("disabled", false);
          $('.dev_remarks').show();
          Swal.fire({
            icon: 'warning',
            title: 'Attention',
            text: 'You are trying to start the validation study after the planned start date. Kindly input the deviation remarks.'
          });
        } else {
          $("#deviationremark").prop("disabled", true);
          $('.dev_remarks').hide();
          needdeviationremarks = false;
        }

        $("#modalvalwfid").prop("innerHTML", recipient);
        val_wf_id_modal = recipient;
        url = button.data('href');
      });

      // Form submission handling
      $("#btnSubmitData").click(function(e) {
        var form = document.getElementById('formmodalbeginvalidation');

        if(form.checkValidity() === false) {
           Swal.fire({
            icon: 'error',
            title: 'Missing Details',
            text: 'Mandetory details are missing.',
          });
          form.classList.add('was-validated');
          return;
        }
        
        // Check for duplicate entries across all file input sections
        var selectedEmployees = $('.file-input-section select[name="employee[]"]').map(function() {
          return $(this).val();
        }).get();

        // Check if there are any duplicate entries
        if (hasDuplicates(selectedEmployees)) {
          Swal.fire({
            icon: 'error',
            title: 'Duplicate Entry!',
            text: 'Selected employees should not be duplicates.',
          });
          return;
        }

        // Check that all files have complete department/employee selections
        if (!allFilesHaveCompleteSelections()) {
          Swal.fire({
            icon: 'error',
            title: 'Incomplete Training Details!',
            text: 'Please select both department and employee for each training certificate file.',
          });
          return;
        }
        
        // Check if at least one entry is added for each department
        if (!hasEntriesForDepartments()) {
          Swal.fire({
            icon: 'error',
            title: 'Training Details Missing!',
            text: 'Please add training details for each department.',
          });
          return;
        } else if (hasNoFilesSelected()) {
          Swal.fire({
            icon: 'error',
            title: 'Training Certificate Missing!',
            text: 'Please choose training certificate PDF file for each employee.',
          });
          return;
        }
        
        // Check for oversized files before submission
        if (hasOversizedFiles()) {
          Swal.fire({
            icon: 'error',
            title: 'Files Too Large!',
            text: 'One or more files exceed the 5MB limit. Please select smaller files.',
          });
          return;
        }

        if (needdeviationremarks == true && $('#deviationremark').val() == '') {
          Swal.fire({
            icon: 'error',
            title: 'Oops...',
            text: 'Please enter the deviation remarks.'
          });
        } else {
          $('#startValidationModal').modal('hide');
          
          // Set the success callback function before showing the modal
          if (typeof setSuccessCallback === 'function') {
            setSuccessCallback(function(response) {
              console.log('E-sign success callback received:', response);
              successfulSubmission();
            });
          } else {
            console.error('setSuccessCallback function not available');
            Swal.fire({
              icon: 'error',
              title: 'System Error',
              text: 'Authentication system not properly initialized. Please refresh the page and try again.'
            });
            return;
          }
          
          $('#enterPasswordRemark').modal('show');
        }
      });
/*
      // Handle remarks submission
      $("#emdlbtnsubmit").on('click', (function(e) {
        e.preventDefault();

        if ($("#user_remark").val().length == 0 || $("#user_password").val().length == 0) {
          Swal.fire({
            icon: 'error',
            title: 'Oops...',
            text: 'Enter password and remarks to proceed.'
          });
        } else {
          $("#prgmodaladd").css('display', 'block');
          $("#emdlbtnsubmit").prop("innerHTML", "Please wait...");
          $("#emdlbtnsubmit").attr('disabled', 'disabled');
          $("#emdlbtnclose").attr('disabled', 'disabled');
          $("#modalbtncross").attr('disabled', 'disabled');

          // Get CSRF token
          var csrfToken = $("input[name='csrf_token']").val();
          
          // Fixed AJAX call to include CSRF token
          $.ajax({
            url: "core/validation/addremarks.php",
            type: "POST",
            data: {
              csrf_token: csrfToken,
              user_remark: $("#user_remark").val(),
              user_password: $("#user_password").val(),
              wf_id: val_wf_id,
              test_wf_id: '' // Add empty value if not needed
            },
            success: function(data) {
              console.log("Response received:", data);
              
              // Try to parse as JSON first
              try {
                var response = JSON.parse(data);
                console.log("Parsed response:", response);
                
                // Update CSRF token if provided
                if (response.csrf_token) {
                  $("input[name='csrf_token']").val(response.csrf_token);
                }
                
                // Check for account locking
                if (response.forceRedirect && response.redirect) {
                  console.log("Force redirect detected to:", response.redirect);
                  
                  // Show alert and redirect
                  Swal.fire({
                    icon: 'error',
                    title: 'Account Locked',
                    text: "Your account has been locked due to too many failed attempts. Please contact the administrator.",
                    allowOutsideClick: false,
                    confirmButtonText: 'OK'
                  }).then(function() {
                    window.location.href = response.redirect;
                  });
                  return;
                }
                
                if (response.status === "success") {
                  successfulSubmission();
                } else {
                  // Display error message
                  var errorMessage = "Please enter the correct password and proceed.";
                  if (response.message === "invalid_credentials" && response.attempts_left) {
                    errorMessage += " You have " + response.attempts_left + " attempts left.";
                  }
                  
                  Swal.fire({
                    icon: 'error',
                    title: 'Oops...',
                    text: errorMessage
                  });
                  
                  // Reset UI
                  $("#prgmodaladd").css('display', 'none');
                  $("#emdlbtnsubmit").prop("innerHTML", "Proceed");
                  $("#emdlbtnsubmit").removeAttr('disabled');
                  $("#emdlbtnclose").removeAttr('disabled');
                }
              } catch (e) {
                // Legacy handling for non-JSON responses
                console.log("Non-JSON response:", data);
                
                if (data === "success") {
                  successfulSubmission();
                } else {
                  Swal.fire({
                    icon: 'error',
                    title: 'Oops...',
                    text: 'Please enter the correct password and proceed.'
                  });
                  
                  // Reset UI
                  $("#prgmodaladd").css('display', 'none');
                  $("#emdlbtnsubmit").prop("innerHTML", "Proceed");
                  $("#emdlbtnsubmit").removeAttr('disabled');
                  $("#emdlbtnclose").removeAttr('disabled');
                }
              }
            },
            error: function(xhr, status, error) {
              console.error("AJAX Error:", error);
              
              Swal.fire({
                icon: 'error',
                title: 'Connection Error',
                text: 'Could not connect to the server. Please try again.'
              });
              
              // Reset UI
              $("#prgmodaladd").css('display', 'none');
              $("#emdlbtnsubmit").prop("innerHTML", "Proceed");
              $("#emdlbtnsubmit").removeAttr('disabled');
              $("#emdlbtnclose").removeAttr('disabled');
            }
          });
        }
      }));
*/
      // File size validation function
      function validateFileSize(fileInput) {
        const maxSizeBytes = 5 * 1024 * 1024; // 5MB in bytes
        const maxSizeMB = 5;
        const files = fileInput.files;
        
        if (files && files.length > 0) {
          for (let i = 0; i < files.length; i++) {
            if (files[i].size > maxSizeBytes) {
              return {
                isValid: false,
                message: `File "${files[i].name}" is ${(files[i].size / (1024 * 1024)).toFixed(2)}MB. Maximum allowed size is ${maxSizeMB}MB.`,
                fileName: files[i].name,
                actualSize: files[i].size
              };
            }
          }
        }
        
        return { isValid: true };
      }
      
      // Display file size error message
      function showFileSizeError(fileInputSection, errorMessage) {
        // Remove any existing error messages
        fileInputSection.find('.file-size-error').remove();
        
        // Add error message
        const errorHtml = '<div class="file-size-error alert alert-danger alert-sm mt-2" role="alert">' +
                         '<i class="mdi mdi-alert-circle"></i> ' + errorMessage + '</div>';
        fileInputSection.find('.col-sm:first').append(errorHtml);
        
        // Add error styling to file input
        fileInputSection.find('input[type="file"]').addClass('is-invalid');
      }
      
      // Clear file size error message
      function clearFileSizeError(fileInputSection) {
        fileInputSection.find('.file-size-error').remove();
        fileInputSection.find('input[type="file"]').removeClass('is-invalid');
      }

      // Helper function for successful submission
// Helper function for successful submission
// Replace your entire successfulSubmission function with this one
function successfulSubmission() {
    console.log('=== successfulSubmission called (UNIQUE FIELDS VERSION) ===');
    console.log('val_wf_id_modal:', val_wf_id_modal);
    
    // Validate required data before proceeding
    if (!val_wf_id_modal) {
      console.error('val_wf_id_modal is missing');
      Swal.fire({
        icon: 'error',
        title: 'Error',
        text: 'Workflow ID is missing. Please try again.'
      });
      return;
    }
    
    Swal.fire({
      icon: 'success',
      title: 'Success',
      text: "Authentication successful. Saving validation data..."
    }).then((result) => {
      // Create a fresh FormData instance
      var formData = new FormData();
      
      // Add the workflow ID and other essential data
      formData.append('val_wf_id', val_wf_id_modal);
      formData.append('justification', $("#justification").val() || '');
      formData.append('sop1', $('#sop1').val() || '');
      formData.append('sop2', $('#sop2').val() || '');
      formData.append('sop3', $('#sop3').val() || '');
      formData.append('sop4', $('#sop4').val() || '');
      formData.append('sop5', $('#sop5').val() || '');
      formData.append('sop6', $('#sop6').val() || '');
      formData.append('sop7', $('#sop7').val() || '');
      formData.append('sop8', $('#sop8').val() || '');
      formData.append('sop9', $('#sop9').val() || '');
      formData.append('sop10', $('#sop10').val() || '');
      formData.append('sop11', $('#sop11').val() || '');
      formData.append('sop12', $('#sop12').val() || '');
      formData.append('sop13', $('#sop13').val() || '');
      formData.append('deviation_remark', $('#deviationremark').val() || '');
      
      // Collect all file input sections
      var fileInputSections = $('.file-input-section');
      var validEntryCount = 0;
      var hasError = false;
      
      console.log('Found', fileInputSections.length, 'file input sections');
      
      // Process each file input section with UNIQUE FIELD NAMES
      fileInputSections.each(function(sectionIndex) {
        if (hasError) return; // Skip if we already have an error
        
        var section = $(this);
        var fileInput = section.find('input[type="file"]')[0];
        var departmentSelect = section.find('select[name="department[]"]')[0];
        var employeeSelect = section.find('select[name="employee[]"]')[0];
        
        console.log(`Processing section ${sectionIndex}:`);
        
        // Check if this section has a file selected
        if (fileInput && fileInput.files && fileInput.files.length > 0) {
          // Get the FIRST file only
          var file = fileInput.files[0];
          var departmentValue = departmentSelect ? departmentSelect.value : '';
          var employeeValue = employeeSelect ? employeeSelect.value : '';
          
          console.log(`Section ${sectionIndex} - File: ${file.name}, Dept: ${departmentValue}, Emp: ${employeeValue}`);
          
          // Validate that department and employee are selected
          if (!departmentValue || !employeeValue) {
            console.error(`Section ${sectionIndex} missing department or employee`);
            Swal.fire({
              icon: 'error',
              title: 'Missing Selection',
              text: `Please select both department and employee for file: ${file.name}`
            });
            hasError = true;
            return false; // Exit from each() loop
          }
          
          // CRITICAL: Use UNIQUE field names, NOT array notation
          // This is the key difference - we use file_0, dept_0, emp_0 instead of fileInput[]
          var fileFieldName = `file_${validEntryCount}`;
          var deptFieldName = `dept_${validEntryCount}`;
          var empFieldName = `emp_${validEntryCount}`;
          
          formData.append(fileFieldName, file);
          formData.append(deptFieldName, departmentValue);
          formData.append(empFieldName, employeeValue);
          
          console.log(`Added unique fields: ${fileFieldName}=${file.name}, ${deptFieldName}=${departmentValue}, ${empFieldName}=${employeeValue}`);
          validEntryCount++;
        }
      });
      
      // Check if we had any validation errors
      if (hasError) {
        return;
      }
      
      if (validEntryCount === 0) {
        Swal.fire({
          icon: 'error',
          title: 'No Files Selected',
          text: 'Please select at least one training certificate file.'
        });
        return;
      }
      
      // IMPORTANT: Add the entry count so PHP knows how many to process
      formData.append('entry_count', validEntryCount);
      
      // Debug log what we're sending
      console.log('=== FINAL FORMDATA BEING SENT ===');
      console.log(`Total valid entries: ${validEntryCount}`);
      
      // Log all the unique field names we're sending
      console.log('Field structure:');
      for (let i = 0; i < validEntryCount; i++) {
        console.log(`  Entry ${i}: file_${i}, dept_${i}, emp_${i}`);
      }
      
      // Log actual FormData contents for debugging
      console.log('FormData contents:');
      for (let pair of formData.entries()) {
        if (pair[1] instanceof File) {
          console.log(`  ${pair[0]}: [File] ${pair[1].name}`);
        } else {
          console.log(`  ${pair[0]}: ${pair[1]}`);
        }
      }
      
      $('#pleasewaitmodal').modal('show');

      $.ajax({
        url: "core/data/save/createreportdata.php",
        type: 'POST',
        data: formData,
        contentType: false,
        processData: false,
        success: function(response) {
          console.log('Upload response:', response);
          $('#dynamicInputsContainer').empty();
          $('#pleasewaitmodal').modal('hide');
          
          // Check if response indicates success
          if (response.includes('success')) {
            Swal.fire({
              icon: 'success',
              title: 'Success',
              text: "The validation protocol is successfully initiated."
            }).then((result) => {
              if (typeof url !== 'undefined' && url) {
              //alert (url);
                window.location.href = url;
              } else {
                window.location.reload();
              }
            });
          } else if (response.includes('Partial success')) {
            // Handle partial success
            Swal.fire({
              icon: 'warning',
              title: 'Partial Success',
              text: response
            }).then((result) => {
              if (typeof url !== 'undefined' && url) {
                window.location.href = url;
              } else {
                window.location.reload();
              }
            });
          } else {
            // Handle error
            console.error('Error response:', response);
            Swal.fire({
              icon: 'error',
              title: 'Error',
              text: response || 'An error occurred while saving the data.'
            });
          }
        },
        error: function(xhr, status, error) {
          console.error('AJAX Error:', {xhr: xhr, status: status, error: error});
          console.error('Response text:', xhr.responseText);
          $('#pleasewaitmodal').modal('hide');
          
          var errorMessage = 'Failed to save validation data.';
          if (xhr.responseText) {
            errorMessage += ' Server response: ' + xhr.responseText;
          }
          
          Swal.fire({
            icon: 'error',
            title: 'Upload Error',
            text: errorMessage
          });
        }
      });
    });
  }


      // Counter to track the number of file input sections
      var entryCounter = 0;

      // Sample data for dropdowns (replace with actual data)
      var departments = ["1-Engineering", "8-QA","0-QC", "7-EHS", "98-Vendor", "99-Users"];

      // Event handler to add a new file input section
  // Event handler to add a new file input section
$('#addEntryBtn').click(function() {
  // Increment the counter
  entryCounter++;

  // Create a new file input section with dropdowns
  // IMPORTANT: No 'multiple' attribute on the file input
  var fileInputSection =
    '<div class="file-input-section" data-entry-id="' + entryCounter + '">' +
    '<div class="container"> <div class="row mt-3+0"> <div class="col-sm"><input class="form-control" type="file" name="fileInput[]" accept=".pdf"></div>' +
    '<div class="col-sm"><select class="form-control"  name="department[]">' +
    '<option value="">Select Department</option>' +
    getDropdownOptions(departments, "D") +
    '</select>' +
    '<select class="form-control" name="employee[]" required>' +
    '<option value="">Select Employee</option>' +
    '</select></div>' +
    '<div class="col-sm"><button class="btn-remove-row btn btn-outline-danger btn-sm">— Remove</button></div> <hr/></div></div>' +
    '</div>';

  // Append the new section to the container
  $('#dynamicInputsContainer').append(fileInputSection);

  // Initialize the dropdowns
  $('select[name="department[]"]').last().change(function() {
    // Update the employee dropdown based on the selected department
    var selectedDepartment = $(this).val();
    var employeeDropdown = $(this).siblings('select[name="employee[]"]');

    // Clear previous options
    employeeDropdown.empty();

    // Fetch employees data for the selected department using AJAX
    $.ajax({
      url: 'core/data/get/fetchemployee.php',
      type: 'GET',
      dataType: 'json',
      data: {
        department_id: selectedDepartment,
        val_wf_id:val_wf_id
      },
      success: function(employeesData) {
        // Check if data is retrieved successfully
        if (employeesData && employeesData.length > 0) {
          // Populate the employee dropdown with the fetched data
          for (var i = 0; i < employeesData.length; i++) {
            employeeDropdown.append('<option value="' + employeesData[i].user_id + '">' + employeesData[i].user_name + '</option>');
          }
        } else {
          // Handle the case where fetching employee data failed
          console.error('Failed to fetch employee data for the selected department.');
        }
      },
      error: function(error) {
        // Handle the case where the AJAX request itself failed
        console.error('Error fetching employee data:', error);
      }
    });
  });

  // Initialize the file input with validation
  $('input[name="fileInput[]"]').last().on('change', function() {
    var fileInputSection = $(this).closest('.file-input-section');
    
    // Validate file size
    var validation = validateFileSize(this);
    if (!validation.isValid) {
      showFileSizeError(fileInputSection, validation.message);
      // Clear the file input
      this.value = '';
      return;
    } else {
      clearFileSizeError(fileInputSection);
    }
    
    // Ensure only one file is selected (safety check)
    if (this.files && this.files.length > 1) {
      Swal.fire({
        icon: 'warning',
        title: 'Multiple Files',
        text: 'Please select only one file per entry. Add more entries for additional files.'
      });
      this.value = '';
      return;
    }
    
    // Store the selected file, department, and employee in a JavaScript variable
    var selectedFile = this.files;
    var selectedDepartment = $(this).closest('.file-input-section').find('select[name="department[]"]').val();
    var selectedEmployee = $(this).closest('.file-input-section').find('select[name="employee[]"]').val();

    console.log("File:", selectedFile);
    console.log("Department:", selectedDepartment);
    console.log("Employee:", selectedEmployee);
  });
});

      // Function to check for duplicate entries
      function isDuplicateEntry(employee) {
        var existingEmployees = $('.file-input-section select[name="employee[]"]').map(function() {
          return $(this).val();
        }).get();
        
        return existingEmployees.includes(employee);
      }

      // Function to check for duplicates in an array
      function hasDuplicates(array) {
        var uniqueValues = new Set(array);
        return array.length !== uniqueValues.size;
      }

      // Event handler when the Delete button inside a table row is clicked
      $('#dynamicInputsContainer').on('click', '.btn-remove-row', function() {
        // Get the entry ID associated with the clicked delete button
        var entryIdToRemove = $(this).closest('.file-input-section').data('entry-id');
        // Remove the specific file input section with the matching entry ID
        $('[data-entry-id="' + entryIdToRemove + '"]').remove();
      });

      // Function to generate dropdown options
      function getDropdownOptions(options, param1) {
        var dropdownOptions = "";

        if (param1 == "D") {
          for (var i = 0; i < options.length; i++) {
            myarray = options[i].split("-");
            dropdownOptions += '<option value="' + myarray[0] + '">' + myarray[1] + '</option>';
          }
        } else if (param1 == "E") {
          for (var i = 0; i < options.length; i++) {
            dropdownOptions += '<option value="' + options[i] + '">' + options[i] + '</option>';
          }
        }
        return dropdownOptions;
      }

      function hasNoFilesSelected() {
        var files = $('input[name="fileInput[]"]');
        var valid = true;

        files.each(function() {
          var selectedValue = $(this).val();
          if (selectedValue.trim() === "") {
            valid = true;
            return true; // Break out of the loop if any department is not selected
          }
          if (selectedValue.length > 0) {
            valid = false;
          }
        });

        return valid;
      }
      
      // Function to check for oversized files before form submission
      function hasOversizedFiles() {
        const fileInputs = $('input[name="fileInput[]"]');
        let hasOversized = false;
        
        fileInputs.each(function() {
          const validation = validateFileSize(this);
          if (!validation.isValid) {
            hasOversized = true;
            return false; // Break out of loop
          }
        });
        
        return hasOversized;
      }
      
      // Function to check if at least one entry is added for each department
      function hasEntriesForDepartments() {
        var fileInputs = $('input[name="fileInput[]"]');
        var departments = $('select[name="department[]"]');
        var employees = $('select[name="employee[]"]');
        var eng = 0;
        var qa = 0;
        
        // Check each file input to see if it has a valid department/employee selection
        for (let i = 0; i < fileInputs.length; i++) {
          const fileInput = fileInputs[i];
          const departmentSelect = departments[i];
          const employeeSelect = employees[i];
          
          // Only count entries that have files selected
          if (fileInput.files && fileInput.files.length > 0) {
            const departmentValue = departmentSelect.value;
            const employeeValue = employeeSelect.value;
            
            // Skip entries without complete department/employee data
            if (!departmentValue || !employeeValue) {
              continue;
            }
            
            if (departmentValue == '1') {
              eng = 1;
            }
            if (departmentValue == '8') {
              qa = 1;
            }
          }
        }

        return (eng == 1 && qa == 1);
      }
      
      // Function to check that all file inputs have complete department/employee selections
      function allFilesHaveCompleteSelections() {
        var fileInputs = $('input[name="fileInput[]"]');
        var departments = $('select[name="department[]"]');
        var employees = $('select[name="employee[]"]');
        
        console.log('Validating complete selections for', fileInputs.length, 'file inputs');
        
        for (let i = 0; i < fileInputs.length; i++) {
          const fileInput = fileInputs[i];
          const departmentSelect = departments[i];
          const employeeSelect = employees[i];
          
          // If a file is selected, department and employee must also be selected
          if (fileInput.files && fileInput.files.length > 0) {
            const departmentValue = departmentSelect ? departmentSelect.value : '';
            const employeeValue = employeeSelect ? employeeSelect.value : '';
            
            console.log(`File ${i}: ${fileInput.files[0].name}, Dept: ${departmentValue}, Emp: ${employeeValue}`);
            
            if (!departmentValue || !employeeValue) {
              console.error(`Incomplete selection for file: ${fileInput.files[0].name}`);
              return false;
            }
          }
        }
        
        return true;
      }

      // Function to open terminate validation search page
      window.openTerminateModal = function() {
        window.location.href = 'search_validations_to_terminate.php';
      };
    });
  </script>

  

    <link rel="stylesheet" href="assets/css/modern-manage-ui.css">

</head>

<body>
  <?php include_once "assets/inc/_pleasewaitmodal.php"; ?>
  <?php include_once "assets/inc/_esignmodal.php"; ?>
  <?php include "assets/inc/_viewprotocolmodal.php"; ?>
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

            <?php include_once "assets/inc/_beginvalidationmodal.php"; ?>
            <h3 class="page-title"> Validation Protocols </h3>
            <nav aria-label="breadcrumb">
              <ul class="breadcrumb">
                <?php if(isset($_SESSION['department_id']) && (int)$_SESSION['department_id'] === 1): ?>
                <li class="breadcrumb-item"><a class='btn btn-gradient-info btn-sm btn-rounded' href="#" onclick="openTerminateModal()">🛑 Terminate Validation Study</a></li>
                <?php endif; ?>
              </ul>
            </nav>
          </div>

          <div class="row">
            <!-- FIX: Corrected conditional display logic -->
            <div class="col-lg-12 grid-margin stretch-card" <?php if (isset($_SESSION['department_id']) && $_SESSION['department_id'] == '1') echo 'style="display:block;"'; else echo 'style="display:none;"'; ?>>
              <div class="card">
                <div class="card-body">
                  <h4 class="card-title">Upcoming Validation Protocols</h4>
                  <?php include "assets/inc/_upcomingvalidations.php"; ?>
                </div>
              </div>
            </div>

            <div class="col-lg-12 grid-margin stretch-card" <?php if (isset($_SESSION['department_id']) && $_SESSION['department_id'] == '1') echo 'style="display:block;"'; else echo 'style="display:none;"'; ?>>
              <div class="card">
                <div class="card-body">
                  <h4 class="card-title">In-progress Validation Protocols</h4>
                  <?php include "assets/inc/_inprogressvalidations.php"; ?>
                </div>
              </div>
            </div>

            <div class="col-lg-12 grid-margin stretch-card" <?php if (isset($_SESSION['department_id']) && $_SESSION['department_id'] == '1') echo 'style="display:block;"'; else echo 'style="display:none;"'; ?>>
              <div class="card">
                <div class="card-body">
                  <h4 class="card-title">Validation Protocols - Pending Team Approval Submission</h4>
                  <?php include "assets/inc/_pendingforlevel1submission.php"; ?>
                </div>
              </div>
            </div>

            <div class="col-lg-12 grid-margin stretch-card" <?php if ((isset($_SESSION['department_id']) && $_SESSION['department_id'] == '1') || (isset($_SESSION['is_qa_head']) && $_SESSION['is_qa_head'] == 'Yes')) echo 'style="display:block;"'; else echo 'style="display:none;"'; ?>>
              <div class="card">
                <div class="card-body">
                  <h4 class="card-title">Validation Protocols - Termination Requests</h4>
                  <?php include "assets/inc/_terminationrequests.php"; ?>
                </div>
              </div>
            </div>

            <!-- Always visible for all users -->
            <div class="col-lg-12 grid-margin stretch-card" <?php if ($_SESSION['is_admin']== 'Yes' || $_SESSION['is_super_admin'] == 'Yes' || ($_SESSION['department_id'] == '9' && ($_SESSION['is_qa_head'] == 'Yes' || $_SESSION['is_unit_head'] == 'Yes'))) echo 'style="display:none;"'; else echo 'style="display:block;"'; ?>>
              <div class="card">
                <div class="card-body">
                  <h4 class="card-title">Validation Protocols - Pending Team Approval</h4>
                  <?php include "assets/inc/_pendingforlevel1approval.php"; ?>
                </div>
              </div>
            </div>

            <div class="col-lg-12 grid-margin stretch-card" <?php if (isset($_SESSION['is_qa_head']) && $_SESSION['is_qa_head'] == 'Yes') echo 'style="display:block;"'; else echo 'style="display:none;"'; ?>>
              <div class="card">
                <div class="card-body">
                  <h4 class="card-title">Validation Protocols - Pending QA Head approval</h4>
                  <?php include "assets/inc/_pendingforlevel3approval.php"; ?>
                </div>
              </div>
            </div>

            <div class="col-lg-12 grid-margin stretch-card" <?php if (isset($_SESSION['is_unit_head']) && $_SESSION['is_unit_head'] == 'Yes') echo 'style="display:block;"'; else echo 'style="display:none;"'; ?>>
              <div class="card">
                <div class="card-body">
                  <h4 class="card-title">Validation Protocols - Pending Unit Head approval</h4>
                  <?php include "assets/inc/_pendingforlevel2approval.php"; ?>
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