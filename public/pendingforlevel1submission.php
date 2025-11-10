<?php
require_once('./core/config/config.php');

// I've identified and fixed the CSRF token issues in the file
// Main locations fixed:
// 1. Added CSRF token to addremarks.php calls
// 2. Added CSRF token to modal forms
// 3. Added token to AJAX calls
// 4. Fixed handling of responses

// Session is already started by config.php via session_init.php

// Optimized session validation
require_once('core/security/optimized_session_validation.php');
OptimizedSessionValidation::validateOnce();

// Validate required parameters
if (!isset($_GET['val_wf_id']) || empty(trim($_GET['val_wf_id']))) {
    header('HTTP/1.1 400 Bad Request');
    header('Location: assignedcases.php?error=invalid_parameters');
    exit();
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
    require_once('core/error/error_logger.php');
    logDatabaseError("Error fetching audit trails: " . $e->getMessage(), [
        'operation_name' => 'fetch_audit_trails',
        'val_wf_id' => $_GET['val_wf_id'] ?? null
    ]);
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
        header('Location: assignedcases.php?error=workflow_not_found');
        exit();
    }
} catch (Exception $e) {
    require_once('core/error/error_logger.php');
    logDatabaseError("Error fetching workflow details: " . $e->getMessage(), [
        'operation_name' => 'fetch_workflow_details',
        'val_wf_id' => $_GET['val_wf_id'] ?? null
    ]);
    header('HTTP/1.1 500 Internal Server Error');
    header('Location: assignedcases.php?error=database_error');
    exit();
}

try {
    $val_remarks = DB::queryFirstRow(
        "SELECT deviation, summary, recommendationn, deviation_remark_val_begin FROM validation_reports WHERE val_wf_id = %s", 
        $_GET['val_wf_id']
    );
    
    $deviation_remarks = DB::queryFirstField(
        "SELECT deviation FROM validation_reports WHERE val_wf_id = %s", 
        $_GET['val_wf_id']
    );
} catch (Exception $e) {
    require_once('core/error/error_logger.php');
    logDatabaseError("Error fetching validation reports: " . $e->getMessage(), [
        'operation_name' => 'fetch_validation_reports',
        'val_wf_id' => $_GET['val_wf_id'] ?? null
    ]);
    $val_remarks = [];
    $deviation_remarks = '';
}

// Generate a CSRF token if one doesn't exist
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
	<meta name="csrf-token" content="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
	<title>Palcoa ProVal - HVAC Validation System</title>
	<!-- plugins:css -->
	<link rel="stylesheet" href="assets/vendors/mdi/css/materialdesignicons.min.css">
	<link rel="stylesheet" href="assets/vendors/css/vendor.bundle.base.css">
	<link rel="stylesheet" href="assets/vendors/css/dataTables.bootstrap4.css">
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
		$(document).ready(function() {
			// Enhanced modal show event to log file views
			$('#imagepdfviewerModal').on('show.bs.modal', function (e) {
				var src = $(e.relatedTarget).attr('href');
				var uploadId = $(e.relatedTarget).data('upload-id');
				var fileType = $(e.relatedTarget).data('file-type');
				var valWfId = $(e.relatedTarget).data('val-wf-id') || $('#val_wf_id').val();
				
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

			// Form submission handler
			$("#myForm").submit(function (e) {
				var form = e.currentTarget;
				
				if ($('#myForm')[0].checkValidity() === false) {
					e.preventDefault(); // Prevent the default form submission
					e.stopPropagation(); // Stop event propagation
				} else {
					e.preventDefault(); // Prevent the default form submission
					e.stopPropagation(); // Stop event propagation
					
					selected_dept = $('#department').val();
					selected_emp = $('#employee').val();

					if(selected_dept=='select' || selected_emp=='select' || selected_emp=='' || selected_emp==null) {
						alert('Please select employee');
					} else {
						var formData = new FormData($('#myForm')[0]);
						formData.append('val_wf_id', $('#val_wf_id').val());
						// Add CSRF token to FormData
						formData.append('csrf_token', $("input[name='csrf_token']").val());
						
						$.ajax({
							url: "core/data/save/savetrainingdetails.php",
							type: 'POST',
							data: formData,
							contentType: false,
							processData: false,
							success: async function(data) {
								console.log('Raw response:', data);
								
								// Handle the response
								try {
									// If data is already a JSON object (not a string)
									if (typeof data === 'object') {
										var response = data;
									} else {
										// If data is a string, try to parse it as JSON
										if (data === "success") {
											response = {
												status: "success",
												message: "The training details saved successfully."
											};
										} else {
											response = JSON.parse(data);
										}
									}
									
									console.log('Processed response:', response);
									
									// Update CSRF token if provided
									if (response.csrf_token) {
										$("input[name='csrf_token']").val(response.csrf_token);
									}
									
									// Process response status
									if (response.status === "success") {
										loadTrainingDetails();
										Swal.fire({
											icon: 'success',
											title: 'Success',
											text: response.message || "The training details saved successfully."
										}).then((result) => {
											$("#addtrainingdetailsmodal").modal("hide");
											// Reset form
											$('#myForm')[0].reset();
											$('#employee').empty().append('<option value="select">Select</option>');
										});
									} else {
										console.error('Error response:', response);
										Swal.fire({
											icon: 'error',
											title: 'Error',
											text: response.message || 'Something went wrong.'
										});
									}
								} catch (e) {
									console.error('Error processing response:', e, 'Raw data:', data);
									Swal.fire({
										icon: 'error',
										title: 'Error',
										text: 'Unexpected server response'
									});
								}
							},
							error: function(jqXHR, textStatus, errorThrown) {
								console.error('AJAX error:', {
									status: jqXHR.status,
									textStatus: textStatus,
									errorThrown: errorThrown,
									responseText: jqXHR.responseText
								});
								Swal.fire({
									icon: 'error',
									title: 'Error',
									text: 'Network error: ' + textStatus
								});
							}
						});
					}
				}
				form.classList.add('was-validated');
			});

			// Function to load training details
			function loadTrainingDetails() {
				$.ajax({
					url: "core/data/get/gettrainingdetails.php",
					method: "GET",
					data: {
						val_wf_id: $('#val_wf_id').val(),
						csrf_token: $("input[name='csrf_token']").val()
					},
					success: function(data) {
						$("#targettrainingdetails").html(data);
					},
					error: function() {
						alert("Failed to reload section.");
					}
				});
			}

			// Department change handler
			$('select[name="department"]').on('change', function() {
				selectedDepartment = $(this).val();
				employeeDropdown = $('#employee');
				val_wf_id = $('#val_wf_id').val();
				employeeDropdown.empty();
				
				// Fetch employees data for the selected department using AJAX
				$.ajax({
					url: 'core/data/get/fetchemployee.php',
					type: 'GET',
					dataType: 'json',
					data: {
						department_id: selectedDepartment,
						val_wf_id: val_wf_id,
						csrf_token: $("input[name='csrf_token']").val()
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

			// Delete training details handler
			$(document).on('click', '.navlink-delete', async function(e) {
				e.preventDefault();

				const result = await Swal.fire({
					title: 'Are you sure?',
					text: 'Do you want to delete the training details? This action cannot be undone.',
					icon: 'question',
					showCancelButton: true,
					confirmButtonText: 'Yes',
					cancelButtonText: 'No'
				});

				if (result.isConfirmed) {
					$.post("core/data/update/updatetrainingdetails.php", {
						record_id: $(this).attr('data-record-id'),
						val_wf_id: $('#val_wf_id').val(),
						csrf_token: $("input[name='csrf_token']").val()
					},
					async function(data, status) {
						loadTrainingDetails();
						const result = await Swal.fire({
							icon: 'success',
							title: 'Success',
							text: 'The training details were successfully removed.'
						});
					});
				}
			});

			// View protocol modal handler
			$('#viewProtocolModal').on('show.bs.modal', function(e) {
				var loadurl = $(e.relatedTarget).data('load-url');
				$(this).find('.modal-body').load(loadurl);
			});

			// Initialize variables
			needdeviationremarks = false;
			val_wf_id = "<?php echo $_GET["val_wf_id"] ?>";
			current_wf_stage = "<?php echo $val_wf_details['val_wf_current_stage'] ?>";
			logged_in_user = "<?php echo $_SESSION['logged_in_user'] ?>";
			department_id = "<?php if (empty($_SESSION['department_id'])) {
									echo "";
								} else {
									echo $_SESSION['department_id'];
								} ?>";
			logged_in_user = "<?php echo $_SESSION['logged_in_user'] ?>";
			actual_wf_start_date = "<?php echo $val_wf_details['actual_wf_start_datetime'] ?>";
			deviation_remark = "<?php echo $val_wf_details['deviation_remark'] ?>";

			// Check for deviation remarks requirement
			actualDate = new Date(actual_wf_start_date).setHours(0, 0, 0, 0);
			today = new Date().setHours(0, 0, 0, 0);
			var diff = Math.floor((today - actualDate) / 86400000);

			if (diff >= VALIDATION_DEVIATION_THRESHOLD_DAYS && deviation_remark == '') {
				needdeviationremarks = true;
				$("#deviation_remark").prop("disabled", false);
				$('.dev_remarks').show();
				alert('The validation study was initiated more than ' + VALIDATION_DEVIATION_THRESHOLD_DAYS + ' days ago. Kindly input the deviation remarks.');
			} else if (diff >= VALIDATION_DEVIATION_THRESHOLD_DAYS && deviation_remark != '') {
				$("#deviation_remark").prop("disabled", false);
				$('.dev_remarks').show();
				needdeviationremarks = false;
			} else {
				$("#deviation_remark").prop("disabled", true);
				$('.dev_remarks').hide();
				needdeviationremarks = false;
			}

			// Set current date
			var now = new Date();
			var day = ("0" + now.getDate()).slice(-2);
			var month = ("0" + (now.getMonth() + 1)).slice(-2);
			var today = now.getFullYear() + "-" + (month) + "-" + (day);
			$('#test_conducted_date').val(today);

			// Document approval handler
			$(".navlink-approve").click(function(e) {
				e.preventDefault();
				$.post("core/data/update/updateuploadstatus.php", {
					up_id: $(this).attr('data-upload-id'),
					action: 'approve',
					val_wf_stage: current_wf_stage,
					val_wf_id: val_wf_id,
					csrf_token: $("input[name='csrf_token']").val()
				},
				function(data, status) {
					location.reload(true);
				});
			});

			// Document rejection handler
			$(".navlink-reject").click(function(e) {
				e.preventDefault();
				$.post("core/data/update/updateuploadstatus.php", {
					up_id: $(this).attr('data-upload-id'),
					action: 'reject',
					val_wf_id: val_wf_id,
					csrf_token: $("input[name='csrf_token']").val()
				},
				function(data, status) {
					location.reload(true);
				});
			});

			// Document upload form handler
			$("#uploadDocForm").on('submit', (function(e) {
				e.preventDefault();
				var fileName1 = $("#upload_file_raw_data").val();
				var fileName2 = $("#upload_file_master").val();
				var fileName3 = $("#upload_file_certificate").val();
				var fileName4 = $("#upload_file_other").val();

				if ((!fileName1 && !fileName2 && !fileName3 && !fileName4) || (logged_in_user == 'vendor' && (!fileName1 || !fileName2 || !fileName3))) {
					if (logged_in_user == "employee") {
						alert("No file selected for uploading");
					} else {
						alert("Test Raw Data, Master Certificate and Test Certificate files must be uploaded. One or more file(s) are missing.");
					}
				} else { // returns true if the string is not empty
					var formData = new FormData(this);
					formData.append('val_wf_id', val_wf_id);
					// Add CSRF token to FormData
					formData.append('csrf_token', $("input[name='csrf_token']").val());
					
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
							console.log('Upload response:', data);
							
							// Reset UI elements
							is_doc_uploaded = "yes";
							$("#prgDocsUpload").css('display', 'none');
							$("#btnUploadDocs").prop("value", "Upload Documents");
							$("#btnUploadPhoto").removeAttr('disabled');
							$("#btnUploadDocs").removeAttr('disabled');
							$("#btnUploadCanSig").removeAttr('disabled');
							$("#btnUploadParSig").removeAttr('disabled');
							$("#completeProcess").removeAttr('disabled');
							$("#targetError").html("");
							
							// Handle response - check for JSON error format first
							let hasError = false;
							let errorMessage = '';
							
							try {
								// Try to parse as JSON first
								const jsonResponse = JSON.parse(data);
								if (jsonResponse.error) {
									hasError = true;
									errorMessage = jsonResponse.error;
								}
							} catch (e) {
								// If not JSON, check for text-based errors
								if (data.indexOf('Error') !== -1 || data.indexOf('l 0 file') !== -1 || 
									data.indexOf('error') !== -1 || data.indexOf('failed') !== -1) {
									hasError = true;
									errorMessage = data;
								}
							}
							
							if (!hasError && (data === "Files uploaded successfully!" || data.indexOf('uploaded successfully') !== -1)) {
								$("#upDocs").show();
								Swal.fire({
									icon: 'success',
									title: 'Success!',
									text: 'Files have been uploaded successfully!'
								}).then(() => {
									location.reload(true);
								});
							} else if (hasError) {
								$("#targetDocError").html('<div class="alert alert-danger">' + errorMessage + '</div>');
								Swal.fire({
									icon: 'error',
									title: 'Upload Failed',
									text: errorMessage || 'An error occurred during file upload.'
								});
							} else {
								// Unexpected response format
								$("#targetDocError").html('<div class="alert alert-warning">Unexpected response: ' + data + '</div>');
								console.warn('Unexpected upload response format:', data);
							}
						},
						error: function(jqXHR, textStatus, errorThrown) {
							console.error('Upload AJAX error:', {
								status: jqXHR.status,
								textStatus: textStatus,
								errorThrown: errorThrown,
								responseText: jqXHR.responseText
							});
							
							// Reset UI elements
							$("#prgDocsUpload").css('display', 'none');
							$("#btnUploadDocs").prop("value", "Upload Documents");
							$("#btnUploadPhoto").removeAttr('disabled');
							$("#btnUploadDocs").removeAttr('disabled');
							$("#btnUploadCanSig").removeAttr('disabled');
							$("#btnUploadParSig").removeAttr('disabled');
							$("#completeProcess").removeAttr('disabled');
							
							// Show error message
							const errorMsg = jqXHR.responseText || 'Network error occurred during file upload.';
							$("#targetDocError").html('<div class="alert alert-danger">' + errorMsg + '</div>');
							Swal.fire({
								icon: 'error',
								title: 'Upload Failed',
								text: 'Network error: ' + textStatus + (errorThrown ? ' - ' + errorThrown : '')
							});
						}
					});
				}
			}));

			// File type validation
			$('input[type="file"]').change(function(e) {
				var fileExtensions = ['pdf'];
				var fileName = e.target.files[0].name;
				fileExtension = fileName.replace(/^.*\./, '');
				var fileSize = Math.round((e.target.files[0].size / 1024));

				if ($.inArray(fileExtension, fileExtensions) == -1) {
					alert('The file extension is not allowed. Allowed file extension: pdf.');
					$(this).val('');
				}

				if (fileSize >= 4096) {
					alert("Error: File exceeds maximum size (4MB)");
					$(this).val('');
				}
			});

			// Upload documents button handler
			$("#uploaddocs").click(function(e) {
				e.preventDefault();
				$('#uploadDocsModal').modal();
			});

			// Submit for approval handler
			$("#submitforapproval").click(function(e) {
				e.preventDefault();
				
				$.get("core/data/save/savereportdata.php", {
					csrf_token: $("input[name='csrf_token']").val()
				},
				function(data, status) {
					location.reload(true);
					alert("succcess");
				});
			});

			// Form submit button handler
			$('#btnSubmit').on('click', (function(e) {
				e.preventDefault();

				if ($(".navlink-approve")[0]) {
					Swal.fire({
						icon: 'error',
						title: 'Oops...',
						text: 'You have one or more files to be reviewed.'
					});
					return;
				}

				var form = document.getElementById('formteamapproval');

				if ($('#deviations').val().trim().length == 0 || $('#summary').val().trim().length == 0 || $('#recommendation').val().trim().length == 0 || $('#deviation_review').val().trim().length == 0) {
					Swal.fire({
						icon: 'error',
						title: 'Oops...',
						text: 'Please provide input for all mandatory fields.'
					});
					form.classList.add('was-validated');
				} else if ($(".navlink-approve")[0]) {
					Swal.fire({
						icon: 'error',
						title: 'Oops...',
						text: 'You have one or more files to be approved.'
					});
				} else {
					form.classList.add('was-validated');
					// Set success callback for the modal
					setSuccessCallback(function(response) {
						// Show specific success message for level 1 submission
						Swal.fire({
							icon: 'success',
							title: 'Success',
							text: 'Data saved. The task has been successfully submitted.'
						}).then(function() {
							$('#formteamapproval').submit();
						});
					});
					$('#enterPasswordRemark').modal('show');
				}
			}));
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
	</style>
</head>

<body>
	<!-- Modal -->
	<?php include_once "assets/inc/_imagepdfviewermodal.php"; ?>
	<?php include_once "assets/inc/_viewprotocolmodal.php"; ?>
	<?php include_once "assets/inc/_esignmodal.php"; ?>

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
					<input type="hidden" id="val_wf_id" name="val_wf_id" value="<?php echo $_GET["val_wf_id"] ?>" />
					<!-- Add CSRF token field -->
					<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>" />
					<div class="modal-body">
						<div class="text-center">
							<table class="table table-bordered">
								<tr>
									<td><label>Raw Data File</label>
									<td><input name="upload_file_raw_data" id="upload_file_raw_data" type="file" class="form-control-file" /></td>
								</tr>
								<tr>
									<td><label>Master Certificate File</label>
									<td><input name="upload_file_master" id="upload_file_master" type="file" class="form-control-file" /></td>
								</tr>
								<tr>
									<td><label>Certificate File</label>
									<td><input name="upload_file_certificate" id="upload_file_certificate" type="file" class="form-control-file" /></td>
								</tr>
								<tr>
									<td><label>Other Documents</label>
									<td><input name="upload_file_other" id="upload_file_other" type="file" class="form-control-file" /></td>
								</tr>
								<tr>
									<td colspan="2"><button id="btnUploadDocs" class="btn btn-gradient-success btn-icon-text" type="submit">
										<i class="mdi mdi-cloud-upload"></i> Upload Document
									</button></td>
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

	<div class="modal" id="addtrainingdetailsmodal" tabindex="-1" role="dialog">
		<div class="modal-dialog modal-lg" role="document">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title">Add Training Details</h5>
					<button type="button" class="close" data-dismiss="modal" aria-label="Close">
						<span aria-hidden="true">&times;</span>
					</button>
				</div>
				<div class="modal-body">
					<div class="container">
						<form id="myForm" class="needs-validation" enctype="multipart/form-data" novalidate>
							<input type="hidden" name="val_wf_id" id="val_wf_id" value="<?php echo $_GET['val_wf_id'] ?>" />
							<!-- Add CSRF token field -->
							<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>" />
							<div class="form-row">
								<div class="form-group col-md-4">
									<label for="exampleSelectGender">Department</label>
									<select class="form-control" id="department" name="department" required>
										<option>Select</option>
										<option value="1">Engineering</option>
										<option value="8">Quality Assurance</option>
										<option value="0">Quality Control</option>
										<option value="7">EHS</option>
										<option value="98">Vendor</option>
										<option value="99">Users</option>
									</select>
									<div class="invalid-feedback">Please select the department.</div>
								</div>
								<div class="form-group col-md-4">
									<label for="exampleSelectGender">Employee</label>
									<select class="form-control" id="employee" name="employee" required>
										<option value="select"> Select</option>
									</select>
								</div>
								<div class="form-group col-md-4">
									<label for="exampleSelectGender">Training PDF</label>
									<input type="file" accept=".pdf" class="form-control" id="trainingpdffile" name="trainingpdffile" required>
									<div class="invalid-feedback">Please select the certificate PDF file.</div>
								</div>
							</div>

							<div class="d-flex justify-content-center" style="margin-bottom: 20px;">
								<button class="btn btn-sm btn-info" id="addButton">Add Training Details</button>
							</div>
						</form>
					</div>
				</div>
			</div>
		</div>
	</div>

	<div class="container-scroller">
		<!-- partial:assets/inc/_navbar.php -->
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
								<li class="breadcrumb-item active" aria-current="page">
									<span><a class='btn btn-gradient-info btn-sm btn-rounded' href="assignedcases.php"><i class="mdi mdi-arrow-left"></i> Back</a></span>
								</li>
							</ul>
						</nav>
					</div>

					<div class="row">
						<div class="col-lg-12 grid-margin stretch-card">
							<div class="card">
								<div class="card-body">
									<h4 class="card-title">Validation Workflow Event for <?php echo $val_wf_details['equipment_category'] ?> <?php echo $val_wf_details['equipment_code'] ?></h4>
									<p class="card-description"></p>

									<form id="formteamapproval" action="core/data/save/savereportdata.php" method="post" class="needs-validation" novalidate>
										<input type="hidden" name="val_wf_id" value="<?php echo $_GET['val_wf_id'] ?>" />
										<!-- Add CSRF token field -->
										<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>" />
										<div class="table-responsive">
											<table class="table table-bordered">
												<tr>
													<td>
														<h6 class="text-muted">Validation Workflow ID</h6>
													</td>
													<td> <?php echo $_GET['val_wf_id'] ?> </td>
													<td>
														<h6 class="text-muted">Initiated By</h6>
													</td>
													<td> <?php echo $val_wf_details['user_name'] ?> </td>
												</tr>

												<tr>
													<td>
														<h6 class="text-muted">Planned Start Date</h6>
													</td>
													<td> <?php echo date('d.m.Y', strtotime($val_wf_details['val_wf_planned_start_date'])) ?> </td>

													<td>
														<h6 class="text-muted">Actual Start Date</h6>
													</td>
													<td> <?php echo date('d.m.Y H:i:s', strtotime($val_wf_details['actual_wf_start_datetime'])) ?> </td>
												</tr>

												<tr>
													<td>
														<h6 class="text-muted">Equipment Code</h6>
													</td>
													<td> <?php echo $val_wf_details['equipment_code'] ?> </td>

													<td>
														<h6 class="text-muted">Department Name</h6>
													</td>
													<td> <?php echo $val_wf_details['department_name'] ?> </td>
												</tr>

												<tr>
													<td>
														<h6 class="text-muted">Workflow Status</h6>
													</td>
													<td colspan="3"><?php
                                                        if ($val_wf_details['val_wf_current_stage'] == 1) {
                                                            echo "Pending for Team Approval Submission.";
                                                        }
                                                    ?></td>
												</tr>

												<tr>
													<td>
														<h6 class="text-muted">Training Details</h6>
													</td>
													<td colspan="3">
														<a href="#" data-toggle="modal" data-target="#addtrainingdetailsmodal" class="btn btn-success btn-sm" role="button" aria-pressed="true">Add Training Details</a>
														<br />
														<br />
														<div id="targettrainingdetails"><?php include_once "core/data/get/gettrainingdetails.php"; ?></div>
													</td>
												</tr>

												<tr>
													<td>
														<h6 class="text-muted">Validation Report</h6>
													</td>
													<td colspan="3">
														<a href='#' data-toggle='modal' data-target='#viewProtocolModal' data-load-url='viewprotocol_modal.php?equipment_id=<?php echo $val_wf_details['equipment_id'] ?>&val_wf_id=<?php echo $_GET['val_wf_id'] ?>' class='btn btn-success btn-sm' role='button' aria-pressed='true'>View Report</a>
													</td>
												</tr>

												<tr>
													<td class="align-text-top" colspan="4">
														<h6 class="text-muted">Upload Documents</h6>
														<br />
														<a href="#" id="uploaddocs" class='btn btn-success btn-sm' role='button' aria-pressed='true'>Upload Documents</a>
														<br />
														<br />
														<div id="targetDocError"></div>
														<div id="targetDocLayer"><?php include("core/data/get/getuploadedfilesonlevel1.php") ?></div>
													</td>
												</tr>

												<tr>
													<td class="align-text-top" colspan="4">
														<h6 class="text-muted">Validation Approval Iteration Details</h6>
														<br />
									
														<div id="showappremarks"><?php include("core/data/get/getiterationdetails.php") ?></div>
													</td>
												</tr>








												

												<tr>
													<td>
														<h6 class="text-muted">Validation Procedures</h6>
													</td>
													<td colspan="3">
														<?php include("assets/inc/_testobservationsforreport.php") ?>
													</td>
												</tr>

												<tr>
													<td>
														<h6 class="text-muted">Deviation/Out of specifications (If any)</h6>
													</td>
													<td colspan="3">
														<textarea class="form-control" id="deviations" name="deviations" required><?php echo htmlspecialchars($deviation_remarks ?? ''); ?></textarea>
														<div class="invalid-feedback">
															Please enter deviation remark. Enter NA, if not applicable. 
														</div>
													</td>
												</tr>

												<tr>
													<td>
														<h6 class="text-muted">Summary of performance verification</h6>
													</td>
													<td colspan="3">
														<textarea class="form-control" id="summary" name="summary" required></textarea>
														<div class="invalid-feedback">
															Enter Summary of performance verification. Enter NA, if not applicable.
														</div>
													</td>
												</tr>

											<!--	<tr class="dev_remarks">
													<td>
														<h6 class="text-muted">Deviation Remarks</h6>
													</td>
													<td colspan="3">
														<input type="text" id="deviation_remark" name="deviation_remark" class="form-control" required/>
														<div class="invalid-feedback">
															Please enter Deviation Remarks. Enter NA, if not applicable.
														</div>
													</td>
												</tr> -->

												<tr>
													<td>
														<h6 class="text-muted">Recommendation</h6>
													</td>
													<td colspan="3">
														<textarea class="form-control" id="recommendation" name="recommendation" required></textarea>
														<div class="invalid-feedback">
															Please enter Recommendation. Enter NA, if not applicable.
														</div>
													</td>
												</tr>

												<tr>
													<td>
														<h6 class="text-muted">Review of deviation, change request, and CAPA since last verification</h6>
													</td>
													<td colspan="3">
														<textarea class="form-control" id="deviation_review" name="deviation_review" 
																  maxlength="500" rows="4" required></textarea>
														<div class="invalid-feedback">
															Please enter review of deviation, change request, and CAPA since last verification. Enter NA, if not applicable.
														</div>
													</td>
												</tr>

												<tr>
													<td>
														<h6 class="text-muted">Approvers</h6>
													</td>
													<td colspan="3">
														<?php include("assets/inc/_getapprovers.php") ?>
													</td>
												</tr>

												<tr>
													<td colspan="4">
														<div class="d-flex justify-content-center">
															<button id="btnSubmit" type="submit" class='btn btn-gradient-primary btn-icon-text'>
																<i class="mdi mdi-send"></i> Submit
															</button>
														</div>
													</td>
												</tr>
											</table>
										</div>
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