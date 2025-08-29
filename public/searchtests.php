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

require_once 'core/config/db.class.php';


?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include_once "assets/inc/_header.php";?>
    <meta name="csrf-token" content="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
    <script>
    $(document).ready(function(){
   
    
    
 	$("#formreport").on('submit',(function(e) {
e.preventDefault();

		$('#pleasewaitmodal').modal('show');
					 $.get("core/data/get/gettestdetails.php",
                      {
                        test_performed_by: $("#test_performed_by").val(),
                        test_status: $("#test_status").val()
                
                        
                      },
                      function(data, status){
                     $('#pleasewaitmodal').modal('hide');
                     $("#displayresults").html(data);
                      $('#tbl-test-details').DataTable({
          						"pagingType": "numbers"
        					} );	
                    		
                       
                      });
 
		
		
		
		
		
		





}));















    
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
              <h3 class="page-title"> Search Tests</h3>
              <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                  <li class="breadcrumb-item"><a class='btn btn-gradient-info btn-sm btn-rounded' href="managetestdetails.php?m=a">+ Add Test</a></li>
                  
                </ol>
              </nav>
            </div>
            
            
            <div class="row">
            
            	<div class="col-12 grid-margin stretch-card">
                <div class="card">
                  <div class="card-body">
                    <h4 class="card-title">Select Criteria</h4>
                    
                    <form class="forms-sample" id="formreport">
                  
                       <div class="form-row">
                      <div class="form-group col-md-6">
                        <label for="wfstageid">Performed By</label>
                        <select class="form-control" id="test_performed_by" name="test_performed_by">
                         <option>Select</option>
                          <option value='0'>Internal Team</option>
                          <option value='1'>External/Vendor Team</option>
                          
                       
                        </select>
                      </div>
                       <div class="form-group col-md-6">
                        <label for="planned_start_from">Test Status</label>
                        <select class="form-control" id="test_status" name="test_status">
                         <option>Select</option>
                          <option value='0'>Active</option>
                          <option value='1'>Inactive</option>
                          
                       
                        </select>
                      </div>
                      </div>
                      
                     
        
     
  
            
        
               
                      
                      
                      <input type="submit" id="searchusers" class="btn btn-gradient-primary mr-2"/>
                      
                    </form>
                  </div>
                </div>
              </div>
            
            
            <div class="col-12 grid-margin stretch-card">
                <div class="card">
                  <div class="card-body">
                <h4 class="card-title">Result</h4>
                    <div class="table-responsive-xl">
	<div id="displayresults">
	<p class="card-description">Select the criteria and hit the Submit button.</p>
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
</body>
</html>
