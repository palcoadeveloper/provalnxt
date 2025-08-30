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
    
      $('#user_type').on('change', function(){
    	var demovalue = $(this).val(); 
        $("div.myDiv").hide();
        $("#show"+demovalue).show();
    });

 	$("#formreport").on('submit',(function(e) {
		e.preventDefault();
	
		if($("#unitid").val()=='select' && $('#user_type').val()=='IE'){
			
    			
    				Swal.fire({
    					icon: 'error',
                        title: 'Oops...',
                        text: 'Please select the unit.'                
    				});
		
		}
		else
		{
		$('#pleasewaitmodal').modal('show');
					 $.get("core/data/get/getuserdetails.php",
                      {
                        usertype:$('#user_type').val(),
                        unitid: $("#unitid").val(),
                        searchcriteria: $("#search_criteria").val(),
                        searchinput: $("#search_input").val(),
                        vendorid: $("#vendor_id").val()
                        
                      },
                      function(data, status){
                      $('#pleasewaitmodal').modal('hide');
                     $("#displayresults").html(data);
                    		 $('#tbl-user-details').DataTable({
          						"pagingType": "numbers"
        					} );	
                       
                      });
 
		}
		
		
		
		
		





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
              <h3 class="page-title"> Search Users</h3>
              <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                  <li class="breadcrumb-item"><a class='btn btn-gradient-success btn-sm btn-rounded' href="manageuserdetails.php?m=a&u=c">+ Add Employee</a></li>
                   <li class="breadcrumb-item"><a class='btn btn-gradient-info btn-sm btn-rounded' href="manageuserdetails.php?m=a&u=v">+ Add Vendor Employee</a></li>
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
                    <div id="showIE" class="myDiv form-group  col-md-6">
                        <label for="exampleSelectGender">Unit</label>
                        <select class="form-control" id="unitid" name="unitid">
                          <option value='select'>Select</option>
                       	<?php if ($_SESSION['is_super_admin']=="Yes")
                       	{
                       	    try {
                       	        $results = DB::query("SELECT unit_id, unit_name FROM units ORDER BY unit_name ASC");
                       	        
                       	        if(!empty($results))
                       	        {
                       	            $output = "";
                       	            foreach ($results as $row) {
                       	                $output .= "<option value='" . htmlspecialchars($row['unit_id'], ENT_QUOTES, 'UTF-8') . "'>" . htmlspecialchars($row['unit_name'], ENT_QUOTES, 'UTF-8') . "</option>";
                       	            }
                       	            
                       	            echo $output;
                       	        }
                       	    } catch (Exception $e) {
                       	        require_once('core/error/error_logger.php');
                       	        logDatabaseError("Error fetching units: " . $e->getMessage(), [
                       	            'operation_name' => 'search_user_units_load',
                       	            'unit_id' => null,
                       	            'val_wf_id' => null,
                       	            'equip_id' => null
                       	        ]);
                       	    }
                       	}
                       	else {
                           try {
                               $unit_name = DB::queryFirstField("SELECT unit_name FROM units WHERE unit_id = %i", intval($_SESSION['unit_id']));
                               
                               echo "<option value='" . htmlspecialchars($_SESSION['unit_id'], ENT_QUOTES, 'UTF-8') . "'>" . htmlspecialchars($unit_name, ENT_QUOTES, 'UTF-8') . "</option>";
                           } catch (Exception $e) {
                               require_once('core/error/error_logger.php');
                               logDatabaseError("Error fetching unit name: " . $e->getMessage(), [
                                   'operation_name' => 'search_user_unit_name_query',
                                   'unit_id' => intval($_SESSION['unit_id']),
                                   'val_wf_id' => null,
                                   'equip_id' => null
                               ]);
                           }
                       	}
                       	?>	
                        </select>
                      </div>

                <div class="form-group  col-md-6">
                <label for="exampleSelectGender">User Type</label>
                <select class="form-control" id="user_type" name="user_type">
                          <option value='IE'>Employee</option>
                          <option value='VE'>Vendor Employee</option>
  </select>
  </div>

<div id="showVE" class="myDiv form-group  col-md-6" style="display:none;">
                        <label for="exampleSelectGender">Vendor</label>
                        
                    	  <select class="form-control" id="vendor_id" name="vendor_id" <?php echo (isset($_GET['u']) && $_GET['u']=='c') ? "disabled":""; ?>>
									
										<option value='select'>Select </option>
                       	<?php 
                       try {
                       	    $vendor_details=DB::query("SELECT vendor_id, vendor_name FROM vendors WHERE vendor_status='Active' ORDER BY vendor_name ASC");
                       	    if(!empty($vendor_details))
                       	    {
                       	        foreach ($vendor_details as $vendor){
                       	            echo "<option value='" . htmlspecialchars($vendor['vendor_id'], ENT_QUOTES, 'UTF-8') . "'>" . htmlspecialchars($vendor['vendor_name'], ENT_QUOTES, 'UTF-8') . "</option>";
                       	        }
                       	    }
                       } catch (Exception $e) {
                           error_log("Error fetching vendor details: " . $e->getMessage());
                       }
                       	
                       	    
                       
                       	?>	
                        </select>
                    
                    
                    
                      </div>
                    
                    
                    
                    
                    
                    
                     
                      
  </div>                    
                       <div class="form-row">
                      <div class="form-group col-md-6">
                        <label for="wfstageid">Search Crietria</label>
                        <select class="form-control" id="search_criteria" name="search_criteria">
                         
                          <option value='0'>Employee Name</option>
                          <option value='1'>Employee ID</option>
                          
                       
                        </select>
                      </div>
                       <div class="form-group col-md-6">
                        <label for="planned_start_from">Input</label>
                        <input type="Text" class="form-control" id="search_input" name="search_input"/>
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
                <div id="displayresults"> <p class="card-description"> Select the criteria and hit the Submit button. </p></div>
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
