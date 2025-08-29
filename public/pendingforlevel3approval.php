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
if (!isset($_GET['val_wf_id']) || empty(trim($_GET['val_wf_id']))) {
    header('HTTP/1.1 400 Bad Request');
    header('Location: ' . BASE_URL . 'error.php?msg=invalid_parameters');
    exit();
}

if (!isset($_GET['val_wf_tracking_id']) || !is_numeric($_GET['val_wf_tracking_id'])) {
    header('HTTP/1.1 400 Bad Request');
    header('Location: ' . BASE_URL . 'error.php?msg=invalid_parameters');
    exit();
}

// Generate a CSRF token if one doesn't exist
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once 'core/config/db.class.php';

try {
    $audit_trails = DB::query(
        "SELECT CONCAT('[', t1.time_stamp, '] - ', t2.wf_stage_description) as 'wf-stages'
         FROM audit_trail t1 
         INNER JOIN workflow_stages t2 ON t1.wf_stage = t2.wf_stage 
         WHERE t1.val_wf_id = %s AND t1.test_wf_id = ''
         ORDER BY t1.time_stamp ASC", 
        $_GET['val_wf_id']
    );
} catch (Exception $e) {
    error_log("Error fetching audit trails: " . $e->getMessage());
    $audit_trails = [];
}

try {
    $val_wf_details = DB::queryFirstRow(
        "SELECT t1.equipment_id, t1.val_wf_current_stage, t1.val_wf_id, t2.equipment_code, t5.val_wf_planned_start_date, 
                t1.actual_wf_start_datetime, t3.user_name, t2.equipment_category, t2.department_id, 
                t4.department_name, t1.deviation_remark
         FROM tbl_val_wf_tracking_details t1
         INNER JOIN equipments t2 ON t1.equipment_id = t2.equipment_id
         INNER JOIN users t3 ON t1.wf_initiated_by_user_id = t3.user_id
         INNER JOIN departments t4 ON t2.department_id = t4.department_id
         INNER JOIN tbl_val_schedules t5 ON t1.val_wf_id = t5.val_wf_id
         WHERE t1.val_wf_id = %s",
        $_GET['val_wf_id']
    );
    
    if (!$val_wf_details) {
        header('HTTP/1.1 404 Not Found');
        header('Location: ' . BASE_URL . 'error.php?msg=workflow_not_found');
        exit();
    }
} catch (Exception $e) {
    error_log("Error fetching workflow details: " . $e->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    header('Location: ' . BASE_URL . 'error.php?msg=database_error');
    exit();
}

try {
    $deviation_remarks = DB::queryFirstField(
        "SELECT deviation FROM validation_reports WHERE val_wf_id = %s", 
        $_GET['val_wf_id']
    );
    
    $approver_details = DB::queryFirstRow(
        "SELECT * FROM tbl_report_approvers WHERE val_wf_id = %s", 
        $_GET['val_wf_id']
    );
} catch (Exception $e) {
    error_log("Error fetching deviation remarks and approver details: " . $e->getMessage());
    $deviation_remarks = '';
    $approver_details = [];
}

// Check if user is authorized as QA head for level 3 approval
try {
    if (isset($_SESSION['is_qa_head']) && $_SESSION['is_qa_head'] == "Yes") {
        $result = DB::query(
            "SELECT level3_head_qa_approval_by FROM tbl_val_wf_approval_tracking_details 
             WHERE val_wf_id IN (
                SELECT val_wf_id FROM tbl_val_wf_tracking_details 
                WHERE val_wf_current_stage = 4 AND val_wf_id = %s AND unit_id = %i
             )", 
            $_GET['val_wf_id'], intval($_SESSION['unit_id'])
        );
        
        if (empty($result)) {
            echo htmlspecialchars("The case can be approved by the user", ENT_QUOTES, 'UTF-8');
        } else {
            echo htmlspecialchars("The case is already approved by head QA", ENT_QUOTES, 'UTF-8');
        }
    } else {
        echo htmlspecialchars("The case can't be approved by you", ENT_QUOTES, 'UTF-8');
    }
} catch (Exception $e) {
    error_log("Error checking level 3 approval authorization: " . $e->getMessage());
    echo htmlspecialchars("Error checking approval authorization", ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
<meta name="viewport"
	content="width=device-width, initial-scale=1, shrink-to-fit=no">
<title>Palcoa ProVal - HVAC Validation System</title>
<!-- plugins:css -->
<link rel="stylesheet"
	href="assets/vendors/mdi/css/materialdesignicons.min.css">
<link rel="stylesheet" href="assets/vendors/css/vendor.bundle.base.css">
<link rel="stylesheet"
	href="assets/vendors/css/dataTables.bootstrap4.css">
<!-- endinject -->
<!-- Plugin css for this page -->
<!-- End plugin css for this page -->
<!-- inject:css -->
<!-- endinject -->
<!-- Layout styles -->
<link rel="stylesheet" href="assets/css/style.css">
<!-- End layout styles -->
<link rel="shortcut icon" href="assets/images/favicon.ico" />
<script src="assets/js/jquery.min.js" type="text/javascript"></script>
	
<script>
	// Validation configuration from server
	const VALIDATION_DEVIATION_THRESHOLD_DAYS = <?= VALIDATION_DEVIATION_THRESHOLD_DAYS ?>;
</script>

<script> 
$(document).ready(function(){
    // SEND BACK BUTTON HANDLER
    $('#btnSendBack').on('click', function(e) {
        e.preventDefault();
        console.log("Send Back button clicked");
        if ($(".navlink-approve")[0]) {
          Swal.fire({
            icon: 'error',
            title: 'Oops...',
            text: 'You have one or more files to be reviewed.'
          });
          return;
        }
        // Validate approver remarks
        if ($('#level3_approver_remark').val().trim().length == 0) {
            Swal.fire({
                icon: 'error',
                title: 'Oops...',
                text: 'Please provide Approver Remarks before sending back.'                
            });
            return;
        }
        
        // Set success callback to append the auth token securely
        setSuccessCallback(function(response) {
            // Build secure URL with the temporary auth token from password validation
            if (response.temp_auth_token) {
                var secureUrl = "core/data/save/sendbackreport.php?wf_id=<?php echo $_GET['val_wf_id']?>&test_wf_id=&level3_approver_remark=" + 
                    encodeURIComponent($("#level3_approver_remark").val()) + 
                    "&deviation_remark=" + encodeURIComponent($("#deviation_remark").val()) + 
                    "&val_wf_approval_tracking_id=<?php echo $_GET['val_wf_tracking_id'] ?>&approval_level=3" +
                    "&auth_token=" + response.temp_auth_token;
                
                // Show confirmation SweetAlert before processing
                Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: 'The report has been sent back successfully.',
                    confirmButtonText: 'OK'
                }).then(function() {
                    // Redirect to process the send back after user acknowledges
                    window.location.href = secureUrl;
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Security Error',
                    text: 'Authentication token missing. Please try again.'
                });
            }
        });
        
        // Show password modal from _esignmodal.php
        $('#enterPasswordRemark').modal('show');
    });

    // SUBMIT BUTTON HANDLER
    $('#btnSubmit').on('click', function(e) {
        e.preventDefault();
        console.log("Submit button clicked");
        if ($(".navlink-approve")[0]) {
          Swal.fire({
            icon: 'error',
            title: 'Oops...',
            text: 'You have one or more files to be reviewed.'
          });
          return;
        }
        var form = document.getElementById('formqaapproval'); 
        
        if ($('#level3_approver_remark').val().trim().length == 0 || (needdeviationremarks == true && $('#deviation_remark').val().trim().length == 0)) {
            Swal.fire({
                icon: 'error',
                title: 'Oops...',
                text: 'Please provide input for all mandatory fields.'                
            });
            form.classList.add('was-validated');
            return;
        } else if ($(".navlink-approve")[0]) {
            Swal.fire({
                icon: 'error',
                title: 'Oops...',
                text: 'You have one or more files to be approved.'                
            });
            return;
        } else {
            // Set success callback for the modal
            setSuccessCallback(function(response) {
                // Show success message for approval
                Swal.fire({
                    icon: 'success',
                    title: 'Success',
                    text: "Data saved. The validation study has been approved."
                }).then(function() {
                    // Add hidden fields to the form before submitting
                    $('<input>').attr({
                        type: 'hidden',
                        name: 'modal_user_remark',
                        value: $("#user_remark").val()
                    }).appendTo('#formqaapproval');
                    
                    // Show loading spinner before AJAX submission
                    showPdfGenerationLoader();
                    
                    // Submit via AJAX to keep spinner visible
                    console.log("Submitting form via AJAX...");
                    $.ajax({
                        url: 'core/data/save/savelevel3approvaldata.php',
                        type: 'GET',
                        data: $('#formqaapproval').serialize(),
                        dataType: 'json',
                        timeout: 300000, // 5 minutes timeout
                        success: function(response, textStatus, xhr) {
                            hidePdfGenerationLoader();
                            console.log("Level3 approval response:", response);
                            
                            if (response.status === 'success') {
                                var successText = response.report_generated ? 
                                    'Level 3 approval completed successfully. Protocol report has been generated.' :
                                    'Level 3 approval completed successfully. Note: Protocol report generation may have encountered issues.';
                                
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Success!',
                                    text: successText,
                                    confirmButtonText: 'OK'
                                }).then(function() {
                                    window.location.href = response.redirect_url || 'manageprotocols.php';
                                });
                            } else if (response.status === 'error') {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Error',
                                    text: response.message || 'An error occurred during the approval process.',
                                    confirmButtonText: 'OK'
                                }).then(function() {
                                    window.location.href = response.redirect_url || 'manageprotocols.php';
                                });
                            } else {
                                // Handle unexpected response format
                                console.warn("Unexpected response format:", response);
                                window.location.href = 'manageprotocols.php';
                            }
                        },
                        error: function(xhr, status, error) {
                            hidePdfGenerationLoader();
                            console.error("AJAX Error:", status, error);
                            console.error("Response text:", xhr.responseText);
                            
                            if (status === 'timeout') {
                                Swal.fire({
                                    icon: 'warning',
                                    title: 'Process Taking Longer',
                                    text: 'The approval process is taking longer than expected. Please check the protocols page to verify if it was completed.',
                                    confirmButtonText: 'Check Protocols'
                                }).then(function() {
                                    window.location.href = 'manageprotocols.php';
                                });
                            } else {
                                // Try to parse error response
                                try {
                                    var errorResponse = JSON.parse(xhr.responseText);
                                    if (errorResponse.status === 'error') {
                                        Swal.fire({
                                            icon: 'error',
                                            title: 'Error',
                                            text: errorResponse.message,
                                            confirmButtonText: 'OK'
                                        }).then(function() {
                                            window.location.href = errorResponse.redirect_url || 'manageprotocols.php';
                                        });
                                        return;
                                    }
                                } catch (e) {
                                    // Response is not JSON
                                }
                                
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Error',
                                    text: 'An error occurred during the approval process. Please try again.',
                                    confirmButtonText: 'OK'
                                }).then(function() {
                                    window.location.reload();
                                });
                            }
                        }
                    });
                });
            });
            
            form.classList.add('was-validated');  
            $('#enterPasswordRemark').modal('show');
        }
    });

    // Helper function to handle error responses
    function handleErrorResponse(response) {
        console.log("Handling error response:", response);
        
        // Handle account locking
        if (response.message === "account_locked" && response.redirect) {
            Swal.fire({
                icon: 'error',
                title: 'Account Locked',
                text: "Your account has been locked due to too many failed attempts. Please contact the administrator."
            }).then(function() {
                window.location.href = response.redirect;
            });
            return;
        }
        
        // Handle CSRF errors
        if (response.message === "security_error" || response.type === "csrf_failure") {
            Swal.fire({
                icon: 'error',
                title: 'Security Error',
                text: "There was a security error. Please refresh the page and try again."
            }).then(function() {
                window.location.reload();
            });
            return;
        }
        
        // Handle server exceptions
        if (response.message === "server_exception") {
            Swal.fire({
                icon: 'error',
                title: 'Server Error',
                text: "Server error: " + (response.details || "Unknown error")
            });
            resetModalState();
            return;
        }
        
        // Handle database errors
        if (response.message === "database_error") {
            Swal.fire({
                icon: 'error',
                title: 'Database Error',
                text: response.details || "There was a database error. Please try again."
            });
            resetModalState();
            return;
        }
        
        // Handle missing parameters
        if (response.message === "missing_parameters") {
            Swal.fire({
                icon: 'error',
                title: 'Missing Information',
                text: "Some required information is missing. Please try again."
            });
            resetModalState();
            return;
        }
        
        // Handle unauthorized access
        if (response.message === "unauthorized") {
            Swal.fire({
                icon: 'error',
                title: 'Unauthorized',
                text: "You are not authorized to perform this action."
            }).then(function() {
                if (response.redirect) {
                    window.location.href = response.redirect;
                }
            });
            return;
        }
        
        // Handle already sent back
        if (response.message === "already_sent_back") {
            Swal.fire({
                icon: 'warning',
                title: 'Already Processed',
                text: "This report has already been sent back."
            }).then(function() {
                window.location.href = "manageprotocols.php";
            });
            return;
        }
        
        // Handle invalid credentials
        if (response.message === "invalid_credentials") {
            let errorMessage = "Please enter the correct password and proceed.";
            if (response.attempts_left) {
                errorMessage += " You have " + response.attempts_left + " attempts left.";
            }
            
            Swal.fire({
                icon: 'error',
                title: 'Invalid Credentials',
                text: errorMessage
            });
            resetModalState();
            return;
        }
        
        // Default error handler for any other error types
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: response.details || response.message || "An unexpected error occurred. Please try again."
        });
        
        resetModalState();
    }

    // Function to reset the modal state
    function resetModalState() {
        $("#prgmodaladd").css('display', 'none');
        $("#emdlbtnsubmit").prop("innerHTML", "Proceed");
        $("#emdlbtnsubmit").removeAttr('disabled');
        $("#emdlbtnclose").removeAttr('disabled');
        $("#modalbtncross").removeAttr('disabled');
        $("#user_remark").val("");
        $("#user_password").val("");
    }

    // Reset modal when closed
    $('#enterPasswordRemark').on('hidden.bs.modal', function () {
        $("#passwordModalTitle").text("Enter Password");
        resetModalState();
        // Remove the action tracking field
        $("#currentAction").remove();
    });

    //Required for closing the session timeout warning alert   
    $(function(){
        $("[data-hide]").on("click", function(){
            $(this).closest("." + $(this).attr("data-hide")).hide();
        });
    });
    
    // Disable the back button - Begin
    window.history.pushState(null, "", window.location.href);        
    window.onpopstate = function() {
        window.history.pushState(null, "", window.location.href);
    };
    // Disable the back button - End
            
    $('#viewProtocolModal').on('show.bs.modal', function (e) {
        var loadurl = $(e.relatedTarget).data('load-url');
        $(this).find('.modal-body').load(loadurl);
    });

    // Enhanced modal show event to log file views
    $('#imagepdfviewerModal').on('show.bs.modal', function (e) {
        var src = $(e.relatedTarget).attr('href');
        var uploadId = $(e.relatedTarget).data('upload-id');
        var fileType = $(e.relatedTarget).data('file-type');
        var valWfId = $(e.relatedTarget).data('val-wf-id') || val_wf_id;
        
        // Generate a unique view ID for this modal open event
        var viewId = Date.now().toString();
        
        // Only log the view if this is a file download link and is triggered from modal
        if (uploadId && fileType && e.relatedTarget) {
            // Log the file view for validation workflow
            $.ajax({
                url: 'core/validation/log_file_view_validation.php',
                type: 'POST',
                data: {
                    upload_id: uploadId,
                    file_type: fileType,
                    file_path: src,
                    val_wf_id: valWfId,
                    view_id: viewId,
                    csrf_token: $("input[name='csrf_token']").val()
                },
                success: function(response) {
                    console.log('File view logged from validation modal');
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

    val_wf_id = "<?php echo $_GET["val_wf_id"]?>";
    current_wf_stage = "<?php echo $val_wf_details['val_wf_current_stage']?>";
    logged_in_user = "<?php echo $_SESSION['logged_in_user'] ?>";
    department_id = "<?php if(empty($_SESSION['department_id'])){echo "";} else {echo $_SESSION['department_id'];} ?>";
    logged_in_user = "<?php echo $_SESSION['logged_in_user'] ?>";
    actual_wf_start_date = "<?php echo $val_wf_details['actual_wf_start_datetime']?>";
    deviation_remark = "<?php echo $val_wf_details['deviation_remark']?>";

    actualDate = new Date(actual_wf_start_date).setHours(0, 0, 0, 0);
    today = new Date().setHours(0, 0, 0, 0);
    var diff = Math.floor((today - actualDate) / 86400000);
     
    if(diff >= VALIDATION_DEVIATION_THRESHOLD_DAYS && deviation_remark == '') {
        needdeviationremarks = true;
        $("#deviation_remark").prop("disabled", false);
        $('.dev_remarks').show();
        alert('The validation study was initiated more than ' + VALIDATION_DEVIATION_THRESHOLD_DAYS + ' days ago. Kindly input the deviation remarks.');
    } else if(diff >= VALIDATION_DEVIATION_THRESHOLD_DAYS && deviation_remark != '') {
        $("#deviation_remark").prop("disabled", false);
        $('.dev_remarks').show();
        needdeviationremarks = false;
    } else {
        $("#deviation_remark").prop("disabled", true);
        $('.dev_remarks').hide();
        needdeviationremarks = false;
    }

    var now = new Date();
    var day = ("0" + now.getDate()).slice(-2);
    var month = ("0" + (now.getMonth() + 1)).slice(-2);
    var today = now.getFullYear() + "-" + (month) + "-" + (day);
    $('#test_conducted_date').val(today);

    $(".navlink-approve").click(function(e) { 
        e.preventDefault();
        $.post("core/data/update/updateuploadstatus.php", {
            up_id: $(this).attr('data-upload-id'),
            action: 'approve',
            csrf_token: $("input[name='csrf_token']").val()
        },
        function(data, status) {
            location.reload(true);
        });
    });

    $(".navlink-reject").click(function(e) { 
        e.preventDefault();
        $.post("core/data/update/updateuploadstatus.php", {
            up_id: $(this).attr('data-upload-id'),
            action: 'reject',
            csrf_token: $("input[name='csrf_token']").val()
        },
        function(data, status) {
            location.reload(true);
        });
    });

    // PASSWORD MODAL SUBMIT HANDLER - COMMENTED OUT, USING _esignmodal.php INSTEAD
    /*
    $("#emdlbtnsubmit").on('click', function(e) {
        e.preventDefault();
        console.log("Password modal submit clicked");
        
        // Get the current CSRF token from the form
        const csrfToken = $("input[name='csrf_token']").val();
        console.log("CSRF Token being sent:", csrfToken);
        
        // Validate inputs
        if($("#user_remark").val().length == 0 || $("#user_password").val().length == 0) {
            Swal.fire({
                icon: 'error',
                title: 'Oops...',
                text: 'Enter password and remarks to proceed.'                
            });
            return;
        }
        
        // Show progress indicator
        $("#prgmodaladd").css('display', 'block');
        
        // Disable buttons to prevent double-submission
        $("#emdlbtnsubmit").prop("innerHTML", "Please wait..."); 
        $("#emdlbtnsubmit").attr('disabled', 'disabled');
        $("#emdlbtnclose").attr('disabled', 'disabled');
        $("#modalbtncross").attr('disabled', 'disabled');
        
        // Determine which action to take based on the currentAction value
        var action = $("#currentAction").val();
        console.log("Current action:", action);
        
        if (action === 'sendback') {
            // SEND BACK ACTION for Level 3
            console.log("Performing send back action for Level 3");
            $.ajax({
                url: "core/data/save/sendbackreport.php",
                type: "POST",
                data: {
                    user_remark: $("#user_remark").val(),
                    user_password: $("#user_password").val(),
                    csrf_token: csrfToken,
                    wf_id: "<?php echo $_GET['val_wf_id']?>",
                    test_wf_id: '',
                    // Use the level3_approver_remark field for the approver's remarks
                    level3_approver_remark: $("#level3_approver_remark").val(),
                    level1_approver_remark: $("#level3_approver_remark").val(), // For backward compatibility
                    deviation_remark: $("#deviation_remark").val(),
                    val_wf_approval_tracking_id: "<?php echo $_GET['val_wf_tracking_id'] ?>",
                    approval_level: 3 // Specify this is a level 3 approval
                },
                dataType: "json",
                success: function(response) {
                    console.log("Response received:", response);
                    
                    // Update CSRF token if provided in response
                    if (response.csrf_token) {
                        $("input[name='csrf_token']").val(response.csrf_token);
                    }
                    
                    if (response.status === "success") {
                        // Hide the modal first
                        $('#enterPasswordRemark').modal('hide');
                        
                        Swal.fire({
                            icon: 'success',
                            title: 'Success',
                            text: "The report has been sent back successfully."
                        }).then(function() {
                            window.location.href = "manageprotocols.php";
                        });
                    } else {
                        handleErrorResponse(response);
                    }
                },
                error: function(xhr, status, error) {
                    console.error("AJAX Error:", status, error);
                    console.log("Response text:", xhr.responseText);
                    
                    try {
                        var response = JSON.parse(xhr.responseText);
                        handleErrorResponse(response);
                    } catch (e) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: "An unexpected error occurred. Please try again."
                        });
                        resetModalState();
                    }
                }
            });
        } else {
            // APPROVAL ACTION
            console.log("Performing approval action");
            $.ajax({
                url: "core/validation/addremarks.php",
                type: "POST",
                data: {
                    user_remark: $("#user_remark").val(),
                    user_password: $("#user_password").val(),
                    csrf_token: csrfToken,
                    wf_id: "<?php echo $_GET['val_wf_id']?>",
                    test_wf_id: '',
                    level1_approver_remark: $("#level3_approver_remark").val(),
                    deviation_remark: $("#deviation_remark").val()
                },
                dataType: "json",
                success: function(response) {
                    console.log("Response received:", response);
                    
                    // Update CSRF token if provided in response
                    if (response.csrf_token) {
                        $("input[name='csrf_token']").val(response.csrf_token);
                        console.log("Updated CSRF token to:", response.csrf_token);
                    }
                    
                    if (response.status === "success") {
                        // Hide the modal first
                        $('#enterPasswordRemark').modal('hide');
                        
                        Swal.fire({
                            icon: 'success',
                            title: 'Success',
                            text: "Data saved. The validation study has been approved."
                        }).then(function() {
                            // Add hidden fields to the form before submitting
                            // These ensure the form has the values entered in the modal
                            $('<input>').attr({
                                type: 'hidden',
                                name: 'modal_user_remark',
                                value: $("#user_remark").val()
                            }).appendTo('#formqaapproval');
                            
                            // Submit the form after modal is closed
                            console.log("Submitting form...");
                            $('#formqaapproval').submit();
                        });
                    } else {
                        handleErrorResponse(response);
                    }
                },
                error: function(xhr, status, error) {
                    console.error("AJAX Error:", status, error);
                    console.log("Response text:", xhr.responseText);
                    
                    try {
                        var response = JSON.parse(xhr.responseText);
                        handleErrorResponse(response);
                    } catch (e) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: "An unexpected error occurred. Please try again."
                        });
                        resetModalState();
                    }
                }
            });
        }
    });
    */

       // UPLOAD DOCUMENTS
    $("#uploaddocs").click(function(e) { 
        e.preventDefault();
        $('#uploadDocsModal').modal();
    });	

    $("#uploadDocForm").on('submit',(function(e) {
        e.preventDefault();
        
        var fileName1 = $("#upload_file_raw_data").val();
        var fileName2 = $("#upload_file_master").val();
        var fileName3 = $("#upload_file_certificate").val();
        var fileName4 = $("#upload_file_other").val();
        
        if((!fileName1 && !fileName2 && !fileName3 && !fileName4) || (logged_in_user=='vendor' && (!fileName1 || !fileName2 || !fileName3))) {
            if(logged_in_user=="employee") {
                alert("No file selected for uploading");
            } else {
                alert("Test Raw Data, Master Certificate and Test Certificate files must be uploaded. One or more file(s) are missing.");
            }
        } else {
            var formData = new FormData(this);
            formData.append('val_wf_id', val_wf_id);
            formData.append('csrf_token', $("input[name='csrf_token']").val());
            
            $("#prgDocsUpload").css('display','block');
            $("#btnUploadDocs").prop("value", "Please wait..."); 
            
            $.ajax({
                url: "core/validation/fileupload.php",
                type: "POST",
                data: formData,
                contentType: false,
                cache: false,
                processData: false,
                success: function(data) {
                    is_doc_uploaded = "yes";
                    
                    $("#prgDocsUpload").css('display','none');
                    $("#btnUploadDocs").prop("value", "Upload Documents"); 
                    $("#btnUploadPhoto").removeAttr('disabled');
                    $("#btnUploadDocs").removeAttr('disabled');
                    $("#btnUploadCanSig").removeAttr('disabled');
                    $("#btnUploadParSig").removeAttr('disabled');
                    $("#completeProcess").removeAttr('disabled');
                    
                    $("#targetError").html("");
                    
                    if(data.indexOf('Error')==-1 && data.indexOf('l 0 file')==-1) {
                        $("#upDocs").show();
                        alert("Files have been uploaded successfully!");
                        location.reload(true);
                    } else {
                        $("#targetDocError").html(data);
                    }
                },
                error: function() {
                    $("#prgDocsUpload").css('display','none');
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

    $('input[type="file"]').change(function(e){
        var fileExtensions = ['pdf'];
        var fileName = e.target.files[0].name;
        fileExtension = fileName.replace(/^.*\./, '');
        
        var fileSize = Math.round((e.target.files[0].size / 1024));
        
        if($.inArray(fileExtension, fileExtensions) == -1) {
            alert('The file extension is not allowed. Allowed file extension: pdf.');
            $(this).val('');
        }
        
        if (fileSize >= 4096) {
            alert("Error: File exceeds maximum size (4MB)");
            $(this).val('');
        }
    });

    // PDF Generation Loading Functions
    let pdfGenerationTimeout;
    
    function showPdfGenerationLoader() {
        $('#pdfGenerationLoader').css('display', 'flex');
        
        // Set a timeout warning after 90 seconds
        pdfGenerationTimeout = setTimeout(function() {
            $('#timeoutWarning').show();
        }, 90000); // 90 seconds
        
        // Hide the loader if something goes wrong after 5 minutes
        setTimeout(function() {
            if ($('#pdfGenerationLoader').is(':visible')) {
                hidePdfGenerationLoader();
                Swal.fire({
                    icon: 'error',
                    title: 'Timeout',
                    text: 'The process is taking longer than expected. Please check if the approval was processed successfully by refreshing the page.',
                    confirmButtonText: 'Refresh Page'
                }).then(function() {
                    window.location.reload();
                });
            }
        }, 300000); // 5 minutes
    }
    
    function hidePdfGenerationLoader() {
        $('#pdfGenerationLoader').css('display', 'none');
        if (pdfGenerationTimeout) {
            clearTimeout(pdfGenerationTimeout);
        }
        $('#timeoutWarning').hide();
    }
    
    // Hide loader when page is about to unload (when redirecting)
    $(window).on('beforeunload', function() {
        hidePdfGenerationLoader();
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

/* Full-screen loading overlay for PDF generation */
#pdfGenerationLoader {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.7);
    display: none;
    z-index: 9999;
    justify-content: center;
    align-items: center;
    flex-direction: column;
}

#pdfGenerationLoader .spinner-container {
    text-align: center;
    background: white;
    padding: 40px;
    border-radius: 10px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
    max-width: 400px;
    width: 90%;
}

#pdfGenerationLoader .spinner-grow {
    width: 4rem !important;
    height: 4rem !important;
    margin-bottom: 20px;
}

#pdfGenerationLoader .loading-text {
    font-size: 18px;
    font-weight: 500;
    color: #333;
    margin-bottom: 10px;
}

#pdfGenerationLoader .loading-subtext {
    font-size: 14px;
    color: #666;
    margin-bottom: 20px;
}

#pdfGenerationLoader .progress-bar-container {
    width: 100%;
    height: 6px;
    background-color: #e9ecef;
    border-radius: 3px;
    overflow: hidden;
    margin-bottom: 15px;
}

#pdfGenerationLoader .progress-bar {
    height: 100%;
    background: linear-gradient(90deg, #007bff, #28a745);
    border-radius: 3px;
    animation: progressAnimation 3s ease-in-out infinite;
}

@keyframes progressAnimation {
    0% { width: 20%; }
    50% { width: 80%; }
    100% { width: 20%; }
}

#pdfGenerationLoader .timeout-warning {
    color: #dc3545;
    font-size: 13px;
    display: none;
}
</style>
  </head>
  <body>
    <?php include_once "assets/inc/_imagepdfviewermodal.php"; ?>
    <?php include_once "assets/inc/_viewprotocolmodal.php";?> 	
    <?php include_once "assets/inc/_esignmodal.php"; ?> 
  
    <!-- PDF Generation Loading Overlay -->
    <div id="pdfGenerationLoader">
        <div class="spinner-container">
            <div class="spinner-grow text-primary" role="status">
                <span class="sr-only">Loading...</span>
            </div>
            <div class="loading-text">Processing Approval</div>
            <div class="loading-subtext">Generating protocol report, please wait...</div>
            <div class="progress-bar-container">
                <div class="progress-bar"></div>
            </div>
            <div class="timeout-warning" id="timeoutWarning">
                This is taking longer than expected. The process may still be running.
            </div>
        </div>
    </div>

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
                            </span> Validation Workflow Details
                        </h3>
                        <nav aria-label="breadcrumb">
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item active" aria-current="page"><span><a class='btn btn-gradient-info btn-sm btn-rounded'
                                        href="manageprotocols.php"><< Back</a> </span>
                                </li>
                            </ul>
                        </nav>
                    </div>
                    
                    <div class="modal fade" id="uploadDocsModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
                      <div class="modal-dialog modal-lg" role="document">
                        <div class="modal-content">
                          <div class="modal-header">
                            <h5 class="modal-title" id="exampleModalLabel">Upload Documents</h5>
                            <button id="modalbtncross" type="button" class="close" data-dismiss="modal" aria-label="Close">
                              <span aria-hidden="true">&times;</span>
                            </button>
                          </div>
                          <div class="d-flex justify-content-center">				
                             <div id="prgmodaladd" class="spinner-grow text-primary" style="width: 3rem; height: 3rem;" role="status">
                              <span class="sr-only">Loading...</span>
                            </div>
                          </div>

                          <form id="uploadDocForm" enctype="multipart/form-data">
                            <input type="hidden" id="val_wf_id" name="val_wf_id" value="<?php echo $_GET["val_wf_id"]?>" />
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>" />
                            <div class="modal-body">
                              <div class="text-center">
                                <table class="table table-bordered">
                                  <tr>
                                    <td><label>Raw Data File</label></td>
                                    <td><input name="upload_file_raw_data" id="upload_file_raw_data" type="file" class="form-control-file" /></td>
                                  </tr>
                                  <tr>
                                    <td><label>Master Certificate File</label></td>
                                    <td><input name="upload_file_master" id="upload_file_master" type="file" class="form-control-file" /></td>
                                  </tr>
                                  <tr>
                                    <td><label>Certificate File</label></td>
                                    <td><input name="upload_file_certificate" id="upload_file_certificate" type="file" class="form-control-file" /></td>
                                  </tr>
                                  <tr>
                                    <td><label>Other Documents</label></td>
                                    <td><input name="upload_file_other" id="upload_file_other" type="file" class="form-control-file" /></td>
                                  </tr>
                                  <tr>
                                    <td colspan="2"><input id="btnUploadDocs" class="btn btn-success" type="submit" value="Upload Document" /></td>
                                  </tr>
                                </table>
                                <br />
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
                    <div class="row">
                        <div class="col-lg-12 grid-margin stretch-card">
                            <div class="card">
                                <div class="card-body">
                                    <h4 class="card-title">Validation Workflow Event for <?php echo $val_wf_details['equipment_category']?> <?php echo $val_wf_details['equipment_code']?></h4>
                                    <p class="card-description"></p>

                                    <form id="formqaapproval" action="core/data/save/savelevel3approvaldata.php" method="get" class="needs-validation" novalidate>
                                        <input type="hidden" name="val_wf_id" value="<?php echo $_GET['val_wf_id']?>"/>
                                        <input type="hidden" name="equipment_id" value="<?php echo $val_wf_details['equipment_id']?>"/>
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>" />
                                        <input type="hidden" name="val_wf_tracking_id" value="<?php echo $_GET['val_wf_tracking_id']?>"/>

                                        <table class="table table-bordered">
                                            <tr>
                                                <td>
                                                    <h6 class="text-muted">Validation Workflow ID</h6>
                                                </td>
                                                <td > <?php echo $_GET['val_wf_id']?>  </td>

                                                <td>
                                                    <h6 class="text-muted">Initiated By</h6>
                                                </td>
                                                <td> <?php echo $val_wf_details['user_name']?>  </td>
                                            </tr>


                                            <tr>
                                                <td>
                                                    <h6 class="text-muted">Planned Start Date</h6>
                                                </td>
                                                <td> <?php echo date("d.m.Y", strtotime($val_wf_details['val_wf_planned_start_date']));?>  </td>

                                                <td><h6 class="text-muted">Actual Start Date</h6></td>
                                                <td> <?php echo date("d.m.Y H:i:s", strtotime($val_wf_details['actual_wf_start_datetime']));?> </td>
                                            </tr>
                                            
                                            <tr>
                                                <td>
                                                    <h6 class="text-muted">Equipment Code</h6>
                                                </td>
                                                <td> <?php echo $val_wf_details['equipment_code']?>  </td>

                                                <td><h6 class="text-muted">Department Name</h6></td>
                                                <td> <?php echo $val_wf_details['department_name']?> </td>
                                            </tr>

                                            <tr>
                                                <td><h6 class="text-muted">Workflow Status</h6></td>
                                                <td colspan="3"><?php 
                                                if($val_wf_details['val_wf_current_stage']==4)
                                                {
                                                    echo "Pending for Level III approval.";
                                                }
                                                ?></td>
                                            </tr>

                                            <tr>
													<td class="align-text-top" colspan="4">
														<h6 class="text-muted">Validation Approval Iteration Details</h6>
														<br />
									
														<div id="showappremarks"><?php include("core/data/get/getiterationdetails.php") ?></div>
													</td>
												</tr>

                                            <tr>
                                                <td><h6 class="text-muted">Validation Report</h6></td>
                                                <td colspan="3">
                                                    <a href='#' data-toggle='modal' data-target='#viewProtocolModal' data-load-url='viewprotocol_modal.php?equipment_id=<?php echo $val_wf_details['equipment_id']?>&val_wf_id=<?php echo $_GET['val_wf_id']?>' class='btn btn-success btn-sm'  role='button' aria-pressed='true'>View Report</a>
                                                </td>
                                            </tr>

                                            <tr>
                                                <td colspan="4"><h6 class="text-muted">Additional Uploaded Documents</h6>
                                                   
                                                   <div class="d-flex">	
                                                        <a href="#" id="uploaddocs" class='btn btn-success btn-sm' role='button' aria-pressed='true'>Upload Documents</a>
                                                    </div>
                                                    <br/>
                                                    <?php include("core/data/get/getuploadedfilesonlevel1.php") ?>
                                                </td>
                                            </tr>

                                            <tr class="dev_remarks">
                                                <td><h6 class="text-muted">Deviation Remarks</h6></td>
                                                <td colspan="3">
                                                    <input type="text" id="deviation_remark" name="deviation_remark" class="form-control" value='<?php echo (empty($deviation_remarks))?'':$deviation_remarks;?>'/>
                                                </td>
                                            </tr>

                                            <tr>
                                                <td><h6 class="text-muted">Approver Remarks</h6></td>
                                                <td colspan="3">
                                                    <textarea rows="2" id="level3_approver_remark" name="level3_approver_remark" class="form-control" required></textarea>
                                                    <div class="invalid-feedback">
                                                        Please enter Remarks. Enter NA, if not applicable.
                                                    </div>
                                                </td>
                                            </tr>

                                            <tr>
                                                <td colspan="4">
                                                    <div class="d-flex justify-content-center"> 
                                                        <input id="btnSubmit" type="submit" class='btn btn-success btn-small' value='Approve' />
                                                        &nbsp;&nbsp;
                                                        <input id="btnSendBack" type="button" class='btn btn-danger btn-small' value='Send Back' />
                                                    </div>
                                                </td>
                                            </tr>
                                        </table>
                                    </form>
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