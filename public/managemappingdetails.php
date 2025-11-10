<?php
require_once('./core/config/config.php');

// Start the session
//session_start();


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
if (isset($_GET['m']) && $_GET['m'] != 'a' && (!isset($_GET['mapping_id']) || !is_numeric($_GET['mapping_id']))) {
    header('HTTP/1.1 400 Bad Request');
    header('Location: ' . BASE_URL . 'error.php?msg=invalid_parameters');
    exit();
}

require_once 'core/config/db.class.php';

if (isset($_GET['m']) && $_GET['m'] != 'a') {
    try {
        $testresult = DB::queryFirstRow(
            "SELECT t1.equipment_id, t4.equipment_code, t1.test_id, t2.test_performed_by, 
             t1.test_type, t1.frequency_label, t1.vendor_id, t1.mapping_status, t2.test_name, t2.test_description, t2.test_purpose
             FROM equipment_test_vendor_mapping t1 
             INNER JOIN tests t2 ON t1.test_id = t2.test_id 
             INNER JOIN equipments t4 ON t1.equipment_id = t4.equipment_id 
             WHERE t1.mapping_id = %d", 
            intval($_GET['mapping_id'])
        );
        
        if (!$testresult) {
            header('HTTP/1.1 404 Not Found');
            header('Location: ' . BASE_URL . 'error.php?msg=mapping_not_found');
            exit();
        }
    } catch (Exception $e) {
        error_log("Error fetching mapping details: " . $e->getMessage());
        header('HTTP/1.1 500 Internal Server Error');
        header('Location: ' . BASE_URL . 'error.php?msg=database_error');
        exit();
    }
}

try {
    $test_details = DB::query("SELECT test_id, test_description, test_performed_by FROM tests WHERE test_status = %s", 'Active');
    $vendor_details = DB::query("SELECT vendor_id, vendor_name FROM vendors WHERE vendor_status = %s", 'Active');
} catch (Exception $e) {
    error_log("Error loading test/vendor details: " . $e->getMessage());
    $test_details = [];
    $vendor_details = [];
}

?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include_once "assets/inc/_header.php";?>
   <script> 
      $(document).ready(function(){
 
 var originalValue = $("#vendor_id").val();
        // Store the original value as a data attribute
        $("#vendor_id").data("originalValue", originalValue);

    


 
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
  
  
$('#unit_id').change(function() { 
 var item=$(this);
    
    fetchEquipments(item.val());

});
 
    
    
    
   

 
 if($('#test_id').val().includes("External"))
    {
    	$( "#vendor_id" ).prop( "disabled", false );
    $('#test_performed_by').val('External');
    }
    else
    {
    // $("#vendor_id").empty();
  $('#vendor_id').val('select');
    	$( "#vendor_id" ).prop( "disabled", true );
    	$('#test_performed_by').val('Internal');
    }
  
  
$('#test_id').change(function() {    
    var item=$(this);	
    //alert(item.val());
    
    
    
    if(item.val().includes("External"))
    {
    	$( "#vendor_id" ).prop( "disabled", false );
    $('#test_performed_by').val('External');
    }
    else
    {
    // $("#vendor_id").empty();
  $('#vendor_id').val('select');
    	$( "#vendor_id" ).prop( "disabled", true );
    	$('#test_performed_by').val('Internal');
    }
    
    
    
});



// Function to submit mapping data after successful authentication
function submitMappingData(mode) {
  $('#pleasewaitmodal').modal('show');
  
  // Prepare data object based on mode
  let data = {
    unit_id: $("#unit_id").val(),
    equipment_id: $("#equipment_id").val(),
    test_id: $("#test_id").val(),
    test_performed_by: $("#test_performed_by").val(),
    vendor_id: $("#vendor_id").val(),
    test_type: $("#test_type").val(),
    frequency_label: $("#frequency_label").val(),
    mapping_status: $("#test_status").val(), // Use mapping_status instead of test_status
    mode: mode
  };
  
  // Add mapping_id for modify mode
  if (mode === 'modify') {
    data.mapping_id = $("#mapping_id").val();
    data.vendorchangeforalltests = vendorchangeforalltests;
  }
  
  // Send AJAX request
  $.get("core/data/save/savemappingdetails.php", data, function(response, status) {
    $('#pleasewaitmodal').modal('hide');
    
    if (mode === 'modify' && vendorchangeforalltests === 1 && response === "success") {
      Swal.fire({
        icon: 'success',
        title: 'Success',
        text: "The mapping record is successfully modified. Vendor changed successfully for all external tests of this equipment."
      }).then((result) => {
        // Build redirect URL with search parameters if available
        let redirectUrl = "searchmapping.php";
        <?php if (isset($_GET['from_search']) && $_GET['from_search'] == '1'): ?>
          const urlParams = new URLSearchParams();
          <?php if (isset($_GET['unitid'])): ?>urlParams.set('unitid', '<?= htmlspecialchars($_GET['unitid'], ENT_QUOTES) ?>');<?php endif; ?>
          <?php if (isset($_GET['dept_id'])): ?>urlParams.set('dept_id', '<?= htmlspecialchars($_GET['dept_id'], ENT_QUOTES) ?>');<?php endif; ?>
          <?php if (isset($_GET['equipment_type'])): ?>urlParams.set('equipment_type', '<?= htmlspecialchars($_GET['equipment_type'], ENT_QUOTES) ?>');<?php endif; ?>
          <?php if (isset($_GET['equipment_id'])): ?>urlParams.set('equipment_id', '<?= htmlspecialchars($_GET['equipment_id'], ENT_QUOTES) ?>');<?php endif; ?>
          <?php if (isset($_GET['etv_mapping_filter'])): ?>urlParams.set('etv_mapping_filter', '<?= htmlspecialchars($_GET['etv_mapping_filter'], ENT_QUOTES) ?>');<?php endif; ?>
          urlParams.set('restore_search', '1');
          redirectUrl += '?' + urlParams.toString();
        <?php endif; ?>
        window.location = redirectUrl;
      });
    } else if (response === "success") {
      Swal.fire({
        icon: 'success',
        title: 'Success',
        text: "The mapping record is successfully " + (mode === 'add' ? "added" : "modified") + "!"
      }).then((result) => {
        // Build redirect URL with search parameters if available
        let redirectUrl = "searchmapping.php";
        <?php if (isset($_GET['from_search']) && $_GET['from_search'] == '1'): ?>
          const urlParams = new URLSearchParams();
          <?php if (isset($_GET['unitid'])): ?>urlParams.set('unitid', '<?= htmlspecialchars($_GET['unitid'], ENT_QUOTES) ?>');<?php endif; ?>
          <?php if (isset($_GET['dept_id'])): ?>urlParams.set('dept_id', '<?= htmlspecialchars($_GET['dept_id'], ENT_QUOTES) ?>');<?php endif; ?>
          <?php if (isset($_GET['equipment_type'])): ?>urlParams.set('equipment_type', '<?= htmlspecialchars($_GET['equipment_type'], ENT_QUOTES) ?>');<?php endif; ?>
          <?php if (isset($_GET['equipment_id'])): ?>urlParams.set('equipment_id', '<?= htmlspecialchars($_GET['equipment_id'], ENT_QUOTES) ?>');<?php endif; ?>
          <?php if (isset($_GET['etv_mapping_filter'])): ?>urlParams.set('etv_mapping_filter', '<?= htmlspecialchars($_GET['etv_mapping_filter'], ENT_QUOTES) ?>');<?php endif; ?>
          urlParams.set('restore_search', '1');
          redirectUrl += '?' + urlParams.toString();
        <?php endif; ?>
        window.location = redirectUrl;
      });
    } else {
      // Handle error responses with specific messages
      let errorMessage = 'An unexpected error occurred. Please try again.';
      let errorTitle = 'Error';
      
      // Check if response is JSON with error message
      try {
        const errorData = JSON.parse(response);
        if (errorData && errorData.error) {
          errorMessage = errorData.error;
          errorTitle = 'Validation Error';
        }
      } catch (e) {
        // If not JSON, check if response contains meaningful error text
        if (typeof response === 'string' && response.trim() !== '' && response !== 'failure') {
          errorMessage = response;
        } else if (response === 'failure') {
          errorMessage = 'The operation failed. Please check your input and try again.';
        }
      }
      
      Swal.fire({
        icon: 'error',
        title: errorTitle,
        text: errorMessage
      }).then((result) => {
        // Build redirect URL with search parameters if available
        let redirectUrl = "searchmapping.php";
        <?php if (isset($_GET['from_search']) && $_GET['from_search'] == '1'): ?>
          const urlParams = new URLSearchParams();
          <?php if (isset($_GET['unitid'])): ?>urlParams.set('unitid', '<?= htmlspecialchars($_GET['unitid'], ENT_QUOTES) ?>');<?php endif; ?>
          <?php if (isset($_GET['dept_id'])): ?>urlParams.set('dept_id', '<?= htmlspecialchars($_GET['dept_id'], ENT_QUOTES) ?>');<?php endif; ?>
          <?php if (isset($_GET['equipment_type'])): ?>urlParams.set('equipment_type', '<?= htmlspecialchars($_GET['equipment_type'], ENT_QUOTES) ?>');<?php endif; ?>
          <?php if (isset($_GET['equipment_id'])): ?>urlParams.set('equipment_id', '<?= htmlspecialchars($_GET['equipment_id'], ENT_QUOTES) ?>');<?php endif; ?>
          <?php if (isset($_GET['etv_mapping_filter'])): ?>urlParams.set('etv_mapping_filter', '<?= htmlspecialchars($_GET['etv_mapping_filter'], ENT_QUOTES) ?>');<?php endif; ?>
          urlParams.set('restore_search', '1');
          redirectUrl += '?' + urlParams.toString();
        <?php endif; ?>
        window.location = redirectUrl;
      });
    }
  }).fail(function(xhr, status, error) {
    // Handle AJAX errors (network issues, server errors, etc.)
    $('#pleasewaitmodal').modal('hide');
    
    let errorMessage = 'Network error or server unavailable. Please try again later.';
    
    if (xhr.status === 403) {
      errorMessage = 'Access denied. Your session may have expired. Please log in again.';
    } else if (xhr.status === 429) {
      errorMessage = 'Too many requests. Please wait a moment before trying again.';
    } else if (xhr.status >= 500) {
      errorMessage = 'Server error occurred. Please contact support if this persists.';
    } else if (xhr.responseText) {
      try {
        const errorData = JSON.parse(xhr.responseText);
        if (errorData && errorData.error) {
          errorMessage = errorData.error;
        }
      } catch (e) {
        // Keep default message if response isn't JSON
      }
    }
    
    Swal.fire({
      icon: 'error',
      title: 'Request Failed',
      text: errorMessage
    });
  });
}

$("#add_mapping").click(function(e) { 
  e.preventDefault();
  
  var form = document.getElementById('formmappingvalidation'); 
  
  if (form.checkValidity() === false) {  
    form.classList.add('was-validated');  
  } else {
    form.classList.add('was-validated');  
    
    if ($("#unit_id").val()=='select') {
      Swal.fire({
        icon: 'error',
        title: 'Oops...',
        text: 'Please select the unit and the equipment.'
      });
    } else if ($("#equipment_id").val()=='select') {
      Swal.fire({
        icon: 'error',
        title: 'Oops...',
        text: 'Please select the unit and the equipment.'
      });
    } else if ($("#test_id").val()=='select') {
      Swal.fire({
        icon: 'error',
        title: 'Oops...',
        text: 'Please select the test.'
      });
    } else if($("#equipment_id").val()=='select') {
      Swal.fire({
        icon: 'error',
        title: 'Oops...',
        text: 'Please select the equipment.'
      });
    }
  else if($("#test_id").val().includes("External") && $("#vendor_id").val()=='select')
  {
  	Swal.fire({
    					icon: 'error',
                        title: 'Oops...',
                        text: 'Please select the vendor.'                
    				});
  }
   else
   {
  
 	setSuccessCallback(function() {
      submitMappingData('add');
    });
    
    // Show e-signature modal
    $('#enterPasswordRemark').modal('show');
   }
   }
   });

$("#modify_mapping").click(async function(e) { 
   e.preventDefault();
   //alert('hello');
      vendorchangeforalltests=0;
    var form = document.getElementById('formmappingvalidation'); 
  
  if (form.checkValidity() === false) {  
                       
                    form.classList.add('was-validated');  
                    
                    }  
                    else
                    {
                    form.classList.add('was-validated');  
                    
   
  if ($("#test_id").val()=='select'||$("#equipment_id").val()=='select'||($("#test_id").val().includes("External") && $("#vendor_id").val()=='select'))
   {
   
   alert("Details are missing.");
   }
   else
   {
   //alert("hello 1234");
   
   	test=$("#test_id").val();
    
    testid = test.substring(0,test.indexOf('-'));
    
    
  
  
  
  
   var item=$("#vendor_id").val();
   if(item=="select")
   {
   
   	vendorid=0;
    
   }
   else
   {
   
      vendorchangeforalltests=0;
     if ($("#vendor_id").data("originalValue") !== $("#vendor_id").val()) {
      
  const result = await Swal.fire({
            title: 'Are you sure?',
            text: 'The vendor performing the test seems to have changed. Do you want to update the same vendor for all the other external tests mapped to this equipment?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes',
            cancelButtonText: 'No'
        });

 if (result.isConfirmed) {
                // User clicked "Yes," continue with form submission
                vendorchangeforalltests = 1;
            } else {
                // User clicked "No," do not submit the form
                vendorchangeforalltests = 0;
            }


     }
   	vendorid=item;
   }
   
   
   
   	setSuccessCallback(function() {
      submitMappingData('modify');
    });
    
    // Show e-signature modal
    $('#enterPasswordRemark').modal('show');
   }
   }
   });
    
});
    </script>
    

    
      <link rel="stylesheet" href="assets/css/modern-manage-ui.css">

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
							</span> ETV Mapping Details
						</h3>
						<nav aria-label="breadcrumb">
							<ul class="breadcrumb">
								<li class="breadcrumb-item active" aria-current="page"><span><a class='btn btn-gradient-info btn-sm btn-rounded'
										href="searchmapping.php<?php
                                        // Build back navigation URL with search parameters
                                        if (isset($_GET['from_search']) && $_GET['from_search'] == '1') {
                                            $back_params = [];
                                            if (isset($_GET['unitid'])) $back_params['unitid'] = $_GET['unitid'];
                                            if (isset($_GET['dept_id'])) $back_params['dept_id'] = $_GET['dept_id'];
                                            if (isset($_GET['equipment_type'])) $back_params['equipment_type'] = $_GET['equipment_type'];
                                            if (isset($_GET['equipment_id'])) $back_params['equipment_id'] = $_GET['equipment_id'];
                                            if (isset($_GET['etv_mapping_filter'])) $back_params['etv_mapping_filter'] = $_GET['etv_mapping_filter'];
                                            $back_params['restore_search'] = '1';
                                            echo '?' . http_build_query($back_params);
                                        }
                                    ?>"><i class="mdi mdi-arrow-left"></i> Back</a> </span>
								</li>
							</ul>
						</nav>
					</div>
					
					
					
		<div class="row">

	<div class="col-lg-12 grid-margin stretch-card">
                <div class="card">
                  <div class="card-body">
                    <h4 class="card-title">Mapping Details</h4>
                    <p class="card-description"> 
                    </p>
                    
                    
                    
                    
                    
                    
                    






								        <form id="formmappingvalidation" class="needs-validation" novalidate>
								        	<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
								        	<?php 
								        if (isset($_GET['m']) && $_GET['m'] != 'a') {
								        	echo '<input type="hidden" id="mapping_id" name="mapping_id" value="' . intval($_GET['mapping_id']) . '" />';
								        }
								        ?>
								        
								        
								     
							<div class="form-row">
							<div class="form-group  col-md-6">
                        <label for="exampleSelectGender">Unit</label>
                        <select class="form-control" id="unit_id" name="unit_id" <?php echo ($_GET['m']!='a')?'disabled':'' ; ?>>
									
									<option value='select'>Select </option>
                       <?php 
						
				            if ($_SESSION['is_super_admin']=="Yes")
                      {
                       	    try {
                       	        $results = DB::query("SELECT unit_id, unit_name FROM units ORDER BY unit_name");
                       	        $output = '';
                       	        
                       	        if (!empty($results)) {
                       	            foreach ($results as $row) {
                       	                $selected = '';
                       	                if (isset($_GET['unit_id']) && intval($row['unit_id']) == intval($_GET['unit_id'])) {
                       	                    $selected = 'selected';
                       	                }
                       	                $output .= "<option value='" . intval($row['unit_id']) . "' " . $selected . ">" . htmlspecialchars($row['unit_name'], ENT_QUOTES, 'UTF-8') . "</option>";
                       	            }
                       	            echo $output;
                       	        }
                       	    } catch (Exception $e) {
                       	        error_log("Error loading units: " . $e->getMessage());
                       	        echo "<option value=''>Error loading units</option>";
                       	    }
                       	    
                       	    
                       	    
                       	}
                       	else {
                       	    //echo "<option>".$_SESSION['unit_id']."</option>";
                       	    
                       	    try {
                       	        $unit_name = DB::queryFirstField("SELECT unit_name FROM units WHERE unit_id = %i and unit_status='Active'", intval($_SESSION['unit_id']));
                       	        $selected = (isset($_GET['m']) && $_GET['m'] != 'a') ? 'selected' : '';
                       	        echo "<option value='" . intval($_SESSION['unit_id']) . "' " . $selected . ">" . htmlspecialchars($unit_name, ENT_QUOTES, 'UTF-8') . "</option>";
                       	    } catch (Exception $e) {
                       	        error_log("Error loading user unit: " . $e->getMessage());
                       	        echo "<option value=''>Error loading unit</option>";
                       	    }
                       	}
                    
                       	
                       	 	
                       	    
                       	    
                       
                       	?>	
                        </select>
                      </div>
                      
                      <div class="form-group  col-md-6">
                        <label for="exampleSelectGender">Equipment Code</label>
                        
                    	  <select class="form-control" id="equipment_id" name="equipment_id" <?php echo ($_GET['m']!='a')?'disabled':'' ; ?> required>
									
							<option value='select'>Select </option>			
                       	<?php 
                       
                       	if($_GET['m']!='a')
                       	{
                       	    $output='';
                       	    $equipment_details=DB::queryFirstRow("select equipment_code from equipments where equipment_id=".$_GET['equip_id']." and equipment_status='Active'");
                       	    $output=$output. "<option value='".$_GET['equip_id']."' selected>".$equipment_details['equipment_code']."</option>";
                       	    echo $output;
                       	}
                       	
                       	
                       
                       	?>	
                        </select>
                     <div class="invalid-feedback">  
                                            Please select the equipment.  
                                        </div>
                    
                    
                    
                      </div>
                      
                      
                      
                      
                      
							</div>
							<div class="form-row">
                    
                    
                    
                    <div class="form-group  col-md-6">
                        <label for="exampleSelectGender">Test Name</label>
                      
                     
                      <select class="form-control" id="test_id" name="test_id" <?php echo ($_GET['m']=='r')?'disabled':'' ; ?> required>
									
										<option value='select'>Select </option>
                       	<?php 
                       
                       	if (!empty($test_details)) {
                       	    foreach ($test_details as $test) {
                       	        $selected = '';
                       	        if (isset($_GET['test_id']) && intval($test['test_id']) == intval($_GET['test_id'])) {
                       	            $selected = 'selected';
                       	        }
                       	        $option_value = intval($test['test_id']) . '-' . htmlspecialchars($test['test_performed_by'], ENT_QUOTES, 'UTF-8');
                       	        echo "<option value='" . $option_value . "' " . $selected . ">" . htmlspecialchars($test['test_description'], ENT_QUOTES, 'UTF-8') . "</option>";
                       	    }
                       	}
                       
                       	?>	
                        </select>
                     
                     <div class="invalid-feedback">  
                                            Please select the test.  
                                        </div>
                     
                     
                     
                     
                     
                     
                     
                     
                      </div>
                      
                       <div class="form-group  col-md-6">
                        <label for="exampleSelectGender">Performed By</label>
                        <select class="form-control" id="test_performed_by" name="test_performed_by"  disabled>
									
										<option value='select'>Select </option>
                       	<?php 
                       	
                       	echo "<option value='Internal' ". ($_GET['m']!='a' && (trim($testresult['test_performed_by'])=='Internal')? "selected" : "") .">Internal Team</option>";
                       	echo "<option value='External' ". ($_GET['m']!='a' && (trim($testresult['test_performed_by'])=='External')? "selected" : "") .">External Team</option>";
                      
                       	    
                       
                       	?>	
                        </select>
                      </div>
                      </div>
                      
                      <div class="form-row">
                       <div class="form-group  col-md-6">
                        <label for="exampleSelectGender">Vendor</label>
                      <select class="form-control" id="vendor_id" name="vendor_id" <?php echo ($_GET['m']=='r')?'disabled':'' ; ?>>
									
										<option value='select'>Select </option>
                       	<?php 
                       
                       	if (!empty($vendor_details)) {
                       	    foreach ($vendor_details as $vendor) {
                       	        $selected = '';
                       	        if (isset($_GET['m']) && $_GET['m'] != 'a' && isset($testresult['vendor_id']) && intval($testresult['vendor_id']) == intval($vendor['vendor_id'])) {
                       	            $selected = 'selected';
                       	        }
                       	        echo "<option value='" . intval($vendor['vendor_id']) . "' " . $selected . ">" . htmlspecialchars($vendor['vendor_name'], ENT_QUOTES, 'UTF-8') . "</option>";
                       	    }
                       	}
                       
                       	?>	
                        </select>
                      </div>
                      
                      
                      <div class="form-group  col-md-6">
                        <label for="exampleSelectGender">Test Category</label>
                        <select class="form-control" id="test_type" name="test_type" <?php echo ($_GET['m']=='r')?'readonly':'' ; ?>>
									
										
                       	<?php 
                       	echo "<option value='Validation_Test'".($_GET['m']!='a' && ($testresult['test_status']=='Active')? "selected" : "") .">Validation Test</option>";
                       	echo "<option value='Routine_Test'".($_GET['m']!='a' && ($testresult['test_status']=='Inactive')? "selected" : "") .">Routine Test</option>";
                 
                       	
                       	    
                       	    
                       
                       	?>	
                        </select> </div>
                      
                      <div class="form-group col-md-6">
                        <label for="frequency_label">Frequency Label <span class="text-danger">*</span></label>
                        <select class="form-control" id="frequency_label" name="frequency_label" required <?php echo ($_GET['m']=='r')?'disabled':'' ; ?>>
                          <option value="">Select Frequency</option>
                          <option value="ALL"<?php echo ($_GET['m']!='a' && ($testresult['frequency_label']=='ALL')? ' selected' : ''); ?>>ALL (All Frequencies)</option>
                          <option value="6M"<?php echo ($_GET['m']!='a' && ($testresult['frequency_label']=='6M')? ' selected' : ''); ?>>6M (Six Monthly)</option>
                          <option value="Y"<?php echo ($_GET['m']!='a' && ($testresult['frequency_label']=='Y')? ' selected' : ''); ?>>Y (Yearly)</option>
                          <option value="2Y"<?php echo ($_GET['m']!='a' && ($testresult['frequency_label']=='2Y')? ' selected' : ''); ?>>2Y (Bi-Yearly)</option>
                        </select>
                      </div>
                      
                      </div>
                      
                     <div class="form-row">
                     <div class="form-group  col-md-6">
                        <label for="exampleSelectGender">Status</label>
                        <select class="form-control" id="test_status" name="test_status" <?php echo ($_GET['m']=='r')?'readonly':'' ; ?>>
									
										
                       	<?php 
                       	echo "<option value='Active'".($_GET['m']!='a' && ($testresult['mapping_status']=='Active')? "selected" : "") .">Active</option>";
                       	echo "<option value='Inactive'".($_GET['m']!='a' && ($testresult['mapping_status']=='Inactive')? "selected" : "") .">Inactive</option>";
                 
                       	
                       	    
                       	    
                       
                       	?>	
                        </select> </div>
                     
                     
                     </div>
							
					
					
                    
                    
                       
                      
                  
                   	
				
									
					
					
					<div class="d-flex justify-content-center"> 


 <?php
                  
                  if($_GET['m']=='m'){
                      ?>
                  <button  id="modify_mapping"	class='btn btn-gradient-success btn-icon-text'><i class="mdi mdi-content-save"></i> Modify Mapping</button>    
                  <?php     
                  }
                  else if($_GET['m']=='a'){
                      ?>
                  <button  id="add_mapping"	class='btn btn-gradient-primary btn-icon-text'><i class="mdi mdi-plus-circle"></i> Add Mapping</button>    
                  <?php  
                  }
                  
                  
                  ?>

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
