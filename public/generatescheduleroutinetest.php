<?php
require_once('./core/config/config.php');

// Validate session timeout
require_once('core/security/session_timeout_middleware.php');
validateActiveSession();
require_once('core/config/db.class.php');

// Check for proper authentication
// Use centralized session validation
require_once('core/security/session_validation.php');
validateUserSession();

// Generate CSRF token if not already set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

?>
<!DOCTYPE html>
<html lang="en">
  <head>
   <?php include_once "assets/inc/_header.php";?> 

 <script type="text/javascript">

  $(document).ready(function(){

     // Removed duplicate handlers - ad-hoc routine test handlers are defined below


    $('#btnViewRTRequests').click(function(e){

      e.preventDefault();


      if($("#unitid").val()==='select')
					{
						Swal.fire({
											icon: 'error',
											title: 'Oops...',
											text: 'Please select Unit.'                
										});
					}
					else 
          {
            $('#pleasewaitmodal').modal('show');
            
            // Check if Ad-hoc Only filter is selected
            if($("#frequencyFilter").val() === 'ADHOC') {
              // Show ad-hoc routine tests
              $.get("core/data/get/getadhocroutinetests.php",
              {
                unitid: $("#unitid").val(),
                rtyear: $("#sch_year").val()
              },
              function(data, status){
                $('#pleasewaitmodal').modal('hide');
                $("#displayresults").html(data);
                $('#datagrid-report').DataTable({
                  "pagingType": "numbers"
                });
              });
            } else {
              // Show regular routine test requests
              $.get("core/data/get/getroutinetestrequests.php",
              {
                unitid: $("#unitid").val(),
                frequencyFilter: $("#frequencyFilter").val()
              },
              function(data, status){
                $('#pleasewaitmodal').modal('hide');
                $("#displayresults").html(data);
                $('#datagrid-report').DataTable({
                  "pagingType": "numbers"
                });
              });
            }
          }
     


    });

  // Regular Routine Test Mark Inactive button handler
  $(document).on("click", "button[name='btnmarkinactive']", function () {
    var routine_test_request_id = $(this).data('request-id');
    
    if(!routine_test_request_id) {
      Swal.fire({
        icon: 'error',
        title: 'Error',
        text: 'Request ID not found. Please ensure you are viewing routine test requests.'
      });
      return;
    }
    
    Swal.fire({
      title: 'Are you sure?',
      text: 'Do you want to mark this routine test request as inactive?',
      icon: 'question',
      showDenyButton: true,
      confirmButtonText: 'Yes',
      denyButtonText: 'No',
      customClass: {
        actions: 'my-actions',
        confirmButton: 'order-2',
        denyButton: 'order-3',
      }
    }).then((result) => {
      if (result.isConfirmed) {
        // Store the data for use in the modal success callback
        window.statusChangeData = {
          routine_test_request_id: routine_test_request_id,
          action: 'mark_inactive'
        };
        
        // Set the workflow IDs for the modal
        window.val_wf_id = '';
        window.test_val_wf_id = '';
        window.operation_context = 'routine_test_request_status_change';
        
        // Set success callback for the existing modal
        setSuccessCallback(function(response) {
          // Change the status after successful authentication
          changeRoutineTestStatusAfterAuth();
        });
        
        // Show the password/remarks modal
        $('#enterPasswordRemark').modal('show');
      } else if (result.isDenied) {
        Swal.fire('Changes are not saved', '', 'info');
      }
    });
  });

  // Regular Routine Test Mark Active button handler
  $(document).on("click", "button[name='btnmarkactive']", function () {
    var routine_test_request_id = $(this).data('request-id');
    
    if(!routine_test_request_id) {
      Swal.fire({
        icon: 'error',
        title: 'Error',
        text: 'Request ID not found. Please ensure you are viewing routine test requests.'
      });
      return;
    }
    
    Swal.fire({
      title: 'Are you sure?',
      text: 'Do you want to mark this routine test request as active?',
      icon: 'question',
      showDenyButton: true,
      confirmButtonText: 'Yes',
      denyButtonText: 'No',
      customClass: {
        actions: 'my-actions',
        confirmButton: 'order-2',
        denyButton: 'order-3',
      }
    }).then((result) => {
      if (result.isConfirmed) {
        // Store the data for use in the modal success callback
        window.statusChangeData = {
          routine_test_request_id: routine_test_request_id,
          action: 'mark_active'
        };
        
        // Set the workflow IDs for the modal
        window.val_wf_id = '';
        window.test_val_wf_id = '';
        window.operation_context = 'routine_test_request_status_change';
        
        // Set success callback for the existing modal
        setSuccessCallback(function(response) {
          // Change the status after successful authentication
          changeRoutineTestStatusAfterAuth();
        });
        
        // Show the password/remarks modal
        $('#enterPasswordRemark').modal('show');
      } else if (result.isDenied) {
        Swal.fire('Changes are not saved', '', 'info');
      }
    });
  });

  // Ad-hoc functionality integrated into main View Routine Test Requests button

  // Ad-hoc Routine Test Mark Inactive button handler
  $(document).on("click", "button[name='btnadhocinactive']", function () {
    var rt_wf_id=$(this).data('wf-id');
    
    if(!rt_wf_id) {
      Swal.fire({
        icon: 'error',
        title: 'Error',
        text: 'Workflow ID not found. Please ensure you are viewing ad-hoc routine tests.'
      });
      return;
    }
    
    Swal.fire({
      title: 'Are you sure?',
      text: 'Do you want to mark this routine test as inactive?',
      icon: 'question',
      showDenyButton: true,
      confirmButtonText: 'Yes',
      denyButtonText: 'No',
      customClass: {
        actions: 'my-actions',
        confirmButton: 'order-2',
        denyButton: 'order-3',
      }
    }).then((result) => {
      if (result.isConfirmed) {
        // Store the data for use in the modal success callback
        window.adhocRTStatusChangeData = {
          rtwfid: rt_wf_id,
          status: 0,
          action: 'mark_inactive',
          status_from: 'Active',
          status_to: 'Inactive'
        };
        
        // Set the workflow IDs for the modal
        window.val_wf_id = '';
        window.test_val_wf_id = '';
        window.operation_context = 'adhoc_routine_test_status_change';
        
        // Set success callback for the existing modal
        setSuccessCallback(function(response) {
          // Change the status after successful authentication
          changeAdhocRTStatusAfterAuth();
        });
        
        // Show the password/remarks modal
        $('#enterPasswordRemark').modal('show');
      } else if (result.isDenied) {
        Swal.fire('Changes are not saved', '', 'info');
      }
    });
  });

  // Ad-hoc Routine Test Mark Active button handler
  $(document).on("click", "button[name='btnadhocactive']", function () {
    var rt_wf_id=$(this).data('wf-id');
    
    if(!rt_wf_id) {
      Swal.fire({
        icon: 'error',
        title: 'Error',
        text: 'Workflow ID not found. Please ensure you are viewing ad-hoc routine tests.'
      });
      return;
    }
    
    Swal.fire({
      title: 'Are you sure?',
      text: 'Do you want to mark this routine test as active?',
      icon: 'question',
      showDenyButton: true,
      confirmButtonText: 'Yes',
      denyButtonText: 'No',
      customClass: {
        actions: 'my-actions',
        confirmButton: 'order-2',
        denyButton: 'order-3',
      }
    }).then((result) => {
      if (result.isConfirmed) {
        // Store the data for use in the modal success callback
        window.adhocRTStatusChangeData = {
          rtwfid: rt_wf_id,
          status: 1,
          action: 'mark_active',
          status_from: 'Inactive',
          status_to: 'Active'
        };
        
        // Set the workflow IDs for the modal
        window.val_wf_id = '';
        window.test_val_wf_id = '';
        window.operation_context = 'adhoc_routine_test_status_change';
        
        // Set success callback for the existing modal
        setSuccessCallback(function(response) {
          // Change the status after successful authentication
          changeAdhocRTStatusAfterAuth();
        });
        
        // Show the password/remarks modal
        $('#enterPasswordRemark').modal('show');
      } else if (result.isDenied) {
        Swal.fire('Changes are not saved', '', 'info');
      }
    });
  });
    	
    

  });

// Function to change ad-hoc routine test status after successful authentication
function changeAdhocRTStatusAfterAuth() {
  $('#pleasewaitmodal').modal('show');
  
  $.get("core/workflow/changeadhocrtstauts.php", {
    rtwfid: window.adhocRTStatusChangeData.rtwfid,
    status: window.adhocRTStatusChangeData.status
  })
  .done(function(data) {
    $('#pleasewaitmodal').modal('hide');
    
    if(data == 'running') {
      Swal.fire('The routine test is already running and thus cannot be marked inactive.', '', 'error');
    } else if(data == 'error_no_rtwfid') {
      Swal.fire('Error: Workflow ID not provided.', '', 'error');
    } else if(data == 'error_no_status') {
      Swal.fire('Error: Status parameter not provided.', '', 'error');
    } else if(data == 'error_not_found') {
      Swal.fire('Error: Routine test not found with the provided workflow ID.', '', 'error');
    } else if(data.startsWith('expired_date')) {
      var expiredDate = data.split('|')[1];
      Swal.fire({
        icon: 'warning',
        title: 'Planned Date Expired',
        text: 'Cannot activate - planned date (' + expiredDate + ') has passed. Please update the planned date first.',
        confirmButtonText: 'OK'
      });
    } else if(data == 'conflict_exists') {
      Swal.fire({
        icon: 'warning',
        title: 'Active Routine Test Exists',
        text: 'Cannot activate - an active routine test already exists for this equipment and test combination.',
        confirmButtonText: 'OK'
      });
    } else if(data == 'success') {
      Swal.fire('Status updated successfully!', '', 'success');
      $('#btnViewRTRequests').click(); // Refresh the list
    } else if(data == 'failure') {
      Swal.fire('Something went wrong.', '', 'error');
    } else {
      // Handle other response data (likely HTML content)
      $("#displayresults").html(data);
      $('#datagrid-report').DataTable({
        "pagingType": "numbers"
      });
      Swal.fire('Status updated successfully!', '', 'success');
      $('#btnViewRTRequests').click(); // Refresh the list
    }
  })
  .fail(function() {
    $('#pleasewaitmodal').modal('hide');
    Swal.fire({
      icon: 'error',
      title: 'Connection Error',
      text: 'Could not connect to the server. Please try again.'
    });
  });
}

 </script>
    
  </head>
  <body>
    <?php include_once "assets/inc/_pleasewaitmodal.php"; ?>
    <?php include_once "assets/inc/_esignmodal.php"; ?>
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
              <h3 class="page-title">Generate Schedule - Routine Test </h3>
              
                  <nav aria-label="breadcrumb">
							<ul class="breadcrumb">
								<li class="breadcrumb-item"><a class='btn btn-gradient-info btn-sm btn-rounded' href="addroutinetest.php">+ Add Routine Test</a></li>

							</ul>
						</nav>
            </div>
            
            
            <div class="row">
            
            	<div class="col-12 grid-margin stretch-card">
                <div class="card">
                  <div class="card-body">
                    <h4 class="card-title">Select Criteria</h4>
                    
                    
                    <form class="needs-validation" novalidate>
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
  <div class="form-row">
    <div class="form-group col-md-6 mb-3">
    <label for="validationCustom01">Unit</label>
      <select class="form-control" id="unitid" name="unitid">
                          <option value="select">Select</option>
                       	<?php 
                       try {
                           if ($_SESSION['is_super_admin']=="Yes") {
                               $results = DB::query("SELECT unit_id, unit_name FROM units ORDER BY unit_name");
                               
                               $output = "";
                               if(!empty($results)) {
                                   foreach ($results as $row) {
                                       $output .= "<option value='" . htmlspecialchars($row['unit_id'], ENT_QUOTES) . "'>" . 
                                                 htmlspecialchars($row['unit_name'], ENT_QUOTES) . "</option>";
                                   }
                                   echo $output;
                               }
                           } else {
                               $unit_id = intval($_SESSION['unit_id']);
                               $unit_name = DB::queryFirstField("SELECT unit_name FROM units WHERE unit_id = %i", $unit_id);
                               
                               if ($unit_name) {
                                   echo "<option value='" . htmlspecialchars($unit_id, ENT_QUOTES) . "'>" . 
                                        htmlspecialchars($unit_name, ENT_QUOTES) . "</option>";
                               }
                           }
                       } catch (Exception $e) {
                           require_once('core/error/error_logger.php');
                           logDatabaseError("Database error in generatescheduleroutinetest.php: " . $e->getMessage(), [
                               'operation_name' => 'routine_test_schedule_unit_load',
                               'unit_id' => null,
                               'val_wf_id' => null,
                               'equip_id' => null
                           ]);
                           echo "<option value=''>Error loading units</option>";
                       }
                       	?>	
                        </select>
    </div>
    <div class="form-group col-md-6 mb-3">
      <label for="validationCustom02">Year</label>
      <input class="form-control" type='text' id='sch_year' name='sch_year' pattern="(?:20)[0-9]{2}" minlength="4" maxlength="4" required/>
      <div class="invalid-feedback">
        Invalid year!
      </div>
    </div>
    
  </div>
  
  <div class="form-row">
    <div class="form-group col-md-6 mb-3">
      <label for="frequencyFilter">Filter by Frequency (optional)</label>
      <select class="form-control" id="frequencyFilter" name="frequencyFilter">
        <option value="">All Frequencies</option>
        <option value="Q">Quarterly</option>
        <option value="H">Half Yearly</option>
        <option value="Y">Yearly</option>
        <option value="2Y">Bi-yearly</option>
        <option value="ADHOC">Ad-hoc Only</option>
      </select>
    </div>
  </div>
 
  <button class="btn btn-primary" type="submit">Generate Annual Schedule</button>
 <button id="btnViewRTRequests" class="btn btn-success">View Routine Test Requests</button>
</form>
      
          
<script>
// Example starter JavaScript for disabling form submissions if there are invalid fields
(function() {
  'use strict';
  window.addEventListener('load', function() {
    // Fetch all the forms we want to apply custom Bootstrap validation styles to
    var forms = document.getElementsByClassName('needs-validation');
    // Loop over them and prevent submission
    var validation = Array.prototype.filter.call(forms, function(form) {
      form.addEventListener('submit', function(event) {
        if (form.checkValidity() === false) {
          event.preventDefault();
          event.stopPropagation();
        }
        else
        {
        		event.preventDefault();
          event.stopPropagation();
        var sch_year=$("#sch_year").val();
			var unit=$("#unitid").val();
			
			// Validate unit selection
			if(unit === 'select') {
				Swal.fire({
					icon: 'error',
					title: 'Oops...',
					text: 'Please select Unit.'                
				});
				return;
			}
   
			// Store the form data for use in the modal success callback
			window.scheduleFormData = {
				unitid: unit,
				schyear: sch_year
			};
			
			// Set the workflow IDs for the modal (required by addremarks.php)
			window.val_wf_id = '';
			window.test_val_wf_id = '';
			window.operation_context = 'routine_test_schedule_generation';
			
			// Set success callback for the existing modal
			setSuccessCallback(function(response) {
				// Generate the routine test schedule after successful authentication
				generateRTScheduleAfterAuth();
			});
			
			// Show the password/remarks modal
			$('#enterPasswordRemark').modal('show');
        }
         
        
        
        
        
        
        form.classList.add('was-validated');
      }, false);
    });
  }, false);
})();

// Function to generate routine test schedule after successful authentication
function generateRTScheduleAfterAuth() {
  $('#pleasewaitmodal').modal('show');
  
  $.get("core/data/get/getrtschedulegenerationstatus.php", {
    schyear: window.scheduleFormData.schyear,
    unitid: window.scheduleFormData.unitid
  })
  .done(function(data) {
    $('#pleasewaitmodal').modal('hide');
    
    if(data.includes('success')) {
      Swal.fire({
        icon: 'success',
        title: 'Success',
        text: data
      });
    } else {
      Swal.fire({
        icon: 'error',
        title: 'Oops',
        text: data
      });
    }
  })
  .fail(function() {
    $('#pleasewaitmodal').modal('hide');
    Swal.fire({
      icon: 'error',
      title: 'Connection Error',
      text: 'Could not connect to the server. Please try again.'
    });
  });
}

// Function to change routine test status after successful authentication
function changeRoutineTestStatusAfterAuth() {
  $('#pleasewaitmodal').modal('show');
  
  $.get("core/workflow/changeroutinetestreqstatus.php", {
    routine_test_request_id: window.statusChangeData.routine_test_request_id
  })
  .done(function(data) {
    $('#pleasewaitmodal').modal('hide');
    $("#displayresults").html(data);
    $('#datagrid-report').DataTable({
      "pagingType": "numbers"
    });
    
    if(data == 'success') {
      Swal.fire('Saved!', '', 'success');
    } else if(data == 'failure') {
      Swal.fire('Something went wrong.', '', 'error');
    }
    
    $('#btnViewRTRequests').click();
  })
  .fail(function() {
    $('#pleasewaitmodal').modal('hide');
    Swal.fire({
      icon: 'error',
      title: 'Connection Error',
      text: 'Could not connect to the server. Please try again.'
    });
  });
}
</script>
                        
                    
                    
                    
                    
                    
                    
                    
                    
                    
                    
                    
                    
                    
                    
                    
                    
                    
                    
                  </div>
                </div>
              </div>
            
            
            <div class="col-12 grid-margin stretch-card">
                <div class="card">
                  <div class="card-body">
                <h4 class="card-title">Result</h4>
                    <p class="card-description"> Select the criteria and hit the Generate Schedule button. </p>
                <div id="displayresults"></div>
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


