<?php
require_once('./core/config/config.php');

// Session is already started by config.php via session_init.php

// Validate session timeout
require_once('core/security/session_timeout_middleware.php');
validateActiveSession();
require_once 'core/config/db.class.php';

// Use centralized session validation
require_once('core/security/session_validation.php');
validateUserSession();

// Define workflow stage constants for better code readability
define('DEPT_ENGINEERING', 1);
define('DEPT_QA', 2);
define('STAGE_NEW_TASK', '1');
define('STAGE_PENDING_APPROVAL', '2');
define('STAGE_APPROVED', '3');

// Generate CSRF token if not already set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

?>
<!DOCTYPE html>
<html lang="en">
  <head>
   <?php include_once "assets/inc/_header.php";?> 
 

<style>
  .ui-autocomplete {
    max-height: 100px;
    overflow-y: auto;
    /* prevent horizontal scrollbar */
    overflow-x: hidden;
  }
  /* IE 6 doesn't support max-height
   * we use height instead, but this forces the menu to always be this tall
   */
  * html .ui-autocomplete {
    height: 100px;
  }

  .input-with-spinner {
        position: relative;
    }

    .with-spinner {
        padding-right: 25px; /* Adjust this value based on the width of your spinner image */
    }

    .spinner {
        position: absolute;
        top: 70%;
        right: 15px; /* Adjust this value based on your design */
        transform: translateY(-50%);
        display: none;
    }

    /* Mobile responsive improvements */
    @media (max-width: 767.98px) {
        .card-body {
            padding: 1rem;
        }
        
        .form-group label {
            font-size: 0.9rem;
            font-weight: 600;
        }
        
        .form-control {
            font-size: 1rem;
            padding: 0.5rem 0.75rem;
        }
        
        .btn {
            width: 100%;
            margin-bottom: 0.5rem;
        }
        
        .page-title {
            font-size: 1.5rem;
        }
        
        .breadcrumb-item .btn {
            width: auto;
            margin-bottom: 0;
        }
        
        /* Improve jQuery UI datepicker on mobile */
        .ui-datepicker {
            font-size: 0.9rem;
        }
        
        .ui-datepicker td {
            padding: 0.2rem;
        }
        
        .ui-autocomplete {
            max-width: 90vw;
            font-size: 0.9rem;
        }
    }
    
    /* Tablet responsive improvements */
    @media (min-width: 768px) and (max-width: 991.98px) {
        .card-body {
            padding: 1.25rem;
        }
        
        .form-control {
            font-size: 0.95rem;
        }
    }

</style>
    <script>
    $(document).ready(function(){
    	
        
  $( function() {
  
	$("#sch_date").datepicker({
   dateFormat: 'dd.mm.yy', 
   changeMonth: true
});

  } );
  var spinner = $('<img>', { src: 'assets/images/wait_spinner.gif', class: 'spinner' }).hide();
    $('.with-spinner').after(spinner);

$( "#equipmentid" ).autocomplete({
    source: function(request, response) {
		 $('.spinner').show(); // Show spinner
        $.getJSON(
            "core/data/get/fetchequipment.php",
            { term: request.term, unitid: $('#unitid').val() }, 
            function(data) {
				   $('.spinner').hide(); // Hide spinner
                if (data.length === 0) {
                    // No matching records found
                    response([{ label: 'No matching records found', value: '', id: '' }]);
                } else {
                    // Matching records found
                    response(data);
                }
            }
        );
    },
    minLength: 2,
    change: function(event, ui) {
        if (ui.item === null) {
            $(this).val('');
            $('#equipmentid').data('equip-id', '');
        }
    },
    select: function(event, ui) {
        event.preventDefault();
        $('#equipmentid').val(ui.item.value);
        $('#equipmentid').data('equip-id', ui.item.id);
    }
});



		$(function(){
			var dtToday = new Date();
			
			var month = dtToday.getMonth() + 1;
			var day = dtToday.getDate();
			var year = dtToday.getFullYear();
			if(month < 10)
				month = '0' + month.toString();
			if(day < 10)
				day = '0' + day.toString();
			
			var maxDate = year + '-' + month + '-' + day;
			//alert(maxDate);
			$('#sch_date').attr('min', maxDate);
		});

		$('#unitid').change(function() {    
    	  		
			var item=this.value;
            if(item=='select'){

                $('#equipmentid').prop('disabled', true);
            }
            else
            {
                $('#equipmentid').prop('disabled', false);
                $("#equipmentid").empty();
    
    	//	$.get("assets/inc/_getequipmentdetailswoall.php",
		//	{
		//		unitid: item
		//	},
		//	function(data, status){
		//		$("#equipmentid").html(data);
		//	});
            }
			
 
		});


		$('#equipmentid').change(function() {    
			var item=$('#equipmentid').data('equip-id');
            
		//	$("#test_id").empty();
		//	$.get("assets/inc/_gettestdetails.php",
		//	{
		//		equipmentid: item
		//	},
		//	function(data, status){
		//		$("#test_id").html(data);
		//	});
		});


		$("#addvalrequest").click(function(e) {
			e.preventDefault();
			var form = document.getElementById('formaddroutine'); 
  
			if (form.checkValidity() === false) {  
				form.classList.add('was-validated');  
            }  
            else
            {
                    form.classList.add('was-validated');  

					if($("#unitid").val()==='select')
					{
						Swal.fire({
											icon: 'error',
											title: 'Oops...',
											text: 'Please select Unit.'                
										});
					}
					else if($("#equipmentid").val()==='select' || $("#equipmentid").data('id')=='')
					{
						Swal.fire({
											icon: 'error',
											title: 'Oops...',
											text: 'Please select Equipment.'                
										});
					}
					
					else
					{
						// Store the form data for use in the modal success callback
						var unit=$("#unitid").val();
						var equip_id=$('#equipmentid').data('equip-id')
						var start_date=$("#sch_date").val();
						
						window.valRequestFormData = {
							unitid: unit,
							equipment_id: equip_id,
							startdate: start_date
						};
						
						// Set the workflow IDs for the modal (leave empty since no actual workflow exists yet)
						window.val_wf_id = '';
						window.test_val_wf_id = '';
						window.operation_context = 'adhoc_validation_request_submission';
						
						// Set success callback for the existing modal
						setSuccessCallback(function(response) {
							// Add the validation request after successful authentication
							addValRequestAfterAuth();
						});
						
						// Show the password/remarks modal
						$('#enterPasswordRemark').modal('show');
					}
			}
		});
		
		// Function to add validation request after successful authentication
		function addValRequestAfterAuth() {
			$('#pleasewaitmodal').modal('show');
			
			$.get("core/data/save/savevalrequest.php", {
				unitid: window.valRequestFormData.unitid,
				equipment_id: window.valRequestFormData.equipment_id,
				startdate: window.valRequestFormData.startdate
			})
			.done(function(data) {
				$('#pleasewaitmodal').modal('hide');
				
				// Trim any whitespace from response
				data = data.trim();
				
				if(data === "success") {
					Swal.fire({
						icon: 'success',
						title: 'Success',
						text: "The validation request is successfully added!"                
					}).then((result) => {
						window.location="generateschedule.php";
					});
				} else {
					if (data.includes('Duplicate')) {
						Swal.fire({
							icon: 'error',
							title: 'Duplicate Configuration',
							text: 'The routine test for the equipment already exists.'                
						}).then((result) => {
							window.location="generatescheduleroutinetest.php";
						});
					} else {
						Swal.fire({
							icon: 'error',
							title: 'Oops...',
							text: 'Something went wrong.'                
						}).then((result) => {
							window.location="generateschedule.php";
						});
					}
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
});
    
   
    
    </script>
    
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
              <h3 class="page-title">Add Validation Request Details</h3>
              <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                 <li class="breadcrumb-item"><a class='btn btn-gradient-info btn-sm btn-rounded' href="generateschedule.php"><< Back</a></li>
                  
                </ol>
              </nav>
            </div>
            
            
            <div class="row">
            
            	<div class="col-12 grid-margin stretch-card">
                <div class="card">
                  <div class="card-body">
                    <h4 class="card-title">Validation Request Details</h4>
                    
                    <form id="formaddroutine" class="needs-validation" novalidate>
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
          <div class="form-row">          
                    <div class="form-group col-12 col-sm-6 col-md-6 col-lg-6">
                        <label for="unitid">Unit</label>
                        <select class="form-control" id="unitid" name="unitid">
                          <option value='select'>Select</option>
                       	
						  <?php 
                       try {
						  if ($_SESSION['is_super_admin']=="Yes")
                       		{
                       	    $results = DB::query("SELECT unit_id, unit_name FROM units ORDER BY unit_name");
                       	    
                       	    $output="";
                       	    if(!empty($results))
                       	    {
                       	        foreach ($results as $row) {
                       	            $output .= "<option value='" . htmlspecialchars($row['unit_id'], ENT_QUOTES) . "'>" . 
                       	                      htmlspecialchars($row['unit_name'], ENT_QUOTES) . "</option>";
                       	        }
                       	        
                       	        echo $output;
                       	    }
                       	}
                       	else 
                       	{
                       	    $unit_id = intval($_SESSION['unit_id']);
                       	    $unit_name = DB::queryFirstField("SELECT unit_name FROM units WHERE unit_id = %?", $unit_id);
                       	    
                       	    if ($unit_name) {
                       	        echo "<option value='" . htmlspecialchars($unit_id, ENT_QUOTES) . "'>" . 
                       	             htmlspecialchars($unit_name, ENT_QUOTES) . "</option>";
                       	    }
                       	}
                       } catch (Exception $e) {
                           require_once('core/error/error_logger.php');
                           logDatabaseError("Database error in addvalrequest.php: " . $e->getMessage(), [
                               'operation_name' => 'validation_request_unit_load',
                               'unit_id' => null,
                               'val_wf_id' => null,
                               'equip_id' => null
                           ]);
                           echo "<option value=''>Error loading units</option>";
                       }
                       	?>	
							
                        </select>
                      </div>
                    
                    
                    <div class="form-group col-12 col-sm-6 col-md-6 col-lg-6">
                        <label for="equipmentid">Equipment ID</label>

                        <input class="form-control with-spinner" id="equipmentid" type="text" name="equipmentid" data-equip-id='' disabled required/>
                       
                       <div class="invalid-feedback">  
                                            Please select the equipment.  
                                        </div>
                      </div>
               </div>     
                 
                 
                <div class="form-row">       
                    
                    
                    
                    
                    <div class="form-group col-12 col-sm-6 col-md-6 col-lg-6">
                        <label for="sch_date">Date</label>
                        <input class="form-control" type='text' id='sch_date' name='sch_date' required/>  
                        <div class="invalid-feedback">  
                                            Please select the date.  
                                        </div>
                      </div>
                    
                  </div>  
                    
                    
                     
                    
                      <input type="button" id="addvalrequest" class="btn btn-gradient-primary mr-2" value='Add Validation Request'/>
                      
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
</body>
</html>

