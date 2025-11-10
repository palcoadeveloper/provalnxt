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

// Check if user belongs to engineering department (department_id = 1)
if (!isset($_SESSION['department_id']) || (int)$_SESSION['department_id'] !== 1) {
    header('Location: ' . BASE_URL . 'home.php?msg=access_denied');
    exit();
}

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
/*	$('#actual_start_from').val(new Date());
	$('#actual_start_to').val(new Date());*/
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



		if(!unit || !equip || !wfstage){

			Swal.fire({
    					icon: 'error',
                        title: 'Oops...',
                        text: 'Please select Unit ID, Equipment and Workflow Stage.'
    				});
		}
		else if ((psdf && !psdt) || (!psdf && psdt) || (psdf > psdt) || (asdf && !asdt) || (!asdf && asdt) || (asdf > asdt))
		{

			Swal.fire({
    					icon: 'error',
                        title: 'Oops...',
                        text: 'Please check the dates.'
    				});

		}

		else
		{
		$('#pleasewaitmodal').modal('show');
					 $.get("core/data/get/getvalidationwfstatus_terminate.php",
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
                                "search": "Search validation reports:",
                                "lengthMenu": "Show _MENU_ entries",
                                "info": "Showing _START_ to _END_ of _TOTAL_ validation reports"
                            }
                        });
                    }, 100); // 100ms delay

                      });

		}






}));

    // Function to handle validation termination with password and remarks
    window.terminateValidation = function(valWfId, equipmentCode) {
        Swal.fire({
            title: 'Terminate Validation?',
            html: `
                <p style="color: #545454; font-size: 1.125em;">Are you sure you want to submit termination request for equipment <strong>${equipmentCode}</strong> (ID: ${valWfId})?</p>
                <div style="text-align: left; margin-top: 20px;">
                    <label for="termination_reason" style="color: #545454; font-size: 1em; font-weight: 500; display: block; margin-bottom: 8px;">Reason for Termination <span style="color: #f27474;">*</span></label>
                    <select id="termination_reason" class="swal2-input" style="display: block; width: 100%; padding: 0.625em; margin: 0 0 1.25em; font-size: 1.125em; border: 1px solid #d9d9d9; border-radius: 0.3125em; box-sizing: border-box;">
                        <option value="Select">Select</option>
                        <option value="Equipment discontinued">Equipment discontinued</option>
                        <option value="Manual Termination">Manual Termination</option>
                        <option value="Unschedule Validation requested">Unschedule Validation requested</option>
                        <option value="Other">Other</option>
                    </select>

                    <label for="termination_remarks" style="color: #545454; font-size: 1em; font-weight: 500; display: block; margin-bottom: 8px;">Remarks <span style="color: #f27474;">*</span></label>
                    <textarea id="termination_remarks" class="swal2-textarea" placeholder="Enter detailed remarks for termination request..." style="display: block; width: 100%; padding: 0.625em; margin: 0; font-size: 1.125em; border: 1px solid #d9d9d9; border-radius: 0.3125em; box-sizing: border-box; min-height: 6.75em; resize: vertical;"></textarea>
                </div>
            `,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, Continue',
            cancelButtonText: 'Cancel',
            preConfirm: () => {
                const reason = document.getElementById('termination_reason').value;
                const remarks = document.getElementById('termination_remarks').value.trim();

                if (reason === 'Select') {
                    Swal.showValidationMessage('Please select a reason for termination');
                    return false;
                }

                if (!remarks) {
                    Swal.showValidationMessage('Please provide remarks for termination');
                    return false;
                }

                return { reason: reason, remarks: remarks };
            }
        }).then((result) => {
            if (result.isConfirmed) {
                const terminationReason = result.value.reason;
                const terminationRemarks = result.value.remarks;

                // Configure the remarks modal
                configureRemarksModal(
                    'terminate_validation', // action
                    'core/data/update/terminate_validation.php', // endpoint
                    {
                        val_wf_id: valWfId,
                        termination_reason: terminationReason,
                        termination_remarks: terminationRemarks,
                        csrf_token: $('meta[name="csrf-token"]').attr('content')
                    },
                    function(response) {
                        // Success callback
                        Swal.fire({
                            icon: 'success',
                            title: 'Request Submitted!',
                            text: response.message || 'Termination request submitted successfully.'
                        }).then(() => {
                            // Refresh the search results
                            $('#formreport').submit();
                        });
                    }
                );

                // Show the modal
                $('#enterPasswordRemark').modal('show');
            }
        });
    };

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


<div class="modal fade bd-example-modal-lg show" id="viewProtocolModal" tabindex="-1" role="dialog" aria-labelledby="myLargeModalLabel" style="padding-right: 17px;">>
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
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
              <h3 class="page-title"> Search Validations to Terminate </h3>

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
                          <option value='2'>Pending for Level I approval</option>
                          <option value='3'>Pending for Level II approval</option>
                          <option value='4'>Pending for Level III approval</option>
                          <!-- Removed Workflow Approved option as per requirement -->
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




                      <button type="submit" id="generatereport" class="btn btn-gradient-primary btn-icon-text">
		<i class="mdi mdi-magnify"></i> Search Validations
	</button>

                    </form>
                  </div>
                </div>
              </div>


            <div class="col-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                <h4 class="card-title">Validations Available for Termination</h4>

                    <div class="table-responsive-xl">
                <div id="displayresults"> <p class="card-description"> Select the criteria and hit the Search Validations button. </p></div>
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
 <?php include "assets/inc/_esignmodal.php"; ?>
</body>
</html>