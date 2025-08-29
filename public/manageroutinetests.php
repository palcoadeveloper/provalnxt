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
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include_once "assets/inc/_header.php";?>
    <meta name="csrf-token" content="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">  
    
    <script>
    $(document).ready(function(){
    
    
    
    
     $('#datagrid-upcoming').DataTable({
  "pagingType": "numbers"
} );
    $('#datagrid-inprogress').DataTable({
  "pagingType": "numbers"
});
   
    
    
     $('#viewProtocolModal').on('show.bs.modal', function (e) {
    var loadurl = $(e.relatedTarget).data('load-url');
    $(this).find('.modal-body').load(loadurl);
});
   
    
    
    
    
    
    
    
    
    
    $("#formmodalvalidation").on('submit',(function(e) {
		e.preventDefault();
		}));
    
    
    $("#startvalidation").click(function() {
   
   $("#confirmbeginvalidation").attr("href",this.href);
   //alert(this.href);
});

routine_test_wf_id="";
needdeviationremarks=false;
$('#exampleModal').on('show.bs.modal', function (event) {

  var button = $(event.relatedTarget) // Button that triggered the modal
  var recipient = button.data('whatever') // Extract info from data-* attributes
  
  
  // If necessary, you could initiate an AJAX request here (and then do the updating in a callback).
  // Update the modal's content. We'll use jQuery here, but you could use a data binding library or other methods instead.
  var modal = $(this)
 
 
 plannedDate=new Date(button.data('planneddate')).setHours(0, 0, 0, 0);
 today=new Date().setHours(0, 0, 0, 0);
 
 if(plannedDate<today)
 {
 	needdeviationremarks=true;
 	$("#deviationremark").prop("disabled", false);
 	$('.dev_remarks').show();
 	alert('You are trying to start the routine test after the planned start date. Kindly input the deviation remarks.');
 }
 else
 {
 	$("#deviationremark").prop("disabled", true);
 	$('.dev_remarks').hide();
 	needdeviationremarks=false;
 }
 
 
 
 
 
 
 
  //alert(recipient);
  $("#modalrtwfid").prop("innerHTML", recipient); 
 
 routine_test_wf_id_modal=recipient;
 url= button.data('href');

});


$("#btnSubmitData").click(function() {
  // Get CSRF token - Make sure a hidden field with the token exists in the form
  var csrfToken = $("input[name='csrf_token']").val();

  if(needdeviationremarks==true && $('#deviationremark').val()==''){
    //alert("Please enter the deviation remarks.");
    Swal.fire({
      icon: 'error',
      title: 'Oops...',
      text: 'Please enter the deviation remarks.'                
    });
  }
  else {	
    $('#pleasewaitmodal').modal('show');
    $.post("core/data/save/creatertreportdata.php",
    {
      csrf_token: csrfToken, // Include the CSRF token
      routine_test_wf_id: routine_test_wf_id_modal,
      justification: $("#justification").val(),
      deviation_remark: $('#deviationremark').val()
    },
    function(data, status){
      $('#pleasewaitmodal').modal('hide');
      
      // Try to parse as JSON first
      try {
        var response = JSON.parse(data);
        console.log("Parsed response:", response);
        
        // Update CSRF token if provided
        if (response.csrf_token) {
          $("input[name='csrf_token']").val(response.csrf_token);
        }
        
        if (response.status === "success") {
          Swal.fire({
            icon: 'success',
            title: 'Success',
            text: "The routine test is successfully initiated."                
          }).then((result) => {
            window.location.href = url;
          });
        } else {
          // Handle error
          Swal.fire({
            icon: 'error',
            title: 'Oops...',
            text: 'Something went wrong.'                
          });
        }
      } catch (e) {
        // Legacy handling for non-JSON responses
        if (data=="success") {
          Swal.fire({
            icon: 'success',
            title: 'Success',
            text: "The routine test is successfully initiated."                
          }).then((result) => {
            window.location.href = url;
          });
        } else {
          Swal.fire({
            icon: 'error',
            title: 'Oops...',
            text: 'Something went wrong.'                
          });
        }
      }
    });
  }
}); 





});
    
    
    </script>
    
  </head>
  <body>
     <?php include_once "assets/inc/_pleasewaitmodal.php"; ?>
     <?php  include "assets/inc/_viewprotocolmodal.php";?> 
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
          
          
          
          
<!-- Modal -->

<div class="modal fade bd-example-modal-lg" id="exampleModal" tabindex="-1" role="dialog" aria-labelledby="myLargeModalLabel">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">

      <div class="modal-header">
        <h4 class="modal-title" id="myLargeModalLabel">Begin Routine Test</h4>
        <button id="modalbtncross" type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        
        <div class="col-lg-12 grid-margin stretch-card">
                <div class="card">
                  <div class="card-body">
                    <h4 class="card-title">Need some details</h4>
                <!--      <p class="card-description"> <?php echo $result['test_purpose']?> -->
                    </p>

<form id="formmodalvalidation" class="needs-validation" novalidate>
        <!-- Add CSRF token field -->
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
        <table class="table table-bordered">
        
        
        
        
        
        
        
                                    <tr>
                                    <td>
                                        <h6 class="text-muted">Routine Test Workflow ID</h6>
                                    </td>
                                    <td colspan="3"> <div id="modalrtwfid"></div>  </td>

                                    
                                </tr>
                                
                                <tr class="dev_remarks">
                                    <td>
                                        <h6 class="text-muted">Deviation Remarks</h6>
                                    </td>
                                    <td colspan="3"><input type="text" class="form-control" id="deviationremark"  placeholder="Deviation Remarks"> </td>

                                    
                                </tr>
                                
        
                                    <tr>
                                    <td>
                                        <h6 class="text-muted">Remarks</h6>
                                    </td>
                                    <td colspan="3"> <textarea class="form-control" id="justification" required></textarea>   </td>

                                    
                                </tr>

                
        <tr>
        <td colspan="4"><div class="d-flex justify-content-center"><button  id="btnSubmitData"	class='upload-check-required btn btn-primary btn-small'>Submit</button></div></td>
                        
        </tr>
        </table>
        </form>
        
        </div></div></div>
        
        
        
        
        
        
      </div>
    </div>
  </div>
</div>
          
 
          
              <h3 class="page-title"> Routine Tests </h3>
              
            </div>
          
          
          
          
          
          
          
          
          <div class="row">
              
              
              
                       
              
              
               <div class="col-lg-12 grid-margin stretch-card" <?php if(isset($_SESSION['department_id']) && $_SESSION['department_id']=='1'){echo 'style="display:block;"';}else{echo 'style="display:none;"';} ?>>
                <div class="card">
                  <div class="card-body">
                    <h4 class="card-title">Upcoming Routine Tests</h4>
                  <!--    <p class="card-description"> Add class <code>.table-bordered</code> 
                    </p>-->
                    
                     
                      <?php include "assets/inc/_upcomingroutinetests.php";?>              
                     
                  </div>
                </div>
              </div>
              
            <div class="col-lg-12 grid-margin stretch-card" <?php if(isset($_SESSION['department_id']) && $_SESSION['department_id']=='1'){echo 'style="display:block;"';}else{echo 'style="display:none;"';} ?>>
                <div class="card">
                  <div class="card-body">
                    <h4 class="card-title">In-progress Routine Tests</h4>
                  <!--    <p class="card-description"> Add class <code>.table-bordered</code> 
                    </p>-->
                    
                      <?php include "assets/inc/_inprogressroutinetests.php";?>              
                      
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