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
        $equipment_details = DB::queryFirstRow("SELECT equipment_id,equipment_code,unit_id,department_id,equipment_category,validation_frequency,area_served,section,design_acph,area_classification,area_classification_in_operation,equipment_type,design_cfm,filteration_fresh_air,filteration_pre_filter,filteration_intermediate,filteration_final_filter_plenum,filteration_exhaust_pre_filter,filteration_exhaust_final_filter,filteration_terminal_filter,filteration_terminal_filter_on_riser,filteration_bibo_filter,filteration_relief_filter,filteration_reativation_filter,equipment_status,equipment_addition_date 
            FROM equipments WHERE equipment_id = %d", intval($_GET['equip_id']));
            
        if (!$equipment_details) {
            header('HTTP/1.1 404 Not Found');
            exit('Equipment not found');
        }
    } catch (Exception $e) {
        error_log("Database error in manageequipmentdetails.php: " . $e->getMessage());
        header('HTTP/1.1 500 Internal Server Error');
        exit('Database error occurred');
    }
}



?>















<!DOCTYPE html>
<html lang="en">

<head>
    <?php include_once "assets/inc/_header.php"; ?>
    <script>
        $(document).ready(function() {

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
                            window.location = "searchequipments.php";
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








    
    <style>
        /* Custom CSS to show red borders for invalid select dropdowns with higher specificity */
        .needs-validation.was-validated .form-control:invalid,
        .was-validated .form-control:invalid {
            border-color: #dc3545 !important;
            box-shadow: inset 0 1px 1px rgba(0, 0, 0, 0.075), 0 0 0 3px rgba(220, 53, 69, 0.1) !important;
        }
        
        .needs-validation.was-validated .form-control:invalid:focus,
        .was-validated .form-control:invalid:focus {
            border-color: #dc3545 !important;
            box-shadow: inset 0 1px 1px rgba(0, 0, 0, 0.075), 0 0 0 0.2rem rgba(220, 53, 69, 0.25) !important;
        }
        
        /* Specific styling for select dropdowns */
        .needs-validation.was-validated select.form-control:invalid,
        .was-validated select.form-control:invalid {
            border: 1px solid #dc3545 !important;
            border-color: #dc3545 !important;
            background-color: #fff !important;
        }
        
        .needs-validation.was-validated select.form-control:invalid:focus,
        .was-validated select.form-control:invalid:focus {
            border-color: #dc3545 !important;
            box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25) !important;
            outline: 0 !important;
        }

        /* Additional specific selectors for different dropdown IDs */
        .was-validated #unit_id:invalid,
        .was-validated #department_id:invalid,
        .was-validated #equipment_category:invalid,
        .was-validated #validation_frequency:invalid,
        .was-validated #equipment_status:invalid {
            border: 1px solid #dc3545 !important;
            border-color: #dc3545 !important;
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
                            Equipment Details
                        </h3>
                        <nav aria-label="breadcrumb">
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item active" aria-current="page"><span><a class='btn btn-gradient-info btn-sm btn-rounded'
                                            href="searchequipments.php">
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
                                                <label for="exampleSelectGender">Equipment Code</label>
                                                <input type="text" class="form-control" value='<?php echo (($_GET['m'] != 'a') ?  $equipment_details['equipment_code'] : ''); ?>' name='equipment_code' id='equipment_code' required />
                                                <div class="invalid-feedback">
                                                    Please provide a valid equipment code.
                                                </div>
                                            </div>


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
                                                            $unit_name = DB::queryFirstField("SELECT unit_name FROM units WHERE unit_id = %i", $unit_id);
                                                            
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
                                                <label for="exampleSelectGender">Validation Frequency</label>
                                                <select class="form-control" id="validation_frequency" name="validation_frequency" required>
                                                    <option value="">Select Frequency</option>
                                                    <?php

                                                    $frequencies = [
                                                        'Q' => 'Quarterly',
                                                        'H' => 'Half yearly',
                                                        'Y' => 'Yearly',
                                                        '2Y' => 'Biyearly'
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
                                        </div>


                                        <div class="form-row">

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