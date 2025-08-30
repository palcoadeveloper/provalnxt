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

// Validate required parameters
if (isset($_GET['m']) && $_GET['m'] != 'a' && (!isset($_GET['user_id']) || !is_numeric($_GET['user_id']))) {
    header('HTTP/1.1 400 Bad Request');
    header('Location: ' . BASE_URL . 'error.php?msg=invalid_parameters');
    exit();
}

require_once 'core/config/db.class.php';

if (isset($_GET['m']) && $_GET['m'] != 'a') {
    try {
        $user_details = DB::queryFirstRow(
            "SELECT employee_id, user_type, vendor_id, user_name, user_mobile, user_email, unit_id, department_id, 
             is_qa_head, is_unit_head, is_admin, is_super_admin, is_dept_head, user_domain_id, user_status, is_account_locked
             FROM users WHERE user_id = %d", 
            intval($_GET['user_id'])
        );
        
        if (!$user_details) {
            header('HTTP/1.1 404 Not Found');
            header('Location: ' . BASE_URL . 'error.php?msg=user_not_found');
            exit();
        }
    } catch (Exception $e) {
        error_log("Error fetching user details: " . $e->getMessage());
        header('HTTP/1.1 500 Internal Server Error');
        header('Location: ' . BASE_URL . 'error.php?msg=database_error');
        exit();
    }
}

try {
    $vendor_details = DB::query("SELECT vendor_id, vendor_name FROM vendors WHERE vendor_status = %s", 'Active');
} catch (Exception $e) {
    require_once('core/error/error_logger.php');
    logDatabaseError("Error loading vendor details: " . $e->getMessage(), [
        'operation_name' => 'manage_user_details_vendor_load',
        'unit_id' => null,
        'val_wf_id' => null,
        'equip_id' => null
    ]);
    $vendor_details = [];
}


?>
<!DOCTYPE html>
<html lang="en">
  <head>
   <?php include_once "assets/inc/_header.php";?>
   <meta name="csrf-token" content="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">  
    <script>
    $(document).ready(function() {
        // Variable declarations for role flags
        var isqahead, isunithead, isadmin, issuperadmin, isdepthead;
        
        // Function to process user creation after domain ID check
        function processUserCreation() {
            // Get role values
            isqahead = $("#user_role_qahead").prop("checked") ? 'Yes' : 'No';
            isunithead = $("#user_role_unithead").prop("checked") ? 'Yes' : 'No';
            isadmin = $("#user_role_admin").prop("checked") ? 'Yes' : 'No';
            issuperadmin = $("#user_role_sadmin").prop("checked") ? 'Yes' : 'No';
            isdepthead = $("#user_role_dhead").prop("checked") ? 'Yes' : 'No';
            
            // Show loading modal
            $('#pleasewaitmodal').modal('show');
            
            // Send AJAX request
            $.post("core/data/save/saveuserdetails.php", {
                employee_id: $("#employee_id").val(),
                user_name: $("#user_name").val(),
                user_mobile: $("#user_mobile").val(),
                user_email: $("#user_email").val(),
                vendor_id: $("#vendor_id").val(),
                user_status: $("#user_status").val(),
                domain_id: $("#domain_id").val(),
                unit_id: $("#unit_id").val(),
                department_id: $("#department_id").val(),
                is_qa_head: isqahead,
                is_unit_head: isunithead,
                is_admin: isadmin,
                is_super_admin: issuperadmin,
                is_dept_head: isdepthead,
                csrf_token: $("#csrf_token").val(),
                mode: 'addc'
            }, function(data, status) {
                // Hide loading modal
                $('#pleasewaitmodal').modal('hide');
                
                if(data === "success") {
                    // Show success message
                    Swal.fire({
                        icon: 'success',
                        title: 'Success',
                        text: "The employee record is successfully added!"
                    }).then((result) => {
                        window.location = "searchuser.php";
                    });
                } else {
                    // Show error message
                    Swal.fire({
                        icon: 'error',
                        title: 'Oops...',
                        text: 'Something went wrong.'
                    }).then((result) => {
                        window.location = "searchuser.php";
                    });
                }
            });
        }

        // Function to process vendor user creation
        function processVendorUserCreation() {
            // Show loading modal
            $('#pleasewaitmodal').modal('show');
            
            // Send AJAX request
            $.post("core/data/save/saveuserdetails.php", {
                employee_id: $("#employee_id").val(),
                user_name: $("#user_name").val(),
                user_mobile: $("#user_mobile").val(),
                user_email: $("#user_email").val(),
                vendor_id: $("#vendor_id").val(),
                user_status: $("#user_status").val(),
                domain_id: $("#domain_id").val(),
                csrf_token: $("#csrf_token").val(),
                mode: 'addv'
            }, function(data, status) {
                // Hide loading modal
                $('#pleasewaitmodal').modal('hide');
                
                if(data === "success") {
                    // Show success message
                    Swal.fire({
                        icon: 'success',
                        title: 'Success',
                        text: "The vendor record is successfully added!"
                    }).then((result) => {
                        window.location = "searchuser.php";
                    });
                } else {
                    // Show error message
                    Swal.fire({
                        icon: 'error',
                        title: 'Oops...',
                        text: 'Something went wrong.'
                    }).then((result) => {
                        window.location = "searchuser.php";
                    });
                }
            });
        }
        
        // Function to submit user data after authentication
        function submitUserData(mode) {
            var usertype = '<?php echo (isset($_GET['u']) && $_GET['u']=='c')? "c" : "v" ; ?>';
            
            if (usertype == 'c') {
                // Get role values for company users
                isqahead = $("#user_role_qahead").prop("checked") ? 'Yes' : 'No';
                isunithead = $("#user_role_unithead").prop("checked") ? 'Yes' : 'No';
                isadmin = $("#user_role_admin").prop("checked") ? 'Yes' : 'No';
                issuperadmin = $("#user_role_sadmin").prop("checked") ? 'Yes' : 'No';
                isdepthead = $("#user_role_dhead").prop("checked") ? 'Yes' : 'No';
                
                // Show loading modal
                $('#pleasewaitmodal').modal('show');
                
                // Send AJAX request
                $.post("core/data/save/saveuserdetails.php", {
                    user_id: $("#user_id").val(),
                    employee_id: $("#employee_id").val(),
                    user_name: $("#user_name").val(),
                    user_mobile: $("#user_mobile").val(),
                    user_email: $("#user_email").val(),
                    user_status: $("#user_status").val(),
                    domain_id: $("#domain_id").val(),
                    unit_id: $("#unit_id").val(),
                    department_id: $("#department_id").val(),
                    is_qa_head: isqahead,
                    is_unit_head: isunithead,
                    is_admin: isadmin,
                    is_super_admin: issuperadmin,
                    is_dept_head: isdepthead,
                    user_locked: $("#user_locked").val(),
                    csrf_token: $("#csrf_token").val(),
                    mode: 'modifyc'
                }, function(data, status) {
                    // Hide loading modal
                    $('#pleasewaitmodal').modal('hide');
                    
                    console.log('Server response:', data);
                    console.log('Status:', status);
                    
                    if(data === "success") {
                        // Show success message
                        Swal.fire({
                            icon: 'success',
                            title: 'Success',
                            text: "The employee record is successfully modified!"
                        }).then((result) => {
                            window.location = "searchuser.php";
                        });
                    } else {
                        // Show error message with actual response
                        Swal.fire({
                            icon: 'error',
                            title: 'Oops...',
                            text: 'Something went wrong. Response: ' + JSON.stringify(data)
                        }).then((result) => {
                            window.location = "searchuser.php";
                        });
                    }
                });
            } else if (usertype == 'v') {
                // For vendor users
                if ($("#vendor_id").val() == 'select') {
                    Swal.fire({
                        icon: 'error',
                        title: 'Oops...',
                        text: 'Vendor not selected.'
                    });
                    return;
                }
                
                // Show loading modal
                $('#pleasewaitmodal').modal('show');
                
                // Send AJAX request
                $.post("core/data/save/saveuserdetails.php", {
                    user_id: $("#user_id").val(),
                    employee_id: $("#employee_id").val(),
                    user_name: $("#user_name").val(),
                    domain_id: $("#domain_id").val(),
                    user_mobile: $("#user_mobile").val(),
                    user_email: $("#user_email").val(),
                    vendor_id: $("#vendor_id").val(),
                    user_status: $("#user_status").val(),
                    user_locked: $("#user_locked").val(),
                    csrf_token: $("#csrf_token").val(),
                    mode: 'modifyv'
                }, function(data, status) {
                    // Hide loading modal
                    $('#pleasewaitmodal').modal('hide');
                    
                    if(data === "success") {
                        // Show success message
                        Swal.fire({
                            icon: 'success',
                            title: 'Success',
                            text: "The vendor record is successfully modified!"
                        }).then((result) => {
                            window.location = "searchuser.php";
                        });
                    } else {
                        // Show error message
                        Swal.fire({
                            icon: 'error',
                            title: 'Oops...',
                            text: 'Something went wrong.'
                        }).then((result) => {
                            window.location = "searchuser.php";
                        });
                    }
                });
            }
        }

        // Add User button click handler
        $("#add_user").click(function(e) {
            e.preventDefault();
            
            var form = document.getElementById('formuservalidation');
            
            if (form.checkValidity() === false) {
                form.classList.add('was-validated');
            } else {
                form.classList.add('was-validated');
                
                var usertype = '<?php echo (isset($_GET['u']) && $_GET['u']=='c')? "c" : "v" ; ?>';
                
                if (usertype == 'c') {
                    if($("#domain_id").val() == '') {
                        Swal.fire({
                            icon: 'error',
                            title: 'Oops...',
                            text: 'Domain ID not entered.'
                        });
                    } else if($("#unit_id").val() === 'select' || $("#unit_id").val() == null) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Oops...',
                            text: 'Unit not selected.'
                        });
                    } else if($("#department_id").val() === 'select' || $("#department_id").val() == null) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Oops...',
                            text: 'Department not selected.'
                        });
                    } else {
                        // Check if domain ID is unique
                        $.get("core/validation/check_domain_id.php", {
                            domain_id: $("#domain_id").val()
                        }, function(response) {
                            if(response === "exists") {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Oops...',
                                    text: 'Domain ID already exists. Please choose a different one.'
                                });
                            } else {
                                // Set success callback for e-signature modal
                                setSuccessCallback(function() {
                                    processUserCreation();
                                });
                                
                                // Show e-signature modal
                                $('#enterPasswordRemark').modal('show');
                            }
                        });
                    }
                } else if (usertype == 'v') {
                    if ($("#domain_id").val() == '') {
                        Swal.fire({
                            icon: 'error',
                            title: 'Oops...',
                            text: 'Domain ID not entered.'
                        });
                    } else if ($("#vendor_id").val() == 'select') {
                        Swal.fire({
                            icon: 'error',
                            title: 'Oops...',
                            text: 'Vendor not selected.'
                        });
                    } else {
                        // Check if domain ID is unique for vendor users
                        $.get("core/validation/check_domain_id.php", {
                            domain_id: $("#domain_id").val()
                        }, function(response) {
                            if(response === "exists") {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Oops...',
                                    text: 'Domain ID already exists. Please choose a different one.'
                                });
                            } else {
                                // Set success callback for e-signature modal
                                setSuccessCallback(function() {
                                    processVendorUserCreation();
                                });
                                
                                // Show e-signature modal
                                $('#enterPasswordRemark').modal('show');
                            }
                        });
                    }
                }
            }
        });

        // Modify User button click handler
        $("#modify_user").click(function(e) {
            e.preventDefault();
            
            var form = document.getElementById('formuservalidation');
            
            if (form.checkValidity() === false) {
                form.classList.add('was-validated');
            } else {
                form.classList.add('was-validated');
                
                var usertype = '<?php echo (isset($_GET['u']) && $_GET['u']=='c')? "c" : "v" ; ?>';
                
                if (usertype == 'c') {
                    if($("#domain_id").val() == '') {
                        Swal.fire({
                            icon: 'error',
                            title: 'Oops...',
                            text: 'Domain ID not entered.'
                        });
                    } else if($("#unit_id").val() === 'select' || $("#unit_id").val() == null) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Oops...',
                            text: 'Unit not selected.'
                        });
                    } else if($("#department_id").val() === 'select' || $("#department_id").val() == null) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Oops...',
                            text: 'Department not selected.'
                        });
                    } else {
                        // Set success callback for e-signature modal
                        setSuccessCallback(function() {
                            submitUserData('modify');
                        });
                        
                        // Show e-signature modal
                        $('#enterPasswordRemark').modal('show');
                    }
                } else if (usertype == 'v') {
                    if ($("#domain_id").val() == '') {
                        Swal.fire({
                            icon: 'error',
                            title: 'Oops...',
                            text: 'Domain ID not entered.'
                        });
                    } else if ($("#vendor_id").val() == 'select') {
                        Swal.fire({
                            icon: 'error',
                            title: 'Oops...',
                            text: 'Vendor not selected.'
                        });
                    } else {
                        // Set success callback for e-signature modal
                        setSuccessCallback(function() {
                            submitUserData('modify');
                        });
                        
                        // Show e-signature modal
                        $('#enterPasswordRemark').modal('show');
                    }
                }
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
							<span class="page-title-icon bg-gradient-primary text-white mr-2">
								<i class="mdi mdi-home"></i>
							</span> User Details
						</h3>
						<nav aria-label="breadcrumb">
							<ul class="breadcrumb">
								<li class="breadcrumb-item active" aria-current="page"><span><a class='btn btn-gradient-info btn-sm btn-rounded'
										href="searchuser.php"><< Back</a> </span>
								</li>
							</ul>
						</nav>
					</div>
					
					
					
		<div class="row">

	<div class="col-lg-12 grid-margin stretch-card">
                <div class="card">
                  <div class="card-body">
                    <h4 class="card-title">User Details</h4>
                    <p class="card-description"> 
                    </p>
                    
                    
                    
                    
                    
                    
                    




								        <form id="formuservalidation" class="needs-validation" novalidate>
								        <?php 
								        echo '<input type="hidden" id="user_id" name="user_id" value="'. (($_GET['m']!='a')?$_GET['user_id']:'').'"/>';
								        ?>
								        <input type="hidden" id="csrf_token" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
								
<div class="form-row">
							<div class="form-group  col-md-6">
                        
                        <label for="exampleSelectGender">Employee ID</label>
                        <input type="text" class="form-control" value='<?php echo ($_GET['m']!='a')?$user_details['employee_id']:'';?>' name='employee_id' id='employee_id' required/>
                      <div class="invalid-feedback">  
                                            Please provide a valid employee ID.  
                                        </div>
                      </div>
                      
                      <div class="form-group  col-md-6">
                        <label for="exampleSelectGender">Employee Name</label>
                        
                    	 <input type="text" class="form-control" value='<?php echo ($_GET['m']!='a')?$user_details['user_name']:'';?>' name='user_name' id='user_name' required/>
                       	
                        </select>
                     
                    
                    
                    
                      </div>
                      
                      
                      
                      
                      
							</div>								
								
		<div class="form-row">
							<div class="form-group  col-md-6">
                        
                        <label for="exampleSelectGender">Domain ID</label>
                        <input type="text" class="form-control" value='<?php echo ($_GET['m']!='a')?$user_details['user_domain_id']:'';?>' name='domain_id' id='domain_id' />
                      </div>
                      
                      <div class="form-group  col-md-6">
                        <label for="exampleSelectGender">User Mobile</label>
                        
                    	  <input  type="text" pattern="[0-9]{10}"  class="form-control" value='<?php echo ($_GET['m']!='a')?$user_details['user_mobile']:'';?>' name='user_mobile' id='user_mobile' maxlength="10" required/>
                     
                    <div class="invalid-feedback">  
                                            Please provide a valid mobile number.  
                                        </div>
                    
                    
                      </div>
                      
                      
                      
                      
                      
							</div>								
		
		<div class="form-row">
							<div class="form-group  col-md-6">
                        
                        <label for="exampleSelectGender">User Email</label>
                        <input type="email" class="form-control" value='<?php echo ($_GET['m']!='a')?$user_details['user_email']:'';?>' name='user_email' id='user_email' required/>
                       <div class="invalid-feedback">  
                                            Please provide a valid email.  
                                        </div> 
                      </div>
                      
                      <div class="form-group  col-md-6">
                        <label for="exampleSelectGender">Unit</label>
                        
                    	 <select class="form-control" id="unit_id" name="unit_id" <?php echo ($_GET['u']=='v' )? "disabled":""; ?>>
									
								<option value='select'>Select</option>		
                       	<?php 
                       	
                       	if ($_SESSION['is_super_admin']=="Yes") {
                       	    // For super admin, show all units
                       	    $results = DB::query("select unit_id, unit_name from units");
                       	    
                       	    if(!empty($results)) {
                       	        foreach ($results as $row) {
                       	            $selected = '';
                       	            // If editing a user, select their unit
                       	            if ($_GET['m'] != 'a' && $user_details['unit_id'] == $row['unit_id']) {
                       	                $selected = 'selected';
                       	            }
                       	            echo "<option value='".$row['unit_id']."' ".$selected.">".$row['unit_name']."</option>";
                       	        }
                       	    }
                       	} else {
                       	    // For non-super admin, show only their unit
                       	    $unit_name = DB::queryFirstField("select unit_name from units where unit_id=".$_SESSION['unit_id']);
                       	    $selected = '';
                       	    
                       	    // If editing a user, check if their unit matches the session unit
                       	    if ($_GET['m'] != 'a' && $user_details['unit_id'] == $_SESSION['unit_id']) {
                       	        $selected = 'selected';
                       	    } else if ($_GET['m'] == 'a') {
                       	        // For new users, select the session unit by default
                       	        $selected = 'selected';
                       	    }
                       	    
                       	    echo "<option value='".$_SESSION['unit_id']."' ".$selected.">".$unit_name."</option>";
                       	}
                    
 
                       	
                       	
                       	
                       	
                       	
                      /* 	
                       	$results = DB::query("select unit_id, unit_name from units");
                       	$output='';
                       	
                       	if(!empty($results))
                       	{
                       	    
                       	    
                       	        foreach ($results as $row) {
                       	            
                       	            $output=$output. "<option value='".$row['unit_id']."'".(($user_details['unit_id']==$row['unit_id'] && $_GET['m']!='a')? 'selected' : '').  ">".$row['unit_name']."</option>";
                       	            
                       	        }
                       	        
                       	        echo $output;
                       	}
                       	   
                       	
                       	
                       	*/
                       	
                       	
                     	
                       	
                       	
                       	
                       	
                       	
                       	
                       	    
                       
                       	?>	
                        </select>
                      
										
                     
                    
                    
                    
                      </div>
                      
                      
                      
                      
                      
							</div>								
		
		
		<div class="form-row">
							<div class="form-group  col-md-6">
                        
                        <label for="exampleSelectGender" >Department</label>
                        <select class="form-control" id="department_id" name="department_id" <?php echo ($_GET['u']=='v' )? "disabled":""; ?>>
								<option value='select'>Select</option>
									
                       	<?php 
                       	
                       	$results = DB::query("select department_id, department_name from departments where department_status='Áctive'");
                       	$output='';
                       	
                       	if(!empty($results))
                       	{
                       	    
                       	    
                       	    foreach ($results as $row) {
                       	        
                       	        $output=$output. "<option value='".$row['department_id']."'".(($user_details['department_id']==$row['department_id'] && $_GET['m']!='a')? 'selected' : '').  ">".$row['department_name']."</option>";
                       	        
                       	    }
                       	    
                       	    echo $output;
                       	}
                       	
                       	
                       	
                       	
                       	
                       	
                       	    
                       	
                       	
                       	
                       	
                       	
                       	
                       	
                       	
                       	
                       	
                       	
                       	
                       	
                       	
                       	
                       	
                       
                       	?>	
                        </select>
                      </div>
                      
                      <div class="form-group  col-md-6">
                        <label for="exampleSelectGender">Vendor</label>
                        
                    	  <select class="form-control" id="vendor_id" name="vendor_id" <?php echo ($_GET['u']=='c' )? "disabled":""; ?>>
									
										<option value='select'>Select </option>
                       	<?php 
                       
                       	if(!empty($vendor_details))
                       	{
                       	    foreach ($vendor_details as $vendor){
                       	     
                       	        echo "<option value='".$vendor['vendor_id']."' ".(($_GET['m']!='a' && $user_details['vendor_id']==$vendor['vendor_id'])?"selected":"").">".$vendor['vendor_name']."</option>";
                       	        
                       	        
                       	    }
                       	}
                       	
                       	    
                       
                       	?>	
                        </select>
                    
                    
                    
                      </div>
                      
                      
                      
                      
                      
							</div>								
		
		
		<div class="form-row">
							<div class="form-group  col-md-6">
                        
                        <label for="exampleSelectGender">User Role</label>
                       <div class="form-check">
                                <label class="form-check-label">
                                  <input type="checkbox" class="form-check-input" name="user_role_qahead" id="user_role_qahead" value="qahead" <?php echo ($_GET['m']!='a' && trim($user_details['is_qa_head'])=='Yes')? " checked":""  ?> <?php echo ($_GET['u']=='v' )? "disabled":""; ?>> QA Head 
                                  </label>
                                  </div>
                                   <div class="form-check">
                                <label class="form-check-label">
                                  <input type="checkbox" class="form-check-input" name="user_role_unithead" id="user_role_unithead" value="unithead" <?php echo ($_GET['m']!='a' && trim($user_details['is_unit_head'])=='Yes')? " checked":""  ?> <?php echo ($_GET['u']=='v' )? "disabled":""; ?>> Unit Head </label>
                                  </div>
                                   <div class="form-check">
                                <label class="form-check-label">
                                  <input type="checkbox" class="form-check-input" name="user_role_admin" id="user_role_admin" value="admin" <?php echo ($_GET['m']!='a' && (trim($user_details['is_admin'])=='Yes')? " checked":"")  ?> <?php echo ($_GET['u']=='v' )? "disabled":""; ?>> Admin </label>
                                  </div>
                                   <div class="form-check">
                                <label class="form-check-label">
                                  <input type="checkbox" class="form-check-input" name="user_role_sadmin" id="user_role_sadmin" value="sadmin" <?php echo ($_GET['m']!='a' && trim($user_details['is_super_admin'])=='Yes')? " checked":""  ?> <?php echo ($_GET['u']=='v' )? "disabled":""; ?>> Super Admin </label>
                              </div>
                              <div class="form-check">
                                <label class="form-check-label">
                                  <input type="checkbox" class="form-check-input" name="user_role_dhead" id="user_role_dhead" value="dhead" <?php echo ($_GET['m']!='a' && trim($user_details['is_dept_head'])=='Yes')? " checked":""  ?> <?php echo ($_GET['u']=='v' )? "disabled":""; ?>> Department Head </label>
                              </div>
                              
                             
                      </div>
                      
                      <div class="form-group  col-md-6">
                        <label for="exampleSelectGender">Status</label>
                        
                    	  <select class="form-control" id="user_status" name="user_status">
									
										
                       	<?php 
                       	echo "<option value='Active'".(($user_details['user_status']=='Active')? "selected" : "") .">Active</option>";
                       	echo "<option value='Inactive'".(($user_details['user_status']=='Inactive')? "selected" : "") .">Inactive</option>";
                 
                       	
                       	    
                       	    
                       
                       	?>	
                        </select>
                     
                    
                    <label for="exampleSelectGender" class="mt-4">Account Locked? </label>
                        
                    	  <select class="form-control" id="user_locked" name="user_locked" >
									
										
                       	<?php 
                       	echo "<option value='Yes'".(($user_details['is_account_locked']==='Yes')? "selected" : "") .">Yes</option>";
                       	echo "<option value='No'".(($user_details['is_account_locked']==='No' || empty($user_details['is_account_locked']))? "selected" : "") . ">No</option>";
                 
                       	
                       	    
                       	    
                       
                       	?>	
                        </select>
                    
                    
                      </div>
                      
                      
                      
                      
                      
							</div>								
		
								
								
								
								
						<div class="d-flex justify-content-center"> 


 <?php
                  
                  if($_GET['m']=='m'){
                      ?>
                  <button  id="modify_user"	class='btn btn-gradient-primary mr-2'>Modify User</button>    
                  <?php     
                  }
                  else if($_GET['m']=='a'){
                      ?>
                  <button  id="add_user"	class='btn btn-gradient-primary mr-2'>Add User</button>    
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
 <?php include "assets/inc/_pleasewaitmodal.php"; ?>
</body>
</html>

