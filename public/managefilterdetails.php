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
    if (!isset($_GET['filter_id']) || !is_numeric($_GET['filter_id'])) {
        header('HTTP/1.1 400 Bad Request');
        exit('Invalid filter ID');
    }
}

// Generate CSRF token if not already set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (isset($_GET['m']) && $_GET['m'] != 'a') {
    try {
        $filter_details = DB::queryFirstRow("SELECT filter_id, unit_id, filter_code, filter_name, filter_size, filter_type_id, manufacturer, specifications, installation_date, planned_due_date, actual_replacement_date, status, creation_datetime, created_by 
            FROM filters WHERE filter_id = %d", intval($_GET['filter_id']));
            
        if (!$filter_details) {
            header('HTTP/1.1 404 Not Found');
            exit('Filter not found');
        }
    } catch (Exception $e) {
        error_log("Database error in managefilterdetails.php: " . $e->getMessage());
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

            $("#installation_date").datepicker({
                dateFormat: 'dd.mm.yy',
                changeMonth: true,
                beforeShow: function(input, inst) {
                    // Disable manual input by preventing focus on the input field
                    setTimeout(function() {
                        $(input).prop('readonly', true);
                    }, 0);
                }
            });

            // Add datepicker for planned due date
            $("#planned_due_date").datepicker({
                dateFormat: 'dd.mm.yy',
                changeMonth: true,
                beforeShow: function(input, inst) {
                    // Disable manual input by preventing focus on the input field
                    setTimeout(function() {
                        $(input).prop('readonly', true);
                    }, 0);
                }
            });

            // Add datepicker for actual replacement date
            $("#actual_replacement_date").datepicker({
                dateFormat: 'dd.mm.yy',
                changeMonth: true,
                beforeShow: function(input, inst) {
                    // Disable manual input by preventing focus on the input field
                    setTimeout(function() {
                        $(input).prop('readonly', true);
                    }, 0);
                }
            });

            // Function to submit filter data after successful authentication
            function submitFilterData(mode) {
                $('#pleasewaitmodal').modal('show');
                
                // Get and log date values for debugging
                var installDate = $("#installation_date").val();
                var plannedDate = $("#planned_due_date").val();
                var actualDate = $("#actual_replacement_date").val();
                
                console.log('Installation date value:', installDate);
                console.log('Planned due date value:', plannedDate);
                console.log('Actual replacement date value:', actualDate);
                
                // Prepare data object based on mode
                let data = {
                    unit_id: $("#unit_id").val(),
                    filter_code: $("#filter_code").val(),
                    filter_name: $("#filter_name").val(),
                    filter_size: $("#filter_size").val(),
                    filter_type_id: $("#filter_type_id").val(),
                    manufacturer: $("#manufacturer").val(),
                    specifications: $("#specifications").val(),
                    installation_date: convertDateFormat(installDate),
                    planned_due_date: plannedDate ? convertDateFormat(plannedDate) : '',
                    actual_replacement_date: actualDate ? convertDateFormat(actualDate) : '',
                    status: $("#status").val(),
                    mode: mode
                };
                
                // Add filter_id for modify mode
                if (mode === 'modify') {
                    data.filter_id = $("#filter_id").val();
                }
                
                // Send AJAX request
                $.get("core/data/save/savefilterdetails.php", data, function(response, status) {
                    $('#pleasewaitmodal').modal('hide');
                    
                    if(response === "success") {
                        Swal.fire({
                            icon: 'success',
                            title: 'Success',
                            text: "The filter record is successfully " + (mode === 'add' ? "added" : "modified") + "!"
                        }).then((result) => {
                            // Build redirect URL with search parameters if available
                            let redirectUrl = "searchfilters.php";
                            <?php if (isset($_GET['from_search']) && $_GET['from_search'] == '1'): ?>
                              const urlParams = new URLSearchParams();
                              <?php if (isset($_GET['unitid'])): ?>urlParams.set('unitid', '<?= htmlspecialchars($_GET['unitid'], ENT_QUOTES) ?>');<?php endif; ?>
                              <?php if (isset($_GET['filter_type'])): ?>urlParams.set('filter_type', '<?= htmlspecialchars($_GET['filter_type'], ENT_QUOTES) ?>');<?php endif; ?>
                              <?php if (isset($_GET['search_filter_id'])): ?>urlParams.set('filter_id', '<?= htmlspecialchars($_GET['search_filter_id'], ENT_QUOTES) ?>');<?php endif; ?>
                              <?php if (isset($_GET['status_filter'])): ?>urlParams.set('status_filter', '<?= htmlspecialchars($_GET['status_filter'], ENT_QUOTES) ?>');<?php endif; ?>
                              <?php if (isset($_GET['manufacturer'])): ?>urlParams.set('manufacturer', '<?= htmlspecialchars($_GET['manufacturer'], ENT_QUOTES) ?>');<?php endif; ?>
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
                                    errorTitle = 'Duplicate Filter Code';
                                } else if (errorMessage.includes('validation') || errorMessage.includes('required field')) {
                                    errorTitle = 'Validation Error';
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
            
            // Add Filter button click handler
            $("#add_filter").click(function(e) {
                e.preventDefault();

                var form = document.getElementById('formfiltervalidation');

                if (form.checkValidity() === false) {
                    form.classList.add('was-validated');
                } else {
                    form.classList.add('was-validated');
                    
                    // Set success callback for e-signature modal
                    setSuccessCallback(function() {
                        submitFilterData('add');
                    });
                    
                    // Show e-signature modal
                    $('#enterPasswordRemark').modal('show');
                }

            });

            // Attach event handler for the modify filter button
            $(document).on('click', '#modify_filter', function(e) {
                    console.log("Modify Filter button clicked");
                    // Prevent the default form submission
                    e.preventDefault();
                    e.stopPropagation();
                    
                    var form = document.getElementById('formfiltervalidation');
                    
                    if (form.checkValidity() === false) {  
                        form.classList.add('was-validated');  
                    } else {
                        form.classList.add('was-validated');  
                        
                        // Set success callback for e-signature modal
                        setSuccessCallback(function() {
                            submitFilterData('modify');
                        });
                        
                        // Show e-signature modal
                        $('#enterPasswordRemark').modal('show');
                    }
                    
                    // Return false to ensure the form doesn't submit
                    return false;
                });
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
							 Filter Details
						</h3>
						<nav aria-label="breadcrumb">
							<ul class="breadcrumb">
								<li class="breadcrumb-item active" aria-current="page"><span><a class='btn btn-gradient-info btn-sm btn-rounded'
										href="searchvendors.php<?php
                                                // Build back navigation URL with search parameters
                                                if (isset($_GET['from_search']) && $_GET['from_search'] == '1') {
                                                    $back_params = [];
                                                    if (isset($_GET['unitid'])) $back_params['unitid'] = $_GET['unitid'];
                                                    if (isset($_GET['filter_type'])) $back_params['filter_type'] = $_GET['filter_type'];
                                                    if (isset($_GET['search_filter_id'])) $back_params['filter_id'] = $_GET['search_filter_id'];
                                                    if (isset($_GET['status_filter'])) $back_params['status_filter'] = $_GET['status_filter'];
                                                    if (isset($_GET['manufacturer'])) $back_params['manufacturer'] = $_GET['manufacturer'];
                                                    $back_params['restore_search'] = '1';
                                                    echo '?' . http_build_query($back_params);
                                                }
                                            ?>"><i class="mdi mdi-arrow-left"></i> Back</a> </span>
								</li>
							</ul>
						</nav>
					</div>



                                            
                    <div class="row">

                        <div class="col-lg-12 grid-margin stretch-card">
                            <div class="card">
                                <div class="card-body">
                                    <h4 class="card-title">Filter Details</h4>
                                    <p class="card-description">
                                    </p>

                                    <form id="formfiltervalidation" class="needs-validation" novalidate>

                                        <?php
                                        if ($_GET['m'] != 'a') {
                                            echo '<input type="hidden" id="filter_id" name="filter_id" value="' . $_GET['filter_id'] . '" />';
                                        }
                                        ?>

                                        <div class="form-row">

                                            <div class="form-group  col-md-4">
                                                <label for="unit_id">Unit <span class="text-danger">*</span></label>
                                                <select class="form-control" id="unit_id" name="unit_id" required>
                                                    <option value="">Select Unit</option>
                                                    <?php
                                                    try {
                                                        if ($_SESSION['is_super_admin'] == "Yes") {
                                                            $results = DB::query("SELECT unit_id, unit_name FROM units ORDER BY unit_name");
                                                            if (!empty($results)) {
                                                                foreach ($results as $row) {
                                                                    $selected = ($_GET['m'] != 'a' && isset($filter_details['unit_id']) && $filter_details['unit_id'] == $row['unit_id']) ? 'selected' : '';
                                                                    echo "<option value='" . htmlspecialchars($row['unit_id'], ENT_QUOTES, 'UTF-8') . "' " . $selected . ">" . htmlspecialchars($row['unit_name'], ENT_QUOTES, 'UTF-8') . "</option>";
                                                                }
                                                            }
                                                        } else {
                                                            $unit_id = intval($_SESSION['unit_id']);
                                                            $unit_name = DB::queryFirstField("SELECT unit_name FROM units WHERE unit_id = %i and unit_status='Active'", $unit_id);
                                                            if ($unit_name) {
                                                                echo "<option value='" . htmlspecialchars($unit_id, ENT_QUOTES) . "'>" . htmlspecialchars($unit_name, ENT_QUOTES) . "</option>";
                                                            }
                                                        }
                                                    } catch (Exception $e) {
                                                        error_log("Error loading units for filter: " . $e->getMessage());
                                                        echo "<option value=''>Error loading units</option>";
                                                    }
                                                    ?>
                                                </select>
                                                <div class="invalid-feedback">
                                                    Please select a unit.
                                                </div>
                                            </div>

                                            <div class="form-group  col-md-4">
                                                <label for="filter_code">Filter Code <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" value='<?php echo (($_GET['m'] != 'a') ?  $filter_details['filter_code'] : ''); ?>' name='filter_code' id='filter_code' required />
                                                <div class="invalid-feedback">
                                                    Please provide a valid filter code.
                                                </div>
                                            </div>

                                            <div class="form-group  col-md-4">
                                                <label for="filter_name">Filter Name</label>
                                                <input type="text" class="form-control" value='<?php echo (($_GET['m'] != 'a') ?  $filter_details['filter_name'] : ''); ?>' name='filter_name' id='filter_name' />
                                            </div>

                                            <div class="form-group  col-md-4">
                                                <label for="filter_size">Filter Size <span class="text-danger">*</span></label>
                                                <select class="form-control" id="filter_size" name="filter_size" required>
                                                    <option value="">Select Size</option>
                                                    <?php
                                                    $sizes = ['Standard', 'Large', 'Small', 'Custom'];
                                                    foreach ($sizes as $size) {
                                                        $selected = ($_GET['m'] != 'a' && isset($filter_details['filter_size']) && $filter_details['filter_size'] == $size) ? 'selected' : '';
                                                        echo "<option value='" . htmlspecialchars($size, ENT_QUOTES, 'UTF-8') . "' " . $selected . ">" . htmlspecialchars($size, ENT_QUOTES, 'UTF-8') . "</option>";
                                                    }
                                                    ?>
                                                </select>
                                                <div class="invalid-feedback">
                                                    Please select a filter size.
                                                </div>
                                            </div>

                                            <div class="form-group  col-md-4">
                                                <label for="filter_type_id">Filter Type <span class="text-danger">*</span></label>
                                                <select class="form-control" id="filter_type_id" name="filter_type_id" required>
                                                    <option value="">Select Type</option>
                                                    <?php
                                                    try {
                                                        $filter_groups = DB::query("SELECT filter_group_id, filter_group_name FROM filter_groups WHERE status = 'Active' ORDER BY filter_group_name");
                                                        if (!empty($filter_groups)) {
                                                            foreach ($filter_groups as $group) {
                                                                $selected = ($_GET['m'] != 'a' && isset($filter_details['filter_type_id']) && $filter_details['filter_type_id'] == $group['filter_group_id']) ? 'selected' : '';
                                                                echo "<option value='" . intval($group['filter_group_id']) . "' " . $selected . ">" . htmlspecialchars($group['filter_group_name'], ENT_QUOTES, 'UTF-8') . "</option>";
                                                            }
                                                        }
                                                    } catch (Exception $e) {
                                                        error_log("Error loading filter groups: " . $e->getMessage());
                                                        echo "<option value=''>Error loading filter types</option>";
                                                    }
                                                    ?>
                                                </select>
                                                <div class="invalid-feedback">
                                                    Please select a filter type.
                                                </div>
                                            </div>

                                            <div class="form-group  col-md-4">
                                                <label for="manufacturer">Manufacturer</label>
                                                <input type="text" class="form-control" value='<?php echo (($_GET['m'] != 'a') ?  $filter_details['manufacturer'] : ''); ?>' name='manufacturer' id='manufacturer' />
                                            </div>

                                        </div>

                                        <div class="form-row">

                                            <div class="form-group  col-md-4">
                                                <label for="installation_date">Installation Date <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" id="installation_date" name="installation_date" value='<?php echo ($_GET['m'] != 'a' && !empty($filter_details['installation_date'])) ? date('d.m.Y', strtotime($filter_details['installation_date'])) : ''; ?>' required>
                                                <div class="invalid-feedback">
                                                    Please provide an installation date.
                                                </div>
                                            </div>

                                            <div class="form-group  col-md-4">
                                                <label for="planned_due_date">Planned Due Date <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" id="planned_due_date" name="planned_due_date" value='<?php echo ($_GET['m'] != 'a' && !empty($filter_details['planned_due_date'])) ? date('d.m.Y', strtotime($filter_details['planned_due_date'])) : ''; ?>' required>
                                                <div class="invalid-feedback">
                                                    Please provide a planned due date.
                                                </div>
                                            </div>

                                            <div class="form-group  col-md-4">
                                                <label for="status">Status <span class="text-danger">*</span></label>
                                                <select class="form-control" id="status" name="status" required>
                                                    <?php
                                                    echo "<option value='Active'" . ($_GET['m'] != 'a' && ($filter_details['status'] == 'Active') ? "selected" : "") . ">Active</option>";
                                                    echo "<option value='Inactive'" . ($_GET['m'] != 'a' && ($filter_details['status'] == 'Inactive') ? "selected" : "") . ">Inactive</option>";
                                                    ?>
                                                </select>
                                                <div class="invalid-feedback">
                                                    Please select a status.
                                                </div>
                                            </div>

                                        </div>

                                        <div class="form-row">
                                            <div class="form-group col-md-4">
                                                <label for="actual_replacement_date">Actual Replacement Date</label>
                                                <input type="text" class="form-control" id="actual_replacement_date" name="actual_replacement_date" value='<?php echo ($_GET['m'] != 'a' && !empty($filter_details['actual_replacement_date'])) ? date('d.m.Y', strtotime($filter_details['actual_replacement_date'])) : ''; ?>'>
                                            </div>
                                        </div>

                                        <div class="form-row">
                                            <div class="form-group col-md-12">
                                                <label for="specifications">Specifications</label>
                                                <textarea class="form-control" id="specifications" name="specifications" rows="3" placeholder="Enter filter specifications and technical details"><?php echo (($_GET['m'] != 'a') ?  htmlspecialchars($filter_details['specifications'] ?: '', ENT_QUOTES, 'UTF-8') : ''); ?></textarea>
                                            </div>
                                        </div>

                                        <div class="form-row">

                                        <div class="d-flex justify-content-center">

					       <?php

                  if($_GET['m']=='m'){
                      ?>
                  <button type="button" id="modify_filter"	class='btn btn-gradient-success btn-icon-text mr-2'>
                    <i class="mdi mdi-content-save"></i> Modify Filter
                  </button>
                  <?php
                  }
                  else if($_GET['m']=='a'){
                      ?>
                  <button type="button" id="add_filter"	class='btn btn-gradient-primary btn-icon-text mr-2'>
                    <i class="mdi mdi-plus-circle"></i> Add Filter
                  </button>
                  <?php
                  }


                  ?>
										 </div>

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
