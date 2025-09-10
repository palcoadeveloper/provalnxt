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

// Validate required parameters
if (isset($_GET['m']) && $_GET['m'] != 'a' && (!isset($_GET['room_loc_id']) || !is_numeric($_GET['room_loc_id']))) {
    header('HTTP/1.1 400 Bad Request');
    header('Location: ' . BASE_URL . 'error.php?msg=invalid_parameters');
    exit();
}

// Initialize CSRF token if not set
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once 'core/config/db.class.php';

if (isset($_GET['m']) && $_GET['m'] != 'a') {
    try {
        $room_details = DB::queryFirstRow(
            "SELECT room_loc_name, room_volume 
             FROM room_locations WHERE room_loc_id = %d", 
            intval($_GET['room_loc_id'])
        );
        
        if (!$room_details) {
            header('HTTP/1.1 404 Not Found');
            header('Location: ' . BASE_URL . 'error.php?msg=room_not_found');
            exit();
        }
    } catch (Exception $e) {
        error_log("Error fetching room details: " . $e->getMessage());
        header('HTTP/1.1 500 Internal Server Error');
        header('Location: ' . BASE_URL . 'error.php?msg=database_error');
        exit();
    }
}

?>
<!DOCTYPE html>
<html lang="en">
  <head>
  <?php include_once "assets/inc/_header.php";?>  
   <script> 
      $(document).ready(function(){
      
      // Function to submit room data after successful authentication
      function submitRoomData(mode) {
        // Get CSRF token
        var csrfToken = $("input[name='csrf_token']").val();
        
        $('#pleasewaitmodal').modal('show');
        
        // Prepare data object based on mode
        let data = {
          csrf_token: csrfToken,
          room_loc_name: $("#room_loc_name").val(),
          room_volume: parseFloat($("#room_volume").val()),
          mode: mode
        };
        
        // Add room_loc_id for modify mode
        if (mode === 'modify') {
          data.room_loc_id = $("#room_loc_id").val();
        }
        
        // Send AJAX request
        $.ajax({
          url: "core/data/save/saveroomdetails.php",
          type: "GET",
          data: data,
          success: function(data, status) {
            $('#pleasewaitmodal').modal('hide');
            
            // Try to parse JSON first
            try {
              var response = JSON.parse(data);
              
              // Update CSRF token if provided
              if (response.csrf_token) {
                $("input[name='csrf_token']").val(response.csrf_token);
              }
              
              if (response.status === "success") {
                Swal.fire({
                  icon: 'success',
                  title: 'Success',
                  text: "The room/location record is successfully " + (mode === 'add' ? "added" : "modified") + "!"
                }).then((result) => {
                  window.location = "searchrooms.php";
                });
              } else {
                Swal.fire({
                  icon: 'error',
                  title: 'Validation Error',
                  text: response.error || 'Something went wrong.'
                });
              }
            } catch (e) {
              // Legacy handling
              if (data === "success") {
                Swal.fire({
                  icon: 'success',
                  title: 'Success',
                  text: "The room/location record is successfully " + (mode === 'add' ? "added" : "modified") + "!"
                }).then((result) => {
                  window.location = "searchrooms.php";
                });
              } else {
                Swal.fire({
                  icon: 'error',
                  title: 'Oops...',
                  text: 'Something went wrong.'
                });
              }
            }
          },
          error: function(xhr, status, error) {
            $('#pleasewaitmodal').modal('hide');
            Swal.fire({
              icon: 'error',
              title: 'Connection Error',
              text: 'Could not connect to the server. Please try again.'
            });
          }
        });
      }
      
      // Add Room button click handler
      $("#add_room").click(function(e) { 
        e.preventDefault();
        
        var form = document.getElementById('formroomvalidation'); 
        
        if (form.checkValidity() === false) {  
          form.classList.add('was-validated');  
        } else {
          form.classList.add('was-validated');
          
          // Set success callback for e-signature modal
          setSuccessCallback(function() {
            submitRoomData('add');
          });
          
          // Show e-signature modal
          $('#enterPasswordRemark').modal('show');
        }
      });

      // Modify Room button click handler
      $("#modify_room").click(function(e) { 
        e.preventDefault();
        
        var form = document.getElementById('formroomvalidation'); 
        
        if (form.checkValidity() === false) {  
          form.classList.add('was-validated');  
        } else {
          form.classList.add('was-validated');
          
          // Set success callback for e-signature modal
          setSuccessCallback(function() {
            submitRoomData('modify');
          });
          
          // Show e-signature modal
          $('#enterPasswordRemark').modal('show');
        }
      });

      // Room volume validation - only allow non-negative numbers
      $("#room_volume").on('input', function() {
        var value = parseFloat($(this).val());
        if (isNaN(value) || value < 0) {
          this.setCustomValidity('Room volume must be a non-negative number');
        } else if (value > 999999.99) {
          this.setCustomValidity('Room volume cannot exceed 999,999.99 ft³');
        } else {
          this.setCustomValidity('');
        }
      });

      // Room name validation
      $("#room_loc_name").on('input', function() {
        var value = $(this).val().trim();
        if (value.length === 0) {
          this.setCustomValidity('Room/Location name is required');
        } else if (value.length > 500) {
          this.setCustomValidity('Room name cannot exceed 500 characters');
        } else {
          this.setCustomValidity('');
        }
      });
      
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
				<h3 class="page-title">
					Room/Location Details
				</h3>
				<nav aria-label="breadcrumb">
					<ul class="breadcrumb">
						<li class="breadcrumb-item active" aria-current="page"><span><a class='btn btn-gradient-info btn-sm btn-rounded'
								href="searchrooms.php"><< Back</a> </span>
						</li>
					</ul>
				</nav>
			</div>
			
		<div class="row">

	<div class="col-lg-12 grid-margin stretch-card">
                <div class="card">
                  <div class="card-body">
                    <h4 class="card-title">Room/Location Details</h4>
                    <p class="card-description"> 
                    </p>
                    
			        <form id='formroomvalidation' class="needs-validation" novalidate>
			        <!-- Add CSRF token field -->
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                       
		        <?php 
		        if(isset($_GET['m']) && $_GET['m']!='a')
		        {
		        
		        echo '<input type="hidden" id="room_loc_id" name="room_loc_id" value="'.$_GET['room_loc_id'].'" />';
	    
	         }
		        
		        ?>
			
			
			<div class="form-row">
                    
                    <div class="form-group col-md-6">
                        <label for="room_loc_name">Room/Location Name *</label>
                        <input type="text" 
                               class="form-control" 
                               value='<?php echo ((isset($_GET['m']) && $_GET['m']!='a')?  htmlspecialchars($room_details['room_loc_name'], ENT_QUOTES, 'UTF-8'):'');?>' 
                               name='room_loc_name' 
                               id='room_loc_name' 
                               maxlength="500"
                               required/>
                        <div class="invalid-feedback">  
                            Please provide a valid room/location name.  
                        </div>
                        <small class="form-text text-muted">Maximum 500 characters</small>
                      </div>
                      
                       <div class="form-group col-md-6">
                        <label for="room_volume">Room Volume (ft³) *</label>
                        <input type="number" 
                               class="form-control" 
                               value='<?php echo ((isset($_GET['m']) && $_GET['m']!='a')? $room_details['room_volume']:'');?>' 
                               name='room_volume' 
                               id='room_volume' 
                               step="0.01"
                               min="0.00"
                               max="999999.99"
                               required/>
                        <div class="invalid-feedback">  
                            Please provide a valid room volume (0.00 - 999,999.99 ft³).  
                        </div>
                        <small class="form-text text-muted">Volume in cubic feet (ft³)</small>
                      </div>
                      
                      </div>
			
			<div class="d-flex justify-content-center"> 
			
			
			 <?php
                  
                  if(isset($_GET['m']) && $_GET['m']=='m'){
                      ?>
                  <button  id="modify_room"	class='btn btn-gradient-primary mr-2'>Modify Room/Location</button>    
                  <?php     
                  }
                  else if(isset($_GET['m']) && $_GET['m']=='a'){
                      ?>
                  <button  id="add_room"	class='btn btn-gradient-primary mr-2'>Add Room/Location</button>    
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