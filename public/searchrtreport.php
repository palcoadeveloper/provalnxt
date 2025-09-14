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
    
    <link rel="stylesheet" href="assets/css/modern-manage-ui.css">
    
    <script>
    $(document).ready(function(){
   
      $("#actual_start_from").datepicker({
   dateFormat: 'dd.mm.yy', 
   changeMonth: true,
   changeYear: true
});

$("#actual_start_to").datepicker({
   dateFormat: 'dd.mm.yy', 
   changeMonth: true,
   changeYear: true
});

$("#planned_start_from").datepicker({
   dateFormat: 'dd.mm.yy', 
   changeMonth: true,
   changeYear: true
});

$("#planned_start_to").datepicker({
   dateFormat: 'dd.mm.yy', 
   changeMonth: true,
   changeYear: true
});
    		  $('#viewProtocolModal').on('show.bs.modal', function (e) {
    var loadurl = $(e.relatedTarget).data('load-url');
    $(this).find('.modal-body').load(loadurl);
});
    		
    		
    		$('#unitid').change(function() {    
    		
    		
    var item=this.value;

    $("#equipmentid").empty();
    
    		 $.get("assets/inc/_geteuipmentdetails.php",
  {
    unitid: item
    
    
  },
  function(data, status){
 $("#equipmentid").html(data);
		
   
  });
 
    
    
    
   
});



$('#wfstageid').change(function() {  

var item=this.value;

if(item==6)
{
	$('#actual_start_from').val(new Date());
	$('#actual_start_to').val(new Date());
	$("#actual_start_from").prop("disabled", true);
	$("#actual_start_to").prop("disabled", true);

}
else
{
	$("#actual_start_from").prop("disabled", false);
	$("#actual_start_to").prop("disabled", false);

}

});



 	$("#formreport").on('submit',(function(e) {
e.preventDefault();




			var unit=$("#unitid").val();
			var equip=$("#equipmentid").val();
			var wfstage=$("#wfstageid").val();
			var psdf=$("#planned_start_from").val();
			var psdt=$("#planned_start_to").val();
			var asdf=$("#actual_start_from").val();
			var asdt=$("#actual_start_to").val();
		
	
		
		// Clear previous validation states
		$('.forms-sample .form-control').removeClass('is-valid is-invalid');
		
		var hasErrors = false;

		// Validate required fields and add visual feedback
		if(!unit) {
		    $("#unitid").addClass('is-invalid');
		    hasErrors = true;
		} else {
		    $("#unitid").addClass('is-valid');
		}
		
		if(!equip) {
		    $("#equipmentid").addClass('is-invalid');
		    hasErrors = true;
		} else {
		    $("#equipmentid").addClass('is-valid');
		}
		
		if(!wfstage) {
		    $("#wfstageid").addClass('is-invalid');
		    hasErrors = true;
		} else {
		    $("#wfstageid").addClass('is-valid');
		}

		if(hasErrors){
			
			Swal.fire({
    					icon: 'error',
                        title: 'Oops...',
                        text: 'Please select Unit ID, Equipment and Workflow Stage.'                
    				});
		}
		else if ((psdf && !psdt) || (!psdf && psdt) || (psdf > psdt) || (asdf && !asdt) || (!asdf && asdt) || (asdf > asdt))
		{
			// Add invalid styling to date fields with errors
			if ((psdf && !psdt) || (!psdf && psdt) || (psdf > psdt)) {
			    $("#planned_start_from").addClass('is-invalid');
			    $("#planned_start_to").addClass('is-invalid');
			} else {
			    $("#planned_start_from").addClass('is-valid');
			    $("#planned_start_to").addClass('is-valid');
			}
			
			if ((asdf && !asdt) || (!asdf && asdt) || (asdf > asdt)) {
			    $("#actual_start_from").addClass('is-invalid');
			    $("#actual_start_to").addClass('is-invalid');
			} else {
			    $("#actual_start_from").addClass('is-valid');
			    $("#actual_start_to").addClass('is-valid');
			}
			
			Swal.fire({
    					icon: 'error',
                        title: 'Oops...',
                        text: 'Please check the dates.'                
    				});
		
		}
		
		else
		{
			// Mark all fields as valid since validation passed
			$('.forms-sample .form-control').addClass('is-valid').removeClass('is-invalid');
		$('#pleasewaitmodal').modal('show');
					 $.get("core/data/get/getroutinetestwfstatus.php",
                      {
                        unitid: unit,
                        equipmentid: equip,
                        wfstageid:wfstage,
                        planned_start_from:psdf,
                        planned_start_to:psdt,
                        actual_start_from:asdf,
                        actual_start_to:asdt
                        
                        
                        
                      },
                      function(data, status){
                      
                      $('#pleasewaitmodal').modal('hide');
                     $("#displayresults").html(data);
                     
                     // Small delay to ensure DOM is ready, then initialize modern DataTable
                     setTimeout(function() {
                         // Destroy existing DataTable if it exists
                         if ($.fn.DataTable.isDataTable('#datagrid-report')) {
                             $('#datagrid-report').DataTable().destroy();
                         }
                         
                         // Initialize modern DataTable with enhanced features
                         $('#datagrid-report').DataTable({
                             "pagingType": "numbers",
                             "pageLength": 25,
                             "lengthMenu": [[10, 25, 50, 100], [10, 25, 50, 100]],
                             "searching": true,
                             "ordering": true,
                             "info": true,
                             "columnDefs": [
                                 {
                                     "targets": -1,
                                     "orderable": false,
                                     "searchable": false
                                 }
                             ],
                             "language": {
                                 "search": "Search routine tests:",
                                 "lengthMenu": "Show _MENU_ entries",
                                 "info": "Showing _START_ to _END_ of _TOTAL_ routine test entries"
                             }
                         });
                     }, 100); // 100ms delay
                       
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
          			
<div class="modal fade bd-example-modal-lg show" id="viewProtocolModal" tabindex="-1" role="dialog" aria-labelledby="myLargeModalLabel" style="padding-right: 17px;">>
  <div class="modal-dialog modal-lg">
    <div class="modal-content">

      <div class="modal-header">
        <h4 class="modal-title" id="myLargeModalLabel">Protocol Report Preview</h4>
        <button id="modalbtncross" type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        

        
        
        
        
        
      </div>
    </div>
  </div>
</div> 
          
			<div class="page-header">
              <h3 class="page-title"> Search Routine Tests </h3>
              
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
                       	<?php if ($_SESSION['is_super_admin']=="Yes")
                       	{
                       	    try {
                       	        $results = DB::query("SELECT unit_id, unit_name FROM units where unit_status='Active' ORDER BY unit_name ASC");
                       	        
                       	        if(!empty($results))
                       	        {
                       	            $output = ""; // Initialize output variable
                       	            foreach ($results as $row) {
                       	                $output .= "<option value='" . htmlspecialchars($row['unit_id'], ENT_QUOTES, 'UTF-8') . "'>" . htmlspecialchars($row['unit_name'], ENT_QUOTES, 'UTF-8') . "</option>";
                       	            }
                       	            
                       	            echo $output;
                       	        }
                       	    } catch (Exception $e) {
                       	        require_once('core/error/error_logger.php');
                       	        logDatabaseError("Error fetching units: " . $e->getMessage(), [
                       	            'operation_name' => 'search_rt_report_units_load',
                       	            'unit_id' => null,
                       	            'val_wf_id' => null,
                       	            'equip_id' => null
                       	        ]);
                       	    }
                       	}
                       	else 
                       	{
                       	    try {
                       	        $unit_name = DB::queryFirstField("SELECT unit_name FROM units WHERE unit_id = %i and unit_status='Active'", intval($_SESSION['unit_id']));
                       	        
                       	        echo "<option value='" . htmlspecialchars($_SESSION['unit_id'], ENT_QUOTES, 'UTF-8') . "'>" . htmlspecialchars($unit_name, ENT_QUOTES, 'UTF-8') . "</option>";
                       	    } catch (Exception $e) {
                       	        require_once('core/error/error_logger.php');
                       	        logDatabaseError("Error fetching unit name: " . $e->getMessage(), [
                       	            'operation_name' => 'search_rt_report_unit_name_query',
                       	            'unit_id' => intval($_SESSION['unit_id']),
                       	            'val_wf_id' => null,
                       	            'equip_id' => null
                       	        ]);
                       	    }
                       	}
                       	?>	
                        </select>
                      </div>
                    
                    
                    
                    
                    
                    
                      <div class="form-group col-md-6">
                        <label for="exampleInputName1">Equipment ID</label>
                       <select class="form-control" id="equipmentid" name="equipmentid">
                       	
                       </select>
                      </div>
                      
  </div>                    
                      
                      <div class="form-group">
                        <label for="wfstageid">Workflow Stage</label>
                        <select class="form-control" id="wfstageid" name="wfstageid">
                         
                          <option value='0'>All</option>
                          <option value='1'>Workflow Initiated</option>
                         
                          <option value='5'>Workflow Approved</option>
                          <option value='6'>Workflow Not Initiated</option>
                       
                        </select>
                      </div>
                      
                      
                      
                      <div class="form-row">
      <div class="form-group col-md-6">
                        <label for="planned_start_from">Planned Start Date (From)</label>
                        <input type="text" class="form-control" id="planned_start_from" name="planned_start_from"/>
                      </div>
    <div class="form-group col-md-6">
                        <label for="planned_start_to">Planned Start Date (To)</label>
                        <input type="text" class="form-control" id="planned_start_to" name="planned_start_to"/>
                      </div>
                      
  </div>
        
     
  
                <div class="form-row">
      <div class="form-group col-md-6">
                        <label for="actual_start_from">Actual Start Date (From)</label>
                        <input type="text" class="form-control" id="actual_start_from" name="actual_start_from"/>
                      </div>
    <div class="form-group col-md-6">
                        <label for="actual_start_to">Actual Start Date (To)</label>
                        <input type="text" class="form-control" id="actual_start_to" name="actual_start_to"/>
                      </div>
                      
  </div>
        
               
                      
                      
                      <input type="submit" id="generatereport" class="btn btn-gradient-original-success mr-2"/>
                      
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

