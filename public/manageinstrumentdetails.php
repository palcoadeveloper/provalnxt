<?php
require_once('./core/config/config.php');

// Validate session timeout
require_once('core/security/session_timeout_middleware.php');
validateActiveSession();

require_once('core/config/db.class.php');

// Include input validation utilities (includes SecurityUtils class)
require_once('core/validation/input_validation_utils.php');

// Generate CSRF token if not present
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

date_default_timezone_set("Asia/Kolkata");

// Validate and sanitize input parameters
$mode = isset($_GET['m']) ? InputValidator::sanitizeString($_GET['m']) : 'a';
$instrument_id = isset($_GET['instrument_id']) ? InputValidator::sanitizeString($_GET['instrument_id']) : '';

// Validate mode parameter
if (!empty($mode) && !in_array($mode, ['a', 'e'], true)) {
    header('Location: searchinstruments.php?msg=invalid_mode');
    exit();
}

// Validate instrument_id if provided
if (!empty($instrument_id) && strlen($instrument_id) > 100) {
    header('Location: searchinstruments.php?msg=invalid_instrument_id');
    exit();
}

// Initialize variables
$instrument_data = [];
$page_title = ($mode === 'e') ? 'Edit Instrument' : 'Add New Instrument';
$button_text = ($mode === 'e') ? 'Update Instrument' : 'Add Instrument';

// If editing, fetch existing data
if ($mode === 'e' && !empty($instrument_id)) {
    try {
        $instrument_data = DB::queryFirstRow(
            "SELECT * FROM instruments WHERE instrument_id = %s", 
            $instrument_id
        );
        
        if (!$instrument_data) {
            header('Location: searchinstruments.php?msg=instrument_not_found');
            exit();
        }
    } catch (Exception $e) {
        error_log("Error fetching instrument data: " . $e->getMessage());
        header('Location: searchinstruments.php?msg=error');
        exit();
    }
}

// Get vendor details
$vendor_details = DB::query("SELECT vendor_id, vendor_name FROM vendors WHERE vendor_status='Active' ORDER BY vendor_name");

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include_once "assets/inc/_header.php";?>
    <meta name="csrf-token" content="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
    
    <!-- jQuery UI for Date Picker -->
    <link rel="stylesheet" href="assets/css/jquery-ui.css">
    <script src="assets/js/jquery-ui.min.js"></script>
    
    
    <link rel="stylesheet" href="assets/css/modern-manage-ui.css">

</head>
<body>
    <?php include_once "assets/inc/_pleasewaitmodal.php"; ?>
    <div class="container-scroller">
        <?php include "assets/inc/_navbar.php"; ?>
        <div class="container-fluid page-body-wrapper">
            <?php include "assets/inc/_sidebar.php"; ?>
            <div class="main-panel">
                <div class="content-wrapper">
                    <?php include "assets/inc/_sessiontimeout.php"; ?>
                    
                    <div class="page-header">
                        <h3 class="page-title">
                            Instrument Details
                        </h3>
                        <nav aria-label="breadcrumb">
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item active" aria-current="page">
                                    <span><a class='btn btn-gradient-info btn-sm btn-rounded' href="searchinstruments.php<?php
                                        // Build back navigation URL with search parameters
                                        if (isset($_GET['from_search']) && $_GET['from_search'] == '1') {
                                            $back_params = [];
                                            if (isset($_GET['search_criteria'])) $back_params['search_criteria'] = $_GET['search_criteria'];
                                            if (isset($_GET['search_input'])) $back_params['search_input'] = $_GET['search_input'];
                                            if (isset($_GET['vendor_id'])) $back_params['vendor_id'] = $_GET['vendor_id'];
                                            if (isset($_GET['instrument_type'])) $back_params['instrument_type'] = $_GET['instrument_type'];
                                            if (isset($_GET['calibration_status'])) $back_params['calibration_status'] = $_GET['calibration_status'];
                                            $back_params['restore_search'] = '1';
                                            echo '?' . http_build_query($back_params);
                                        }
                                    ?>"><i class="mdi mdi-arrow-left"></i> Back</a></span>
                                </li>
                            </ul>
                        </nav>
                    </div>
                    
                    <div class="row">
                        <div class="col-12 grid-margin stretch-card">
                            <div class="card">
                                <div class="card-body">
                                    <h4 class="card-title"><?php echo ($mode === 'e') ? 'Edit Instrument Details' : 'Add New Instrument'; ?></h4>
                                    <p class="card-description"><?php echo ($mode === 'e') ? 'Update the instrument information below' : 'Enter the new instrument information below'; ?></p>
                                    
                                    <form class="forms-sample needs-validation" id="instrumentForm" method="POST" action="core/data/save/saveinstrumentdetails.php" enctype="multipart/form-data" novalidate>
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                                        <input type="hidden" name="mode" value="<?php echo htmlspecialchars($mode, ENT_QUOTES, 'UTF-8'); ?>">
                                        <?php if ($mode === 'e'): ?>
                                            <input type="hidden" name="instrument_id" value="<?php echo htmlspecialchars($instrument_id, ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="existing_certificate_path" value="<?php echo htmlspecialchars($instrument_data['master_certificate_path'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                        <?php endif; ?>
                                        
                                        <!-- Basic Information Row -->
                                        <div class="form-row">
                                            <div class="form-group col-md-6">
                                                <label for="instrument_id">Instrument ID/TAG Number <span class="required">*</span></label>
                                                <input type="text" class="form-control" id="instrument_id" name="instrument_id" 
                                                       value="<?php echo htmlspecialchars($instrument_data['instrument_id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" 
                                                       <?php echo ($mode === 'e') ? 'readonly' : ''; ?> required maxlength="100" placeholder="e.g., INST001">
                                                <div class="invalid-feedback">
                                                    Please provide a valid instrument ID.
                                                </div>
                                            </div>
                                            
                                            <div class="form-group col-md-6">
                                                <label for="instrument_type">Instrument Type <span class="required">*</span></label>
                                                <select class="form-control" id="instrument_type" name="instrument_type" required>
                                                    <option value="">Select Instrument Type</option>
                                                    <option value="Air Capture Hood" <?php echo (($instrument_data['instrument_type'] ?? '') === 'Air Capture Hood') ? 'selected' : ''; ?>>Air Capture Hood</option>
                                                    <option value="Anmometer" <?php echo (($instrument_data['instrument_type'] ?? '') === 'Anmometer') ? 'selected' : ''; ?>>Anmometer</option>
                                                    <option value="Photometer" <?php echo (($instrument_data['instrument_type'] ?? '') === 'Photometer') ? 'selected' : ''; ?>>Photometer</option>
                                                    <option value="Particle Counter" <?php echo (($instrument_data['instrument_type'] ?? '') === 'Particle Counter') ? 'selected' : ''; ?>>Particle Counter</option>
                                                </select>
                                                <div class="invalid-feedback">
                                                    Please select an instrument type.
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Vendor and Serial Number Row -->
                                        <div class="form-row">
                                            <div class="form-group col-md-6">
                                                <label for="vendor_id">Vendor <span class="required">*</span></label>
                                                <select class="form-control" id="vendor_id" name="vendor_id" required>
                                                    <option value="">Select Vendor</option>
                                                    <?php if (!empty($vendor_details)): ?>
                                                        <?php foreach ($vendor_details as $vendor): ?>
                                                            <?php $selected = ($mode === 'e' && $instrument_data['vendor_id'] == $vendor['vendor_id']) ? 'selected' : ''; ?>
                                                            <option value="<?php echo $vendor['vendor_id']; ?>" <?php echo $selected; ?>><?php echo htmlspecialchars($vendor['vendor_name'], ENT_QUOTES, 'UTF-8'); ?></option>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>
                                                </select>
                                                <div class="invalid-feedback">
                                                    Please select a vendor.
                                                </div>
                                            </div>
                                            
                                            <div class="form-group col-md-6">
                                                <label for="serial_number">Serial Number <span class="required">*</span></label>
                                                <input type="text" class="form-control" id="serial_number" name="serial_number" 
                                                       value="<?php echo htmlspecialchars($instrument_data['serial_number'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" 
                                                       required maxlength="100" placeholder="e.g., SN123456">
                                                <div class="invalid-feedback">
                                                    Please provide a valid serial number.
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Calibration Dates Row -->
                                        <div class="form-row">
                                            <div class="form-group col-md-6">
                                                <label for="calibrated_on">Calibrated On <span class="required">*</span></label>
                                                <input type="text" class="form-control" id="calibrated_on" name="calibrated_on" 
                                                       value="<?php echo ($mode === 'e' && $instrument_data['calibrated_on']) ? date('Y-m-d', strtotime($instrument_data['calibrated_on'])) : ''; ?>" 
                                                       required placeholder="Click to select date">
                                                <div class="invalid-feedback">
                                                    Please select calibration date.
                                                </div>
                                                <small class="form-text text-muted">Click to select calibration date</small>
                                            </div>
                                            
                                            <div class="form-group col-md-6">
                                                <label for="calibration_due_on">Calibration Due On <span class="required">*</span></label>
                                                <input type="text" class="form-control" id="calibration_due_on" name="calibration_due_on" 
                                                       value="<?php echo ($mode === 'e' && $instrument_data['calibration_due_on']) ? date('Y-m-d', strtotime($instrument_data['calibration_due_on'])) : ''; ?>" 
                                                       required placeholder="Auto-filled from calibration date">
                                                <div class="invalid-feedback">
                                                    Please select calibration due date.
                                                </div>
                                                <small class="form-text text-muted">Auto-filled as 1 year from calibration date</small>
                                            </div>
                                        </div>
                                        
                                        <!-- File Upload and Status Row -->
                                        <div class="form-row">
                                            <div class="form-group col-md-6">
                                                <label for="master_certificate_file">Master Certificate File <span class="required">*</span></label>
                                                <input type="file" class="form-control-file" 
                                                       name="master_certificate_file" id="master_certificate_file" 
                                                       accept=".pdf">
                                                <div class="invalid-feedback">
                                                    Please select a PDF certificate file.
                                                </div>
                                                <small class="form-text text-muted">Upload PDF calibration certificate (max 10MB)</small>
                                                <small class="form-text text-danger font-weight-bold">
                                                    <i class="mdi mdi-alert-circle"></i> This field is mandatory (except when only changing status from Active to Inactive)
                                                </small>
                                            </div>
                                            
                                            <div class="form-group col-md-6">
                                                <label for="instrument_status">Instrument Status <span class="required">*</span></label>
                                                <select class="form-control" id="instrument_status" name="instrument_status" required>
                                                    <option value="Active" <?php echo (($instrument_data['instrument_status'] ?? 'Active') === 'Active') ? 'selected' : ''; ?>>Active</option>
                                                    <option value="Inactive" <?php echo (($instrument_data['instrument_status'] ?? '') === 'Inactive') ? 'selected' : ''; ?>>Inactive</option>
                                                </select>
                                                <div class="invalid-feedback">
                                                    Please select instrument status.
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="d-flex justify-content-center"> 
                                            <?php
                                            if ($mode === 'e') {
                                                ?>
                                                <button type="submit" class="btn btn-gradient-success btn-icon-text" id="modify_instrument"><i class="mdi mdi-content-save"></i> Modify Instrument</button>    
                                                <?php     
                                            } else if ($mode === 'a') {
                                                ?>
                                                <button type="submit" class="btn btn-gradient-primary btn-icon-text" id="add_instrument"><i class="mdi mdi-plus-circle"></i> Add Instrument</button>    
                                                <?php  
                                            }
                                            ?>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($mode === 'e'): ?>
                        <!-- Master Certificate History Card -->
                        <div class="col-12 grid-margin stretch-card">
                            <div class="card">
                                <div class="card-body">
                                    <h4 class="card-title">Master Certificate History</h4>
                                    <p class="card-description">View and manage all master certificate files uploaded for this instrument</p>
                                    
                                    <div class="table-responsive">
                                        <table class="table table-striped table-bordered" id="certificateHistoryTable">
                                            <thead>
                                                <tr>
                                                    <th>Status</th>
                                                    <th>Certificate File</th>
                                                    <th>Calibrated On</th>
                                                    <th>Calibration Due On</th>
                                                    <th>Uploaded By</th>
                                                    <th>Upload Date</th>
                                                    <th>File Size</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody id="certificateHistoryBody">
                                                <tr>
                                                    <td colspan="8" class="text-center">
                                                        <div class="spinner-border spinner-border-sm" role="status">
                                                            <span class="sr-only">Loading...</span>
                                                        </div>
                                                        Loading certificate history...
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    
                                    <div id="noCertificatesMessage" style="display: none;" class="text-center text-muted py-4">
                                        <i class="mdi mdi-file-document-outline mdi-48px"></i>
                                        <p class="mt-2">No certificate files have been uploaded for this instrument yet.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php include "assets/inc/_footercopyright.php"; ?>
            </div>
        </div>
    </div>
    
    <?php include "assets/inc/_footerjs.php"; ?>
    
    <script>
    $(document).ready(function() {
        
        // Date picker initialization
        $("#calibrated_on").datepicker({
            dateFormat: 'yy-mm-dd',
            changeMonth: true,
            changeYear: true,
            maxDate: new Date()
        });

        $("#calibration_due_on").datepicker({
            dateFormat: 'yy-mm-dd',
            changeMonth: true,
            changeYear: true,
            minDate: new Date()
        });

        // Calculate due date based on calibration date (1 year default)
        $("#calibrated_on").change(function() {
            var calibratedDate = $(this).val();
            if (calibratedDate) {
                var dueDate = new Date(calibratedDate);
                dueDate.setFullYear(dueDate.getFullYear() + 1);
                var dueDateString = dueDate.getFullYear() + '-' + 
                    String(dueDate.getMonth() + 1).padStart(2, '0') + '-' + 
                    String(dueDate.getDate()).padStart(2, '0');
                $("#calibration_due_on").val(dueDateString);
            }
        });

        // Add calibration due date validation
        $("#calibration_due_on").change(function() {
            var calibratedDate = $("#calibrated_on").val();
            var dueDate = $(this).val();
            if (calibratedDate && dueDate) {
                if (new Date(dueDate) <= new Date(calibratedDate)) {
                    alert('Calibration due date must be after the calibrated date.');
                    // Reset to auto-calculated date
                    var autoDate = new Date(calibratedDate);
                    autoDate.setFullYear(autoDate.getFullYear() + 1);
                    var autoDateString = autoDate.getFullYear() + '-' + 
                        String(autoDate.getMonth() + 1).padStart(2, '0') + '-' + 
                        String(autoDate.getDate()).padStart(2, '0');
                    $(this).val(autoDateString);
                }
            }
        });

        // File upload validation with enhanced feedback
        $("#master_certificate_file").change(function() {
            var file = this.files[0];
            
            if (file) {
                // Clear validation styling
                $(this).removeClass('is-invalid');
                $(this).siblings('.invalid-feedback').hide();
                
                // Check file type
                if (file.type !== 'application/pdf') {
                    alert('Please select a PDF file only.');
                    $(this).val('').addClass('is-invalid');
                    updateSubmitButtonState();
                    return;
                }
                
                // Check file size (10MB max)
                var maxSize = 10 * 1024 * 1024; // 10MB in bytes
                if (file.size > maxSize) {
                    alert('File size must be less than 10MB.');
                    $(this).val('').addClass('is-invalid');
                    updateSubmitButtonState();
                    return;
                }
                
                // File is valid
                updateSubmitButtonState();
            } else {
                // No file selected - show validation (required for both modes)
                $(this).addClass('is-invalid');
                $(this).siblings('.invalid-feedback').text('Master Certificate File is required.').show();
                updateSubmitButtonState();
            }
        });
        
        // Function to update submit button state based on file requirements
        function updateSubmitButtonState() {
            var fileInput = $('#master_certificate_file')[0];
            var hasFile = fileInput.files && fileInput.files.length > 0;
            var submitBtn = $('#add_instrument, #modify_instrument');
            var isStatusChangeToInactive = isOnlyStatusChangeToInactive();
            
            // Allow submission without file only if changing status from Active to Inactive
            if (!hasFile && !isStatusChangeToInactive) {
                submitBtn.prop('disabled', true).addClass('btn-secondary').removeClass('btn-gradient-primary');
                submitBtn.attr('title', 'Please upload a Master Certificate File before submitting');
            } else {
                submitBtn.prop('disabled', false).removeClass('btn-secondary').addClass('btn-gradient-primary');
                submitBtn.removeAttr('title');
            }
        }
        
        // Function to check if this is only a status change from Active to Inactive
        function isOnlyStatusChangeToInactive() {
            var mode = $('input[name="mode"]').val();
            
            // Only applicable in edit mode
            if (mode !== 'e') {
                return false;
            }
            
            // Check if current status is Active and new status is Inactive
            var currentStatus = '<?php echo htmlspecialchars($instrument_data['instrument_status'] ?? '', ENT_QUOTES, 'UTF-8'); ?>';
            var newStatus = $('#instrument_status').val();
            
            return (currentStatus === 'Active' && newStatus === 'Inactive');
        }
        
        // Update button state when instrument status changes
        $('#instrument_status').change(function() {
            updateSubmitButtonState();
        });
        
        // Initialize button state
        updateSubmitButtonState();
        
        // Form submission for both add and modify
        $('#instrumentForm').submit(function(e) {
            e.preventDefault();
            
            var form = this;
            var isValid = true;
            
            // Check if file is required and not provided (with status change exception)
            var fileInput = $('#master_certificate_file')[0];
            var isStatusChangeToInactive = isOnlyStatusChangeToInactive();
            
            if ((!fileInput.files || fileInput.files.length === 0) && !isStatusChangeToInactive) {
                $('#master_certificate_file').addClass('is-invalid');
                $('#master_certificate_file').siblings('.invalid-feedback').text('Master Certificate File is required (except when only changing status to Inactive).');
                $('#master_certificate_file').siblings('.invalid-feedback').show();
                
                // Show alert to make it more prominent
                Swal.fire({
                    title: 'Required Field Missing!',
                    text: 'Master Certificate File is mandatory for all instruments, except when only changing status from Active to Inactive.',
                    icon: 'warning',
                    confirmButtonText: 'OK'
                });
                
                isValid = false;
            } else {
                $('#master_certificate_file').removeClass('is-invalid');
                $('#master_certificate_file').siblings('.invalid-feedback').hide();
            }
            
            if (form.checkValidity() === false || !isValid) {
                form.classList.add('was-validated');
                return;
            }
            
            $('#pleasewaitmodal').modal('show');
            
            // Create FormData object to handle file upload
            var formData = new FormData(this);
            
            $.ajax({
                type: 'POST',
                url: $(this).attr('action'),
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function(result) {
                    $('#pleasewaitmodal').modal('hide');
                    if (result.success) {
                        Swal.fire({
                            title: 'Success!',
                            text: 'Instrument saved successfully!',
                            icon: 'success',
                            confirmButtonText: 'OK'
                        }).then((result) => {
                            // Build redirect URL with search parameters if available
                            let redirectUrl = "searchinstruments.php";
                            <?php if (isset($_GET['from_search']) && $_GET['from_search'] == '1'): ?>
                                const urlParams = new URLSearchParams();
                                <?php if (isset($_GET['search_criteria'])): ?>urlParams.set('search_criteria', '<?= htmlspecialchars($_GET['search_criteria'], ENT_QUOTES) ?>');<?php endif; ?>
                                <?php if (isset($_GET['search_input'])): ?>urlParams.set('search_input', '<?= htmlspecialchars($_GET['search_input'], ENT_QUOTES) ?>');<?php endif; ?>
                                <?php if (isset($_GET['vendor_id'])): ?>urlParams.set('vendor_id', '<?= htmlspecialchars($_GET['vendor_id'], ENT_QUOTES) ?>');<?php endif; ?>
                                <?php if (isset($_GET['instrument_type'])): ?>urlParams.set('instrument_type', '<?= htmlspecialchars($_GET['instrument_type'], ENT_QUOTES) ?>');<?php endif; ?>
                                <?php if (isset($_GET['calibration_status'])): ?>urlParams.set('calibration_status', '<?= htmlspecialchars($_GET['calibration_status'], ENT_QUOTES) ?>');<?php endif; ?>
                                urlParams.set('restore_search', '1');
                                redirectUrl += '?' + urlParams.toString();
                            <?php endif; ?>
                            window.location.href = redirectUrl;
                        });
                    } else {
                        Swal.fire({
                            title: 'Error!',
                            text: result.message,
                            icon: 'error',
                            confirmButtonText: 'OK'
                        });
                    }
                },
                error: function(xhr, status, error) {
                    $('#pleasewaitmodal').modal('hide');
                    let errorMessage = 'An error occurred';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMessage = xhr.responseJSON.message;
                    } else if (error) {
                        errorMessage = error;
                    }
                    Swal.fire({
                        title: 'Error!',
                        text: errorMessage,
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                }
            });
        });
        
        // Load certificate history for edit mode
        <?php if ($mode === 'e' && !empty($instrument_id)): ?>
        loadCertificateHistory('<?php echo addslashes($instrument_id); ?>');
        <?php endif; ?>
    });
    
    // Function to load certificate history
    function loadCertificateHistory(instrumentId) {
        $.ajax({
            url: 'core/data/get/getinstrumentcertificatehistory.php',
            type: 'GET',
            data: { instrument_id: instrumentId },
            dataType: 'json',
            success: function(response) {
                if (response.success && response.data.length > 0) {
                    displayCertificateHistory(response.data);
                } else {
                    showNoCertificatesMessage();
                }
            },
            error: function(xhr, status, error) {
                console.error('Error loading certificate history:', error);
                showNoCertificatesMessage();
            }
        });
    }
    
    // Function to display certificate history
    function displayCertificateHistory(certificates) {
        var tbody = $('#certificateHistoryBody');
        tbody.empty();
        
        certificates.forEach(function(cert) {
            var statusBadge = getStatusBadge(cert.status, cert.is_active);
            var actions = getCertificateActions(cert);
            
            var row = `
                <tr class="${cert.is_active == 1 ? 'table-success' : ''}">
                    <td>${statusBadge}</td>
                    <td>
                        <div class="d-flex align-items-center">
                            <i class="mdi mdi-file-pdf text-danger mr-2"></i>
                            <span class="text-truncate" style="max-width: 150px;" title="${cert.display_filename}">
                                ${cert.display_filename}
                            </span>
                        </div>
                    </td>
                    <td>${cert.calibrated_on_formatted}</td>
                    <td>${cert.calibration_due_on_formatted}</td>
                    <td>${cert.uploaded_by_name || 'Unknown'}</td>
                    <td>${cert.uploaded_date_formatted}</td>
                    <td>${cert.file_size_formatted}</td>
                    <td>${actions}</td>
                </tr>
            `;
            tbody.append(row);
        });
        
        $('#certificateHistoryTable').show();
        $('#noCertificatesMessage').hide();
    }
    
    // Function to show no certificates message
    function showNoCertificatesMessage() {
        $('#certificateHistoryTable').hide();
        $('#noCertificatesMessage').show();
    }
    
    // Function to get status badge
    function getStatusBadge(status, isActive) {
        var activeIndicator = isActive == 1 ? '<span class="badge badge-success badge-pill ml-1">Active</span>' : '<span class="badge badge-secondary badge-pill ml-1">Inactive</span>';
        
        switch(status) {
            case 'Valid':
                return `<span class="badge badge-success">${status}</span>${activeIndicator}`;
            case 'Due Soon':
                return `<span class="badge badge-warning">${status}</span>${activeIndicator}`;
            case 'Expired':
                return `<span class="badge badge-danger">${status}</span>${activeIndicator}`;
            default:
                return `<span class="badge badge-secondary">${status}</span>${activeIndicator}`;
        }
    }
    
    // Function to get certificate actions
    function getCertificateActions(cert) {
        return `
            <div class="btn-group" role="group">
                <button type="button" class="btn btn-sm btn-outline-primary" 
                        onclick="viewCertificate('${cert.certificate_file_path}', '${cert.display_filename}')"
                        title="View Certificate">
                    
                </button>
                <button type="button" class="btn btn-sm btn-outline-success" 
                        onclick="downloadCertificate('${cert.certificate_file_path}', '${cert.display_filename}')"
                        title="Download Certificate">
                    <i class="mdi mdi-download"></i>
                </button>
            </div>
        `;
    }
    
    // Function to view certificate in modal
    function viewCertificate(filePath, fileName) {
        var modal = $('#imagepdfviewerModal');
        
        // Set modal data
        modal.data('modalData', {
            src: filePath,
            title: fileName,
            allowDownload: true,
            downloadUrl: filePath
        });
        
        // Update modal title
        modal.find('.modal-title').text('Certificate: ' + fileName);
        
        // Show modal
        modal.modal('show');
    }
    
    // Function to download certificate
    function downloadCertificate(filePath, fileName) {
        // Create temporary link for download
        var link = document.createElement('a');
        link.href = filePath;
        link.download = fileName;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
    </script>
    
    <?php include_once "assets/inc/_imagepdfviewermodal.php"; ?>
    <?php include_once "assets/inc/_footerjs.php"; ?>
</body>
</html>