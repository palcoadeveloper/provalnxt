<?php
require_once('./core/config/config.php');

// Validate session timeout
require_once('core/security/session_timeout_middleware.php');
validateActiveSession();
require_once 'core/config/db.class.php';
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

   <script>

$(document).ready(function(){
  $(document).on ("click", "button[name='btnmarkinactive']", function () {

   var val_wf_id=$(this).data('wf-id');
   
   // Store the data for use in the modal success callback
   window.statusChangeData = {
     valwfid: val_wf_id,
     status: 0,
     action: 'mark_inactive',
     status_from: 'Active',
     status_to: 'Inactive'
   };
   
   // Set the workflow IDs for the modal
   window.val_wf_id = val_wf_id;
   window.test_val_wf_id = '';
   window.operation_context = 'adhoc_validation_status_change';
   
   // Set success callback for the existing modal
   setSuccessCallback(function(response) {
     // Change the status after successful authentication
     changeAdhocStatusAfterAuth();
   });
   
   // Show the password/remarks modal
   $('#enterPasswordRemark').modal('show');

    });

    $(document).on ("click", "button[name='btnmarkactive']", function () {
      var val_wf_id=$(this).data('wf-id');
      
             // Store the data for use in the modal success callback
       window.statusChangeData = {
         valwfid: val_wf_id,
         status: 1,
         action: 'mark_active',
         status_from: 'Inactive',
         status_to: 'Active'
       };
      
      // Set the workflow IDs for the modal
      window.val_wf_id = val_wf_id;
      window.test_val_wf_id = '';
      window.operation_context = 'adhoc_validation_status_change';
      
      // Set success callback for the existing modal
      setSuccessCallback(function(response) {
        // Change the status after successful authentication
        changeAdhocStatusAfterAuth();
      });
      
      // Show the password/remarks modal
      $('#enterPasswordRemark').modal('show');

    });



  $('#btnadhocrequests').click(function(e){
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
            $.get("core/data/get/getadhocvalidationrequests.php",
					  {
						
						unitid: $("#unitid").val(),
            valyear: $("#sch_year").val()
						
						
					  },
					  function(data, status){
					  
					 $('#pleasewaitmodal').modal('hide');
           $("#displayresults").html(data);
                    		$('#datagrid-report').DataTable({
  "pagingType": "numbers"
} );
							
					   
					  });
          }


  });


});

</script>



   btnadhocrequests
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
              <h3 class="page-title">Generate Schedule - Validation </h3>
              <nav aria-label="breadcrumb">
							<ul class="breadcrumb">
								<li class="breadcrumb-item"><a class='btn btn-gradient-info btn-sm btn-rounded' href="addvalrequest.php">+ Add Validation Request</a></li>

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
                          <option value='select'>Select</option>
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
                           error_log("Database error in generateschedule.php: " . $e->getMessage());
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
  
 
  <button class="btn btn-primary" type="submit">Generate Annual Schedule</button>
  <button id="btnadhocrequests" class="btn btn-success">View Adhoc Validation Requests</button>
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
			window.operation_context = 'schedule_generation';
			
			// Set success callback for the existing modal
			setSuccessCallback(function(response) {
				// Generate the schedule after successful authentication
				generateScheduleAfterAuth();
			});
			
			// Show the password/remarks modal
			$('#enterPasswordRemark').modal('show');
        }
         
        
        
        
        
        
        form.classList.add('was-validated');
      }, false);
    });
  }, false);
})();

// Function to generate schedule after successful authentication
function generateScheduleAfterAuth() {
  $('#pleasewaitmodal').modal('show');
  
  $.get("core/data/get/getschedulegenerationstatus.php", {
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

// Function to change adhoc validation status after successful authentication
function changeAdhocStatusAfterAuth() {
  $('#pleasewaitmodal').modal('show');
  
  $.get("core/workflow/changeadhocvalstatus.php", {
    valwfid: window.statusChangeData.valwfid,
    status: window.statusChangeData.status
  })
  .done(function(data) {
    $('#pleasewaitmodal').modal('hide');
    
    if(data == 'running') {
      Swal.fire('The validation is already running and thus cannot be marked inactive.', '', 'error');
    } else if(data == 'success') {
      $("#displayresults").html(data);
      $('#datagrid-report').DataTable({
        "pagingType": "numbers"
      });
      Swal.fire('Saved!', '', 'success');
      $('#btnadhocrequests').click();
    } else if(data == 'failure') {
      Swal.fire('Something went wrong.', '', 'error');
    } else {
      // Handle other response data (likely HTML content)
      $("#displayresults").html(data);
      $('#datagrid-report').DataTable({
        "pagingType": "numbers"
      });
      Swal.fire('Status updated successfully!', '', 'success');
      $('#btnadhocrequests').click();
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
                        
                    
                    
                    
                    
                    
                    
                    
                    
                    
                    
                    
                    
                    
                    
                    
                    
                    
                    
                  </div>
                </div>
              </div>
            
            
            <div class="col-12 grid-margin stretch-card">
                <div class="card">
                  <div class="card-body">
                <h4 class="card-title">Result</h4>
                    
                <div id="displayresults">

                <p class="card-description"> Select the criteria and hit the Generate Annual Schedule or the View Adhoc Validation Requests button. </p>
                </div>
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

