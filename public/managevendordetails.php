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

// Validate required parameters
if (isset($_GET['m']) && $_GET['m'] != 'a' && (!isset($_GET['vendor_id']) || !is_numeric($_GET['vendor_id']))) {
    header('HTTP/1.1 400 Bad Request');
    header('Location: ' . BASE_URL . 'error.php?msg=invalid_parameters');
    exit();
}

require_once 'core/config/db.class.php';

if (isset($_GET['m']) && $_GET['m'] != 'a') {
    try {
        $user_details = DB::queryFirstRow(
            "SELECT vendor_id, vendor_name, vendor_spoc_name, vendor_spoc_mobile, vendor_spoc_email, vendor_status
             FROM vendors WHERE vendor_id = %d", 
            intval($_GET['vendor_id'])
        );
        
        if (!$user_details) {
            header('HTTP/1.1 404 Not Found');
            header('Location: ' . BASE_URL . 'error.php?msg=vendor_not_found');
            exit();
        }
    } catch (Exception $e) {
        error_log("Error fetching vendor details: " . $e->getMessage());
        header('HTTP/1.1 500 Internal Server Error');
        header('Location: ' . BASE_URL . 'error.php?msg=database_error');
        exit();
    }
}

?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include_once "assets/inc/_header.php";?>
    <meta name="csrf-token" content="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
   <script> 
      $(document).ready(function(){
      
      // Function to submit vendor data after successful authentication
      function submitVendorData(mode) {
        $('#pleasewaitmodal').modal('show');
        
        // Prepare data object based on mode
        let data = {
          vendor_name: $("#vendor_name").val(),
          spoc_name: $("#spoc_name").val(),
          spoc_mobile: $("#spoc_mobile").val(),
          spoc_email: $("#spoc_email").val(),
          vendor_status: $("#vendor_status").val(),
          mode: mode
        };
        
        // Add vendor_id for modify mode
        if (mode === 'modify') {
          data.vendor_id = $("#vendor_id").val();
        }
        
        // Send AJAX request
        $.get("core/data/save/savevendordetails.php", data, function(response, status) {
          $('#pleasewaitmodal').modal('hide');
          
          if(response === "success") {
            Swal.fire({
              icon: 'success',
              title: 'Success',
              text: "The vendor record is successfully " + (mode === 'add' ? "added" : "modified") + "!"                
            }).then((result) => {
              window.location = "searchvendors.php";
            });
          } else {
            Swal.fire({
              icon: 'error',
              title: 'Oops...',
              text: 'Something went wrong.'                
            }).then((result) => {
              window.location = "searchvendors.php";
            });
          }
        });
      }
      
      // Add Vendor button click handler
      $("#add_vendor").click(function(e) { 
        e.preventDefault();
        
        var form = document.getElementById('formvendorvalidation'); 
        
        if (form.checkValidity() === false) {  
          form.classList.add('was-validated');  
        } else {
          form.classList.add('was-validated');
          
          // Set success callback for e-signature modal
          setSuccessCallback(function() {
            submitVendorData('add');
          });
          
          // Show e-signature modal
          $('#enterPasswordRemark').modal('show');
        }
      });

      // Modify Vendor button click handler
      $("#modify_vendor").click(function(e) { 
        e.preventDefault();
        
        var form = document.getElementById('formvendorvalidation'); 
        
        if (form.checkValidity() === false) {  
          form.classList.add('was-validated');  
        } else {
          form.classList.add('was-validated');
          
          // Set success callback for e-signature modal
          setSuccessCallback(function() {
            submitVendorData('modify');
          });
          
          // Show e-signature modal
          $('#enterPasswordRemark').modal('show');
        }
      });

    
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
							<span class="page-title-icon bg-gradient-primary text-white mr-2">
								<i class="mdi mdi-home"></i>
							</span> Vendor Details
						</h3>
						<nav aria-label="breadcrumb">
							<ul class="breadcrumb">
								<li class="breadcrumb-item active" aria-current="page"><span><a class='btn btn-gradient-info btn-sm btn-rounded'
										href="searchvendors.php"><< Back</a> </span>
								</li>
							</ul>
						</nav>
					</div>
					
					
					
		<div class="row">

	<div class="col-lg-12 grid-margin stretch-card">
                <div class="card">
                  <div class="card-body">
                    <h4 class="card-title">Vendor Details</h4>
                    <p class="card-description"> 
                    </p>
                    
                    
                    
                    
                    
                    
                    




								        <form id="formvendorvalidation" class="needs-validation" novalidate>
								        
								        <?php 
								        if($_GET['m']!='a')
								        {
								        
								        echo '<input type="hidden" id="vendor_id" name="vendor_id" value="'.$_GET['vendor_id'].'" />';
								    
								         }
								        
								        ?>
								        
								        
								        <div class="form-row">
								        
								         <div class="form-group  col-md-4">
								         <label for="vendor_name">Vendor Name</label>
								         <input type="text" class="form-control" value='<?php echo (($_GET['m']!='a')? $user_details['vendor_name']:'');?>' name='vendor_name' id="vendor_name" required/>
								         <div class="invalid-feedback">  
                                            Please provide a valid name.  
                                        </div>
								         </div>
								        
								        <div class="form-group  col-md-4">
								        <label for="spoc_name">Vendor SPOC Name</label>
								        <input type="text" class="form-control" value='<?php echo (($_GET['m']!='a')? $user_details['vendor_spoc_name']:'');?>' name='spoc_name' id='spoc_name' required/>
								        <div class="invalid-feedback">  
                                            Please provide a valid SPOC name.  
                                        </div>
								        </div>
								        
								        <div class="form-group  col-md-4">
								        <label for="spoc_mobile">Vendor SPOC Mobile</label>
								        <input type="text" pattern="[0-9]{10}" class="form-control" value='<?php echo (($_GET['m']!='a')?  $user_details['vendor_spoc_mobile']:'');?>' name='spoc_mobile' id='spoc_mobile' maxlength="10" required/>
								        <div class="invalid-feedback">  
                                            Please provide a valid mobile.  
                                        </div>
								        </div>
								        
								        </div>
								        
								        <div class="form-row">
								        <div class="form-group  col-md-4">
								        <label for="spoc_email">Vendor SPOC Email</label>
								        <input type="email"  pattern="[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$" class="form-control" value='<?php echo (($_GET['m']!='a')?  $user_details['vendor_spoc_email']:'');?>' name='spoc_email' id='spoc_email' required/>
								        <div class="invalid-feedback">  
                                            Please provide a valid email.  
                                        </div>  
								        </div>
								        
								        <div class="form-group  col-md-4">
								        
								        <label for="vendor_status">Vendor Status</label>
								        <select class="form-control" id="vendor_status" name="vendor_status" id="vendor_status">
									
										
                       	<?php 
                       	echo "<option value='Active'".(($_GET['m']!='a' && $user_details['vendor_status']=='Active')? "selected" : "") .">Active</option>";
                       	echo "<option value='Inactive'".(($_GET['m']!='a' && $user_details['vendor_status']=='Inactive')? "selected" : "") .">Inactive</option>";
                 
                       	
                       	    
                       	    
                       
                       	?>	
                        </select>
								        
								        </div>
								        
								        
								        </div>
								        
								        <div class="form-row">
								        
								        <div class="d-flex justify-content-center">
										
					       <?php
                  
                  if($_GET['m']=='m'){
                      ?>
                  <button  id="modify_vendor"	class='btn btn-gradient-primary mr-2'>Modify Vendor</button>    
                  <?php     
                  }
                  else if($_GET['m']=='a'){
                      ?>
                  <button  id="add_vendor"	class='btn btn-gradient-primary mr-2'>Add Vendor</button>    
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
