<?php
require_once('./core/config/config.php');

// Session is already started by config.php via session_init.php

// Validate session timeout
require_once('core/security/session_timeout_middleware.php');
validateActiveSession();
require_once('core/config/db.class.php');

// Use centralized session validation
require_once('core/security/session_validation.php');
validateUserSession();

// Validate and sanitize URL parameters
if (isset($_GET['m']) && $_GET['m'] != 'a') {
    if (!isset($_GET['equip_id']) || !is_numeric($_GET['equip_id'])) {
        header('HTTP/1.1 400 Bad Request');
        exit('Invalid equipment ID');
    }
}

// Generate CSRF token if not already set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (isset($_GET['m']) && $_GET['m'] != 'a') {
    try {
        $equipment_details = DB::queryFirstRow("SELECT equipment_id,equipment_code,unit_id,department_id,equipment_category,validation_frequency,first_validation_date,validation_frequencies,starting_frequency,area_served,section,design_acph,area_classification,area_classification_in_operation,equipment_type,design_cfm,filteration_fresh_air,filteration_pre_filter,filteration_intermediate,filteration_final_filter_plenum,filteration_exhaust_pre_filter,filteration_exhaust_final_filter,filteration_terminal_filter,filteration_terminal_filter_on_riser,filteration_bibo_filter,filteration_relief_filter,filteration_reativation_filter,equipment_status,equipment_addition_date 
            FROM equipments WHERE equipment_id = %d", intval($_GET['equip_id']));
            
        if (!$equipment_details) {
            header('HTTP/1.1 404 Not Found');
            exit('Equipment not found');
        }
        
        // Get the unit's validation scheduling logic
        $unit_details = DB::queryFirstRow("SELECT validation_scheduling_logic FROM units WHERE unit_id = %d and unit_status='Active'", intval($equipment_details['unit_id']));
        if (!$unit_details) {
            $unit_details = ['validation_scheduling_logic' => 'dynamic']; // default fallback
        }
    } catch (Exception $e) {
        error_log("Database error in manageequipmentdetails.php: " . $e->getMessage());
        header('HTTP/1.1 500 Internal Server Error');
        exit('Database error occurred');
    }
} else {
    // For add mode, we'll handle this with JavaScript by checking the selected unit
    $unit_details = ['validation_scheduling_logic' => 'dynamic']; // default
}



?>















<!DOCTYPE html>
<html lang="en">

<head>
    <?php include_once "assets/inc/_header.php"; ?>
    <script>
        $(document).ready(function() {

            // Get unit's validation scheduling logic from PHP
            var currentUnitValidationLogic = '<?php echo isset($unit_details['validation_scheduling_logic']) ? $unit_details['validation_scheduling_logic'] : 'dynamic'; ?>';
            console.log('Unit Validation Scheduling Logic:', currentUnitValidationLogic);

            // Function to convert date format
            function convertDateFormat(dateString) {
                var dateParts = dateString.split('.');
                return dateParts[2] + '-' + dateParts[1] + '-' + dateParts[0];
            }

            $("#equipment_addition_date").datepicker({
                dateFormat: 'dd.mm.yy',
                changeMonth: true,
                beforeShow: function(input, inst) {
                    // Disable manual input by preventing focus on the input field
                    setTimeout(function() {
                        $(input).prop('readonly', true);
                    }, 0);
                }
            });

            // Add datepicker for first validation date
            $("#first_validation_date").datepicker({
                dateFormat: 'dd.mm.yy',
                changeMonth: true,
                beforeShow: function(input, inst) {
                    // Disable manual input by preventing focus on the input field
                    setTimeout(function() {
                        $(input).prop('readonly', true);
                    }, 0);
                }
            });

            // Frequency type switching logic
            $('#frequency_type').on('change', function() {
                const selectedType = $(this).val();
                if (selectedType === 'single') {
                    $('#single_freq_section').show();
                    $('#dual_freq_section').hide();
                    $('#single_freq_select').attr('required', true);
                    $('#combined_freq_select').attr('required', false);
                } else if (selectedType === 'dual') {
                    $('#single_freq_section').hide();
                    $('#dual_freq_section').show();
                    $('#single_freq_select').attr('required', false);
                    $('#combined_freq_select').attr('required', true);
                }
            });

            // Combined frequency selection change handler for Starting Frequency field
            $('#combined_freq_select').on('change', function() {
                const selectedCombination = $(this).val();
                const validCombinations = ['6M,Y', 'Y,2Y', '6M,Y,2Y'];

                if (selectedCombination && validCombinations.includes(selectedCombination)) {
                    // Show starting frequency field and make it required
                    toggleStartingFrequencyField(true, true);
                    // Populate dropdown options based on selected combination
                    populateStartingFrequencyOptions(selectedCombination);
                } else {
                    // Hide starting frequency field
                    toggleStartingFrequencyField(false);
                }
            });

            // Initialize frequency sections based on current selection
            function initializeFrequencySections() {
                const currentFrequencyType = $('#frequency_type').val();
                if (currentFrequencyType === 'single') {
                    $('#single_freq_section').show();
                    $('#dual_freq_section').hide();
                    $('#single_freq_select').attr('required', true);
                    $('#combined_freq_select').attr('required', false);
                    // Hide starting frequency field for single frequency type
                    toggleStartingFrequencyField(false);
                } else if (currentFrequencyType === 'dual') {
                    $('#single_freq_section').hide();
                    $('#dual_freq_section').show();
                    $('#single_freq_select').attr('required', false);
                    $('#combined_freq_select').attr('required', true);

                    // Check if combined frequency is already selected and handle starting frequency field
                    const selectedCombination = $('#combined_freq_select').val();
                    const validCombinations = ['6M,Y', 'Y,2Y', '6M,Y,2Y'];

                    if (selectedCombination && validCombinations.includes(selectedCombination)) {
                        toggleStartingFrequencyField(true, true);
                        populateStartingFrequencyOptions(selectedCombination);
                    } else {
                        toggleStartingFrequencyField(false);
                    }
                } else {
                    // If no frequency type selected, hide starting frequency field
                    toggleStartingFrequencyField(false);
                }
            }

            // Function to populate starting frequency options based on selected combination
            function populateStartingFrequencyOptions(selectedCombination) {
                const startingFreqSelect = $('#starting_freq_combined_select');
                startingFreqSelect.empty();
                startingFreqSelect.append('<option value="">Select Starting Frequency</option>');

                if (!selectedCombination) {
                    return;
                }

                // Map frequency codes to display labels
                const frequencyLabels = {
                    '6M': 'Six Monthly',
                    'Y': 'Yearly',
                    '2Y': 'Bi-Yearly'
                };

                // Split the combination string and add options
                const frequencies = selectedCombination.split(',');
                frequencies.forEach(function(freq) {
                    const trimmedFreq = freq.trim();
                    if (frequencyLabels[trimmedFreq]) {
                        startingFreqSelect.append('<option value="' + trimmedFreq + '">' + frequencyLabels[trimmedFreq] + '</option>');
                    }
                });

                // Pre-select existing value for edit mode
                <?php if ($_GET['m'] != 'a' && isset($equipment_details['starting_frequency'])): ?>
                const existingStartingFreq = '<?php echo htmlspecialchars($equipment_details['starting_frequency'], ENT_QUOTES); ?>';
                if (existingStartingFreq) {
                    startingFreqSelect.val(existingStartingFreq);
                }
                <?php endif; ?>
            }

            // Function to control starting frequency field visibility and validation
            function toggleStartingFrequencyField(show, required = false) {
                if (show) {
                    $('#starting_freq_section').show();
                    $('#starting_freq_combined_select').attr('required', required);
                } else {
                    $('#starting_freq_section').hide();
                    $('#starting_freq_combined_select').attr('required', false);
                    $('#starting_freq_combined_select').val(''); // Clear selection when hidden
                }
            }

            // Initialize on page load
            initializeFrequencySections();

            // Function to show/hide validation frequency fields based on unit's validation scheduling logic
            function toggleValidationFrequencyFields(validationLogic) {
                console.log('toggleValidationFrequencyFields called with:', validationLogic);
                if (validationLogic === 'fixed') {
                    console.log('Showing fields for Fixed Dates');
                    // Show frequency_type, single_freq_select, combined_freq_select and First Validation Date for Fixed Dates
                    $('#fixed_dates_validation_frequency').hide();
                    $('#validation_frequency').attr('required', false);
                    $('#first_validation_date').closest('.form-group').show();
                    $('#first_validation_date').attr('required', true);
                    
                    // Show dynamic frequency fields
                    $('#dynamic_dates_frequency_type').show();
                    $('#dynamic_dates_frequency_selections').show();
                    $('#frequency_type').attr('required', true);
                    
                    // Initialize the dynamic sections based on current frequency type selection
                    initializeFrequencySections();
                } else {
                    console.log('Showing fields for Dynamic Dates');
                    // Show only validation_frequency dropdown for Dynamic Dates, hide First Validation Date
                    $('#fixed_dates_validation_frequency').show();
                    $('#validation_frequency').attr('required', true);
                    $('#first_validation_date').closest('.form-group').hide();
                    $('#first_validation_date').attr('required', false);
                    
                    // Hide dynamic frequency fields
                    $('#dynamic_dates_frequency_type').hide();
                    $('#dynamic_dates_frequency_selections').hide();
                    $('#frequency_type').attr('required', false);
                    $('#single_freq_select').attr('required', false);
                    $('#combined_freq_select').attr('required', false);

                    // Hide starting frequency field for dynamic dates
                    toggleStartingFrequencyField(false);
                }
            }
            
            // Initialize validation frequency fields on page load
            toggleValidationFrequencyFields(currentUnitValidationLogic);
            
            // Handle unit selection change in add mode
            $('#unit_id').on('change', function() {
                var selectedUnitId = $(this).val();
                console.log('Unit selected:', selectedUnitId);
                if (selectedUnitId) {
                    // Fetch unit's validation scheduling logic via AJAX
                    $.ajax({
                        url: 'core/data/get/getunitvalidationlogic.php',
                        method: 'GET',
                        data: { unit_id: selectedUnitId },
                        dataType: 'json',
                        success: function(response) {
                            console.log('AJAX response:', response);
                            if (response && response.validation_scheduling_logic) {
                                currentUnitValidationLogic = response.validation_scheduling_logic;
                                console.log('Updated validation logic to:', currentUnitValidationLogic);
                                toggleValidationFrequencyFields(currentUnitValidationLogic);
                            }
                        },
                        error: function(xhr, status, error) {
                            console.log('AJAX error:', error);
                            // Default to dynamic if error
                            currentUnitValidationLogic = 'dynamic';
                            toggleValidationFrequencyFields(currentUnitValidationLogic);
                        }
                    });
                }
            });

            // Function to submit equipment data after successful authentication
            function submitEquipmentData(mode, pen_val = 0, pen_rt = 0) {
                $('#pleasewaitmodal').modal('show');
                
                // Prepare data object based on mode
                let data = {
                    equipment_code: $("#equipment_code").val(),
                    unit_id: $("#unit_id").val(),
                    department_id: $("#department_id").val(),
                    equipment_category: $("#equipment_category").val(),
                    equipment_addition_date: convertDateFormat($("#equipment_addition_date").val()),
                    validation_frequency: $("#validation_frequency").val(),
                    first_validation_date: convertDateFormat($("#first_validation_date").val()),
                    frequency_type: $("#frequency_type").val(),
                    starting_frequency: $("#frequency_type").val() === 'single' ? $("#single_freq_select").val() : $("#starting_freq_combined_select").val(),
                    validation_frequencies: $("#frequency_type").val() === 'single' ? $("#single_freq_select").val() : $("#combined_freq_select").val(),
                    equipment_status: $("#equipment_status").val(),
                    area_served: $("#area_served").val(),
                    section: $("#section").val(),
                    design_acph: $("#design_acph").val(),
                    area_classification: $("#area_classification").val(),
                    area_classification_in_operation: $("#area_classification_in_operation").val(),
                    equipment_type: $("#equipment_type").val(),
                    design_cfm: $("#design_cfm").val(),
                    filteration_fresh_air: $("#filteration_fresh_air").val(),
                    filteration_pre_filter: $("#filteration_pre_filter").val(),
                    filteration_intermediate: $("#filteration_intermediate").val(),
                    filteration_final_filter_plenum: $("#filteration_final_filter_plenum").val(),
                    filteration_exhaust_pre_filter: $("#filteration_exhaust_pre_filter").val(),
                    filteration_exhaust_final_filter: $("#filteration_exhaust_final_filter").val(),
                    filteration_terminal_filter: $("#filteration_terminal_filter").val(),
                    filteration_terminal_filter_on_riser: $("#filteration_terminal_filter_on_riser").val(),
                    filteration_bibo_filter: $("#filteration_bibo_filter").val(),
                    filteration_relief_filter: $("#filteration_relief_filter").val(),
                    filteration_reativation_filter: $("#filteration_reativation_filter").val(),
                    mode: mode
                };
                
                // Add equipment_id for modify mode
                if (mode === 'modify') {
                    data.equipment_id = $("#equipment_id").val();
                    data.pen_val = pen_val;
                    data.pen_rt = pen_rt;
                }
                
                // Send AJAX request
                $.get("core/data/save/saveequipmentdetails.php", data, function(response, status) {
                    $('#pleasewaitmodal').modal('hide');
                    
                    if(response === "success") {
                        Swal.fire({
                            icon: 'success',
                            title: 'Success',
                            text: "The equipment record is successfully " + (mode === 'add' ? "added" : "modified") + "!"
                        }).then((result) => {
                            // Build redirect URL with search parameters if available
                            let redirectUrl = "searchequipments.php";
                            <?php if (isset($_GET['from_search']) && $_GET['from_search'] == '1'): ?>
                                const urlParams = new URLSearchParams();
                                <?php if (isset($_GET['unitid'])): ?>urlParams.set('unitid', '<?= htmlspecialchars($_GET['unitid'], ENT_QUOTES) ?>');<?php endif; ?>
                                <?php if (isset($_GET['dept_id'])): ?>urlParams.set('dept_id', '<?= htmlspecialchars($_GET['dept_id'], ENT_QUOTES) ?>');<?php endif; ?>
                                <?php if (isset($_GET['equipment_type'])): ?>urlParams.set('equipment_type', '<?= htmlspecialchars($_GET['equipment_type'], ENT_QUOTES) ?>');<?php endif; ?>
                                <?php if (isset($_GET['equipment_id'])): ?>urlParams.set('equipment_id', '<?= htmlspecialchars($_GET['equipment_id'], ENT_QUOTES) ?>');<?php endif; ?>
                                <?php if (isset($_GET['etv_mapping_filter'])): ?>urlParams.set('etv_mapping_filter', '<?= htmlspecialchars($_GET['etv_mapping_filter'], ENT_QUOTES) ?>');<?php endif; ?>
                                urlParams.set('restore_search', '1');
                                redirectUrl += '?' + urlParams.toString();
                            <?php endif; ?>
                            window.location = redirectUrl;
                        });
                    } else {
                        // Try to parse JSON error response
                        let errorMessage = 'Something went wrong. Please try again.';
                        let errorTitle = 'Error';
                        
                        try {
                            const errorData = JSON.parse(response);
                            if (errorData.error) {
                                errorMessage = errorData.error;
                                
                                // Set specific titles based on error type
                                if (errorMessage.includes('already exists')) {
                                    errorTitle = 'Duplicate Equipment Code';
                                } else if (errorMessage.includes('validation') || errorMessage.includes('required field')) {
                                    errorTitle = 'Validation Error';
                                } else if (errorMessage.includes('reference data') || errorMessage.includes('unit/department')) {
                                    errorTitle = 'Invalid Reference';
                                } else if (errorMessage.includes('too long')) {
                                    errorTitle = 'Input Too Long';
                                } else {
                                    errorTitle = 'Database Error';
                                }
                            }
                        } catch (e) {
                            // If not JSON, check if it's a simple string error
                            if (typeof response === 'string' && response !== 'failure') {
                                errorMessage = response;
                            }
                        }
                        
                        Swal.fire({
                            icon: 'error',
                            title: errorTitle,
                            text: errorMessage,
                            confirmButtonText: 'OK'
                        }).then((result) => {
                            // Don't redirect on error - let user fix the issue
                            $('#pleasewaitmodal').modal('hide');
                        });
                    }
                });
            }
            
            // Add Equipment button click handler
            $("#add_equipment").click(function(e) {
                e.preventDefault();

                var form = document.getElementById('formequipmentvalidation');

                if (form.checkValidity() === false) {
                    form.classList.add('was-validated');
                } else {
                    form.classList.add('was-validated');
                    
                    // Set success callback for e-signature modal
                    setSuccessCallback(function() {
                        submitEquipmentData('add');
                    });
                    
                    // Show e-signature modal
                    $('#enterPasswordRemark').modal('show');
                }

            });

            // Attach event handler for the modify equipment button
            // Using on() method for better compatibility with dynamically generated content
            $(document).on('click', '#modify_equipment', function(e) {
                    console.log("Modify Equipment button clicked");
                    // Prevent the default form submission
                    e.preventDefault();
                    e.stopPropagation();
                    
                    // Debug alert - commented out for production
                    // alert("Modify Equipment button clicked");
                    
                    var form = document.getElementById('formequipmentvalidation');
                    
                    if (form.checkValidity() === false) {  
                        form.classList.add('was-validated');  
                    } else {
                        form.classList.add('was-validated');  
                        
                        if ($("#equipment_status").val() === 'Inactive') {
                            // Check for pending tasks before showing e-signature modal
                            checkAndKillPendingTasks($("#equipment_id").val());
                        } else {
                            // Set success callback for e-signature modal
                            setSuccessCallback(function() {
                                submitEquipmentData('modify');
                            });
                            
                            // Show e-signature modal
                            $('#enterPasswordRemark').modal('show');
                        }
                    }
                    
                    // Return false to ensure the form doesn't submit
                    return false;
                });
            // Define the checkAndKillPendingTasks function within document.ready
            function checkAndKillPendingTasks(equipId) {
                
                $.ajax({
                    url: 'core/workflow/killpendingvalrt.php',
                    method: 'POST',
                    data: { action: 'get_pending_counts', equip_id: equipId },
                    dataType: 'json',
                    success: function(response) {
                        if (!response || typeof response !== 'object') {
                            Swal.fire('Error', 'Invalid response from server', 'error');
                            return;
                        }
                        
                        var pendingValidations = response.pending_validations;
                        var pendingRoutineTests = response.pending_routine_tests;
                        var totalPending = response.total_pending_tests;
                        
                        if (totalPending > 0) {
                            // Set success callback for e-signature modal with pending tasks info
                            setSuccessCallback(function() {
                                submitEquipmentData('modify', response.pending_validations, response.pending_routine_tests);
                            });
                            
                            // Show e-signature modal
                            $('#enterPasswordRemark').modal('show');
                        } else {
                            // Set success callback for e-signature modal with no pending tasks
                            setSuccessCallback(function() {
                                submitEquipmentData('modify', 0, 0);
                            });
                            
                            // Show e-signature modal
                            $('#enterPasswordRemark').modal('show');
                        }
                    },
                    error: function(xhr, status, error) {
                        Swal.fire('Error', 'Failed to fetch pending counts: ' + error, 'error');
                    }
                });
            }
            
            // Define the saveEquipmentDetails function within document.ready
            function saveEquipmentDetails(pen_val, pen_rt) {
                // Set success callback for e-signature modal
                setSuccessCallback(function() {
                    submitEquipmentData('modify', pen_val, pen_rt);
                });
                
                // Show e-signature modal
                $('#enterPasswordRemark').modal('show');
            }
        });
    </script>








    
    <link rel="stylesheet" href="assets/css/modern-manage-ui.css">

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
                            Equipment Details
                        </h3>
                        <nav aria-label="breadcrumb">
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item active" aria-current="page"><span><a class='btn btn-gradient-info btn-sm btn-rounded'
                                            href="searchequipments.php<?php
                                                // Build back navigation URL with search parameters
                                                if (isset($_GET['from_search']) && $_GET['from_search'] == '1') {
                                                    $back_params = [];
                                                    if (isset($_GET['unitid'])) $back_params['unitid'] = $_GET['unitid'];
                                                    if (isset($_GET['dept_id'])) $back_params['dept_id'] = $_GET['dept_id'];
                                                    if (isset($_GET['equipment_type'])) $back_params['equipment_type'] = $_GET['equipment_type'];
                                                    if (isset($_GET['equipment_id'])) $back_params['equipment_id'] = $_GET['equipment_id'];
                                                    if (isset($_GET['etv_mapping_filter'])) $back_params['etv_mapping_filter'] = $_GET['etv_mapping_filter'];
                                                    $back_params['restore_search'] = '1';
                                                    echo '?' . http_build_query($back_params);
                                                }
                                            ?>">
                                            << Back</a> </span>
                                </li>
                            </ul>
                        </nav>
                    </div>



                    <div class="row">

                        <div class="col-lg-12 grid-margin stretch-card">
                            <div class="card">
                                <div class="card-body">
                                    <h4 class="card-title">Equipment Details</h4>
                                    <p class="card-description">
                                    </p>











                                    <form id="formequipmentvalidation" class="needs-validation" novalidate>

                                        <?php
                                        if ($_GET['m'] != 'a') {

                                            echo '<input type="hidden" id="equipment_id" name="equipment_id" value="' . $_GET['equip_id'] . '" />';
                                        }

                                        ?>



                                        <div class="form-row">

                                            <div class="form-group  col-md-4">
                                                <label for="exampleSelectGender">Unit</label>
                                                <select class="form-control" id="unit_id" name="unit_id" required>
                                                    <option value="">Select Unit</option>

                                                    <?php

                                                    try {
                                                        if ($_SESSION['is_super_admin'] == "Yes") {
                                                            $results = DB::query("SELECT unit_id, unit_name FROM units ORDER BY unit_name");

                                                            $output = "";
                                                            if (!empty($results)) {
                                                                foreach ($results as $row) {
                                                                    $selected = '';
                                                                    if (isset($equipment_details) && $equipment_details['unit_id'] == $row['unit_id']) {
                                                                        $selected = 'selected';
                                                                    }
                                                                    $output .= "<option value='" . htmlspecialchars($row['unit_id'], ENT_QUOTES) . "' " . $selected . ">" . htmlspecialchars($row['unit_name'], ENT_QUOTES) . "</option>";
                                                                }
                                                                echo $output;
                                                            }
                                                        } else {
                                                            $unit_id = intval($_SESSION['unit_id']);
                                                            $unit_name = DB::queryFirstField("SELECT unit_name FROM units WHERE unit_id = %i and unit_status='Active'", $unit_id);
                                                            
                                                            if ($unit_name) {
                                                                echo "<option value='" . htmlspecialchars($unit_id, ENT_QUOTES) . "'>" . htmlspecialchars($unit_name, ENT_QUOTES) . "</option>";
                                                            }
                                                        }
                                                    } catch (Exception $e) {
                                                        error_log("Database error in manageequipmentdetails.php unit query: " . $e->getMessage());
                                                        echo "<option value=''>Error loading units</option>";
                                                    }






                                                    ?>
                                                </select>
                                                <div class="invalid-feedback">
                                                    Please select a unit.
                                                </div>
                                            </div>

                                            <div class="form-group  col-md-4">
                                                <label for="exampleSelectGender">Equipment Code</label>
                                                <input type="text" class="form-control" value='<?php echo (($_GET['m'] != 'a') ?  $equipment_details['equipment_code'] : ''); ?>' name='equipment_code' id='equipment_code' required />
                                                <div class="invalid-feedback">
                                                    Please provide a valid equipment code.
                                                </div>
                                            </div>

                                            <div class="form-group  col-md-4">
                                                <label for="exampleSelectGender">Department</label>
                                                <select class="form-control" id="department_id" name="department_id" required>
                                                    <option value="">Select Department</option>

                                                    <?php


                                                    try {
                                                        $results = DB::query("SELECT department_id, department_name FROM departments WHERE department_status = %s", 'Active');
                                                        $output = "";

                                                        if (!empty($results)) {
                                                            foreach ($results as $row) {
                                                                $selected = ($_GET['m'] != 'a' && isset($equipment_details['department_id']) && $equipment_details['department_id'] == $row['department_id']) ? 'selected' : '';
                                                                $output .= "<option value='" . intval($row['department_id']) . "' " . $selected . ">" . htmlspecialchars($row['department_name'], ENT_QUOTES, 'UTF-8') . "</option>";
                                                            }
                                                            echo $output;
                                                        }
                                                    } catch (Exception $e) {
                                                        error_log("Error loading departments: " . $e->getMessage());
                                                        echo "<option value=''>Error loading departments</option>";
                                                    }








                                                    ?>
                                                </select>
                                                <div class="invalid-feedback">
                                                    Please select a department.
                                                </div>
                                            </div>








                                        </div>


                                        <div class="form-row">


                                            <div class="form-group  col-md-4">
                                                <label for="exampleSelectGender">Category</label>
                                                <select class="form-control" id="equipment_category" name="equipment_category" required>
                                                    <option value="">Select Category</option>
                                                    <?php

                                                    $categories = [
                                                        'AHU' => 'AHU',
                                                        'AHD' => 'AHU Cum Dehumidifier',
                                                        'VU' => 'Ventilation Unit',
                                                        'VS' => 'Ventilation System'
                                                    ];
                                                    
                                                    foreach ($categories as $value => $label) {
                                                        $selected = ($_GET['m'] != 'a' && isset($equipment_details['equipment_category']) && trim($equipment_details['equipment_category']) == $value) ? 'selected' : '';
                                                        echo "<option value='" . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . "' " . $selected . ">" . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . "</option>";
                                                    }



                                                    ?>
                                                </select>
                                                <div class="invalid-feedback">
                                                    Please select an equipment category.
                                                </div>
                                            </div>

                                            <div class="form-group  col-md-4">
                                                <label for="exampleSelectGender">Equipment Addition Date</label>
                                                <input type="text" class="form-control" id="equipment_addition_date" name="equipment_addition_date" value='<?php echo ($_GET['m'] != 'a' && isset($equipment_details['equipment_addition_date']) && !empty($equipment_details['equipment_addition_date'])) ? htmlspecialchars(date('d.m.Y', strtotime($equipment_details['equipment_addition_date'])), ENT_QUOTES, 'UTF-8') : ''; ?>' required>
                                                <div class="invalid-feedback">
                                                    Please provide an equipment addition date.
                                                </div>
                                            </div>

                                            <div class="form-group  col-md-4">
                                                <label for="exampleSelectGender">Status</label>
                                                <select class="form-control" id="equipment_status" name="equipment_status" required>


                                                    <?php
                                                    echo "<option value='Active'" . ($_GET['m'] != 'a' && ($equipment_details['equipment_status'] == 'Active') ? "selected" : "") . ">Active</option>";
                                                    echo "<option value='Inactive'" . ($_GET['m'] != 'a' && ($equipment_details['equipment_status'] == 'Inactive') ? "selected" : "") . ">Inactive</option>";





                                                    ?>
                                                </select>
                                                <div class="invalid-feedback">
                                                    Please select an equipment status.
                                                </div>
                                            </div>

                                        </div>

                                        <div class="form-row">

                                            <div class="form-group  col-md-4">
                                                <label for="exampleSelectGender">First Validation Date <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" id="first_validation_date" name="first_validation_date" value='<?php echo ($_GET['m'] != 'a' && !empty($equipment_details['first_validation_date'])) ? date('d.m.Y', strtotime($equipment_details['first_validation_date'])) : ''; ?>' required>
                                                <div class="invalid-feedback">
                                                    Please provide a first validation date.
                                                </div>
                                            </div>

                                            <!-- Fixed Dates: Show only validation_frequency dropdown -->
                                            <div class="form-group col-md-4" id="fixed_dates_validation_frequency" style="display: none;">
                                                <label for="validation_frequency">Validation Frequency <span class="text-danger">*</span></label>
                                                <select class="form-control" id="validation_frequency" name="validation_frequency">
                                                    <option value="">Select Frequency</option>
                                                    <?php
                                                    $frequencies = [
                                                        'Y' => 'Yearly',
                                                        '2Y' => 'Bi-Yearly'
                                                    ];
                                                    
                                                    foreach ($frequencies as $value => $label) {
                                                        $selected = ($_GET['m'] != 'a' && isset($equipment_details['validation_frequency']) && trim($equipment_details['validation_frequency']) == $value) ? 'selected' : '';
                                                        echo "<option value='" . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . "' " . $selected . ">" . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . "</option>";
                                                    }
                                                    ?>
                                                </select>
                                                <div class="invalid-feedback">
                                                    Please select a validation frequency.
                                                </div>
                                            </div>
                                            
                                            <!-- Dynamic Dates: Show frequency_type -->
                                            <div class="form-group col-md-4" id="dynamic_dates_frequency_type" style="display: none;">
                                                <label for="frequency_type">Frequency Type <span class="text-danger">*</span></label>
                                                <select class="form-control" id="frequency_type" name="frequency_type">
                                                    <?php
                                                    // Determine frequency type based on data
                                                    $frequency_type = 'single'; // default
                                                    if ($_GET['m'] != 'a' && isset($equipment_details['validation_frequencies']) && !empty($equipment_details['validation_frequencies'])) {
                                                        $frequency_type = 'dual';
                                                    }
                                                    ?>
                                                    <option value="single" <?php echo ($frequency_type == 'single') ? 'selected' : ''; ?>>Single</option>
                                                    <option value="dual" <?php echo ($frequency_type == 'dual') ? 'selected' : ''; ?>>Combined</option>
                                                </select>
                                                <div class="invalid-feedback">
                                                    Please select a frequency type.
                                                </div>
                                            </div>

                                            <!-- Dynamic Dates: Show single/combined frequency select -->
                                            <div class="form-group col-md-4" id="dynamic_dates_frequency_selections" style="display: none;">
                                                <div id="single_freq_section">
                                                    <label for="single_freq_select">Validation Frequency <span class="text-danger">*</span></label>
                                                    <select class="form-control" id="single_freq_select" name="single_freq_select">
                                                        <option value="">Select Frequency</option>
                                                        <option value="6M" <?php echo ($_GET['m'] != 'a' && isset($equipment_details['starting_frequency']) && $equipment_details['starting_frequency'] == '6M') ? 'selected' : ''; ?>>Six Monthly</option>
                                                        <option value="Y" <?php echo ($_GET['m'] != 'a' && isset($equipment_details['starting_frequency']) && $equipment_details['starting_frequency'] == 'Y') ? 'selected' : ''; ?>>Yearly</option>
                                                        <option value="2Y" <?php echo ($_GET['m'] != 'a' && isset($equipment_details['starting_frequency']) && $equipment_details['starting_frequency'] == '2Y') ? 'selected' : ''; ?>>Bi-Yearly</option>
                                                    </select>
                                                    <div class="invalid-feedback">
                                                        Please select a validation frequency.
                                                    </div>
                                                </div>
                                                <div id="dual_freq_section" style="display: none;">
                                                    <label for="combined_freq_select">Frequency Combination <span class="text-danger">*</span></label>
                                                    <select class="form-control" id="combined_freq_select" name="combined_freq_select">
                                                        <option value="">Select Combination</option>
                                                        <option value="6M,Y" <?php echo ($_GET['m'] != 'a' && isset($equipment_details['validation_frequencies']) && $equipment_details['validation_frequencies'] == '6M,Y') ? 'selected' : ''; ?>>Six Monthly + Yearly</option>
                                                        <option value="Y,2Y" <?php echo ($_GET['m'] != 'a' && isset($equipment_details['validation_frequencies']) && $equipment_details['validation_frequencies'] == 'Y,2Y') ? 'selected' : ''; ?>>Yearly + Bi-Yearly</option>
                                                        <option value="6M,Y,2Y" <?php echo ($_GET['m'] != 'a' && isset($equipment_details['validation_frequencies']) && $equipment_details['validation_frequencies'] == '6M,Y,2Y') ? 'selected' : ''; ?>>Six Monthly + Yearly + Bi-Yearly</option>
                                                    </select>
                                                    <div class="invalid-feedback">
                                                        Please select a frequency combination.
                                                    </div>
                                                </div>
                                                <br/>
                                                <div id="starting_freq_section" style="display: none;">
                                                    <label for="starting_freq_combined_select">Starting Frequency <span class="text-danger">*</span></label>
                                                    <select class="form-control" id="starting_freq_combined_select" name="starting_freq_combined_select">
                                                        <option value="">Select Starting Frequency</option>
                                                    </select>
                                                    <div class="invalid-feedback">
                                                        Please select a starting frequency.
                                                    </div>
                                                </div>
                                            </div>

                                        </div>
                                        <br />
                                        <h4 class="card-title">Additional Parameters</h4>
                                        <p class="card-description">
                                        </p>


                                        <div class="form-row">

                                            <div class="form-group  col-md-4">
                                                <label for="exampleSelectGender">Area Served</label>
                                                <input type="text" class="form-control" value="<?php echo (($_GET['m'] != 'a') ?  $equipment_details['area_served'] : ''); ?>" id='area_served' name='area_served' required />
                                                <div class="invalid-feedback">
                                                    Please provide area served.
                                                </div>
                                            </div>

                                            <div class="form-group  col-md-4">
                                                <label for="exampleSelectGender">Section</label>
                                                <input type="text" class="form-control" value='<?php echo (($_GET['m'] != 'a') ?  $equipment_details['section'] : ''); ?>' id='section' name='section' required />
                                                <div class="invalid-feedback">
                                                    Please provide section.
                                                </div>
                                            </div>
                                            <div class="form-group  col-md-4">
                                                <label for="exampleSelectGender">Design ACPH</label>
                                                <input type="text" class="form-control" value='<?php echo (($_GET['m'] != 'a') ?  $equipment_details['design_acph'] : ''); ?>' id='design_acph' name='design_acph' required />
                                                <div class="invalid-feedback">
                                                    Please provide design ACPH.
                                                </div>
                                            </div>

                                        </div>

                                        <div class="form-row">

                                            <div class="form-group col-md-4">
                                                <label for="exampleSelectGender">Area Classification (At Rest)</label>
                                                <input type="text" class="form-control" value='<?php echo (($_GET['m'] != 'a') ?  $equipment_details['area_classification'] : ''); ?>' id='area_classification' name='area_classification' required />
                                                <div class="invalid-feedback">
                                                    Please provide area classification (at rest).
                                                </div>
                                            </div>

                                             <div class="form-group col-md-4">
                                                <label for="exampleSelectGender">Area Classification (In Operation)</label>
                                                <input type="text" class="form-control" value='<?php echo (($_GET['m'] != 'a') ?  $equipment_details['area_classification_in_operation'] : ''); ?>' id='area_classification_in_operation' name='area_classification_in_operation' required />
                                                <div class="invalid-feedback">
                                                    Please provide area classification (in operation).
                                                </div>
                                            </div>



                                            <div class="form-group  col-md-4">
                                                <label for="exampleSelectGender">Equipment Type</label>
                                                <input type="text" class="form-control" value='<?php echo (($_GET['m'] != 'a') ?  $equipment_details['equipment_type'] : ''); ?>' name='equipment_type' id='equipment_type' required />
                                                <div class="invalid-feedback">
                                                    Please provide equipment type.
                                                </div>
                                            </div>

                                           

                                        </div>




                                        <div class="form-row">
                                             <div class="form-group  col-md-4">
                                                <label for="exampleSelectGender">Design CFM</label>
                                                <input type="text" class="form-control" value='<?php echo (($_GET['m'] != 'a') ?  $equipment_details['design_cfm'] : ''); ?>' name='design_cfm' id='design_cfm' required />
                                                <div class="invalid-feedback">
                                                    Please provide design CFM.
                                                </div>
                                            </div>


                                            <div class="form-group  col-md-4">
                                                <label for="exampleSelectGender">Filteration Fresh Air</label>
                                                <input type="text" class="form-control" value='<?php echo (($_GET['m'] != 'a') ?  $equipment_details['filteration_fresh_air'] : ''); ?>' name='filteration_fresh_air' id='filteration_fresh_air' required />
                                                <div class="invalid-feedback">
                                                    Please provide filteration fresh air details.
                                                </div>
                                            </div>
                                            <div class="form-group  col-md-4">
                                                <label for="exampleSelectGender">Filtration Pre filter</label>
                                                <input type="text" class="form-control" value='<?php echo (($_GET['m'] != 'a') ? $equipment_details['filteration_pre_filter'] : ''); ?>' name='filteration_pre_filter' id='filteration_pre_filter' required />
                                                <div class="invalid-feedback">
                                                    Please provide filteration pre filter details.
                                                </div>
                                            </div>
                                            


                                        </div>

                                        <div class="form-row">
                                            <div class="form-group  col-md-4">
                                                <label for="exampleSelectGender">Filteration Intermediate</label>
                                                <input type="text" class="form-control" value='<?php echo (($_GET['m'] != 'a') ?  $equipment_details['filteration_intermediate'] : ''); ?>' name='filteration_intermediate' id='filteration_intermediate' required />
                                                <div class="invalid-feedback">
                                                    Please provide filteration intermediate details.
                                                </div>
                                            </div>


                                            <div class="form-group  col-md-4">
                                                <label for="exampleSelectGender">Filteration Final Filter Plenum</label>
                                                <input type="text" class="form-control" value='<?php echo (($_GET['m'] != 'a') ?  $equipment_details['filteration_final_filter_plenum'] : ''); ?>' name='filteration_final_filter_plenum' id='filteration_final_filter_plenum' required />
                                                <div class="invalid-feedback">
                                                    Please provide filteration final filter plenum details.
                                                </div>
                                            </div>
                                            <div class="form-group  col-md-4">
                                                <label for="exampleSelectGender">Filteration Exhaust Pre Filter</label>
                                                <input type="text" class="form-control" value='<?php echo (($_GET['m'] != 'a') ? $equipment_details['filteration_exhaust_pre_filter'] : ''); ?>' name='filteration_exhaust_pre_filter' id='filteration_exhaust_pre_filter' required />
                                                <div class="invalid-feedback">
                                                    Please provide filteration exhaust pre filter details.
                                                </div>
                                            </div>

                                        </div>


                                        <div class="form-row">
                                                                                        <div class="form-group  col-md-4">
                                                <label for="exampleSelectGender">Filteration Exhaust Final Filter</label>
                                                <input type="text" class="form-control" value='<?php echo (($_GET['m'] != 'a') ? $equipment_details['filteration_exhaust_final_filter'] : ''); ?>' name='filteration_exhaust_final_filter' id='filteration_exhaust_final_filter' required />
                                                <div class="invalid-feedback">
                                                    Please provide filteration exhaust final filter details.
                                                </div>
                                            </div>

                                            <div class="form-group  col-md-4">
                                                <label for="exampleSelectGender">Filteration Terminal Filter</label>
                                                <input type="text" class="form-control" value='<?php echo (($_GET['m'] != 'a') ?  $equipment_details['filteration_terminal_filter'] : ''); ?>' name='filteration_terminal_filter' id='filteration_terminal_filter' required />
                                                <div class="invalid-feedback">
                                                    Please provide filteration terminal filter details.
                                                </div>
                                            </div>

                                            <div class="form-group  col-md-4">
                                                <label for="exampleSelectGender">Filteration Terminal Filter On Riser</label>
                                                <input type="text" class="form-control" value='<?php echo (($_GET['m'] != 'a') ?  $equipment_details['filteration_terminal_filter_on_riser'] : ''); ?>' name='filteration_terminal_filter_on_riser' id='filteration_terminal_filter_on_riser' required />
                                                <div class="invalid-feedback">
                                                    Please provide filteration terminal filter on riser details.
                                                </div>
                                            </div>
                                           

                                        </div>

                                        <div class="form-row">
                                             <div class="form-group  col-md-4">
                                                <label for="exampleSelectGender">Fliteration BIBO Filter</label>
                                                <input type="text" class="form-control" value='<?php echo (($_GET['m'] != 'a') ?  $equipment_details['filteration_bibo_filter'] : ''); ?>' name='filteration_bibo_filter' id='filteration_bibo_filter' required />
                                                <div class="invalid-feedback">
                                                    Please provide filteration BIBO filter details.
                                                </div>
                                            </div>

                                            <div class="form-group  col-md-4">
                                                <label for="exampleSelectGender">Filteration Relief Filter</label>
                                                <input type="text" class="form-control" value='<?php echo (($_GET['m'] != 'a') ?  $equipment_details['filteration_relief_filter'] : ''); ?>' name='filteration_relief_filter' id='filteration_relief_filter' required />
                                                <div class="invalid-feedback">
                                                    Please provide filteration relief filter details.
                                                </div>
                                            </div>
                                            <div class="form-group  col-md-4">
                                                <label for="exampleSelectGender">Filtraion Reactivation filter</label>
                                                <input type="text" class="form-control" value='<?php echo (($_GET['m'] != 'a') ?  $equipment_details['filteration_reativation_filter'] : ''); ?>' name='filteration_reativation_filter' id='filteration_reativation_filter' required />
                                                <div class="invalid-feedback">
                                                    Please provide filteration reactivation filter details.
                                                </div>
                                            </div>
                                        </div>



                                        <div class="d-flex justify-content-center">



                                            <?php

                                            if ($_GET['m'] == 'm') {
                                            ?>
                                                <button type="button" id="modify_equipment" class='btn btn-gradient-primary mr-2'>Modify Equipment</button>
                                            <?php
                                            } else if ($_GET['m'] == 'a') {
                                            ?>
                                                <button type="button" id="add_equipment" class='btn btn-gradient-primary mr-2'>Add Equipment</button>
                                            <?php
                                            }


                                            ?>


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
    <?php include "assets/inc/_esignmodal.php"; ?>
</body>

</html>