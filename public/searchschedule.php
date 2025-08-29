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

// Generate CSRF token if not present
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once('core/config/db.class.php');


?>
<!DOCTYPE html>
<html lang="en">
  <head>
      <?php include_once "assets/inc/_header.php";?>
      <meta name="csrf-token" content="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
   
    
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
         
         
         

<!-- modal begin -->  
		<div id="mymodal" class="modal" data-backdrop="static" data-keyboard="false" tabindex="-1">
    		<div class="modal-dialog modal-sm">
        		<div class="modal-content" style="text-align: center">
            		<div class="modal-body text-center">
        <div style="position: relative;
  text-align: center;
  margin: 15px auto 35px auto;
  z-index: 9999;
  display: block;
  width: 80px;
  height: 80px;
  border: 10px solid rgba(0, 0, 0, .3);
  border-radius: 50%;
  border-top-color: #000;
  animation: spin 1s ease-in-out infinite;
  -webkit-animation: spin 1s ease-in-out infinite;"></div>
        <div clas="loader-txt">
          <p>Please wait while your request is being processed.</p>
        </div>
      </div>
            	</div>
    		</div>
		</div>  
         <!-- modal end -->  
         
       
         
         
         
         
         
         
         
         
         
         
         
         
			          <div class="page-header">
              <h3 class="page-title">Search Schedule - Validation Protocols </h3>
             
            </div>
            
            
            <div class="row">
            
            	<div class="col-12 grid-margin stretch-card">
                <div class="card">
                  <div class="card-body">
                    <h4 class="card-title">Search</h4>
                    
                    <form class="needs-validation" novalidate>
  <div class="form-row">
    <div class="form-group col-md-4 mb-3">
    <label for="validationCustom01">Unit</label>
      <select class="form-control" id="unitid" name="unitid" required>
                          <option value="">Select</option>
                       	<?php if ($_SESSION['is_super_admin']=="Yes")
                       	{
                       	    try {
                       	        $results = DB::query("SELECT unit_id, unit_name FROM units ORDER BY unit_name ASC");
                       	        
                       	        if(!empty($results))
                       	        {
                       	            $output = ""; // Initialize output variable
                       	            foreach ($results as $row) {
                       	                $output .= "<option value='" . htmlspecialchars($row['unit_id'], ENT_QUOTES, 'UTF-8') . "'>" . htmlspecialchars($row['unit_name'], ENT_QUOTES, 'UTF-8') . "</option>";
                       	            }
                       	            
                       	            echo $output;
                       	        }
                       	    } catch (Exception $e) {
                       	        error_log("Error fetching units: " . $e->getMessage());
                       	    }
                       	}
                       	else {
                       	    try {
                       	        $unit_name = DB::queryFirstField("SELECT unit_name FROM units WHERE unit_id = %i", intval($_SESSION['unit_id']));
                       	        
                       	        echo "<option value='" . htmlspecialchars($_SESSION['unit_id'], ENT_QUOTES, 'UTF-8') . "'>" . htmlspecialchars($unit_name, ENT_QUOTES, 'UTF-8') . "</option>";
                       	    } catch (Exception $e) {
                       	        error_log("Error fetching unit name: " . $e->getMessage());
                       	    }
                       	}
                       	?>	
                        </select>
                        <div class="invalid-feedback">                                                                  
     Please select a unit!                                                                         
    </div>
    </div>
    <div class="form-group col-md-4 mb-3">
      <label for="validationCustom02">Year</label>
      <input class="form-control" type='text' id='sch_year' name='sch_year' pattern="(?:20)[0-9]{2}" minlength="4" maxlength="4" required/>
      <div class="invalid-feedback">
        Invalid year!
      </div>
    </div>
     <div class="form-group col-md-4 mb-3">
       <label for="validationCustom01">Schedule Type</label>
      <select class="form-control" id="sch_type" name="sch_type">
                          
                       	  <option value='val'>Validation</option>";
                       	  <option value='rt'>Routine Test</option>";
                          <option value='paval'>Validation - Planned Vs Actual</option>";
                      	  <option value='part'>Routine Test - Planned Vs Actual</option>";
                        </select>
    </div>
    
  </div>
  
 
  <button class="btn btn-primary" type="submit">Search Schedule</button>
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
			var sch_type=$("#sch_type").val();
			$('#mymodal').modal('show');
	 $.get("core/data/get/getschedule.php",
  {
    schyear: sch_year,
    unitid: unit,
    schtype:sch_type
    
  },
  function(data, status){
  
 $("#displayresults").html(data);

		  $('#mymodal').modal('hide');
    $('#datagrid').DataTable();
  });
          
          
          
          
          
          
        
        		
        
        }
         
        
        
        
        
        
        form.classList.add('was-validated');
      }, false);
    });
  }, false);
})();
</script>
                        
                    
                    
                    
                    
                    
                    
                    
                    
                    
                    
                    
                    
                    
                    
                    
                    
                    
             
                  </div>
                </div>
              </div>
            
            
            <div class="col-12 grid-margin stretch-card"">
                
                
                <div class="card">
                  <div class="card-body">
                <h4 class="card-title">Result</h4>
                    
                    <div class="table-responsive-xl">
                <div id="displayresults">
                <p class="card-description"> Select the criteria and hit the Submit button. </p>
                
                </div>
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
 <?php include "assets/inc/_imagepdfviewermodal.php"; ?>
</body>
</html>

