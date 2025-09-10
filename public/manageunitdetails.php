<?php
require_once('./core/config/config.php');

// Session is already started by config.php via session_init.php

// Check for proper authentication
if (!isset($_SESSION['logged_in_user']) || !isset($_SESSION['user_name'])) {
    session_destroy();
    header('Location: ' . BASE_URL . 'login.php?msg=session_required');
    exit();
}

// Check for superadmin role
if (!isset($_SESSION['is_super_admin']) || $_SESSION['is_super_admin'] !== 'Yes') {
    header('HTTP/1.1 403 Forbidden');
    header('Location: ' . BASE_URL . 'error.php?msg=access_denied');
    exit();
}

// Validate session timeout
require_once('core/security/session_timeout_middleware.php');
validateActiveSession();

// Validate required parameters
if (isset($_GET['m']) && $_GET['m'] != 'a' && (!isset($_GET['unit_id']) || !is_numeric($_GET['unit_id']))) {
    header('HTTP/1.1 400 Bad Request');
    header('Location: ' . BASE_URL . 'error.php?msg=invalid_parameters');
    exit();
}

// Initialize CSRF token if not set
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once 'core/config/db.class.php';

// Get unit details for modify/read modes
if (isset($_GET['m']) && $_GET['m'] != 'a') {
    try {
        $unit_details = DB::queryFirstRow(
            "SELECT unit_name, unit_status, primary_test_id, secondary_test_id, 
                    two_factor_enabled, otp_validity_minutes, otp_digits, otp_resend_delay_seconds,
                    validation_scheduling_logic
             FROM units WHERE unit_id = %i", 
            intval($_GET['unit_id'])
        );
        
        if (!$unit_details) {
            header('HTTP/1.1 404 Not Found');
            header('Location: ' . BASE_URL . 'error.php?msg=unit_not_found');
            exit();
        }
    } catch (Exception $e) {
        error_log("Error fetching unit details: " . $e->getMessage());
        header('HTTP/1.1 500 Internal Server Error');
        header('Location: ' . BASE_URL . 'error.php?msg=database_error');
        exit();
    }
}

// Get tests for dropdowns
try {
    $tests = DB::query("SELECT test_id, test_name, test_description FROM tests WHERE test_status = %s ORDER BY test_name", 'Active');
} catch (Exception $e) {
    error_log("Error fetching tests: " . $e->getMessage());
    $tests = [];
}

?>
<!DOCTYPE html>
<html lang="en">
  <head>
  <?php include_once "assets/inc/_header.php";?>  
   <script> 
      $(document).ready(function(){
      
      // Function to submit unit data
      function submitUnitData(mode) {
        // Get CSRF token
        var csrfToken = $("input[name='csrf_token']").val();
        
        $('#pleasewaitmodal').modal('show');
        
        // Prepare data object based on mode
        let data = {
          csrf_token: csrfToken,
          unit_name: $("#unit_name").val(),
          unit_status: $("#unit_status").val(),
          primary_test_id: $("#primary_test_id").val(),
          secondary_test_id: $("#secondary_test_id").val(),
          validation_scheduling_logic: $("#validation_scheduling_logic").val(),
          two_factor_enabled: $("#two_factor_enabled").val(),
          otp_validity_minutes: $("#otp_validity_minutes").val(),
          otp_digits: $("#otp_digits").val(),
          otp_resend_delay_seconds: $("#otp_resend_delay_seconds").val(),
          mode: mode
        };
        
        // Add unit_id for modify mode or unit_id_input for add mode
        if (mode === 'modify') {
          data.unit_id = $("#unit_id").val();
        } else if (mode === 'add') {
          data.unit_id_input = $("#unit_id_input").val();
        }
        
        // Send AJAX request
        $.ajax({
          url: "core/data/save/saveunitdetails.php",
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
                  text: "The unit record is successfully " + (mode === 'add' ? "added" : "modified") + "!"
                }).then((result) => {
                  window.location = "searchunits.php";
                });
              } else {
                Swal.fire({
                  icon: 'error',
                  title: 'Error',
                  text: response.message || 'Something went wrong.'
                });
              }
            } catch (e) {
              console.log('JSON parse error:', e);
              console.log('Raw response:', data);
              
              // Try to extract JSON from response if it's wrapped
              let errorMessage = 'An error occurred while processing your request.';
              
              // Check if data contains JSON
              if (typeof data === 'string' && data.includes('{')) {
                try {
                  // Find JSON in the string
                  const jsonStart = data.indexOf('{');
                  const jsonEnd = data.lastIndexOf('}') + 1;
                  if (jsonStart >= 0 && jsonEnd > jsonStart) {
                    const jsonPart = data.substring(jsonStart, jsonEnd);
                    const parsed = JSON.parse(jsonPart);
                    if (parsed.message) {
                      errorMessage = parsed.message;
                    }
                  }
                } catch (parseError) {
                  // If still can't parse, show the raw data if it's reasonable length
                  if (data.length < 200 && data !== "success") {
                    errorMessage = data;
                  }
                }
              } else if (data === "success") {
                Swal.fire({
                  icon: 'success',
                  title: 'Success',
                  text: "The unit record is successfully " + (mode === 'add' ? "added" : "modified") + "!"
                }).then((result) => {
                  window.location = "searchunits.php";
                });
                return;
              }
              
              Swal.fire({
                icon: 'error',
                title: 'Error',
                text: errorMessage
              });
            }
          },
          error: function(xhr, status, error) {
            $('#pleasewaitmodal').modal('hide');
            
            let errorMessage = 'Could not connect to the server. Please try again.';
            
            // Try to get more specific error information
            if (xhr.responseJSON && xhr.responseJSON.message) {
              errorMessage = xhr.responseJSON.message;
            } else if (xhr.responseText) {
              try {
                const response = JSON.parse(xhr.responseText);
                if (response.message) {
                  errorMessage = response.message;
                }
              } catch (e) {
                // Use default error message if parsing fails
              }
            }
            
            Swal.fire({
              icon: 'error',
              title: 'Error',
              text: errorMessage
            });
          }
        });
      }
      
      // Add button click
      $("#addunit").on('click', function(e) {
        e.preventDefault();
        
        var form = document.getElementById('formunitvalidation');
        
        if (form.checkValidity() === false) {
          form.classList.add('was-validated');
        } else {
          form.classList.add('was-validated');
          submitUnitData('add');
        }
      });
      
      // Modify button click
      $("#modifyunit").on('click', function(e) {
        e.preventDefault();
        
        var form = document.getElementById('formunitvalidation');
        
        if (form.checkValidity() === false) {
          form.classList.add('was-validated');
        } else {
          form.classList.add('was-validated');
          submitUnitData('modify');
        }
      });
      
      // 2FA toggle handler
      $("#two_factor_enabled").change(function() {
        if ($(this).val() === 'Yes') {
          $("#tfa-settings").show();
        } else {
          $("#tfa-settings").hide();
        }
      });
      
      // Initialize 2FA settings visibility
      if ($("#two_factor_enabled").val() === 'Yes') {
        $("#tfa-settings").show();
      } else {
        $("#tfa-settings").hide();
      }
      
      // Secondary Test dependency validation
      $("#secondary_test_id").change(function() {
        if ($(this).val() !== "" && $("#primary_test_id").val() === "") {
          Swal.fire({
            icon: 'warning',
            title: 'Primary Test Required',
            text: 'Please select a Primary Test before selecting a Secondary Test.'
          });
          $(this).val(""); // Reset secondary test selection
        }
      });
      
      // Clear secondary test when primary test is cleared
      $("#primary_test_id").change(function() {
        if ($(this).val() === "") {
          $("#secondary_test_id").val("");
        }
      });
      
      // Handle Validation Scheduling Logic dropdown change
      $("#validation_scheduling_logic").on("change", function() {
        var selectedValue = $(this).val();
        
        if (selectedValue === "fixed") {
          // Disable dropdowns for Fixed Dates
          $("#primary_test_id").prop("disabled", true);
          $("#secondary_test_id").prop("disabled", true);
        } else {
          // Enable dropdowns for Dynamic Dates
          $("#primary_test_id").prop("disabled", false);
          $("#secondary_test_id").prop("disabled", false);
        }
      });
      
      // Initialize dropdown state on page load
      $("#validation_scheduling_logic").trigger("change");
      
    });
    
    </script>
    
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
							 Unit Details
						</h3>
						<nav aria-label="breadcrumb">
							<ul class="breadcrumb">
								<li class="breadcrumb-item active" aria-current="page"><span><a class='btn btn-gradient-info btn-sm btn-rounded'
									href="searchunits.php"><< Back</a> </span>
								</li>
							</ul>
						</nav>
					</div>
					
					

	<div class="row">

	<div class="col-lg-12 grid-margin stretch-card">
                <div class="card">
                  <div class="card-body">
                    <h4 class="card-title">Unit Details</h4>
                    <p class="card-description"> 
                    </p>
                    
				        <form id='formunitvalidation' class="needs-validation" novalidate>
				        <!-- Add CSRF token field -->
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                       
			        <?php 
			        if(isset($_GET['m']) && $_GET['m']!='a')
			        {
			        
			        echo '<input type="hidden" id="unit_id" name="unit_id" value="'.$_GET['unit_id'].'" />';
			    
			         }
			        
			        ?>
			
			
			<div class="form-row">
                    
                    <?php if(isset($_GET['m']) && $_GET['m']=='a'): ?>
                    <div class="form-group col-md-4">
                        <label for="unit_id_input">Unit ID *</label>
                        <input type="text" class="form-control" name='unit_id_input' id='unit_id_input' required/>
                        <div class="invalid-feedback">Please provide a unique unit ID.</div>
                        <small class="text-muted">This ID will be permanent and cannot be changed later.</small>
                    </div>
                    <?php else: ?>
                    <div class="form-group col-md-4">
                        <label for="unit_id_display">Unit ID</label>
                        <input type="text" class="form-control" value='<?php echo $_GET['unit_id']; ?>' readonly/>
                        <small class="text-muted">Unit ID cannot be modified.</small>
                    </div>
                    <?php endif; ?>

                    <div class="form-group col-md-4">
                        <label for="unit_name">Unit Name *</label>
                        <input type="text" class="form-control" value='<?php echo ((isset($_GET['m']) && $_GET['m']!='a')?  htmlspecialchars($unit_details['unit_name'], ENT_QUOTES, 'UTF-8'):'');?>' name='unit_name' id='unit_name' required <?php echo ((isset($_GET['m']) && $_GET['m']=='r')?'readonly':'');?>/>
                        <div class="invalid-feedback">Please provide a unit name.</div>
                    </div>

                    <div class="form-group col-md-4">
                        <label for="validation_scheduling_logic">Validation Scheduling Logic *</label>
                        <select class="form-control" id="validation_scheduling_logic" name="validation_scheduling_logic" required <?php echo ((isset($_GET['m']) && $_GET['m']=='r')?'disabled':'');?>>
                            <option value="dynamic" <?php echo (isset($_GET['m']) && $_GET['m']!='a' && $unit_details['validation_scheduling_logic']=='dynamic') ? 'selected' : (!isset($_GET['m']) || $_GET['m']=='a' ? 'selected' : ''); ?>>Dynamic Dates</option>
                            <option value="fixed" <?php echo (isset($_GET['m']) && $_GET['m']!='a' && $unit_details['validation_scheduling_logic']=='fixed') ? 'selected' : ''; ?>>Fixed Dates</option>
                        </select>
                        <div class="invalid-feedback">Please select a validation scheduling logic.</div>
                        <small class="text-muted">Dynamic dates adjust automatically; Fixed dates remain constant.</small>
                    </div>


                    </div>
                    
                    <div class="form-row">
                      
                      <div class="form-group col-md-4">
                        <label for="primary_test_id">Primary Test *</label>
                        <select class="form-control" id="primary_test_id" name="primary_test_id" required <?php echo ((isset($_GET['m']) && $_GET['m']=='r')?'disabled':'');?>>
                            <option value="">Select Primary Test</option>
                            <?php 
                            foreach ($tests as $test) {
                                $selected = (isset($_GET['m']) && $_GET['m']!='a' && $unit_details['primary_test_id'] == $test['test_id']) ? 'selected' : '';
                                echo "<option value='".$test['test_id']."' $selected>".htmlspecialchars($test['test_name'], ENT_QUOTES, 'UTF-8')." - ".htmlspecialchars($test['test_description'], ENT_QUOTES, 'UTF-8')."</option>";
                            }
                            ?>
                        </select>
                        <div class="invalid-feedback">Please select a Primary Test.</div>
                      </div>

                      <div class="form-group col-md-4">
                        <label for="secondary_test_id">Secondary Test</label>
                        <select class="form-control" id="secondary_test_id" name="secondary_test_id" <?php echo ((isset($_GET['m']) && $_GET['m']=='r')?'disabled':'');?>>
                            <option value="">Select Secondary Test</option>
                            <?php 
                            foreach ($tests as $test) {
                                $selected = (isset($_GET['m']) && $_GET['m']!='a' && $unit_details['secondary_test_id'] == $test['test_id']) ? 'selected' : '';
                                echo "<option value='".$test['test_id']."' $selected>".htmlspecialchars($test['test_name'], ENT_QUOTES, 'UTF-8')." - ".htmlspecialchars($test['test_description'], ENT_QUOTES, 'UTF-8')."</option>";
                            }
                            ?>
                        </select>
                        <div class="invalid-feedback">Please select a valid Secondary Test.</div>
                      </div>

                      <div class="form-group col-md-4">
                        <label for="unit_status">Status</label>
                        <select class="form-control" id="unit_status" name="unit_status" <?php echo ((isset($_GET['m']) && $_GET['m']=='r')?'disabled':'');?>>
			            <?php 
			            echo "<option value='Active'".(isset($_GET['m']) && $_GET['m']!='a' && ($unit_details['unit_status']=='Active')? "selected" : "") .">Active</option>";
			            echo "<option value='Inactive'".(isset($_GET['m']) && $_GET['m']!='a' && ($unit_details['unit_status']=='Inactive')? "selected" : "") .">Inactive</option>";
			            ?>	
                        </select>
                        <div class="invalid-feedback">Please select a unit status.</div>
                      </div>

                    </div>
                    
                    <div class="form-row">
                      
                      <div class="form-group col-md-4">
                        <label for="two_factor_enabled">Two Factor Authentication</label>
                        <select class="form-control" id="two_factor_enabled" name="two_factor_enabled" <?php echo ((isset($_GET['m']) && $_GET['m']=='r')?'disabled':'');?>>
			            <?php 
			            echo "<option value='No'".(isset($_GET['m']) && $_GET['m']!='a' && ($unit_details['two_factor_enabled']=='No')? "selected" : "") .">No</option>";
			            echo "<option value='Yes'".(isset($_GET['m']) && $_GET['m']!='a' && ($unit_details['two_factor_enabled']=='Yes')? "selected" : "") .">Yes</option>";
			            ?>	
                        </select>
                        <div class="invalid-feedback">Please select Two Factor Authentication setting.</div>
                      </div>

                    </div>

                    <!-- 2FA Settings (shown when 2FA is enabled) -->
                    <div id="tfa-settings" style="display: none;">
                        <h5 class="mt-4 mb-3">Two Factor Authentication Settings</h5>
                        
                        <div class="form-row">
                            <div class="form-group col-md-3">
                                <label for="otp_validity_minutes">OTP Validity (Minutes)</label>
                                <input type="number" class="form-control" 
                                       value='<?php echo ((isset($_GET['m']) && $_GET['m']!='a')?  $unit_details['otp_validity_minutes']:5);?>' 
                                       name='otp_validity_minutes' id='otp_validity_minutes' 
                                       min="1" max="15" 
                                       <?php echo ((isset($_GET['m']) && $_GET['m']=='r')?'readonly':'');?>/>
                                <div class="invalid-feedback">Please enter a valid OTP validity between 1-15 minutes.</div>
                                <small class="text-muted">Valid range: 1-15 minutes</small>
                            </div>

                            <div class="form-group col-md-3">
                                <label for="otp_digits">OTP Digits</label>
                                <input type="number" class="form-control" 
                                       value='<?php echo ((isset($_GET['m']) && $_GET['m']!='a')?  $unit_details['otp_digits']:6);?>' 
                                       name='otp_digits' id='otp_digits' 
                                       min="4" max="8" 
                                       <?php echo ((isset($_GET['m']) && $_GET['m']=='r')?'readonly':'');?>/>
                                <div class="invalid-feedback">Please enter valid OTP digits between 4-8.</div>
                                <small class="text-muted">Valid range: 4-8 digits</small>
                            </div>

                            <div class="form-group col-md-3">
                                <label for="otp_resend_delay_seconds">OTP Resend Delay (Seconds)</label>
                                <input type="number" class="form-control" 
                                       value='<?php echo ((isset($_GET['m']) && $_GET['m']!='a')?  $unit_details['otp_resend_delay_seconds']:60);?>' 
                                       name='otp_resend_delay_seconds' id='otp_resend_delay_seconds' 
                                       min="30" max="300" 
                                       <?php echo ((isset($_GET['m']) && $_GET['m']=='r')?'readonly':'');?>/>
                                <div class="invalid-feedback">Please enter valid resend delay between 30-300 seconds.</div>
                                <small class="text-muted">Valid range: 30-300 seconds</small>
                            </div>
                        </div>
                    </div>
                    
                     
			        <?php 
			        if(isset($_GET['m']) && $_GET['m']=='a')
			        {
			        	echo '<input type="submit" id="addunit" class="btn btn-gradient-primary mr-2" value="Add Unit"/>';
			        }
			        if(isset($_GET['m']) && $_GET['m']=='m')
			        {
			        	echo '<input type="submit" id="modifyunit" class="btn btn-gradient-primary mr-2" value="Modify Unit"/>';
			        }
			        ?>
			        
                      
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