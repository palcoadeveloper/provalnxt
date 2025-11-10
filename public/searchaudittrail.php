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

    <link rel="stylesheet" href="assets/css/modern-manage-ui.css">

    <style>
    /* Modern DataTable Styling */
    #datagrid-audit-trail {
        border-collapse: separate;
        border-spacing: 0;
        border-radius: 8px !important;
        overflow: hidden;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }
    
    #datagrid-audit-trail thead th {
        background: linear-gradient(135deg, #b967db 0%, #9c4ac7 100%) !important;
        color: white !important;
        font-weight: 600 !important;
        text-transform: none !important;
        font-size: 0.85rem !important;
        letter-spacing: 0.3px !important;
        padding: 15px 12px !important;
        border: none !important;
        position: relative !important;
        vertical-align: middle !important;
        text-align: center !important;
    }
    
    #datagrid-audit-trail thead th:first-child {
        border-top-left-radius: 8px;
    }
    
    #datagrid-audit-trail thead th:last-child {
        border-top-right-radius: 8px;
    }
    
    #datagrid-audit-trail tbody td {
        padding: 12px !important;
        vertical-align: middle !important;
        border-bottom: 1px solid #e3e6f0 !important;
        transition: all 0.3s ease !important;
    }
    
    #datagrid-audit-trail tbody td:last-child {
        text-align: center !important;
    }
    
    #datagrid-audit-trail tbody tr {
        transition: all 0.3s ease !important;
    }
    
    #datagrid-audit-trail tbody tr:hover {
        background-color: #f8f9fe !important;
        transform: scale(1.02) !important;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1) !important;
    }
    
    #datagrid-audit-trail tbody tr:nth-child(even) {
        background-color: #fafbfc !important;
    }
    
    /* DataTable Controls Styling */
    .dataTables_wrapper .dataTables_length select {
        padding: 6px 12px !important;
        border: 2px solid #e3e6f0 !important;
        border-radius: 6px !important;
        background-color: white !important;
        transition: all 0.3s ease !important;
    }
    
    .dataTables_wrapper .dataTables_length select:focus {
        border-color: #b967db !important;
        outline: none !important;
        box-shadow: 0 0 0 3px rgba(185, 103, 219, 0.1) !important;
    }
    
    .dataTables_wrapper .dataTables_filter input {
        padding: 8px 16px !important;
        border: 2px solid #e3e6f0 !important;
        border-radius: 25px !important;
        background-color: white !important;
        transition: all 0.3s ease !important;
        width: 250px !important;
    }
    
    .dataTables_wrapper .dataTables_filter input:focus {
        border-color: #348fe2 !important;
        outline: none !important;
        box-shadow: 0 0 0 3px rgba(52, 143, 226, 0.1) !important;
        width: 300px !important;
    }
    
    .dataTables_wrapper .dataTables_paginate .paginate_button {
        padding: 8px 16px !important;
        margin: 0 2px !important;
        border: 1px solid #e3e6f0 !important;
        border-radius: 6px !important;
        background: white !important;
        color: #5a5c69 !important;
        transition: all 0.3s ease !important;
    }
    
    .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
        background: #b967db !important;
        color: white !important;
        border-color: #b967db !important;
        transform: translateY(-1px) !important;
    }
    
    .dataTables_wrapper .dataTables_paginate .paginate_button.current {
        background: linear-gradient(135deg, #b967db 0%, #9c4ac7 100%) !important;
        color: white !important;
        border-color: #b967db !important;
        font-weight: 600 !important;
    }
    
    .dataTables_wrapper .dataTables_info {
        color: #6c757d !important;
        font-weight: 500 !important;
        padding-top: 12px !important;
    }
    
    /* Action Buttons Enhancement */
    #datagrid-audit-trail .btn {
        border-radius: 6px !important;
        font-size: 0.75rem !important;
        padding: 0.375rem 0.75rem !important;
        font-weight: 500 !important;
        transition: all 0.3s ease !important;
        text-transform: none !important;
        letter-spacing: 0.3px !important;
    }
    
    #datagrid-audit-trail .btn:hover {
        transform: translateY(-2px) !important;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15) !important;
    }
    
    /* Loading and Processing States */
    .dataTables_processing {
        background: rgba(255, 255, 255, 0.9) !important;
        border: 1px solid #ddd !important;
        border-radius: 8px !important;
        color: #348fe2 !important;
        font-weight: 600 !important;
        padding: 16px 24px !important;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1) !important;
    }

    /* Form Spacing and Layout */
    .forms-sample {
        margin-bottom: 1.5rem !important;
    }
    
    .forms-sample .form-row {
        margin-bottom: 1rem !important;
    }
    
    .forms-sample .form-group {
        margin-bottom: 1.25rem !important;
    }
    
    .forms-sample .form-group:last-child {
        margin-bottom: 0 !important;
    }
    
    /* Form Controls Enhancement */
    .forms-sample .form-control {
        border: 2px solid #e3e6f0 !important;
        border-radius: 8px !important;
        padding: 12px 16px !important;
        font-size: 0.95rem !important;
        transition: all 0.3s ease !important;
        background-color: #fafbfc !important;
    }
    
    .forms-sample .form-control:focus {
        border-color: #348fe2 !important;
        background-color: white !important;
        box-shadow: 0 0 0 3px rgba(52, 143, 226, 0.1) !important;
        outline: none !important;
    }
    
    .forms-sample label {
        font-weight: 600 !important;
        color: #5a5c69 !important;
        margin-bottom: 8px !important;
        font-size: 0.9rem !important;
        text-transform: uppercase !important;
        letter-spacing: 0.5px !important;
    }
    
    /* Date Picker Enhancement */
    .ui-datepicker {
        border: 2px solid #b967db !important;
        border-radius: 8px !important;
        box-shadow: 0 4px 12px rgba(185, 103, 219, 0.2) !important;
    }
    
    .ui-datepicker .ui-datepicker-header {
        background: linear-gradient(135deg, #b967db 0%, #9c4ac7 100%) !important;
        color: white !important;
        border: none !important;
        font-weight: 600 !important;
    }
    
    .ui-datepicker .ui-datepicker-title {
        color: white !important;
    }
    
    .ui-datepicker .ui-datepicker-prev,
    .ui-datepicker .ui-datepicker-next {
        border: none !important;
        background: none !important;
    }
    
    .ui-datepicker .ui-datepicker-prev:hover,
    .ui-datepicker .ui-datepicker-next:hover {
        background: rgba(255, 255, 255, 0.2) !important;
        border-radius: 4px !important;
    }
    
    /* Responsive Improvements */
    @media (max-width: 768px) {
        .dataTables_wrapper .dataTables_filter input {
            width: 200px !important;
        }
        
        .dataTables_wrapper .dataTables_filter input:focus {
            width: 240px !important;
        }
        
        #datagrid-audit-trail tbody tr:hover {
            transform: none !important;
        }
        
        .forms-sample .btn-gradient-primary:hover,
        .forms-sample .btn-gradient-success:hover {
            transform: none !important;
        }
        
        .forms-sample .btn-gradient-primary,
        .forms-sample .btn-gradient-success {
            margin-bottom: 0.5rem !important;
            width: 100% !important;
        }
    }
    </style>
    
    <script>
    $(document).ready(function(){
    
      // Function to convert date format
            function convertDateFormat(dateString) {
                var dateParts = dateString.split('.');
                return dateParts[2] + '-' + dateParts[1] + '-' + dateParts[0];
            }
   


$('#datagrid-audit-trail').DataTable({
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
                            "search": "Search audit trail:",
                            "lengthMenu": "Show _MENU_ entries",
                            "info": "Showing _START_ to _END_ of _TOTAL_ audit trail entries"
                        }
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
                    
                    // Small delay to ensure DOM is ready, then initialize modern DataTable
                    setTimeout(function() {
                        // Destroy existing DataTable if it exists
                        if ($.fn.DataTable.isDataTable('#datagrid-audit-trail')) {
                            $('#datagrid-audit-trail').DataTable().destroy();
                        }
                        
                        // Initialize modern DataTable with enhanced features
                        $('#datagrid-audit-trail').DataTable({
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
                                "search": "Search audit trail:",
                                "lengthMenu": "Show _MENU_ entries",
                                "info": "Showing _START_ to _END_ of _TOTAL_ audit trail entries"
                            }
                        });
                    }, 100); // 100ms delay
                       
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
                       	        $results = DB::query("SELECT unit_id, unit_name FROM units where unit_status='Active' ORDER BY unit_name ASC");
                       	        
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
                       	        $unit_name = DB::queryFirstField("SELECT unit_name FROM units WHERE unit_id = %i and unit_status='Active'", intval($_SESSION['unit_id']));
                       	        
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
                      
                      
                      <button type="submit" id="generatereport" class="btn btn-gradient-primary btn-icon-text mr-2">
                        <i class="mdi mdi-file-chart"></i> Generate Report
                      </button>

                      <button type="button" id="downloadreport" class="btn btn-gradient-danger btn-icon-text mr-2">
                        <i class="mdi mdi-file-pdf"></i> Export Report (PDF)
                      </button>
                    </form>
                  </div>
                </div>
              </div>
            
            
            <div class="col-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                <h4 class="card-title">Result</h4>
                    
                    <div class="table-responsive-xl">
                <div id="displayresults"> <div class="text-center text-muted py-4">
                        <i class="mdi mdi-filter-variant icon-lg mb-2"></i>
                        <p> Use the search filters above to generate a report</p>
                      </div></div>
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
