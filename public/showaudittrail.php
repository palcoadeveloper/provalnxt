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
    <!-- Required meta tags -->
     <?php include_once "assets/inc/_header.php";?>
     <meta name="csrf-token" content="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
    <script>
    $(document).ready(function(){
    
      // Function to convert date format
            function convertDateFormat(dateString) {
                var dateParts = dateString.split('.');
                return dateParts[2] + '-' + dateParts[1] + '-' + dateParts[0];
            }
   


$('#datagrid-audit-trail').DataTable({
  "pagingType": "numbers"
});
    

  



 	$("#generatereport").on('click',(function(e) {
e.preventDefault();




			var category=$("#audittrailcategory").val();
			
			var asdf=($("#start_from").val()=='')?'':convertDateFormat($("#start_from").val());
			var asdt=($("#start_to").val()=='')?'':convertDateFormat($("#start_to").val());
      var unit=$("#unit_id").val();
		
	
		$('#pleasewaitmodal').modal('show');
					 $.get("core/data/get/getaudittraillogreport.php",
                      {
                        audittrailcategory:category,
                        start_from:asdf,
                        start_to:asdt,
                        unit_id:unit,
                        export:'no'
                        
                        
                      },
                      function(data, status){
                       $('#pleasewaitmodal').modal('hide');
                    $("#displayresults").html(data);
                    		$('#datagrid-audit-trail').DataTable({
  "pagingType": "numbers"
} );
                       
                      });
 
		
		
		
		
		





}));

// Handle Export Report (PDF) button click - Open in modal
$("#exportreport").on('click',(function(e) {
    e.preventDefault();
    
    var category=$("#audittrailcategory").val();
    var asdf=($("#start_from").val()=='')?'':convertDateFormat($("#start_from").val());
    var asdt=($("#start_to").val()=='')?'':convertDateFormat($("#start_to").val());
    var unit=$("#unit_id").val();
    
    // Build the URL for the PDF modal viewer
    var pdfUrl = "core/data/get/getaudittraillogreport_modal.php?audittrailcategory=" + encodeURIComponent(category) +
                 "&start_from=" + encodeURIComponent(asdf) +
                 "&start_to=" + encodeURIComponent(asdt) +
                 "&unit_id=" + encodeURIComponent(unit) +
                 "&file=report.pdf"; // Add PDF indicator for modal detection
    
    // Create a temporary link to trigger the modal
    var tempLink = $('<a>').attr({
        'href': pdfUrl,
        'data-toggle': 'modal',
        'data-target': '#imagepdfviewerModal',
        'data-title': 'Audit Trail Log Report'
    });
    
    // Trigger the modal
    tempLink.trigger('click');
}));

// Handle Download Report (PDF) button click - Direct download
$("#downloadreport").on('click',(function(e) {
    e.preventDefault();
    e.stopPropagation();
    
    console.log('Download report button clicked'); // Debug log
    
    var category=$("#audittrailcategory").val();
    var asdf=($("#start_from").val()=='')?'':convertDateFormat($("#start_from").val());
    var asdt=($("#start_to").val()=='')?'':convertDateFormat($("#start_to").val());
    var unit=$("#unit_id").val();
    
    console.log('Form values:', category, asdf, asdt, unit); // Debug log
    
    // Build the URL for the PDF modal viewer
    var pdfUrl = "core/data/get/getaudittraillogreport_modal.php?audittrailcategory=" + encodeURIComponent(category) +
                 "&start_from=" + encodeURIComponent(asdf) +
                 "&start_to=" + encodeURIComponent(asdt) +
                 "&unit_id=" + encodeURIComponent(unit) +
                 "&file=report.pdf"; // Add PDF indicator for modal detection
    
    console.log('PDF URL:', pdfUrl); // Debug log
    
    // Create a temporary link to trigger the modal (similar to export button)
    var tempLink = $('<a>').attr({
        'href': pdfUrl,
        'data-toggle': 'modal',
        'data-target': '#imagepdfviewerModal',
        'data-title': 'Audit Trail Log Report'
    }).css('display', 'none'); // Hide the link
    
    console.log('Created temp link with download enabled'); // Debug log
    
    // Add link to DOM, trigger it, then remove it
    $('body').append(tempLink);
    tempLink[0].click(); // Use native click instead of jQuery trigger
    tempLink.remove();
    
    console.log('Triggered modal via temp link'); // Debug log
}));

    $("#start_from").datepicker({
   dateFormat: 'dd.mm.yy', 
   changeMonth: true,
      changeYear: true
});
$("#start_to").datepicker({
   dateFormat: 'dd.mm.yy', 
   changeMonth: true,
      changeYear: true
});


    
    });
    
    
    
    
    </script>
    
  </head>
  <body>
  <?php include_once "assets/inc/_pleasewaitmodal.php"; ?>
    <div class="container-scroller">
       <!-- partial:assets/inc/_navbar.php -->
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
              <h3 class="page-title">Audit Trail Log Report </h3>
              
            </div>
            
            
            <div class="row">
            
            	<div class="col-12 grid-margin stretch-card">
                <div class="card">
                  <div class="card-body">
                    <h4 class="card-title">Select Criteria</h4>
                    
                    <form class="forms-sample" id="formreport" action="core/data/get/getaudittraillogreport.php">
                                 
                     
                      
                      
                      
                   
     
  
                <div class="form-row">
      <div class="form-group col-md-6">
                        <label for="start_from">Start Date (From)</label>
                  
                        <input type="text" class="form-control" id="start_from" name="start_from" >
                      </div>
    <div class="form-group col-md-6">
                        <label for="start_to">End Date (To)</label>
                     
                        <input type="text" class="form-control" id="start_to" name="start_to" >
                      </div>
                      
  </div>
        
           <div class="form-row">
                    
                    
                      <div class="form-group col-md-6">
                        <label for="exampleInputName1">Audit Trail Category</label>
                       <select class="form-control" id=audittrailcategory name="audittrailcategory">
                       		<option value='any'>All</option>
                       		<option value='tran_login_int_emp'>Login - Employee</option>
                           <option value='tran_login_ext_emp'>Login - Vendor</option>
                       		<option value='tran_logout'>Log Out</option>
                       		<option value='tran_password_reset'>Password Reset</option>
                       		
                       		<option value='tran_schgen'>Schedule Generation</option>
                       		<option value='tran_valbgn'>Validation</option>
                       		<option value='tran_approve'>Protocol Review/Approve</option>
                       		<option value='tran_review_approve'>Transaction Review/Approve</option>
                       		
                       		
                       		<option value='master_update'>Master Update</option>
                       		
                       		
                       </select>
                      </div>

                      <div class="form-group col-md-6">
                      <label for="exampleSelectGender">Unit</label>
                        


                      <select class="form-control" id="unit_id" name="unit_id">
                          
                       	<option value="select">Select</option>
                          <?php if ($_SESSION['is_super_admin']=="Yes")
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
                       	        error_log("Error fetching units: " . $e->getMessage());
                       	    }
                       	}
                       	else 
                       	{
                       	    try {
                       	        $unit_name = DB::queryFirstField("SELECT unit_name FROM units WHERE unit_id = %i", intval($_SESSION['unit_id']));
                       	        
                       	        echo "<option value='" . htmlspecialchars($_SESSION['unit_id'], ENT_QUOTES, 'UTF-8') . "'>" . htmlspecialchars($unit_name, ENT_QUOTES, 'UTF-8') . "</option>";
                       	    } catch (Exception $e) {
                       	        error_log("Error fetching unit name: " . $e->getMessage());
                       	    }
                       	}
                       	?>	
                        <option value="99">Vendor</option>
                        </select>





  </div>
                      
  </div>      
                      
                      
                      <input type="submit" id="generatereport" class="btn btn-gradient-primary mr-2" value="Generate Report"/>

                      <input type="button" id="downloadreport" class="btn btn-gradient-success mr-2" value="Export Report (PDF)"/>
                    </form>
                  </div>
                </div>
              </div>
            
            
            <div class="col-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                <h4 class="card-title">Result</h4>
                    
                    <div class="table-responsive-xl">
                <div id="displayresults"> <p class="card-description"> Select the criteria and hit the Generate Report/Export Report button. </p></div>
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
