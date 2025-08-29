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
    
    
    		


function fetchEquipments(unitid) {
  
     $('#equipment_id').empty();
    
     $.get("core/data/get/getequipmentdetailsformaster.php",
                      {
                      	
                        unit_id: unitid
                        
                      },
                      function(data, status){
                     
                         $('#equipment_id').append(data);
                      });
  
  
  }
  
  
$('#unitid').change(function() { 
 var item=$(this);
    
    fetchEquipments(item.val());

});

 	$("#formreport").on('submit',(function(e) {
e.preventDefault();

		if($("#unitid").val()=='Select'){
			Swal.fire({
    					icon: 'error',
                        title: 'Oops...',
                        text: 'Please select unit.'                
    				});
		}
		
		else
		{
					$('#pleasewaitmodal').modal('show');
					 $.get("core/data/get/getmappingdetails.php",
                      {
                        unitid: $("#unitid").val(),
                        equipment_id: $("#equipment_id").val()
                       
                        
                      },
                      function(data, status){
                     $('#pleasewaitmodal').modal('hide');
                            $("#displayresults").html(data);
                            $('#tbl-mapping-details').DataTable({
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
              <h3 class="page-title"> Search Equipment-Test-Vendor Mapping</h3>
              <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                  <li class="breadcrumb-item"><a class='btn btn-gradient-info btn-sm btn-rounded' href="managemappingdetails.php?m=a">+ Add ETV Mapping</a></li>
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
                    
                    <div class="form-group  col-md-6">
                        <label for="exampleSelectGender">Unit</label>
                        <select class="form-control" id="unitid" name="unitid">
                          <option>Select</option>
                       	<?php 
						
						                       	if ($_SESSION['is_super_admin']=="Yes")
                       	{
                       	    try {
                       	        $results = DB::query("SELECT unit_id, unit_name FROM units ORDER BY unit_name ASC");
                       	        
                       	        if(!empty($results))
                       	        {
                       	            foreach ($results as $row) {
                       	                $output=$output. "<option value='" . htmlspecialchars($row['unit_id'], ENT_QUOTES, 'UTF-8') . "'>" . htmlspecialchars($row['unit_name'], ENT_QUOTES, 'UTF-8') . "</option>";
                       	            }
                       	            
                       	            echo $output;
                       	        }
                       	    } catch (Exception $e) {
                       	        require_once('core/error/error_logger.php');
                       	        logDatabaseError("Error fetching units: " . $e->getMessage(), [
                       	            'operation_name' => 'search_mapping_units_load',
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
                       	            'operation_name' => 'search_mapping_unit_name_query',
                       	            'unit_id' => intval($_SESSION['unit_id']),
                       	            'val_wf_id' => null,
                       	            'equip_id' => null
                       	        ]);
                       	    }
                       	}
                    
 
						
						/*
						if ($_SESSION['is_super_admin']=="Yes")
                       	{
                       	    $results = DB::query("select unit_id, unit_name from units");
                       	    
                       	    
                       	    if(!empty($results))
                       	    {
                       	        foreach ($results as $row) {
                       	            
                       	            $output=$output. "<option value='".$row['unit_id']."'>".$row['unit_name']."</option>";
                       	            
                       	        }
                       	        
                       	        echo $output;
                       	        
                       	    }
                       	
                       	    
                       	    
                       	}
                       	else {
                       	    echo "<option>".$_SESSION['unit_id']."</option>";
                       	}
						
						*/
                       	?>	
                        </select>
                      </div>
                    
                    <div class="form-group  col-md-6">
                        <label for="exampleSelectGender">Equipment Code</label>
                        
                    	  <select class="form-control" id="equipment_id" name="equipment_id">
									
										<option value='select'>Select </option>
                       	
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
                    <div id="displayresults"><p class="card-description"> Select the criteria and hit the Submit button. </p></div>
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
